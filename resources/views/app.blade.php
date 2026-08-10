<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Tells the browser to render form controls and scrollbars dark, so the
         chrome around the app matches the app itself. --}}
    <meta name="color-scheme" content="dark">

    <title>{{ config('platform.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
</head>
<body>
    {{-- Surface, content colour and height come from resources/css/app.css, so
         the page is already correct before any JavaScript runs. --}}
    <div id="app"></div>

    <noscript>
        <div class="p-8 text-sm">
            This application requires JavaScript.
        </div>
    </noscript>
</body>
</html>
