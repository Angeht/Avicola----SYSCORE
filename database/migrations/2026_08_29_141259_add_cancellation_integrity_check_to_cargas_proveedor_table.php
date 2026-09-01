<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->dropForeign(['anulada_por']);
        });

        DB::statement(<<<'SQL'
ALTER TABLE cargas_proveedor
ADD CONSTRAINT chk_carga_proveedor_anulacion
CHECK (
    (anulada_at IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL)
    OR
    (anulada_at IS NOT NULL AND anulada_por IS NOT NULL
        AND motivo_anulacion IS NOT NULL
        AND CHAR_LENGTH(TRIM(motivo_anulacion)) >= 10)
)
SQL);

        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->foreign('anulada_por')
                ->references('id')
                ->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->dropForeign(['anulada_por']);
        });

        DB::statement('ALTER TABLE cargas_proveedor DROP CHECK chk_carga_proveedor_anulacion');

        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->foreign('anulada_por')
                ->references('id')
                ->on('usuarios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
