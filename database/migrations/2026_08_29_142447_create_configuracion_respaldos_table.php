<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_respaldos', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('activo')->default(true);
            $table->string('frecuencia', 20)->default('DIARIA');
            $table->time('hora')->default('02:00:00');
            $table->unsignedTinyInteger('dia_semana')->nullable();
            $table->unsignedTinyInteger('dia_mes')->nullable();
            $table->unsignedSmallInteger('retencion_cantidad')->default(14);
            $table->boolean('verificar_automaticamente')->default(true);
            $table->foreignId('actualizado_por')->nullable()->constrained('usuarios');
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE configuracion_respaldos
ADD CONSTRAINT chk_configuracion_respaldo_frecuencia
CHECK (frecuencia IN ('DIARIA', 'SEMANAL', 'MENSUAL')),
ADD CONSTRAINT chk_configuracion_respaldo_dia_semana
CHECK (dia_semana IS NULL OR dia_semana BETWEEN 1 AND 7),
ADD CONSTRAINT chk_configuracion_respaldo_dia_mes
CHECK (dia_mes IS NULL OR dia_mes BETWEEN 1 AND 28),
ADD CONSTRAINT chk_configuracion_respaldo_retencion
CHECK (retencion_cantidad BETWEEN 1 AND 365)
SQL);

        DB::table('configuracion_respaldos')->insert([
            'id' => 1,
            'activo' => true,
            'frecuencia' => 'DIARIA',
            'hora' => '02:00:00',
            'dia_semana' => 1,
            'dia_mes' => 1,
            'retencion_cantidad' => 14,
            'verificar_automaticamente' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permisos')->updateOrInsert(
            ['codigo' => 'RESPALDOS_GESTIONAR'],
            [
                'nombre' => 'Gestionar respaldos y restauraciones',
                'descripcion' => 'Configurar, crear, verificar, descargar y restaurar copias de seguridad.',
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permisos')->where('codigo', 'RESPALDOS_GESTIONAR')->delete();
        Schema::dropIfExists('configuracion_respaldos');
    }
};
