<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('medios_pago')->updateOrInsert(
            ['codigo' => 'TRANSFERENCIA'],
            [
                'nombre' => 'Transferencia',
                'es_efectivo' => false,
                'activo' => true,
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('medios_pago')
            ->where('codigo', 'TRANSFERENCIA')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('cobranzas')
                    ->whereColumn('cobranzas.medio_pago_id', 'medios_pago.id');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('pagos_proveedor')
                    ->whereColumn('pagos_proveedor.medio_pago_id', 'medios_pago.id');
            })
            ->delete();
    }
};
