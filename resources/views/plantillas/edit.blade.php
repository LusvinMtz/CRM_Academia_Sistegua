@extends('layouts.app')

@section('title', 'Editar plantilla')
@section('content')
    <form method="POST" action="{{ route('plantillas.update', $plantilla) }}" novalidate>
        @csrf @method('PUT')
        @include('plantillas._form')
    </form>
@endsection
