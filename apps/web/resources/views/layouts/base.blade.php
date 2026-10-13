<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6B1E2E">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- PWA: bisa dipasang di layar utama (NFR-01). iOS memakai apple-touch-icon dan meta di bawah. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Dental Photo">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>@yield('title') – {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    @yield('body')
    @stack('scripts')
</body>
</html>
