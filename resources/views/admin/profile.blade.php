@extends('layouts.admin-layout')

@section('content')
@php
    $displayName = $admin?->full_name ?: session('admin_name', 'Administrator');
    $displayPhone = $admin?->phone_number ?: '';
@endphp
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-4">
            <svg class="w-8 h-8 text-gray-900" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">My Profile</h1>
                <p class="text-gray-600 mt-1">View and update your account information.</p>
            </div>
        </div>
        @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
    </div>

    <!-- Profile Content -->
    <div class="grid grid-cols-1 gap-6">
        <!-- Profile Information -->
        <div class="bg-white rounded-lg shadow p-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Profile Information</h2>
                <button id="editProfileButton" type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg transition">
                    Edit Profile
                </button>
            </div>

            <form id="profileDetailsForm" method="POST" action="{{ route('admin.profile.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $displayName) }}" class="profile-detail-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" readonly>
                </div>

                <!-- Mobile Number -->
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                    <input id="phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel" value="{{ $displayPhone }}" placeholder="Enter your mobile number" class="profile-detail-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500" readonly>
                    <p class="mt-1 text-sm text-gray-500">Used to sign in and receive administrator password reset codes by SMS.</p>
                </div>

                <!-- Account Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Account Status</label>
                    <div class="flex items-center">
                        <span class="inline-block px-3 py-1 {{ ($admin->is_active ?? false) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} text-sm font-medium rounded-full">{{ ($admin->is_active ?? false) ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>

                <div id="profileEditActions" class="hidden flex items-center justify-end gap-3">
                    <button id="cancelProfileEdit" type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (() => {
        const editButton = document.getElementById('editProfileButton');
        const cancelButton = document.getElementById('cancelProfileEdit');
        const editActions = document.getElementById('profileEditActions');
        const editableInputs = document.querySelectorAll('.profile-detail-input');

        editButton?.addEventListener('click', () => {
            editableInputs.forEach((input) => input.removeAttribute('readonly'));
            editActions?.classList.remove('hidden');
            editButton.classList.add('hidden');
            document.getElementById('full_name')?.focus();
        });

        cancelButton?.addEventListener('click', () => window.location.reload());
    })();
</script>
@endsection
