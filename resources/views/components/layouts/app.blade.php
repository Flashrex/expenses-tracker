@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
                <a href="{{ route('overview') }}" class="flex items-center gap-2 rounded-lg focus-visible:outline-2 focus-visible:outline-emerald-600 dark:focus-visible:outline-emerald-400">
                    <x-heroicon-o-banknotes class="size-7 text-emerald-600 dark:text-emerald-400" />
                    <span class="hidden font-semibold sm:inline">{{ config('app.name') }}</span>
                </a>

                <nav aria-label="Main" class="flex flex-1 items-center justify-center gap-1">
                    <x-nav-link :href="route('overview')" :active="request()->routeIs('overview')" icon="heroicon-o-chart-pie" label="Overview" />
                    <x-nav-link :href="route('trends')" :active="request()->routeIs('trends')" icon="heroicon-o-chart-bar" label="Trends" />
                    <x-nav-link :href="route('upload')" :active="request()->routeIs('upload', 'upload.*')" icon="heroicon-o-arrow-up-tray" label="Upload" />
                </nav>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Log out" aria-label="Log out" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:outline-emerald-400">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="size-6" />
                    </button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    </body>
</html>
