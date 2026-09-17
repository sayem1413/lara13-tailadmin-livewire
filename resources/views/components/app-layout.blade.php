<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>{{ $title ? "{$title} - " : '' }}{{ setting('app_name', config('app.name')) }}</title>

        <script>
            if (localStorage.getItem('dark-mode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-gray-50 font-sans antialiased dark:bg-gray-900" x-data="{ sidebarOpen: false }">
        <div class="flex h-screen overflow-hidden">
            <x-layout.sidebar :menu="$menu" />

            <div class="flex flex-1 flex-col overflow-hidden">
                <x-layout.header />

                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    @if (session('success'))
                        <x-ui.alert type="success" class="mb-6">{{ session('success') }}</x-ui.alert>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScriptConfig
    </body>
</html>
