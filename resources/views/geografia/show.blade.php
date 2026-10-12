@extends('layouts.app')

@section('title', $departamento->nombre)
@section('acciones')
    <a href="{{ route('geografia.index') }}" class="btn btn-sm btn-light">
        <i class="ki-outline ki-arrow-left fs-2"></i> Volver
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-header border-0 pt-6">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold text-gray-900">Municipios de {{ $departamento->nombre }}</span>
                <span class="text-muted mt-1 fw-semibold fs-7">
                    Código {{ $departamento->codigo }} · Región {{ $departamento->region }} · {{ $departamento->municipios->count() }} municipios
                </span>
            </h3>
        </div>
        <div class="card-body py-4">
            <div class="row g-4">
                @foreach ($departamento->municipios as $m)
                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                        <div class="d-flex align-items-center border border-dashed border-gray-300 rounded px-4 py-3">
                            <span class="badge badge-light-primary fw-bold me-3">{{ $m->codigo }}</span>
                            <span class="text-gray-800 fw-semibold">{{ $m->nombre }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
