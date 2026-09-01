<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesajes_carga', function (Blueprint $table) {
            $table->foreignId('editado_por')
                ->nullable()
                ->after('observacion')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('autorizado_por')
                ->nullable()
                ->after('editado_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->dateTime('editado_at')->nullable()->after('autorizado_por');
        });
    }

    public function down(): void
    {
        Schema::table('pesajes_carga', function (Blueprint $table) {
            $table->dropConstrainedForeignId('autorizado_por');
            $table->dropConstrainedForeignId('editado_por');
            $table->dropColumn('editado_at');
        });
    }
};
