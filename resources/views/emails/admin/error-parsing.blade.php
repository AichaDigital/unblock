@extends('emails.layouts.email')

@section('title', 'Error en el parseo de logs')

@section('content')
    {{-- Header --}}
    <h1 style="color: #1f2937; font-size: 24px; font-weight: 600; margin: 0 0 20px 0;">Hola Administrador</h1>

    {{-- Error message --}}
    <h2 style="color: #dc2626; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">Se ha alcanzado un error de parseo en un log. Revisa la cuestión por favor.</h2>

    {{-- Log content with word-wrap: CRITICAL for long log lines --}}
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #1f2937; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; line-height: 1.5; margin: 20px 0;">{{ $log }}</div>

    {{-- Note: Layout footer is automatically added --}}
@endsection
