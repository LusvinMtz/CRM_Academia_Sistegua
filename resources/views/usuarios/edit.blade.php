@extends('layouts.app')

@section('title', 'Editar usuario')
@section('content')
    <form method="POST" action="{{ route('usuarios.update', $usuario) }}" novalidate>
        @csrf @method('PUT')
        @include('usuarios._form')
    </form>
@endsection
