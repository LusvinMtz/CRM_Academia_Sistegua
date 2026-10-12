@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('content')
    <form method="POST" action="{{ route('usuarios.store') }}" novalidate>
        @csrf
        @include('usuarios._form')
    </form>
@endsection
