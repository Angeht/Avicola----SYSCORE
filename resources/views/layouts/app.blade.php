<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#101310">

        <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas">
        <a href="#main-content" class="fixed top-3 left-3 z-[70] -translate-y-20 bg-hazard px-4 py-2 font-semibold text-ink-950 transition focus:translate-y-0">
            Ir al contenido
        </a>

        <div data-sidebar-backdrop data-open="false" class="fixed inset-0 z-40 hidden bg-ink-950/70 backdrop-blur-sm data-[open=true]:block lg:hidden"></div>

        <aside id="main-sidebar" data-sidebar data-open="false" class="industrial-hatch fixed inset-y-0 left-0 z-50 flex w-[286px] -translate-x-full flex-col border-r border-white/10 bg-ink-950 text-white transition-transform duration-300 data-[open=true]:translate-x-0 lg:translate-x-0">
            <div class="border-b border-white/10 px-5 py-5">
                <x-app-logo :name="$company?->nombre_comercial ?: config('app.name')" inverse />
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-6" aria-label="Navegación principal">
                <p class="px-3 font-mono text-[9px] font-semibold tracking-[0.22em] text-steel-500 uppercase">Control</p>
                <div class="mt-3 grid gap-1">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <x-slot:icon>
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" />
                            </svg>
                        </x-slot:icon>
                        Panel operativo
                    </x-nav-link>

                    @if ($authenticatedUser?->tienePermiso('REPORTES_VER'))
                        <x-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">
                            <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3" /><path d="M2 21h20" /></svg></x-slot:icon>
                            Reportes
                        </x-nav-link>
                    @endif

                    @if ($authenticatedUser?->tienePermiso('AUDITORIA_VER'))
                        <x-nav-link :href="route('auditorias.index')" :active="request()->routeIs('auditorias.*')">
                            <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 6v5c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-3Z" /><path d="m8.5 12 2.2 2.2 4.8-5" /></svg></x-slot:icon>
                            Auditoría
                        </x-nav-link>
                    @endif
                </div>

                <div class="mt-8">
                    <p class="px-3 font-mono text-[9px] font-semibold tracking-[0.22em] text-steel-500 uppercase">Catálogos</p>
                    <div class="mt-3 grid gap-1">
                        @if ($authenticatedUser?->tieneAlgunPermiso(['VENTAS_REGISTRAR', 'COBRANZAS_REGISTRAR']))
                            <x-nav-link :href="route('clientes.index')" :active="request()->routeIs('clientes.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-9v6m-3-3h6" /></svg></x-slot:icon>
                                Clientes
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['CARGAS_REGISTRAR', 'PROVEEDORES_PAGAR']))
                            <x-nav-link :href="route('proveedores.index')" :active="request()->routeIs('proveedores.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 20h18M5 20V8l7-4 7 4v12M9 11h6m-6 4h6" /></svg></x-slot:icon>
                                Proveedores
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('TIPOS_JABA_GESTIONAR'))
                            <x-nav-link :href="route('tipos-jaba.index')" :active="request()->routeIs('tipos-jaba.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16l-2 12H6L4 7Z" /><path d="M8 7V4h8v3M7 11h10m-9 4h8" /></svg></x-slot:icon>
                                Jabas y taras
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['CARGAS_REGISTRAR', 'PRECIO_DIA_GESTIONAR', 'MERCADERIA_AJUSTAR']))
                            <x-nav-link :href="route('productos.index')" :active="request()->routeIs('productos.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /></svg></x-slot:icon>
                                Productos
                            </x-nav-link>
                        @endif
                    </div>
                </div>

                <div class="mt-8">
                    <div class="flex items-center justify-between px-3">
                        <p class="font-mono text-[9px] font-semibold tracking-[0.22em] text-steel-500 uppercase">Operación</p>
                        <span class="bg-signal/15 px-1.5 py-0.5 font-mono text-[8px] text-signal uppercase">Flujo</span>
                    </div>
                    <div class="mt-3 grid gap-1">
                        @if ($authenticatedUser?->tienePermiso('PRECIO_DIA_GESTIONAR'))
                            <x-nav-link :href="route('precios-dia.index')" :active="request()->routeIs('precios-dia.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12 12 20 4 12V4h8l8 8Z" /><circle cx="8.5" cy="8.5" r="1.2" /></svg></x-slot:icon>
                                Precio del día
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('CAJA_ABRIR_CERRAR'))
                            <x-nav-link :href="route('caja.index')" :active="request()->routeIs('caja.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Z" /><path d="M7 7V4h10v3m-4 6h7" /><circle cx="9" cy="13" r="2" /></svg></x-slot:icon>
                                Apertura y cierre
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['VENTAS_REGISTRAR', 'VENTAS_ANULAR']))
                            <x-nav-link :href="route('ventas.index')" :active="request()->routeIs('ventas.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3M8 12h8m-8 3h5" /><path d="m16 15 2 2 3-4" /></svg></x-slot:icon>
                                Ventas y pesajes
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['CARGAS_REGISTRAR', 'CARGAS_ANULAR']))
                            <x-nav-link :href="route('cargas-proveedor.index')" :active="request()->routeIs('cargas-proveedor.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8h12v10H3V8Zm12 3h3l3 3v4h-6v-7Z" /><circle cx="7" cy="19" r="2" /><circle cx="18" cy="19" r="2" /></svg></x-slot:icon>
                                Cargas de proveedor
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['PROVEEDORES_PAGAR', 'PROVEEDORES_PAGO_ANULAR']))
                            <x-nav-link :href="route('pagos-proveedor.index')" :active="request()->routeIs('pagos-proveedor.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3" /><path d="M8 12h8m-8 3h5" /></svg></x-slot:icon>
                                Pagos a proveedores
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['COBRANZAS_REGISTRAR', 'COBRANZAS_ANULAR']))
                            <x-nav-link :href="route('cobranzas.index')" :active="request()->routeIs('cobranzas.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16v12H4V7Zm3-3h10v3" /><path d="M8 12h8m-8 3h8" /><circle cx="16.5" cy="13.5" r="2.5" /></svg></x-slot:icon>
                                Cobranzas
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tieneAlgunPermiso(['MERCADERIA_AJUSTAR', 'MERCADERIA_CONCILIAR']))
                            <x-nav-link :href="route('mercaderia.index')" :active="request()->routeIs('mercaderia.*', 'beneficiados.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 7 8-4 8 4-8 4-8-4Z" /><path d="m4 7v10l8 4 8-4V7m-8 4v10" /><path d="M8 15h3m5-2v4m-2-2h4" /></svg></x-slot:icon>
                                Mercadería
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('MERCADERIA_CONCILIAR'))
                            <x-nav-link :href="route('conciliaciones-mercaderia.index')" :active="request()->routeIs('conciliaciones-mercaderia.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16v12H4V6Z" /><path d="m7 12 3 3 6-7" /></svg></x-slot:icon>
                                Conciliación física
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('CONFIGURACION_EMPRESA_GESTIONAR'))
                            <x-nav-link :href="route('configuracion.edit')" :active="request()->routeIs('configuracion.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16v12H4V6Z" /><path d="M8 10h8m-8 4h5" /><path d="M7 3v3m10-3v3" /></svg></x-slot:icon>
                                Configuración
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('USUARIOS_GESTIONAR'))
                            <x-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*', 'roles.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20v-2a6 6 0 0 1 12 0v2m2-9h4m-2-2v4" /><path d="M15 18a5 5 0 0 1 6-4" /></svg></x-slot:icon>
                                Usuarios y roles
                            </x-nav-link>
                        @endif

                        @if ($authenticatedUser?->tienePermiso('RESPALDOS_GESTIONAR'))
                            <x-nav-link :href="route('respaldos.index')" :active="request()->routeIs('respaldos.*')">
                                <x-slot:icon><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><ellipse cx="12" cy="5" rx="7" ry="3" /><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3 1.1 0 2.2-.1 3-.3" /><path d="m17 16 2 2 3-4" /></svg></x-slot:icon>
                                Respaldos y restauración
                            </x-nav-link>
                        @endif
                    </div>
                </div>
            </nav>

            <div class="border-t border-white/10 p-4">
                <a href="{{ route('profile.password.edit') }}" class="group flex items-center gap-3 border border-white/10 bg-white/4 p-3 transition hover:border-white/20 hover:bg-white/7">
                    <span class="grid size-10 shrink-0 place-items-center bg-hazard font-display text-lg font-bold text-ink-950">
                        {{ $authenticatedUser?->iniciales() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $authenticatedUser?->nombreCompleto() }}</span>
                        <span class="mt-0.5 block truncate font-mono text-[8px] tracking-wider text-steel-300 uppercase">
                            {{ $authenticatedUser?->roles->pluck('nombre')->join(' · ') }}
                        </span>
                    </span>
                    <svg class="size-4 text-steel-500 transition group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </a>

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center justify-center gap-2 border border-white/10 text-xs font-semibold tracking-wider text-steel-300 uppercase transition hover:border-danger/60 hover:bg-danger/10 hover:text-white">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M10 17l5-5-5-5m5 5H3m11-8h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5" />
                        </svg>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-[286px]">
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-line bg-paper/90 px-4 backdrop-blur-md sm:px-6 lg:px-8">
                <button type="button" data-sidebar-toggle aria-controls="main-sidebar" aria-expanded="false" class="grid size-11 place-items-center border border-line text-ink-950 lg:hidden" aria-label="Abrir menú">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <div class="ml-4 min-w-0 lg:ml-0">
                    <p class="truncate font-display text-lg font-bold tracking-wide text-ink-950 uppercase">@yield('section', 'Panel operativo')</p>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden font-mono text-[9px] tracking-[0.16em] text-steel-500 uppercase sm:block">
                        {{ now()->translatedFormat('D, d M · H:i') }}
                    </span>
                    <span class="flex items-center gap-2 border border-signal/25 bg-signal-soft px-2.5 py-1.5 font-mono text-[9px] font-semibold tracking-wider text-signal uppercase">
                        <span class="size-1.5 animate-pulse bg-signal"></span>
                        Sistema activo
                    </span>
                </div>
            </header>

            <main id="main-content" class="industrial-grid min-h-[calc(100vh-4rem)] px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
                <div class="mx-auto max-w-[1480px]">
                    <x-flash-message />
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
