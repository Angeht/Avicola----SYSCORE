<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof Usuario, 403);

        $today = now()->toDateString();

        $salesSummary = DB::table('vw_resumen_diario_ventas')
            ->where('fecha', $today)
            ->first();

        $currentPrice = DB::table('vw_precio_vigente')
            ->where('fecha', $today)
            ->orderByDesc('vigente_desde')
            ->first();

        $stockBalances = DB::table('vw_saldo_mercaderia_actual')
            ->orderBy('producto')
            ->get();

        $alerts = DB::table('vw_alertas_operativas')
            ->orderByDesc('fecha')
            ->orderByDesc('referencia_id')
            ->limit(5)
            ->get();

        $openCashSession = DB::table('sesiones_caja')
            ->where('usuario_id', $user->getKey())
            ->whereNull('cierre_at')
            ->orderByDesc('id')
            ->first();

        return view('dashboard', [
            'activeClients' => DB::table('clientes')->where('activo', true)->count(),
            'activeSuppliers' => DB::table('proveedores')->where('activo', true)->count(),
            'alerts' => $alerts,
            'authenticatedUser' => $user,
            'currentPrice' => $currentPrice,
            'openCashSession' => $openCashSession,
            'salesSummary' => $salesSummary,
            'stockBirds' => (int) $stockBalances->sum('pollos_disponibles'),
            'stockBalances' => $stockBalances,
            'stockKilograms' => (float) $stockBalances->sum('kg_disponibles'),
        ]);
    }
}
