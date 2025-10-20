@extends('emails.layouts.email')

@section('title', 'Error en el parseo de logs')

@section('content')
    <h1>Hola Administrador</h1>

    <h2>Se ha alcanzo un error de parseo en un log. Revisa la cuestión por favor.</h2>

    <p class="code">{{ $log }}</p>

@endsection
