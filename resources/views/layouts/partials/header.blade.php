@php($yo = auth()->user())
<div id="kt_app_header" class="app-header">
    <div class="app-container container-fluid d-flex align-items-stretch justify-content-between">

        {{-- Botón del menú lateral (móvil) --}}
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Mostrar menú">
            <button type="button" class="btn btn-icon btn-active-color-primary w-35px h-35px" data-sidebar-toggle aria-label="Mostrar menú">
                <i class="ki-outline ki-abstract-14 fs-1"></i>
            </button>
        </div>

        <div class="d-flex align-items-center flex-grow-1 min-w-0">
            <a href="{{ route('dashboard') }}" class="d-lg-none me-3 flex-shrink-0">
                <img alt="{{ config('academia.nombre') }}" src="{{ asset('assets/media/logos/academia-icon.png') }}" class="h-35px rounded">
            </a>
            <h1 class="text-gray-900 fw-bold fs-3 my-0 text-truncate">@yield('title', 'Inicio')</h1>
        </div>

        <div class="d-flex align-items-stretch justify-content-end flex-shrink-0">
            <div class="app-navbar flex-shrink-0">

                {{-- Modo claro / oscuro --}}
                <div class="app-navbar-item ms-1 ms-md-3">
                    <button type="button" class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px"
                            data-tema-toggle title="Cambiar modo claro/oscuro">
                        <i class="ki-outline ki-moon fs-1 tema-claro"></i>
                        <i class="ki-outline ki-night-day fs-1 tema-oscuro"></i>
                    </button>
                </div>

                {{-- Usuario --}}
                <div class="app-navbar-item ms-1 ms-md-3 dropdown">
                    <button type="button" class="btn p-0 border-0 cursor-pointer symbol symbol-35px" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="symbol-label bg-light-primary text-primary fw-bold">{{ $yo->iniciales }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end menu-usuario p-0 shadow">
                        <div class="d-flex align-items-center px-5 py-4">
                            <div class="symbol symbol-50px me-4">
                                <span class="symbol-label bg-light-primary text-primary fs-3 fw-bold">{{ $yo->iniciales }}</span>
                            </div>
                            <div class="d-flex flex-column overflow-hidden">
                                <span class="fw-bold fs-6 text-gray-900 text-truncate">{{ $yo->nombre_completo }}</span>
                                <span class="fw-semibold text-muted fs-7 text-truncate">{{ $yo->email }}</span>
                                <span class="badge badge-light-primary fw-bold fs-8 mt-1 align-self-start">{{ $yo->getRoleNames()->first() ?? 'Sin rol' }}</span>
                            </div>
                        </div>
                        <div class="separator"></div>
                        <div class="py-2">
                            <a href="{{ route('perfil.edit') }}" class="dropdown-item px-5 py-2">
                                <i class="ki-outline ki-user fs-4 me-2"></i> Mi perfil
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item px-5 py-2">
                                    <i class="ki-outline ki-exit-right fs-4 me-2"></i> Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
