<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_archivo', 180)->unique();
            $table->string('disco', 40)->default('local');
            $table->string('ruta')->unique();
            $table->string('tipo', 30)->index();
            $table->string('estado', 30)->index();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('usuarios');
            $table->dateTime('creado_at')->index();
            $table->dateTime('verificado_at')->nullable();
            $table->foreignId('verificado_por')->nullable()->constrained('usuarios');
            $table->dateTime('eliminado_at')->nullable();
            $table->foreignId('eliminado_por')->nullable()->constrained('usuarios');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE respaldos
ADD CONSTRAINT chk_respaldo_tipo
CHECK (tipo IN ('MANUAL', 'AUTOMATICO', 'PRE_RESTAURACION')),
ADD CONSTRAINT chk_respaldo_estado
CHECK (estado IN ('EN_PROCESO', 'COMPLETADO', 'FALLIDO', 'ELIMINADO')),
ADD CONSTRAINT chk_respaldo_checksum
CHECK (checksum_sha256 IS NULL OR CHAR_LENGTH(checksum_sha256) = 64)
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};
