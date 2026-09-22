@php($request ??= request())

<x-guest-layout title="Reset Password">
    <h1 class="mb-1 text-2xl font-semibold text-gray-800 dark:text-white/90">Reset Password</h1>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Enter your new password below.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-forms.label for="email">Email</x-forms.label>
            <x-forms.input type="email" icon="mail" name="email" id="email" value="{{ old('email', $request->email) }}" required autofocus />
            <x-forms.error for="email" />
        </div>

        <div>
            <x-forms.label for="password">New Password</x-forms.label>
            <x-forms.password meter name="password" id="password" required autocomplete="new-password" />
            <x-forms.error for="password" />
        </div>

        <div>
            <x-forms.label for="password_confirmation">Confirm Password</x-forms.label>
            <x-forms.password name="password_confirmation" id="password_confirmation" required autocomplete="new-password" />
            <x-forms.error for="password_confirmation" />
        </div>

        <x-ui.button type="submit" class="w-full" loading-text="Resetting...">Reset Password</x-ui.button>
    </form>
</x-guest-layout>
