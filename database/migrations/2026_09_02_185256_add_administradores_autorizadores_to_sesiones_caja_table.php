<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sesiones_caja', function (Blueprint $table) {
            $table->foreignId('apertura_autorizada_por')
                ->nullable()
                ->after('apertura_at')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('cierre_autorizada_por')
                ->nullable()
                ->after('cerrada_por')
                ->constrained('usuarios')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sesiones_caja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apertura_autorizada_por');
            $table->dropConstrainedForeignId('cierre_autorizada_por');
        });
    }
};
