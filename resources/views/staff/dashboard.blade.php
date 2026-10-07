@extends('staff.layout')

@section('title', 'Staff Dashboard')

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Administrator Dashboard</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Authorized School Staff</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Manage students, parents, pickup verification, QR codes, and notifications from one hub.</p>
    </header>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Total students</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">120</p>
        </div>
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Total parents</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">210</p>
        </div>
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Pending pickups</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950">98</p>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
        <article class="space-y-6">
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Quick actions</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <a href="/staff/pickup-verification" class="rounded-3xl bg-amber-500 px-5 py-4 text-sm font-semibold text-slate-950 text-center hover:bg-amber-400">Open QR Scanner</a>
                    <a href="#students" class="rounded-3xl bg-slate-100 px-5 py-4 text-sm font-semibold text-slate-700 text-center hover:bg-slate-200">Manage Students</a>
                </div>
            </div>

            <div id="pickup-records" class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Recent pickup records</p>
                    <a href="#" class="text-sm font-semibold text-amber-500 hover:text-amber-600">View all</a>
                </div>
                <div class="mt-6 space-y-4 text-sm text-slate-700">
                    <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-950">Juan Dela Cruz</p>
                        <p class="mt-1 text-slate-500">Verified at 03:32 PM</p>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="font-semibold text-slate-950">Ana Reyes</p>
                        <p class="mt-1 text-slate-500">Verified at 03:30 PM</p>
                    </div>
                </div>
            </div>
        </article>

        <aside class="space-y-6">
            <div id="students" class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Students</p>
                <p class="mt-3 text-lg font-semibold text-slate-950">Manage student records and QR assignments.</p>
            </div>

            <div id="parents" class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Parents/Guardians</p>
                <p class="mt-3 text-lg font-semibold text-slate-950">Review authorized guardians and their assigned QR codes.</p>
            </div>
        </aside>
    </section>
@endsection
