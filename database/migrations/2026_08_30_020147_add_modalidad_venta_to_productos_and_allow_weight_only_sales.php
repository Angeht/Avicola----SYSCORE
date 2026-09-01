<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->enum('modalidad_venta', ['PESAJE_VIVO', 'SOLO_PESO'])
                ->default('PESAJE_VIVO')
                ->after('unidad_medida_id');
        });

        DB::table('productos')
            ->whereRaw('UPPER(nombre) LIKE ?', ['%BEN%FICIADO%'])
            ->update(['modalidad_venta' => 'SOLO_PESO']);

        DB::statement('ALTER TABLE pesajes_venta DROP CHECK chk_pesaje_venta_pollos');
        DB::statement('ALTER TABLE pesajes_venta ADD CONSTRAINT chk_pesaje_venta_pollos CHECK (cantidad_pollos >= 0)');
    }

    public function down(): void
    {
        if (DB::table('pesajes_venta')->where('cantidad_pollos', 0)->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan ventas registradas solo por peso.');
        }

        DB::statement('ALTER TABLE pesajes_venta DROP CHECK chk_pesaje_venta_pollos');
        DB::statement('ALTER TABLE pesajes_venta ADD CONSTRAINT chk_pesaje_venta_pollos CHECK (cantidad_pollos > 0)');

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('modalidad_venta');
        });
    }
};
