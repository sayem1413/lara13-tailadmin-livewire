<x-app-layout title="Change Password">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Change Password</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Ensure your account is using a long, random password to stay secure.</p>
    </div>

    <x-ui.card class="max-w-2xl">
        @if (session('status') === 'password-updated')
            <x-ui.alert type="success" class="mb-6">Your password has been updated.</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('user-password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <x-forms.label for="current_password">Current Password</x-forms.label>
                <x-forms.input type="password" name="current_password" id="current_password" required autocomplete="current-password" />
                <x-forms.error for="current_password" bag="updatePassword" />
            </div>

            <div>
                <x-forms.label for="password">New Password</x-forms.label>
                <x-forms.input type="password" name="password" id="password" required autocomplete="new-password" />
                <x-forms.error for="password" bag="updatePassword" />
            </div>

            <div>
                <x-forms.label for="password_confirmation">Confirm New Password</x-forms.label>
                <x-forms.input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" />
                <x-forms.error for="password_confirmation" bag="updatePassword" />
            </div>

            <x-ui.button type="submit">Update Password</x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
