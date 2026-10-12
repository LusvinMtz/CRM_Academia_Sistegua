@extends('layouts.app')

@section('title', 'Nuevo grupo')
@section('content')
    <form method="POST" action="{{ route('grupos.store') }}" novalidate>
        @csrf
        @include('grupos._form')
    </form>
@endsection
