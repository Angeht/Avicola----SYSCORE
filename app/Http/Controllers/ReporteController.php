<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerAccountReportRequest;
use App\Http\Requests\ReportFiltersRequest;
use App\Models\Cliente;
use App\Models\ConfiguracionEmpresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    /**
     * @var array<string, array{
     *     title: string,
     *     description: string,
     *     eyebrow: string,
     *     filters: list<string>,
     *     states: array<string, string>,
     *     columns: array<string, array{label: string, format: string}>
     * }>
     */
    private const REPORTS = [
        'ventas' => [
            'title' => 'Ventas por periodo',
            'description' => 'Detalle por producto, cliente y estado de cada venta.',
            'eyebrow' => 'Comercial / Ventas',
            'filters' => ['cliente', 'producto', 'estado'],
            'states' => ['TODOS' => 'Todos los estados', 'ACTIVA' => 'Activas', 'ANULADA' => 'Anuladas'],
            'columns' => [
                'fecha' => ['label' => 'Fecha', 'format' => 'date'],
                'numero_venta' => ['label' => 'Venta', 'format' => 'text'],
                'cliente' => ['label' => 'Cliente', 'format' => 'text'],
                'producto' => ['label' => 'Producto', 'format' => 'text'],
                'cantidad_pollos' => ['label' => 'Aves', 'format' => 'integer'],
                'peso_neto_kg' => ['label' => 'Peso kg', 'format' => 'decimal3'],
                'precio_aplicado_kg' => ['label' => 'Precio/kg', 'format' => 'money4'],
                'total_detalle' => ['label' => 'Total', 'format' => 'money'],
                'estado' => ['label' => 'Estado', 'format' => 'status'],
            ],
        ],
        'cuentas-cobrar' => [
            'title' => 'Deudas y abonos por cliente',
            'description' => 'Muestra por cliente cuántas ventas se realizaron, la deuda total, lo abonado y cuánto falta pagar.',
            'eyebrow' => 'Finanzas / Clientes',
            'filters' => ['cliente', 'estado'],
            'states' => [
                'TODOS' => 'Todos los clientes',
                'PENDIENTE' => 'Sin pagos',
                'PARCIAL' => 'Con deuda actual',
                'SALDADA' => 'Sin deuda',
                'SALDO_FAVOR' => 'Con saldo a favor',
            ],
            'columns' => [
                'cliente' => ['label' => 'Cliente', 'format' => 'text'],
                'cantidad_ventas' => ['label' => 'Ventas realizadas', 'format' => 'integer'],
                'total_deuda' => ['label' => 'Total deuda', 'format' => 'money'],
                'total_abonado' => ['label' => 'Total abonado', 'format' => 'money'],
                'deuda_actual' => ['label' => 'Restante por pagar', 'format' => 'money'],
                'cantidad_pagos' => ['label' => 'Cobros', 'format' => 'integer'],
                'ultimo_pago' => ['label' => 'Último pago', 'format' => 'date'],
                'estado' => ['label' => 'Estado', 'format' => 'status'],
            ],
        ],
        'deudas-proveedores' => [
            'title' => 'Deudas con proveedores',
            'description' => 'Cargas pendientes de pago, proveedor, producto y saldo actual.',
            'eyebrow' => 'Finanzas / Proveedores',
            'filters' => ['proveedor', 'producto', 'estado'],
            'states' => ['TODOS' => 'Pendientes y parciales', 'PENDIENTE' => 'Sin pagos', 'PARCIAL' => 'Pago parcial'],
            'columns' => [
                'fecha' => ['label' => 'Fecha', 'format' => 'date'],
                'numero_carga' => ['label' => 'Carga', 'format' => 'text'],
                'proveedor' => ['label' => 'Proveedor', 'format' => 'text'],
                'producto' => ['label' => 'Producto', 'format' => 'text'],
                'peso_neto_kg' => ['label' => 'Peso kg', 'format' => 'decimal3'],
                'costo_total' => ['label' => 'Costo', 'format' => 'money'],
                'total_pagado' => ['label' => 'Pagado', 'format' => 'money'],
                'saldo_pendiente' => ['label' => 'Saldo', 'format' => 'money'],
                'estado' => ['label' => 'Estado', 'format' => 'status'],
            ],
        ],
        'mercaderia' => [
            'title' => 'Movimientos y valorización',
            'description' => 'Entradas, salidas y valor estimado según el precio vigente de cada fecha.',
            'eyebrow' => 'Inventario / Mercadería',
            'filters' => ['producto'],
            'states' => [],
            'columns' => [
                'fecha' => ['label' => 'Fecha', 'format' => 'date'],
                'producto' => ['label' => 'Producto', 'format' => 'text'],
                'tipo_movimiento' => ['label' => 'Movimiento', 'format' => 'status'],
                'referencia_id' => ['label' => 'Referencia', 'format' => 'integer'],
                'pollos_entrada' => ['label' => 'Aves entrada', 'format' => 'integer'],
                'pollos_salida' => ['label' => 'Aves salida', 'format' => 'integer'],
                'kg_entrada' => ['label' => 'Kg entrada', 'format' => 'decimal3'],
                'kg_salida' => ['label' => 'Kg salida', 'format' => 'decimal3'],
                'precio_kg' => ['label' => 'Precio/kg', 'format' => 'money4'],
                'valor_movimiento' => ['label' => 'Valor neto', 'format' => 'money'],
            ],
        ],
        'caja' => [
            'title' => 'Apertura y cierre del día',
            'description' => 'Ingresos, egresos y resultado general por jornada, incluyendo todos los medios de pago.',
            'eyebrow' => 'Tesorería / Jornadas',
            'filters' => ['usuario', 'estado'],
            'states' => ['TODOS' => 'Todas las jornadas', 'ABIERTA' => 'Abiertas', 'CERRADA' => 'Cerradas'],
            'columns' => [
                'fecha' => ['label' => 'Fecha', 'format' => 'date'],
                'usuario' => ['label' => 'Responsable', 'format' => 'text'],
                'estado' => ['label' => 'Estado', 'format' => 'status'],
                'monto_apertura' => ['label' => 'Apertura', 'format' => 'money'],
                'ingresos_efectivo' => ['label' => 'Ingreso efectivo', 'format' => 'money'],
                'ingresos_otros' => ['label' => 'Otros ingresos', 'format' => 'money'],
                'egresos_efectivo' => ['label' => 'Egreso efectivo', 'format' => 'money'],
                'egresos_otros' => ['label' => 'Otros egresos', 'format' => 'money'],
                'neto_otros' => ['label' => 'Neto otros medios', 'format' => 'money'],
                'efectivo_esperado' => ['label' => 'Efectivo esperado', 'format' => 'money'],
                'monto_contado' => ['label' => 'Contado', 'format' => 'money'],
                'diferencia' => ['label' => 'Diferencia', 'format' => 'money'],
                'resultado_general' => ['label' => 'Resultado general', 'format' => 'money'],
            ],
        ],
    ];

    public function index(): View
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return view('reportes.index', [
            'metrics' => [
                'ventas' => (float) DB::table('vw_totales_venta')
                    ->where('estado', 'ACTIVA')
                    ->whereDate('fecha_venta', '>=', $monthStart)
                    ->whereDate('fecha_venta', '<=', $today)
                    ->sum('total_venta'),
                'cuentas_cobrar' => (float) DB::query()
                    ->fromSub($this->receivablesQuery([
                        'cliente_id' => null,
                        'desde' => '2000-01-01',
                        'estado' => 'TODOS',
                        'hasta' => $today,
                    ])->reorder(), 'cuentas_cliente')
                    ->sum('deuda_actual'),
                'deudas_proveedores' => (float) DB::table('vw_saldos_carga_proveedor')
                    ->where('saldo_pendiente', '>', 0)
                    ->sum('saldo_pendiente'),
                'ingresos_hoy' => (float) DB::table('vw_cobranzas_diarias')
                    ->where('fecha', $today)
                    ->sum('total_cobrado'),
                'stock_kg' => (float) DB::table('vw_saldo_mercaderia_actual')->sum('kg_disponibles'),
            ],
            'reports' => self::REPORTS,
        ]);
    }

    public function customerAccount(CustomerAccountReportRequest $request, Cliente $cliente): View
    {
        $cutoff = $request->string('hasta')->toString();
        $account = $this->customerAccountData($cliente, $cutoff);

        return view('reportes.customer-account', [
            'cliente' => $cliente,
            'cutoff' => $cutoff,
            'movements' => $account['movements']->paginate(30)->withQueryString(),
            'status' => $account['status'],
            'summary' => $account['summary'],
        ]);
    }

    public function customerAccountCsv(CustomerAccountReportRequest $request, Cliente $cliente): StreamedResponse
    {
        $cutoff = $request->string('hasta')->toString();
        $account = $this->customerAccountData($cliente, $cutoff);
        $filename = 'estado-de-cuenta-'.Str::slug($cliente->nombres_razon_social).'-'.$cutoff.'.csv';

        return response()->streamDownload(function () use ($account, $cliente, $cutoff): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Estado de cuenta del cliente'], ';', '"', '');
            fputcsv($stream, ['Cliente', $this->csvValue($cliente->nombres_razon_social, 'text')], ';', '"', '');
            fputcsv($stream, ['Documento', $this->csvValue($cliente->nro_documento, 'text')], ';', '"', '');
            fputcsv($stream, ['Corte al', $cutoff], ';', '"', '');
            fputcsv($stream, ['Total de ventas', $this->csvValue($account['summary']['total_sales'], 'money')], ';', '"', '');
            fputcsv($stream, ['Total abonado', $this->csvValue($account['summary']['total_collections'], 'money')], ';', '"', '');
            fputcsv($stream, ['Restante por pagar', $this->csvValue($account['summary']['remaining'], 'money')], ';', '"', '');
            fputcsv($stream, [], ';', '"', '');
            fputcsv($stream, ['Fecha', 'Movimiento', 'Documento', 'Detalle', 'Venta (cargo)', 'Abono (pago)', 'Saldo acumulado'], ';', '"', '');

            foreach ($account['movements']->cursor() as $movement) {
                fputcsv($stream, [
                    $movement->fecha_movimiento,
                    $movement->tipo,
                    $this->csvValue($movement->documento, 'text'),
                    $this->csvValue($movement->detalle, 'text'),
                    $this->csvValue($movement->cargo, 'money'),
                    $this->csvValue($movement->abono, 'money'),
                    $this->csvValue($movement->saldo_acumulado, 'money'),
                ], ';', '"', '');
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function customerAccountPrint(CustomerAccountReportRequest $request, Cliente $cliente): View
    {
        $cutoff = $request->string('hasta')->toString();
        $account = $this->customerAccountData($cliente, $cutoff);
        $totalRows = (clone $account['movements'])->reorder()->count();

        return view('reportes.customer-account-print', [
            'cliente' => $cliente,
            'cutoff' => $cutoff,
            'generatedAt' => now(),
            'movements' => $account['movements']->limit(1000)->get(),
            'status' => $account['status'],
            'summary' => $account['summary'],
            'truncated' => $totalRows > 1000,
        ]);
    }

    public function customerAccountTicket(CustomerAccountReportRequest $request, Cliente $cliente): View
    {
        $cutoff = $request->string('hasta')->toString();
        $account = $this->customerAccountData($cliente, $cutoff);
        $cycle = $this->currentCustomerAccountCycle($account['movements']);

        return view('reportes.customer-account-ticket', [
            'cliente' => $cliente,
            'company' => ConfiguracionEmpresa::query()
                ->with('tipoDocumento:id,codigo,nombre')
                ->firstOrNew(['id' => 1], [
                    'razon_social' => 'AVÍCOLA - CONFIGURAR',
                    'nombre_comercial' => 'AVÍCOLA - CONFIGURAR',
                ]),
            'cutoff' => $cutoff,
            'cycleReset' => $cycle['reset_after_settlement'],
            'generatedAt' => now(),
            'movements' => $cycle['movements']->limit(200)->get(),
            'status' => $account['status'],
            'summary' => $cycle['summary'],
            'truncated' => $cycle['movements_count'] > 200,
        ]);
    }

    public function show(ReportFiltersRequest $request, string $report): View
    {
        $definition = $this->definition($report);
        $filters = $request->safe()->all();
        $query = $this->reportQuery($report, $filters);
        $rows = $query->paginate(25)->withQueryString();

        return view('reportes.show', [
            ...$this->viewData($report, $definition, $filters),
            'rows' => $rows,
            'summary' => $this->summary($report, $query, $filters),
        ]);
    }

    public function csv(ReportFiltersRequest $request, string $report): StreamedResponse
    {
        $definition = $this->definition($report);
        $filters = $request->safe()->all();
        $query = $this->reportQuery($report, $filters);
        $columns = $definition['columns'];
        $filename = Str::slug($definition['title']).'-'.$filters['desde'].'-'.$filters['hasta'].'.csv';

        return response()->streamDownload(function () use ($columns, $query): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, collect($columns)->pluck('label')->all(), ';', '"', '');

            foreach ($query->cursor() as $row) {
                fputcsv($stream, collect($columns)
                    ->map(fn (array $column, string $field): string|int|float => $this->csvValue($row->{$field}, $column['format']))
                    ->all(), ';', '"', '');
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(ReportFiltersRequest $request, string $report): View
    {
        $definition = $this->definition($report);
        $filters = $request->safe()->all();
        $query = $this->reportQuery($report, $filters);
        $totalRows = (clone $query)->reorder()->count();

        return view('reportes.print', [
            ...$this->viewData($report, $definition, $filters),
            'generatedAt' => now(),
            'rows' => $query->limit(1000)->get(),
            'summary' => $this->summary($report, $query, $filters),
            'truncated' => $totalRows > 1000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function viewData(string $report, array $definition, array $filters): array
    {
        return [
            'clients' => in_array('cliente', $definition['filters'], true)
                ? Cliente::query()->select(['id', 'nombres_razon_social'])->orderBy('nombres_razon_social')->get()
                : collect(),
            'definition' => $definition,
            'filters' => $filters,
            'products' => in_array('producto', $definition['filters'], true)
                ? Producto::query()->select(['id', 'nombre'])->orderBy('nombre')->get()
                : collect(),
            'providers' => in_array('proveedor', $definition['filters'], true)
                ? Proveedor::query()->select(['id', 'nombre_razon_social'])->orderBy('nombre_razon_social')->get()
                : collect(),
            'reportKey' => $report,
            'users' => in_array('usuario', $definition['filters'], true)
                ? Usuario::query()->select(['id', 'nombres', 'apellidos', 'usuario'])->orderBy('apellidos')->orderBy('nombres')->get()
                : collect(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $report): array
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);

        return self::REPORTS[$report];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportQuery(string $report, array $filters): Builder
    {
        return match ($report) {
            'ventas' => $this->salesQuery($filters),
            'cuentas-cobrar' => $this->receivablesQuery($filters),
            'deudas-proveedores' => $this->supplierDebtsQuery($filters),
            'mercaderia' => $this->inventoryQuery($filters),
            'caja' => $this->cashQuery($filters),
            default => abort(404),
        };
    }

    /**
     * @return array{
     *     movements: Builder,
     *     status: string,
     *     summary: array{
     *         sales_count: int,
     *         total_sales: int|float,
     *         collections_count: int,
     *         total_collections: int|float,
     *         remaining: int|float,
     *         credit: int|float
     *     }
     * }
     */
    private function customerAccountData(Cliente $cliente, string $cutoff): array
    {
        $saleProducts = DB::table('venta_detalles as vd')
            ->join('precio_dia_versiones as pv', 'pv.id', '=', 'vd.precio_version_id')
            ->join('precios_dia as pd', 'pd.id', '=', 'pv.precio_dia_id')
            ->join('productos as p', 'p.id', '=', 'pd.producto_id')
            ->selectRaw("vd.venta_id, GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ', ') as productos")
            ->groupBy('vd.venta_id');
        $sales = DB::table('vw_totales_venta as v')
            ->leftJoinSub($saleProducts, 'productos_venta', fn (JoinClause $join): JoinClause => $join->on('productos_venta.venta_id', '=', 'v.venta_id'))
            ->selectRaw('v.fecha_venta as fecha_movimiento, 1 as orden, v.venta_id as referencia_id')
            ->selectRaw("'VENTA' as tipo, v.numero_venta as documento")
            ->selectRaw("CONCAT(COALESCE(productos_venta.productos, 'Sin producto'), ' · ', v.peso_neto_kg, ' kg') as detalle")
            ->selectRaw('v.total_venta as cargo, 0 as abono')
            ->where('v.cliente_id', $cliente->id)
            ->where('v.estado', 'ACTIVA')
            ->whereDate('v.fecha_venta', '<=', $cutoff);
        $collections = DB::table('cobranzas as c')
            ->join('medios_pago as mp', 'mp.id', '=', 'c.medio_pago_id')
            ->selectRaw('c.fecha_pago as fecha_movimiento, 2 as orden, c.id as referencia_id')
            ->selectRaw("'ABONO' as tipo, c.numero_cobranza as documento")
            ->selectRaw("CONCAT('Medio: ', mp.nombre) as detalle")
            ->selectRaw('0 as cargo, c.monto_total as abono')
            ->where('c.cliente_id', $cliente->id)
            ->whereNull('c.anulada_at')
            ->whereDate('c.fecha_pago', '<=', $cutoff);

        $salesCount = (clone $sales)->count();
        $collectionsCount = (clone $collections)->count();
        $totalSalesCents = (int) round((float) (clone $sales)->sum('v.total_venta') * 100);
        $totalCollectionsCents = (int) round((float) (clone $collections)->sum('c.monto_total') * 100);
        $remainingCents = max($totalSalesCents - $totalCollectionsCents, 0);
        $creditCents = max($totalCollectionsCents - $totalSalesCents, 0);
        $status = match (true) {
            $salesCount === 0 && $collectionsCount === 0 => 'SIN_MOVIMIENTOS',
            $creditCents > 0 => 'SALDO_FAVOR',
            $remainingCents === 0 => 'SALDADA',
            $totalCollectionsCents === 0 => 'PENDIENTE',
            default => 'PARCIAL',
        };
        $movements = DB::query()
            ->fromSub($sales->unionAll($collections), 'movimientos_cliente')
            ->select('movimientos_cliente.*')
            ->selectRaw('ROUND(SUM(cargo - abono) OVER (ORDER BY fecha_movimiento, orden, referencia_id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW), 2) as saldo_acumulado')
            ->orderBy('fecha_movimiento')
            ->orderBy('orden')
            ->orderBy('referencia_id');

        return [
            'movements' => $movements,
            'status' => $status,
            'summary' => [
                'sales_count' => $salesCount,
                'total_sales' => $totalSalesCents / 100,
                'collections_count' => $collectionsCount,
                'total_collections' => $totalCollectionsCents / 100,
                'remaining' => $remainingCents / 100,
                'credit' => $creditCents / 100,
            ],
        ];
    }

    /**
     * @return array{
     *     movements: Builder,
     *     movements_count: int,
     *     reset_after_settlement: bool,
     *     summary: array{
     *         sales_count: int,
     *         total_sales: int|float,
     *         collections_count: int,
     *         total_collections: int|float,
     *         remaining: int|float,
     *         credit: int|float
     *     }
     * }
     */
    private function currentCustomerAccountCycle(Builder $movements): array
    {
        $lastSettledMovement = DB::query()
            ->fromSub((clone $movements)->reorder(), 'historial_cuenta_cliente')
            ->where('saldo_acumulado', 0)
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('orden')
            ->orderByDesc('referencia_id')
            ->first(['fecha_movimiento', 'orden', 'referencia_id']);
        $cycleMovements = DB::query()
            ->fromSub((clone $movements)->reorder(), 'ciclo_cuenta_cliente')
            ->select('ciclo_cuenta_cliente.*')
            ->when($lastSettledMovement, function (Builder $query, object $settledMovement): void {
                $query->where(function (Builder $query) use ($settledMovement): void {
                    $query->where('fecha_movimiento', '>', $settledMovement->fecha_movimiento)
                        ->orWhere(function (Builder $query) use ($settledMovement): void {
                            $query->where('fecha_movimiento', $settledMovement->fecha_movimiento)
                                ->where('orden', '>', $settledMovement->orden);
                        })
                        ->orWhere(function (Builder $query) use ($settledMovement): void {
                            $query->where('fecha_movimiento', $settledMovement->fecha_movimiento)
                                ->where('orden', $settledMovement->orden)
                                ->where('referencia_id', '>', $settledMovement->referencia_id);
                        });
                });
            })
            ->orderBy('fecha_movimiento')
            ->orderBy('orden')
            ->orderBy('referencia_id');
        $totals = DB::query()
            ->fromSub((clone $cycleMovements)->reorder(), 'resumen_ciclo_cuenta')
            ->selectRaw('COUNT(*) as movements_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'VENTA' THEN 1 ELSE 0 END), 0) as sales_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ABONO' THEN 1 ELSE 0 END), 0) as collections_count")
            ->selectRaw('COALESCE(SUM(cargo), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(abono), 0) as total_collections')
            ->firstOrFail();
        $totalSalesCents = (int) round((float) $totals->total_sales * 100);
        $totalCollectionsCents = (int) round((float) $totals->total_collections * 100);

        return [
            'movements' => $cycleMovements,
            'movements_count' => (int) $totals->movements_count,
            'reset_after_settlement' => $lastSettledMovement !== null,
            'summary' => [
                'sales_count' => (int) $totals->sales_count,
                'total_sales' => $totalSalesCents / 100,
                'collections_count' => (int) $totals->collections_count,
                'total_collections' => $totalCollectionsCents / 100,
                'remaining' => max($totalSalesCents - $totalCollectionsCents, 0) / 100,
                'credit' => max($totalCollectionsCents - $totalSalesCents, 0) / 100,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesQuery(array $filters): Builder
    {
        return DB::table('vw_totales_venta_detalle as d')
            ->join('ventas as v', 'v.id', '=', 'd.venta_id')
            ->join('productos as p', 'p.id', '=', 'd.producto_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->selectRaw("v.id as venta_id, DATE(v.fecha_venta) as fecha, v.numero_venta, COALESCE(c.nombres_razon_social, 'Venta directa') as cliente, p.nombre as producto, d.cantidad_pollos, d.peso_neto_kg, d.precio_aplicado_kg, d.total_detalle, CASE WHEN v.anulada_at IS NULL THEN 'ACTIVA' ELSE 'ANULADA' END as estado")
            ->whereDate('v.fecha_venta', '>=', $filters['desde'])
            ->whereDate('v.fecha_venta', '<=', $filters['hasta'])
            ->when($filters['cliente_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('v.cliente_id', $id))
            ->when($filters['producto_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('d.producto_id', $id))
            ->when(($filters['estado'] ?? 'TODOS') === 'ACTIVA', fn (Builder $query): Builder => $query->whereNull('v.anulada_at'))
            ->when(($filters['estado'] ?? 'TODOS') === 'ANULADA', fn (Builder $query): Builder => $query->whereNotNull('v.anulada_at'))
            ->orderByDesc('v.fecha_venta')
            ->orderByDesc('v.id')
            ->orderBy('p.nombre');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function receivablesQuery(array $filters): Builder
    {
        $sales = DB::table('vw_totales_venta as v')
            ->selectRaw('v.cliente_id')
            ->selectRaw('COUNT(*) as cantidad_ventas')
            ->selectRaw('COALESCE(SUM(v.total_venta), 0) as total_deuda')
            ->whereNotNull('v.cliente_id')
            ->where('v.estado', 'ACTIVA')
            ->whereDate('v.fecha_venta', '<=', $filters['hasta'])
            ->groupBy('v.cliente_id');
        $collections = DB::table('cobranzas as co')
            ->selectRaw('co.cliente_id')
            ->selectRaw('COALESCE(SUM(co.monto_total), 0) as total_abonado')
            ->selectRaw('COUNT(*) as cantidad_pagos')
            ->selectRaw('MAX(co.fecha_pago) as ultimo_pago')
            ->whereNotNull('co.cliente_id')
            ->whereNull('co.anulada_at')
            ->whereDate('co.fecha_pago', '<=', $filters['hasta'])
            ->groupBy('co.cliente_id');
        $balances = DB::table('clientes as c')
            ->leftJoinSub($sales, 'v', fn (JoinClause $join): JoinClause => $join->on('v.cliente_id', '=', 'c.id'))
            ->leftJoinSub($collections, 'co', fn (JoinClause $join): JoinClause => $join->on('co.cliente_id', '=', 'c.id'))
            ->selectRaw('c.id as cliente_id, c.nombres_razon_social as cliente')
            ->selectRaw('COALESCE(v.cantidad_ventas, 0) as cantidad_ventas')
            ->selectRaw('ROUND(COALESCE(v.total_deuda, 0), 2) as total_deuda')
            ->selectRaw('ROUND(COALESCE(co.total_abonado, 0), 2) as total_abonado')
            ->selectRaw('ROUND(GREATEST(COALESCE(v.total_deuda, 0) - COALESCE(co.total_abonado, 0), 0), 2) as deuda_actual')
            ->selectRaw('COALESCE(co.cantidad_pagos, 0) as cantidad_pagos')
            ->selectRaw('co.ultimo_pago')
            ->selectRaw("CASE WHEN COALESCE(co.total_abonado, 0) > COALESCE(v.total_deuda, 0) THEN 'SALDO_FAVOR' WHEN COALESCE(v.total_deuda, 0) = COALESCE(co.total_abonado, 0) THEN 'SALDADA' WHEN COALESCE(co.total_abonado, 0) = 0 THEN 'PENDIENTE' ELSE 'PARCIAL' END as estado")
            ->where(function (Builder $query): void {
                $query->whereNotNull('v.cliente_id')
                    ->orWhereNotNull('co.cliente_id');
            })
            ->when($filters['cliente_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('c.id', $id));

        return DB::query()
            ->fromSub($balances, 'saldo_cliente')
            ->when(
                in_array($filters['estado'] ?? 'TODOS', ['PENDIENTE', 'PARCIAL', 'SALDADA', 'SALDO_FAVOR'], true),
                fn (Builder $query): Builder => $query->where('estado', $filters['estado']),
            )
            ->orderByDesc('deuda_actual')
            ->orderBy('cliente');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function supplierDebtsQuery(array $filters): Builder
    {
        return DB::table('vw_saldos_carga_proveedor as s')
            ->join('cargas_proveedor as c', 'c.id', '=', 's.carga_id')
            ->join('proveedores as pr', 'pr.id', '=', 's.proveedor_id')
            ->join('productos as p', 'p.id', '=', 'c.producto_id')
            ->selectRaw('s.fecha_carga as fecha, s.numero_carga, pr.nombre_razon_social as proveedor, p.nombre as producto, s.peso_neto_kg, s.costo_total, s.total_pagado, s.saldo_pendiente, s.estado_pago as estado')
            ->where('s.saldo_pendiente', '>', 0)
            ->whereDate('s.fecha_carga', '>=', $filters['desde'])
            ->whereDate('s.fecha_carga', '<=', $filters['hasta'])
            ->when($filters['proveedor_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('s.proveedor_id', $id))
            ->when($filters['producto_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('c.producto_id', $id))
            ->when(in_array($filters['estado'] ?? 'TODOS', ['PENDIENTE', 'PARCIAL'], true), fn (Builder $query): Builder => $query->where('s.estado_pago', $filters['estado']))
            ->orderBy('s.fecha_carga')
            ->orderBy('s.carga_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function inventoryQuery(array $filters): Builder
    {
        $priceSql = '(SELECT pv.precio_kg FROM vw_precio_vigente pv WHERE pv.producto_id = m.producto_id AND pv.fecha <= m.fecha_movimiento ORDER BY pv.fecha DESC, pv.vigente_desde DESC LIMIT 1)';

        return DB::table('vw_movimientos_mercaderia as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->selectRaw("m.fecha_movimiento as fecha, p.nombre as producto, m.tipo_movimiento, m.referencia_id, m.pollos_entrada, m.pollos_salida, m.kg_entrada, m.kg_salida, COALESCE($priceSql, 0) as precio_kg, ROUND(m.movimiento_neto_kg * COALESCE($priceSql, 0), 2) as valor_movimiento")
            ->whereDate('m.fecha_movimiento', '>=', $filters['desde'])
            ->whereDate('m.fecha_movimiento', '<=', $filters['hasta'])
            ->when($filters['producto_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('m.producto_id', $id))
            ->orderByDesc('m.fecha_movimiento')
            ->orderByDesc('m.referencia_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cashQuery(array $filters): Builder
    {
        return DB::table('vw_resumen_caja_usuario as c')
            ->join('usuarios as u', 'u.id', '=', 'c.usuario_id')
            ->selectRaw("c.fecha_operacion as fecha, CONCAT(u.nombres, ' ', u.apellidos) as usuario, c.estado, c.monto_apertura, c.ingresos_efectivo, c.ingresos_otros_medios as ingresos_otros, c.egresos_proveedor_efectivo as egresos_efectivo, c.egresos_proveedor_otros_medios as egresos_otros, c.neto_otros_medios as neto_otros, c.efectivo_esperado, c.monto_contado_efectivo as monto_contado, c.diferencia_efectivo as diferencia, ROUND(COALESCE(c.monto_contado_efectivo, c.efectivo_esperado) + c.neto_otros_medios, 2) as resultado_general")
            ->whereDate('c.fecha_operacion', '>=', $filters['desde'])
            ->whereDate('c.fecha_operacion', '<=', $filters['hasta'])
            ->when($filters['usuario_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('c.usuario_id', $id))
            ->when(in_array($filters['estado'] ?? 'TODOS', ['ABIERTA', 'CERRADA'], true), fn (Builder $query): Builder => $query->where('c.estado', $filters['estado']))
            ->orderByDesc('c.fecha_operacion')
            ->orderByDesc('c.sesion_caja_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, value: int|float, format: string}>
     */
    private function summary(string $report, Builder $query, array $filters): array
    {
        $summary = DB::query()->fromSub((clone $query)->reorder(), 'r');

        return match ($report) {
            'ventas' => $this->salesSummary($summary),
            'cuentas-cobrar' => $this->receivablesSummary($summary),
            'deudas-proveedores' => $this->supplierDebtsSummary($summary),
            'mercaderia' => $this->inventorySummary($summary, $filters),
            'caja' => $this->cashSummary($summary),
            default => [],
        };
    }

    /** @return list<array{label: string, value: int|float, format: string}> */
    private function salesSummary(Builder $query): array
    {
        $totals = $query->selectRaw('COUNT(DISTINCT venta_id) as operaciones, COALESCE(SUM(cantidad_pollos), 0) as aves, COALESCE(SUM(peso_neto_kg), 0) as kg, COALESCE(SUM(total_detalle), 0) as total')->first();

        return [
            ['label' => 'Operaciones', 'value' => (int) $totals->operaciones, 'format' => 'integer'],
            ['label' => 'Aves vendidas', 'value' => (int) $totals->aves, 'format' => 'integer'],
            ['label' => 'Peso vendido', 'value' => (float) $totals->kg, 'format' => 'decimal3'],
            ['label' => 'Venta total', 'value' => (float) $totals->total, 'format' => 'money'],
        ];
    }

    /** @return list<array{label: string, value: int|float, format: string}> */
    private function receivablesSummary(Builder $query): array
    {
        $totals = $query->selectRaw('COUNT(*) as clientes, COALESCE(SUM(total_deuda), 0) as ventas, COALESCE(SUM(total_abonado), 0) as pagos, COALESCE(SUM(deuda_actual), 0) as deuda')->first();

        return [
            ['label' => 'Clientes', 'value' => (int) $totals->clientes, 'format' => 'integer'],
            ['label' => 'Total deuda', 'value' => (float) $totals->ventas, 'format' => 'money'],
            ['label' => 'Total abonado', 'value' => (float) $totals->pagos, 'format' => 'money'],
            ['label' => 'Total restante', 'value' => (float) $totals->deuda, 'format' => 'money'],
        ];
    }

    /** @return list<array{label: string, value: int|float, format: string}> */
    private function supplierDebtsSummary(Builder $query): array
    {
        $totals = $query->selectRaw('COUNT(*) as cargas, COALESCE(SUM(costo_total), 0) as costo, COALESCE(SUM(total_pagado), 0) as pagado, COALESCE(SUM(saldo_pendiente), 0) as saldo')->first();

        return [
            ['label' => 'Cargas', 'value' => (int) $totals->cargas, 'format' => 'integer'],
            ['label' => 'Costo', 'value' => (float) $totals->costo, 'format' => 'money'],
            ['label' => 'Pagado', 'value' => (float) $totals->pagado, 'format' => 'money'],
            ['label' => 'Deuda pendiente', 'value' => (float) $totals->saldo, 'format' => 'money'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, value: int|float, format: string}>
     */
    private function inventorySummary(Builder $query, array $filters): array
    {
        $movements = $query->selectRaw('COALESCE(SUM(kg_entrada), 0) as entradas, COALESCE(SUM(kg_salida), 0) as salidas')->first();
        $priceAtCutoffSql = '(SELECT pv.precio_kg FROM vw_precio_vigente pv WHERE pv.producto_id = m.producto_id AND pv.fecha <= ? ORDER BY pv.fecha DESC, pv.vigente_desde DESC LIMIT 1)';
        $stock = DB::table('vw_movimientos_mercaderia as m')
            ->whereDate('m.fecha_movimiento', '<=', $filters['hasta'])
            ->when($filters['producto_id'] ?? null, fn (Builder $query, int|string $id): Builder => $query->where('m.producto_id', $id))
            ->selectRaw('COALESCE(SUM(m.movimiento_neto_kg), 0) as kg')
            ->selectRaw("COALESCE(SUM(m.movimiento_neto_kg * COALESCE($priceAtCutoffSql, 0)), 0) as valor", [$filters['hasta']])
            ->first();

        return [
            ['label' => 'Entradas del periodo', 'value' => (float) $movements->entradas, 'format' => 'decimal3'],
            ['label' => 'Salidas del periodo', 'value' => (float) $movements->salidas, 'format' => 'decimal3'],
            ['label' => 'Stock al corte', 'value' => (float) $stock->kg, 'format' => 'decimal3'],
            ['label' => 'Valorización al corte', 'value' => (float) $stock->valor, 'format' => 'money'],
        ];
    }

    /** @return list<array{label: string, value: int|float, format: string}> */
    private function cashSummary(Builder $query): array
    {
        $totals = $query->selectRaw('COUNT(*) as jornadas, COALESCE(SUM(ingresos_efectivo + ingresos_otros), 0) as ingresos, COALESCE(SUM(egresos_efectivo + egresos_otros), 0) as egresos, COALESCE(SUM(resultado_general), 0) as resultado')->first();

        return [
            ['label' => 'Jornadas', 'value' => (int) $totals->jornadas, 'format' => 'integer'],
            ['label' => 'Ingresos totales', 'value' => (float) $totals->ingresos, 'format' => 'money'],
            ['label' => 'Egresos totales', 'value' => (float) $totals->egresos, 'format' => 'money'],
            ['label' => 'Resultado general', 'value' => (float) $totals->resultado, 'format' => 'money'],
        ];
    }

    private function csvValue(mixed $value, string $format): string|int|float
    {
        if (in_array($format, ['integer', 'decimal3', 'money', 'money4'], true)) {
            return match ($format) {
                'integer' => (int) $value,
                'decimal3' => number_format((float) $value, 3, '.', ''),
                'money4' => number_format((float) $value, 4, '.', ''),
                default => number_format((float) $value, 2, '.', ''),
            };
        }

        $text = (string) $value;

        return preg_match('/^[=+\-@]/u', ltrim($text)) === 1 ? "'{$text}" : $text;
    }
}
