@php
    /*
     * The company's colour, painted before the first byte of React arrives.
     *
     * Doing this from the shared Inertia prop alone would flash the house
     * palette on every full page load, which on a coloured shell reads as the
     * page breaking. `useCompanyTheme` keeps it in step afterwards — this is
     * only the head start.
     */
    $themeUser = auth()->user();
    // The same fallback HandleInertiaRequests shares, so the served colour and
    // the one React settles on are never two different answers.
    $themeColor = ($themeUser?->currentTeam ?? $themeUser?->fallbackTeam())?->themeColor();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if ($themeColor) data-company-theme style="--brand-base: {{ $themeColor }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
