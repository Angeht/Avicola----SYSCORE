<div>
    <!-- Simplicity is the essence of happiness. - Cedric Bledsoe -->
</div>
@extends('layouts.app')

@section('title', 'Centro de reportes')
@section('section', 'Reportes')

@section('content')
    @php
        $reportMeta = [
            'ventas' => ['metric' => $metrics['ventas'], 'metric_label' => 'Venta del mes', 'format' => 'money', 'code' => 'RPT-01'],
            'cuentas-cobrar' => ['metric' => $metrics['cuentas_cobrar'], 'metric_label' => 'Saldo por cobrar', 'format' => 'money', 'code' => 'RPT-02'],
            'deudas-proveedores' => ['metric' => $metrics['deudas_proveedores'], 'metric_label' => 'Deuda vigente', 'format' => 'money', 'code' => 'RPT-03'],
            'mercaderia' => ['metric' => $metrics['stock_kg'], 'metric_label' => 'Stock actual kg', 'format' => 'decimal', 'code' => 'RPT-04'],
            'caja' => ['metric' => $metrics['ingresos_hoy'], 'metric_label' => 'Ingresos de hoy', 'format' => 'money', 'code' => 'RPT-05'],
        ];
        $metricValue = static fn (array $meta): string => $meta['format'] === 'money'
            ? 'S/ '.number_format((float) $meta['metric'], 2, ',', '.')
            : number_format((float) $meta['metric'], 3, ',', '.');
    @endphp

    <header class="reveal-up grid gap-6 border-b border-ink-950 pb-8 lg:grid-cols-[1fr_360px] lg:items-end">
        <div><p class="font-mono text-[10px] font-semibold tracking-[0.2em] text-signal uppercase">Análisis / Decisiones</p><h1 class="mt-2 max-w-4xl font-display text-5xl font-bold tracking-tight text-ink-950 uppercase sm:text-6xl">Centro de reportes</h1><p class="mt-4 max-w-2xl text-sm leading-6 text-steel-500">Cruza fechas, clientes, productos y responsables. Cada reporte puede abrirse en pantalla, descargarse para Excel o imprimirse como PDF.</p></div>
        <aside class="corner-frame border border-line bg-paper p-5 shadow-panel"><p class="font-mono text-[9px] tracking-wider text-steel-500 uppercase">Corte operativo</p><p class="mt-2 font-display text-3xl font-bold text-ink-950">{{ now()->translatedFormat('d M Y') }}</p><div class="mt-4 h-1 bg-line"><span class="block h-full w-4/5 bg-hazard"></span></div><p class="mt-3 text-xs leading-5 text-steel-500">Los importes se calculan directamente desde las vistas consolidadas de la base de datos.</p></aside>
    </header>

    <section class="reveal-up reveal-up-delay-1 mt-7 grid gap-4 lg:grid-cols-2" aria-label="Reportes disponibles">
        @foreach ($reports as $key => $report)
            @php($meta = $reportMeta[$key])
            <a href="{{ route('reportes.show', $key) }}" class="group relative overflow-hidden border border-line bg-paper p-6 shadow-panel transition duration-300 hover:-translate-y-1 hover:border-ink-950 {{ $loop->last ? 'lg:col-span-2' : '' }}">
                <span class="absolute top-0 right-0 border-b border-l border-line bg-canvas px-3 py-2 font-mono text-[8px] font-semibold tracking-wider text-steel-500">{{ $meta['code'] }}</span>
                <div class="flex min-h-44 flex-col justify-between">
                    <div class="pr-16"><p class="font-mono text-[9px] font-semibold tracking-[0.18em] text-signal uppercase">{{ $report['eyebrow'] }}</p><h2 class="mt-3 font-display text-3xl font-bold text-ink-950 uppercase sm:text-4xl">{{ $report['title'] }}</h2><p class="mt-3 max-w-2xl text-sm leading-6 text-steel-500">{{ $report['description'] }}</p></div>
                    <div class="mt-7 flex items-end justify-between gap-5 border-t border-line pt-4"><div><p class="font-mono text-[8px] tracking-wider text-steel-500 uppercase">{{ $meta['metric_label'] }}</p><p class="mt-1 font-display text-2xl font-bold text-ink-950">{{ $metricValue($meta) }}</p></div><span class="grid size-11 place-items-center bg-ink-950 text-white transition group-hover:bg-hazard group-hover:text-ink-950">→</span></div>
                </div>
            </a>
        @endforeach
    </section>
@endsection
