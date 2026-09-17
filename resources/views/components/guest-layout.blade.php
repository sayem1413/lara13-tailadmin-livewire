@props(['title' => null])

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
    <body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10 font-sans antialiased dark:bg-gray-900">
        <div class="w-full max-w-md">
            <div class="mb-8 flex justify-center">
                <a href="{{ route('login') }}" class="flex items-center gap-2 text-xl font-semibold text-gray-800 dark:text-white/90">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-500 text-base font-bold text-white">
                        {{ Str::substr(setting('app_name', config('app.name')), 0, 1) }}
                    </span>
                    {{ setting('app_name', config('app.name')) }}
                </a>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-white/10 dark:bg-white/[0.03]">
                {{ $slot }}
            </div>
        </div>

        @livewireScriptConfig
    </body>
</html>
