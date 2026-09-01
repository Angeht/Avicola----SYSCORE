<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restauraciones_respaldo', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operacion_uuid')->unique();
            $table->string('respaldo_nombre', 180);
            $table->string('respaldo_ruta');
            $table->string('respaldo_preventivo_nombre', 180)->nullable();
            $table->string('respaldo_preventivo_ruta')->nullable();
            $table->string('estado', 30)->index();
            $table->foreignId('solicitado_por')->nullable()->constrained('usuarios');
            $table->string('solicitante', 160);
            $table->dateTime('iniciado_at')->index();
            $table->dateTime('completado_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE restauraciones_respaldo
ADD CONSTRAINT chk_restauracion_respaldo_estado
CHECK (estado IN ('EN_PROCESO', 'COMPLETADA', 'FALLIDA_REVERTIDA', 'FALLIDA_CRITICA'))
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restauraciones_respaldo');
    }
};
