<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#101310">

        <title>@yield('title', 'Acceso') · {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-ink-950">
        <a href="#main-content" class="fixed top-3 left-3 z-50 -translate-y-20 bg-hazard px-4 py-2 font-semibold text-ink-950 transition focus:translate-y-0">
            Ir al contenido
        </a>

        <div class="grid min-h-screen lg:grid-cols-[minmax(360px,0.85fr)_1.15fr]">
            <aside class="industrial-hatch relative hidden overflow-hidden border-r border-white/10 bg-ink-950 p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14">
                <div class="absolute -top-32 -right-24 size-80 rounded-full border border-hazard/15 bg-hazard/5 blur-3xl"></div>
                <div class="relative">
                    <x-app-logo inverse />
                </div>

                <div class="relative max-w-xl">
                    <p class="font-mono text-[10px] font-semibold tracking-[0.28em] text-hazard uppercase">Sistema / 01</p>
                    <h1 class="mt-6 font-display text-6xl leading-[0.88] font-extrabold tracking-tight uppercase xl:text-7xl">
                        Operación<br>
                        <span class="text-hazard">bajo control.</span>
                    </h1>
                    <p class="mt-7 max-w-md text-base leading-7 text-steel-300">
                        Ventas, pesajes, caja y mercadería conectados en una sola estación de trabajo.
                    </p>
                </div>

                <div class="relative grid grid-cols-3 border border-white/10">
                    <div class="border-r border-white/10 p-4">
                        <span class="block font-display text-2xl font-bold text-white">30</span>
                        <span class="font-mono text-[8px] tracking-widest text-steel-300 uppercase">Tablas</span>
                    </div>
                    <div class="border-r border-white/10 p-4">
                        <span class="block font-display text-2xl font-bold text-white">17</span>
                        <span class="font-mono text-[8px] tracking-widest text-steel-300 uppercase">Reportes</span>
                    </div>
                    <div class="p-4">
                        <span class="block font-display text-2xl font-bold text-hazard">24/7</span>
                        <span class="font-mono text-[8px] tracking-widest text-steel-300 uppercase">Control</span>
                    </div>
                </div>
            </aside>

            <main id="main-content" class="industrial-grid relative flex min-h-screen items-center justify-center bg-canvas px-5 py-10 sm:px-8 lg:px-12">
                <div class="absolute top-0 right-0 h-2 w-1/3 bg-hazard"></div>
                <div class="w-full max-w-md">
                    <div class="mb-10 lg:hidden">
                        <x-app-logo />
                    </div>

                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
