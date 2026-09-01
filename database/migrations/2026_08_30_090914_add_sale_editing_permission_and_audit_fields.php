<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->unsignedBigInteger('editada_por')->nullable()->after('observacion');
            $table->dateTime('editada_at')->nullable()->after('editada_por')->index();
            $table->string('motivo_edicion', 255)->nullable()->after('editada_at');
            $table->foreign('editada_por')->references('id')->on('usuarios');
        });

        DB::statement(<<<'SQL'
ALTER TABLE ventas
ADD CONSTRAINT chk_venta_edicion CHECK (
    (editada_at IS NULL AND editada_por IS NULL AND motivo_edicion IS NULL)
    OR
    (editada_at IS NOT NULL AND editada_por IS NOT NULL
     AND motivo_edicion IS NOT NULL AND CHAR_LENGTH(TRIM(motivo_edicion)) >= 10)
)
SQL);

        DB::table('permisos')->updateOrInsert(
            ['codigo' => 'VENTAS_EDITAR'],
            [
                'nombre' => 'Editar ventas',
                'descripcion' => 'Corregir cliente, productos, pesajes y precios de una venta activa.',
            ],
        );

        $permissionId = DB::table('permisos')->where('codigo', 'VENTAS_EDITAR')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('nombre', ['ADMINISTRADOR', 'ADMINISTRADOR OPERATIVO', 'CAJA'])
            ->pluck('id');

        if ($permissionId !== null) {
            DB::table('rol_permiso')->insertOrIgnore(
                $roleIds
                    ->map(fn (mixed $roleId): array => [
                        'rol_id' => $roleId,
                        'permiso_id' => $permissionId,
                    ])
                    ->all(),
            );
        }

    }

    public function down(): void
    {
        $permissionId = DB::table('permisos')->where('codigo', 'VENTAS_EDITAR')->value('id');

        if ($permissionId !== null) {
            DB::table('rol_permiso')->where('permiso_id', $permissionId)->delete();
            DB::table('permisos')->where('id', $permissionId)->delete();
        }

        DB::statement('ALTER TABLE ventas DROP CHECK chk_venta_edicion');

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropForeign(['editada_por']);
            $table->dropIndex(['editada_at']);
            $table->dropColumn(['editada_por', 'editada_at', 'motivo_edicion']);
        });
    }
};
