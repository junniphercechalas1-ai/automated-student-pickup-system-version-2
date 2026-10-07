@extends('staff.layout')

@section('title', 'Manage Students')

@section('head')
    @vite(['resources/js/staff-students.js'])
@endsection

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Student management</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Add and review students</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Create student records with QR codes so parents can select the correct child during registration.</p>
    </header>

    <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Create student</p>
            <form id="studentForm" class="mt-6 space-y-5">
                <div class="space-y-2 text-sm">
                    <label for="studentFullName" class="block font-medium text-slate-700">Full name</label>
                    <input id="studentFullName" name="full_name" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                </div>
                <div class="space-y-2 text-sm">
                    <label for="studentGradeLevel" class="block font-medium text-slate-700">Grade level</label>
                    <input id="studentGradeLevel" name="grade_level" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                </div>
                <div class="space-y-2 text-sm">
                    <label for="studentSection" class="block font-medium text-slate-700">Section</label>
                    <input id="studentSection" name="section" type="text" class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-3xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Add student</button>
            </form>
            <p id="studentMessage" class="mt-4 text-sm"></p>
        </div>

        <aside class="space-y-6">
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Student list</p>
                    <button id="refreshStudents" class="rounded-3xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Refresh</button>
                </div>
                <div id="studentsList" class="mt-6 space-y-3 text-sm text-slate-700"></div>
            </div>
        </aside>
    </section>
@endsection
