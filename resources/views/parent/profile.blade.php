@extends('parent.layout')

@section('title', 'Profile')

@section('content')
    <!-- Profile Hero Section -->
    <div class="parent-profile-hero">
        <div class="parent-profile-hero-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="parent-profile-hero-content">
            <h1 class="parent-profile-hero-title">Profile</h1>
            <p class="parent-profile-hero-subtitle">View and update your account information.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="mt-6 rounded-[32px] bg-emerald-50 p-6 text-sm text-emerald-900 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="grid gap-6 lg:grid-cols-3">
        <!-- Profile Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Personal Information Card -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <!-- Header with Icon and Title -->
                <div class="mb-6 flex items-start justify-between">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 flex-shrink-0">
                            <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Parent / Guardian Information</p>
                            <p class="mt-1 text-xs text-slate-600">Edit your personal details.</p>
                        </div>
                    </div>
                    <a href="/parent/profile/edit" class="rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 flex-shrink-0">Edit</a>
                </div>
                
                <!-- Information Fields -->
                <div class="space-y-4">
                    <!-- Full Name -->
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Full Name</label>
                            <p class="mt-1 text-base font-semibold text-slate-950">{{ $fullName ?? 'Not provided' }}</p>
                        </div>
                    </div>

                    <!-- Relationship to Student -->
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 11a6 6 0 00-5.86 0 3 3 0 00-6.14 0A6.993 6.993 0 0112 20a6.993 6.993 0 005.07-2.1A3 3 0 0017.93 11z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Relationship to Student</label>
                            <p class="mt-1 text-base font-semibold text-slate-950">{{ $relationship ?? 'Not provided' }}</p>
                        </div>
                    </div>

                    <!-- Contact Number -->
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Contact Number</label>
                            <p class="mt-1 text-base font-semibold text-slate-950">{{ $mobile ?? 'Not provided' }}</p>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Email Address</label>
                            <p class="mt-1 text-base font-semibold text-slate-950">{{ $email ?? 'Not provided' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Card -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Security Settings</p>
                </div>
                
                <div class="space-y-4">
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-950">Change Password</p>
                        <p class="mt-1 text-sm text-slate-600">Update your password to keep your account secure.</p>

                        @if ($errors->any())
                            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('parent.profile.password') }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="current_password" class="mb-1 block text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Current Password</label>
                                <input id="current_password" name="current_password" type="password" required class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </div>
                            <div>
                                <label for="password" class="mb-1 block text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">New Password</label>
                                <input id="password" name="password" type="password" required class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </div>
                            <div>
                                <label for="password_confirmation" class="mb-1 block text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Confirm New Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
                            </div>
                            <button type="submit" class="w-full rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update Password</button>
                        </form>
                    </div>

                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-950">Active Sessions</p>
                        <p class="mt-1 text-sm text-slate-600">Manage devices where you're logged in.</p>
                        <a href="{{ route('parent.profile.sessions') }}" class="mt-4 inline-flex rounded-2xl bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-200">View Sessions</a>
                    </div>

                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-950">Account Help</p>
                        <p class="mt-1 text-sm text-slate-600">Having trouble with your account? Send a message to the admin.</p>
                        <a href="{{ route('parent.contact-admin') }}" class="mt-4 inline-flex rounded-2xl bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-200">Contact Admin</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-4">
            <!-- Account Status -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zm0-9a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1zm0-4a1 1 0 110 2 1 1 0 010-2z"></path>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Account Status</p>
                </div>
                <div class="space-y-4">
                    <div class="p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Account Type</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">Parent/Guardian</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Member Since</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">May 15, 2026</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Last Login</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">Today, 8:30 AM</p>
                    </div>
                </div>
            </div>

            <!-- Linked Student -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Linked Student</p>
                </div>
                <div class="space-y-4">
                    <div class="p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Student Name</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">{{ $student['name'] }}</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-[0.05em]">Grade / Section</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">{{ $student['class'] }}</p>
                    </div>
                    <a href="{{ route('parent.student') }}" class="mt-2 block w-full rounded-2xl bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">View Student Info</a>
                </div>
            </div>

        </aside>
    </section>
@endsection
