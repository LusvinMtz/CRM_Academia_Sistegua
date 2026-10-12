@extends('layouts.app')

@section('title', 'Nuevo rol')
@section('content')
    <form method="POST" action="{{ route('roles.store') }}" novalidate>
        @csrf
        @include('roles._form')
    </form>
@endsection
