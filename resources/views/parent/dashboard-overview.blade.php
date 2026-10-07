@extends('parent.layout')

@section('title', 'Dashboard')

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Welcome back</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Parent/Guardian Dashboard</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">View your assigned student, QR code, reminders, notifications, and pickup history.</p>
    </header>

    <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
        <!-- Quick Student Overview -->
        <article class="space-y-6">
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">My Student</p>
                <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ $student['name'] }}</h3>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $student['class'] }}</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                        <p class="text-sm text-slate-500">Relationship</p>
                        <p class="mt-3 text-lg font-semibold text-slate-950">{{ $relationship }}</p>
                    </div>
                    @extends('parent.layout')

                    @section('title', 'Dashboard')

                    @section('content')
                        <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Welcome back</p>
                            <h2 class="mt-2 text-3xl font-semibold text-slate-950">Parent/Guardian Dashboard</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">View your assigned student, QR code, reminders, notifications, and pickup history.</p>
                        </header>

                        <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                            <!-- Quick Student Overview -->
                            <article class="space-y-6">
                                <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">My Student</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">{{ $student['name'] }}</h3>
                                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ $student['class'] }}</p>
                                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                            <p class="text-sm text-slate-500">Relationship</p>
                                            <p class="mt-3 text-lg font-semibold text-slate-950">{{ $relationship }}</p>
                                        </div>
                                        <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                            <p class="text-sm text-slate-500">QR validity</p>
                                            <p class="mt-3 text-lg font-semibold text-slate-950">Temporary</p>
                                        </div>
                                        <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                            <p class="text-sm text-slate-500">Mobile</p>
                                            <p class="mt-3 text-lg font-semibold text-slate-950">{{ $mobile }}</p>
                                        </div>
                                    </div>
                                    <a href="/parent/student" class="mt-6 inline-block rounded-3xl bg-amber-500 px-6 py-3 text-sm font-semibold text-white hover:bg-amber-600">View Details</a>
                                </div>

                                <!-- Quick Notifications Preview -->
                                <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Recent Notifications</p>
                                        <a href="/parent/notifications" class="text-sm font-semibold text-amber-500 hover:text-amber-600">View all</a>
                                    </div>
                                    <ul class="mt-6 space-y-4 text-sm text-slate-700">
                                        <li class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                            <p class="font-semibold text-slate-950">Pickup reminder sent</p>
                                            <p class="mt-1 text-slate-500">Your child will be dismissed today at 03:30 PM.</p>
                                            <p class="mt-2 text-xs text-slate-400">8:45 AM</p>
                                        </li>
                                        <li class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                            <p class="font-semibold text-slate-950">QR code verified</p>
                                            <p class="mt-1 text-slate-500">Your QR code is active for pickup verification.</p>
                                            <p class="mt-2 text-xs text-slate-400">Yesterday, 03:10 PM</p>
                                        </li>
                                    </ul>
                                </div>
                            </article>

                            <aside class="space-y-6">
                                <!-- Quick QR Code Preview -->
                                <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">My QR Code</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">Authorized pickup badge</p>
                                    <div class="mt-6 flex flex-col items-center justify-center gap-4 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6">
                                        <canvas id="parentQrCodeCanvas" data-qr-value='{{ json_encode(["qr_token" => $parent_qr_token ?? null], JSON_UNESCAPED_SLASHES) }}' data-expires-at="{{ $parent_qr_expires_at ?? '' }}" class="rounded-3xl bg-white p-4 shadow-sm" style="max-width: 200px;"></canvas>
                                        <p id="parentQrExpiry" class="text-xs font-semibold text-amber-600"></p>
                                        <p class="text-xs text-slate-600 text-center">Temporary code. Show it directly to gate staff before it expires.</p>
                                    </div>
                                    <a href="/parent/qr-code" class="mt-4 block rounded-3xl bg-slate-100 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-200">Full QR Code</a>
                                </div>

                                <!-- Next Dismissal -->
                                <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Next Dismissal</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">Today at 03:30 PM</h3>
                                    <p class="mt-4 text-sm leading-6 text-slate-600">A reminder notification will be sent 30 minutes before dismissal.</p>
                                </div>

                                <!-- Quick Pickup History -->
                                <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Recent Pickups</p>
                                        <a href="/parent/pickup-history" class="text-sm font-semibold text-amber-500 hover:text-amber-600">See all</a>
                                    </div>
                                    <div class="mt-6 space-y-4 text-sm text-slate-700">
                                        <div class="grid grid-cols-[1.3fr_0.8fr_0.9fr] items-center gap-3 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                            <span>03 Jul 2026</span>
                                            <span>03:32 PM</span>
                                            <span class="text-emerald-700">Verified</span>
                                        </div>
                                        <div class="grid grid-cols-[1.3fr_0.8fr_0.9fr] items-center gap-3 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                            <span>02 Jul 2026</span>
                                            <span>03:30 PM</span>
                                            <span class="text-emerald-700">Verified</span>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </section>
                    @endsection
