@extends('parent.layout')

@section('title', 'Notifications')

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Message Center</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Notifications</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Stay updated with pickup reminders, QR code alerts, and other important notifications.</p>
    </header>

    <section class="grid gap-6 lg:grid-cols-4">
        <!-- Statistics -->
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">Unread</p>
            <p class="mt-4 text-4xl font-bold text-amber-600">2</p>
            <p class="mt-2 text-xs text-slate-600">Waiting for you</p>
        </div>

        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">This Week</p>
            <p class="mt-4 text-4xl font-bold text-slate-950">12</p>
            <p class="mt-2 text-xs text-slate-600">Notifications</p>
        </div>

        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">This Month</p>
            <p class="mt-4 text-4xl font-bold text-slate-950">45</p>
            <p class="mt-2 text-xs text-slate-600">Total</p>
        </div>

        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">Archived</p>
            <p class="mt-4 text-4xl font-bold text-slate-500">120</p>
            <p class="mt-2 text-xs text-slate-600">Older messages</p>
        </div>
    </section>

    <!-- Notifications List -->
    <section class="rounded-[32px] bg-white shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-200 p-6">
            <div class="flex items-center justify-between">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500 font-semibold">All Notifications</p>
                <div class="flex gap-2">
                    <button class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Mark all as read</button>
                    <button class="rounded-2xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Filter</button>
                </div>
            </div>
        </div>

        <div class="divide-y divide-slate-200">
            <!-- Unread Notification 1 -->
            <div class="border-l-4 border-amber-500 bg-amber-50 p-6 hover:bg-amber-100 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-950">Pickup reminder sent</p>
                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">Your child will be dismissed today at 03:30 PM. Please ensure you are available to pick up {{ $student['name'] }} on time.</p>
                        <p class="mt-3 text-xs text-slate-500">Today, 8:45 AM</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>

            <!-- Unread Notification 2 -->
            <div class="border-l-4 border-amber-500 bg-amber-50 p-6 hover:bg-amber-100 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-950">Important: Update your profile</p>
                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">Please update your contact information to ensure we can reach you in case of emergencies.</p>
                        <p class="mt-3 text-xs text-slate-500">Today, 7:30 AM</p>
                        <button class="mt-3 rounded-2xl bg-amber-500 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-600">Update Now</button>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>

            <!-- Read Notification 1 -->
            <div class="border-l-4 border-transparent p-6 hover:bg-slate-50 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-semibold text-slate-950">QR code verified</p>
                        <p class="mt-2 text-sm text-slate-700">Your QR code is active for pickup verification. {{ $student['name'] }} is ready for pickup.</p>
                        <p class="mt-3 text-xs text-slate-500">Yesterday, 03:10 PM</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>

            <!-- Read Notification 2 -->
            <div class="border-l-4 border-transparent p-6 hover:bg-slate-50 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-semibold text-slate-950">Pickup reminder sent</p>
                        <p class="mt-2 text-sm text-slate-700">Your child will be dismissed today at 03:30 PM.</p>
                        <p class="mt-3 text-xs text-slate-500">2 Jul 2026, 8:45 AM</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>

            <!-- Read Notification 3 -->
            <div class="border-l-4 border-transparent p-6 hover:bg-slate-50 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-semibold text-slate-950">QR code verified</p>
                        <p class="mt-2 text-sm text-slate-700">Your QR code is active for pickup verification.</p>
                        <p class="mt-3 text-xs text-slate-500">1 Jul 2026, 03:05 PM</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>

            <!-- Read Notification 4 -->
            <div class="border-l-4 border-transparent p-6 hover:bg-slate-50 cursor-pointer transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-semibold text-slate-950">Pickup reminder sent</p>
                        <p class="mt-2 text-sm text-slate-700">Your child will be dismissed today at 03:30 PM.</p>
                        <p class="mt-3 text-xs text-slate-500">30 Jun 2026, 8:45 AM</p>
                    </div>
                    <button class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
            </div>
        </div>

        <!-- Load More -->
        <div class="border-t border-slate-200 p-6 text-center">
            <button class="rounded-3xl bg-slate-100 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">Load More Notifications</button>
        </div>
    </section>
@endsection
