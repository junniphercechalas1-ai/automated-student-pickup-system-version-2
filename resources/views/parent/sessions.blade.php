@extends('parent.layout')

@section('title', 'Active Sessions')

@section('content')
    <div class="parent-profile-hero">
        <div class="parent-profile-hero-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40" aria-hidden="true">
                <rect x="3" y="4" width="18" height="13" rx="2"></rect>
                <path d="M8 21h8M12 17v4"></path>
            </svg>
        </div>
        <div class="parent-profile-hero-content">
            <h1 class="parent-profile-hero-title">Active Sessions</h1>
            <p class="parent-profile-hero-subtitle">Review the browser currently signed in to your account.</p>
        </div>
    </div>

    <section class="mt-6 max-w-3xl space-y-4">
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-blue-600 font-semibold">Current Session</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">This device</h2>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200">Active</span>
            </div>

            <dl class="mt-6 space-y-4">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">IP address</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $ipAddress ?: 'Unavailable' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Browser details</dt>
                    <dd class="mt-1 break-all text-sm text-slate-900">{{ $userAgent ?: 'Unavailable' }}</dd>
                </div>
            </dl>
        </div>

        <p class="text-sm text-slate-600">Only this browser session is available to view. Your current authentication setup does not provide a list of sessions on other devices.</p>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('parent.profile') }}" class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Back to Profile</a>
            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Sign Out This Device</button>
            </form>
        </div>
    </section>
@endsection