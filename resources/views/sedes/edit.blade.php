@extends('layouts.app')

@section('title', 'Sede '.$sede->nombre)
@section('content')
    <form method="POST" action="{{ route('sedes.update', $sede) }}" novalidate>
        @csrf @method('PUT')
        @include('sedes._form')
    </form>
@endsection
