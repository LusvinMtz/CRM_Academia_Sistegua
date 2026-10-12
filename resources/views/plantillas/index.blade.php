@extends('layouts.app')

@section('title', 'Plantillas de correo')
@section('acciones')
    @can('campanias.crear')
        <a href="{{ route('plantillas.create') }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-2"></i> Nueva plantilla</a>
    @endcan
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        @foreach ($plantillas as $p)
            <div class="col-lg-6">
                <div class="card h-100 {{ $p->activa ? '' : 'opacity-75' }}">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title flex-column">
                            <h3 class="fw-bold mb-1">{{ $p->nombre }}</h3>
                            <span class="text-muted fs-7 fw-semibold">Capacitaciones</span>
                        </div>
                        <div class="card-toolbar gap-2">
                            @if ($p->predeterminada)<span class="badge badge-light-primary">Predeterminada</span>@endif
                            @unless ($p->activa)<span class="badge badge-light-danger">Inactiva</span>@endunless
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div class="text-gray-500 fs-8 fw-bold text-uppercase mb-1">Asunto</div>
                        <div class="text-gray-900 fw-semibold mb-4">{{ $p->asunto }}</div>
                        <div class="text-gray-500 fs-8 fw-bold text-uppercase mb-1">Mensaje</div>
                        <div class="text-gray-700 fs-7" style="white-space: pre-line">{{ \Illuminate\Support\Str::limit($p->mensaje, 400) }}</div>
                    </div>
                    <div class="card-footer d-flex gap-2 pt-0 border-0">
                        @can('campanias.editar')
                            <a href="{{ route('plantillas.edit', $p) }}" class="btn btn-sm btn-light btn-active-light-primary">Editar</a>
                        @endcan
                        @can('campanias.desactivar')
                            @unless ($p->predeterminada && $p->activa)
                                <form method="POST" action="{{ route('plantillas.estado', $p) }}"
                                      data-confirmar="{{ $p->activa ? "¿Desactivar la plantilla {$p->nombre}? Ya no se ofrecerá al enviar invitaciones." : "¿Activar la plantilla {$p->nombre}?" }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-light {{ $p->activa ? 'btn-active-light-warning' : 'btn-active-light-success' }}">{{ $p->activa ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            @endunless
                        @endcan
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
