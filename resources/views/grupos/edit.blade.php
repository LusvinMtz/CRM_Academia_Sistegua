@extends('layouts.app')

@section('title', 'Editar grupo')
@section('content')
    <form method="POST" action="{{ route('grupos.update', $grupo) }}" novalidate>
        @csrf @method('PUT')
        @include('grupos._form')
    </form>
@endsection
