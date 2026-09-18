<x-guest-layout title="Forgot Password">
    <h1 class="mb-1 text-2xl font-semibold text-gray-800 dark:text-white/90">Forgot Password?</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Enter your email and we'll send you a link to reset your password.</p>

    @if (session('status'))
        <div x-init="showToast('success', @js(session('status')))"></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-forms.label for="email">Email</x-forms.label>
            <x-forms.input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus />
            <x-forms.error for="email" />
        </div>

        <x-ui.button type="submit" class="w-full">Email Password Reset Link</x-ui.button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Back to sign in</a>
    </p>
</x-guest-layout>
