<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ $company->nombre_comercial ?: $company->razon_social }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        @media print {
            .ticket-controls { display: none !important; }
            body { background: #fff !important; }
            .ticket-paper { width: auto !important; min-height: 0 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-canvas text-ink-950 antialiased">
    <div class="ticket-controls mx-auto flex w-full max-w-[80mm] gap-2 px-3 py-4">
        <a href="{{ $backHref }}" class="inline-flex min-h-10 flex-1 items-center justify-center border border-line bg-white px-3 font-mono text-[9px] font-semibold tracking-wider uppercase">← Volver</a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-10 flex-1 items-center justify-center bg-ink-950 px-3 font-mono text-[9px] font-semibold tracking-wider text-white uppercase">Imprimir ticket</button>
    </div>

    <main class="ticket-paper mx-auto min-h-[120mm] w-full max-w-[80mm] bg-white px-4 py-6 shadow-panel sm:px-5">
        <header class="border-b-2 border-dashed border-ink-950 pb-4 text-center">
            <p class="font-display text-xl font-extrabold uppercase">{{ $company->nombre_comercial ?: $company->razon_social }}</p>
            @if ($company->razon_social && $company->razon_social !== $company->nombre_comercial)
                <p class="mt-1 text-[10px] font-semibold uppercase">{{ $company->razon_social }}</p>
            @endif
            @if ($company->nro_documento)
                <p class="mt-1 font-mono text-[10px]">{{ $company->tipoDocumento?->codigo ?? 'Documento' }}: {{ $company->nro_documento }}</p>
            @endif
            @if ($company->direccion)<p class="mt-1 text-[10px] leading-4">{{ $company->direccion }}</p>@endif
            @if ($company->telefono)<p class="text-[10px]">Tel. {{ $company->telefono }}</p>@endif
        </header>

        @if ($isCancelled)
            <div class="my-4 border-2 border-danger px-3 py-2 text-center font-mono text-sm font-bold tracking-[0.2em] text-danger uppercase">Anulado</div>
        @endif

        @yield('ticket-content')

        <footer class="mt-5 border-t-2 border-dashed border-ink-950 pt-4 text-center">
            @if ($company->mensaje_ticket)<p class="text-[11px] leading-5">{{ $company->mensaje_ticket }}</p>@endif
            <p class="mt-3 font-mono text-[8px] tracking-wider text-steel-500 uppercase">Documento de control interno</p>
        </footer>
    </main>
</body>
</html>
