<x-guest-layout title="Sign In">
    <h1 class="mb-1 text-2xl font-semibold text-gray-800 dark:text-white/90">Sign In</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Enter your email and password to access your account.</p>

    @if (session('status'))
        <x-ui.alert type="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-forms.label for="email">Email</x-forms.label>
            <x-forms.input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
            <x-forms.error for="email" />
        </div>

        <div>
            <x-forms.label for="password">Password</x-forms.label>
            <x-forms.input type="password" name="password" id="password" required autocomplete="current-password" />
            <x-forms.error for="password" />
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <x-forms.checkbox name="remember" />
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
                    Forgot password?
                </a>
            @endif
        </div>

        <x-ui.button type="submit" class="w-full">Sign In</x-ui.button>
    </form>
</x-guest-layout>
