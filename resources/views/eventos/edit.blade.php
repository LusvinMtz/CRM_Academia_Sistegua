@extends('layouts.app')

@section('title', 'Editar '.$config['singular'])
@section('content')
    <form method="POST" action="{{ route('eventos.update', [$segmento, $evento]) }}" novalidate>
        @csrf @method('PUT')
        @include('eventos._form')
    </form>
@endsection
