@extends('layouts.app')

@section('title', 'Rol: '.$rol->name)
@section('content')
    <form method="POST" action="{{ route('roles.update', $rol) }}" novalidate>
        @csrf @method('PUT')
        @include('roles._form')
    </form>
@endsection
