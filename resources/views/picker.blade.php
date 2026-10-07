<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Automated Student Pickup Verification</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900">
        <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr] gap-4 p-4 lg:p-6">
            <aside class="flex flex-col rounded-[32px] bg-slate-950 text-white shadow-2xl overflow-hidden ring-1 ring-black/10">
                <div class="p-6 border-b border-white/10">
                    <div class="mb-6 flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-3xl bg-slate-800 text-2xl font-bold text-amber-300">O</div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Orion Christian</p>
                            <h1 class="text-xl font-semibold">Pickup System</h1>
                        </div>
                    </div>
                    <p class="text-sm leading-6 text-slate-300">Web verification dashboard for parents, guardians, and gate staff with QR code scanning and Supabase backend.</p>
                </div>

                <nav class="flex-1 space-y-1 p-6">
                    <a href="#" class="block rounded-3xl bg-amber-300/10 px-4 py-3 text-sm font-semibold text-amber-200 hover:bg-amber-300/20">Dashboard</a>
                    <a href="#" class="block rounded-3xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5">My Students</a>
                    <a href="#" class="block rounded-3xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5">My QR Code</a>
                    <a href="#pickup-history" class="block rounded-3xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5">Pickup History</a>
                    <a href="#notifications" class="block rounded-3xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5">Notifications</a>
                    <a href="#" class="block rounded-3xl px-4 py-3 text-sm text-slate-300 hover:bg-white/5">Profile</a>
                </nav>

                <div class="space-y-3 bg-slate-900/80 p-6 border-t border-white/10">
                    <div class="rounded-3xl bg-slate-800 p-4 text-sm text-slate-300">
                        <p class="font-medium text-slate-100">Active mode</p>
                        <p class="mt-1 text-xs text-slate-400">Supabase backend configured via env variables.</p>
                    </div>
                    <div class="rounded-3xl bg-slate-800 p-4 text-sm text-slate-300">
                        <p class="font-medium text-slate-100">Scanner status</p>
                        <p class="mt-1 text-xs text-slate-400">Use the controls in the main page to start or stop the camera.</p>
                    </div>
                </div>
            </aside>

            <main class="space-y-6">
                <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Good morning, Maria Santos</p>
                            <h2 class="mt-2 text-3xl font-semibold text-slate-950">Student Pickup Verification</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Scan authorized student QR codes at dismissal and automatically log pickup events in Supabase.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-3xl bg-slate-950/95 p-4 text-sm text-slate-50 shadow-sm ring-1 ring-white/10">
                                <p class="text-slate-400">Total Students</p>
                                <p class="mt-2 text-xl font-semibold">120</p>
                            </div>
                            <div class="rounded-3xl bg-slate-950/95 p-4 text-sm text-slate-50 shadow-sm ring-1 ring-white/10">
                                <p class="text-slate-400">Total Parents</p>
                                <p class="mt-2 text-xl font-semibold">210</p>
                            </div>
                            <div class="rounded-3xl bg-slate-950/95 p-4 text-sm text-slate-50 shadow-sm ring-1 ring-white/10">
                                <p class="text-slate-400">Pending Pickups</p>
                                <p class="mt-2 text-xl font-semibold">98</p>
                            </div>
                        </div>
                    </div>
                </header>

                <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
                    <article class="space-y-6">
                        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">QR Scanner</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">Gate verification</h3>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <button id="startScan" class="inline-flex items-center justify-center rounded-3xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Start scanner</button>
                                    <button id="stopScan" class="inline-flex items-center justify-center rounded-3xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">Stop scanner</button>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-6 lg:grid-cols-[1.45fr_0.55fr]">
                                <div class="rounded-3xl bg-slate-900 overflow-hidden shadow-inner">
                                    <video id="scannerVideo" class="w-full aspect-[4/3] object-cover" playsinline muted></video>
                                    <canvas id="scannerCanvas" class="hidden"></canvas>
                                </div>
                                <div class="space-y-4">
                                    <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                        <p class="text-sm text-slate-500">Status</p>
                                        <p id="pickerStatus" class="mt-3 text-xl font-semibold text-slate-950">Idle</p>
                                    </div>
                                    <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                        <p class="text-sm text-slate-500">Last scanned QR code</p>
                                        <p id="lastCode" class="mt-3 text-lg font-medium text-slate-950">None</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Verification</p>
                                    <h3 class="mt-2 text-2xl font-semibold text-slate-950">Pickup result</h3>
                                </div>
                                <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-sm font-semibold text-emerald-700">Ready to verify</span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600">When the scanner reads an authorized QR code, the student details appear and the pickup event is stored in Supabase.</p>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                    <p class="text-sm text-slate-500">Student name</p>
                                    <p id="studentName" class="mt-3 text-lg font-semibold text-slate-950">-</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                    <p class="text-sm text-slate-500">Student ID</p>
                                    <p id="studentId" class="mt-3 text-lg font-semibold text-slate-950">-</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                    <p class="text-sm text-slate-500">Class / Section</p>
                                    <p id="studentClass" class="mt-3 text-lg font-semibold text-slate-950">-</p>
                                </div>
                                <div class="rounded-3xl bg-slate-50 p-5 ring-1 ring-slate-200">
                                    <p class="text-sm text-slate-500">Pickup time</p>
                                    <p id="pickupTime" class="mt-3 text-lg font-semibold text-slate-950">-</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <aside class="space-y-6">
                        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">My QR code</p>
                            <h3 class="mt-2 text-2xl font-semibold text-slate-950">Authorized pickup badge</h3>
                            <div class="mt-6 flex flex-col items-center justify-center gap-4 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6">
                                <div class="aspect-square w-full max-w-[220px] rounded-3xl bg-slate-950 p-6 text-white flex items-center justify-center text-center text-sm font-semibold">QR Code placeholder</div>
                                <p class="text-sm text-slate-600">Show this QR code to the school gate staff when picking up your child. The system verifies it instantly.</p>
                            </div>
                        </div>

                        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Upcoming dismissal</p>
                            <h3 class="mt-2 text-2xl font-semibold text-slate-950">Today at 03:30 PM</h3>
                            <p class="mt-4 text-sm leading-6 text-slate-600">A reminder notification will be sent 30 minutes before dismissal time.</p>
                        </div>

                        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                            <div class="flex items-center justify-between">
                                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Pickup activity</p>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Latest</span>
                            </div>
                            <ul id="scanHistorySidebar" class="mt-5 space-y-3 text-sm text-slate-700 max-h-80 overflow-y-auto" data-scan-history></ul>
                        </div>
                    </aside>
                </section>

                <section id="notifications" class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Recent notifications</p>
                            <a href="#" class="text-sm font-semibold text-amber-500 hover:text-amber-600">View all</a>
                        </div>
                        <ul class="mt-6 space-y-4 text-sm text-slate-700">
                            <li class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-950">SMS reminder sent to Maria Santos</p>
                                <p class="mt-1 text-slate-500">Juan Dela Cruz will be dismissed at 03:30 PM today.</p>
                                <p class="mt-2 text-xs text-slate-400">10:00 AM</p>
                            </li>
                            <li class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                <p class="font-semibold text-slate-950">Pickup confirmed</p>
                                <p class="mt-1 text-slate-500">Juan Dela Cruz was verified by gate staff.</p>
                                <p class="mt-2 text-xs text-slate-400">Yesterday, 03:32 PM</p>
                            </li>
                        </ul>
                    </div>

                    <div id="pickup-history" class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <div class="flex items-center justify-between">
                            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Pickup history</p>
                            <button class="rounded-3xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Filter</button>
                        </div>
                        <div class="mt-6 space-y-4 text-sm text-slate-700">
                            <div class="grid grid-cols-[1.3fr_0.8fr_0.9fr_0.8fr] items-center gap-3 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                <span>03 Jul 2026</span>
                                <span>03:32 PM</span>
                                <span>Juan Dela Cruz</span>
                                <span class="text-emerald-700">Verified</span>
                            </div>
                            <div class="grid grid-cols-[1.3fr_0.8fr_0.9fr_0.8fr] items-center gap-3 rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                <span>03 Jul 2026</span>
                                <span>03:30 PM</span>
                                <span>Juan Dela Cruz</span>
                                <span class="text-emerald-700">Verified</span>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
