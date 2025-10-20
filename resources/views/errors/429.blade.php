@extends('layouts.errors')
@section('content')
<main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
    <div class="text-center">
        <p class="text-8xl font-semibold text-red-800">429</p>
        <h1 class="mt-4 text-balance text-5xl font-semibold tracking-tight text-gray-900 sm:text-7xl">{{ __('errors.429.title') }}</h1>
        <p class="mt-6 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">{{ __('errors.429.message') }}</p>
        <div class="mt-10 flex items-center justify-center gap-x-6">
            <a class="text-sm font-semibold text-gray-900">{{ __('errors.429.support') }}</a>
        </div>
    </div>
</main>
@endsection
