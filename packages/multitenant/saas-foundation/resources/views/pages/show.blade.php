<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?: $page->title }}</title>
    @if($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/multitenant/saas-foundation/css/pages/show.css') }}">
</head>
<body>
    <header class="header">
        <div class="header-brand">{{ $tenant->name }}</div>
        <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
    </header>

    <main class="content">
        <h1>{{ $page->title }}</h1>
        <div>
            {!! $page->content !!}
        </div>
    </main>

    <footer class="footer">
        &copy; {{ date('Y') }} {{ $tenant->name }}. All rights reserved.
    </footer>
</body>
</html>