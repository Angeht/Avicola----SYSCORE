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
            $table->foreignId('anulacion_autorizada_por')
                ->nullable()
                ->after('anulada_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        $this->createCancellationTrigger(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->createCancellationTrigger(false);

        Schema::table('cargas_proveedor', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anulacion_autorizada_por');
        });
    }

    private function createCancellationTrigger(bool $requiresAuthorizer): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_carga_proveedor_validar_anulacion');
        $authorizerRequired = $requiresAuthorizer
            ? ' OR NEW.anulacion_autorizada_por IS NULL'
            : '';
        $authorizerImmutable = $requiresAuthorizer
            ? "\n        OR NOT (OLD.anulacion_autorizada_por <=> NEW.anulacion_autorizada_por)"
            : '';

        DB::unprepared(<<<SQL
CREATE TRIGGER trg_carga_proveedor_validar_anulacion
BEFORE UPDATE ON cargas_proveedor
FOR EACH ROW
BEGIN
    DECLARE v_pagos_vigentes INT DEFAULT 0;

    IF OLD.anulada_at IS NULL AND NEW.anulada_at IS NOT NULL THEN
        SELECT COUNT(*)
          INTO v_pagos_vigentes
        FROM pagos_proveedor
        WHERE carga_id = OLD.id
          AND anulada_at IS NULL;

        IF v_pagos_vigentes > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Anule primero los pagos vigentes de la carga.';
        END IF;

        IF NEW.anulada_por IS NULL{$authorizerRequired} OR NEW.motivo_anulacion IS NULL OR CHAR_LENGTH(TRIM(NEW.motivo_anulacion)) < 10 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La anulación requiere responsable, autorización, fecha y un motivo válido.';
        END IF;
    END IF;

    IF OLD.anulada_at IS NOT NULL AND (
        NOT (OLD.anulada_por <=> NEW.anulada_por){$authorizerImmutable}
        OR NOT (OLD.anulada_at <=> NEW.anulada_at)
        OR NOT (OLD.motivo_anulacion <=> NEW.motivo_anulacion)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La anulación de una carga no puede modificarse ni revertirse.';
    END IF;
END
SQL);
    }
};
