@php
    $item = function (string $ruta, string $patron, string $icono, string $titulo, array $params = []) {
        $activo = request()->routeIs($patron)
            && collect($params)->every(fn ($v, $k) => request()->route($k) === $v) ? 'active' : '';
        return '<div class="menu-item"><a class="menu-link '.$activo.'" href="'.route($ruta, $params).'">'
            .'<span class="menu-icon"><i class="ki-outline '.$icono.' fs-2"></i></span>'
            .'<span class="menu-title">'.e($titulo).'</span></a></div>';
    };
    $proximo = fn (string $icono, string $titulo, string $fase) =>
        '<div class="menu-item"><span class="menu-link disabled opacity-50" title="Disponible en la '.e($fase).'">'
        .'<span class="menu-icon"><i class="ki-outline '.$icono.' fs-2"></i></span>'
        .'<span class="menu-title">'.e($titulo).'</span>'
        .'<span class="menu-badge"><span class="badge badge-sm badge-light-warning">'.e($fase).'</span></span></span></div>';
    $seccion = fn (string $titulo) =>
        '<div class="menu-item pt-5"><div class="menu-content"><span class="menu-heading fw-bold text-uppercase fs-7">'.e($titulo).'</span></div></div>';
@endphp
<div id="kt_app_sidebar" class="app-sidebar flex-column">
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
            <img alt="{{ config('academia.nombre') }}" src="{{ asset('assets/media/logos/academia-icon.png') }}" class="h-40px w-40px rounded me-3">
            <span class="d-flex flex-column lh-sm">
                <span class="text-white fw-bold fs-5">{{ config('academia.nombre') }}</span>
                <span class="text-gray-500 fs-8">{{ implode(' · ', config('academia.sedes')) }}</span>
            </span>
        </a>
        <button type="button" class="btn btn-icon btn-sm d-lg-none text-gray-500" data-sidebar-close aria-label="Cerrar menú">
            <i class="ki-outline ki-cross fs-2"></i>
        </button>
    </div>

    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div class="app-sidebar-wrapper sidebar-scroll my-5">
            <div class="menu menu-column menu-rounded menu-sub-indention fw-semibold fs-6 px-3" id="kt_app_sidebar_menu">

                {!! $item('dashboard', 'dashboard', 'ki-element-11', 'Inicio') !!}

                @canany(['sedes.ver', 'contactos.ver'])
                    {!! $seccion('Academia') !!}
                    @can('sedes.ver') {!! $item('sedes.index', 'sedes.*', 'ki-bank', 'Sedes') !!} @endcan
                    @can('contactos.ver')
                        {!! $item('contactos.index', 'contactos.*', 'ki-people', 'Clientes', ['tipo' => 'clientes']) !!}
                        {!! $item('grupos.index', 'grupos.*', 'ki-abstract-26', 'Grupos') !!}
                    @endcan
                @endcanany

                @canany(['eventos.ver', 'certificados.ver'])
                    {!! $seccion('Formación') !!}
                    @can('eventos.ver')
                        {!! $item('eventos.index', 'eventos.*', 'ki-book-open', 'Capacitaciones', ['tipo' => 'capacitaciones']) !!}
                    @endcan
                    @can('certificados.ver')
                        {!! $item('certificados.index', 'certificados.*', 'ki-award', 'Certificados') !!}
                    @endcan
                @endcanany

                @can('campanias.ver')
                    {!! $seccion('Comunicación') !!}
                    {!! $item('invitaciones.index', 'invitaciones.*', 'ki-sms', 'Invitaciones') !!}
                    {!! $item('plantillas.index', 'plantillas.*', 'ki-notepad-edit', 'Plantillas') !!}
                @endcan

                @can('reportes.ver')
                    {!! $seccion('Control') !!}
                    {!! $item('reportes.eventos', 'reportes.*', 'ki-chart-simple', 'Reportes') !!}
                @endcan

                @canany(['usuarios.ver', 'roles.ver', 'correo.ver'])
                    {!! $seccion('Administración') !!}
                    @can('usuarios.ver') {!! $item('usuarios.index', 'usuarios.*', 'ki-profile-user', 'Usuarios') !!} @endcan
                    @can('roles.ver') {!! $item('roles.index', 'roles.*', 'ki-shield-tick', 'Roles y permisos') !!} @endcan
                    @can('correo.ver') {!! $item('correo.edit', 'correo.*', 'ki-send', 'Correo de envío') !!} @endcan
                    @can('correo.ver') {!! $item('whatsapp.edit', 'whatsapp.*', 'ki-whatsapp', 'WhatsApp') !!} @endcan
                @endcanany
            </div>
        </div>
    </div>

</div>
