@extends('layouts.app')

@section('title', 'Nueva plantilla')
@section('content')
    <form method="POST" action="{{ route('plantillas.store') }}" novalidate>
        @csrf
        @include('plantillas._form')
    </form>
@endsection
