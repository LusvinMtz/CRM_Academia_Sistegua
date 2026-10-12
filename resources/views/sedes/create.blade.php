@extends('layouts.app')

@section('title', 'Nueva sede')
@section('content')
    <form method="POST" action="{{ route('sedes.store') }}" novalidate>
        @csrf
        @include('sedes._form')
    </form>
@endsection
