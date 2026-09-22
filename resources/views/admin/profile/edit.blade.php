<x-app-layout title="Profile">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Profile</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Update your account's profile information.</p>
    </div>

    <x-ui.card class="max-w-2xl">
        @if (session('status') === 'profile-information-updated')
            <div x-init="showToast('success', 'Your profile has been updated.')"></div>
        @endif

        <form method="POST" action="{{ route('user-profile-information.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <x-forms.label for="avatar">Avatar</x-forms.label>
                <div class="max-w-xs">
                    <x-forms.file-upload name="avatar" id="avatar" :preview="$user->avatarUrl()" :max-size-mb="2" bag="updateProfileInformation" />
                </div>
                <x-forms.error for="avatar" bag="updateProfileInformation" />
            </div>

            <div>
                <x-forms.label for="name">Name</x-forms.label>
                <x-forms.input type="text" icon="user-circle" name="name" id="name" value="{{ old('name', $user->name) }}" required />
                <x-forms.error for="name" bag="updateProfileInformation" />
            </div>

            <div>
                <x-forms.label for="email">Email</x-forms.label>
                <x-forms.input type="email" icon="mail" name="email" id="email" value="{{ old('email', $user->email) }}" required />
                <x-forms.error for="email" bag="updateProfileInformation" />
            </div>

            <x-ui.button type="submit" loading-text="Saving...">Save Changes</x-ui.button>
        </form>
    </x-ui.card>
</x-app-layout>
