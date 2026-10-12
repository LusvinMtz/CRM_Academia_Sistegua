@extends('layouts.app')

@section('title', $config['nuevo'])
@section('content')
    <form method="POST" action="{{ route('contactos.store', $segmento) }}" novalidate>
        @csrf
        @include('contactos._form')
    </form>
@endsection
