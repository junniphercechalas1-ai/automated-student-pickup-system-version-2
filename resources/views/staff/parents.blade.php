@extends('staff.layout')

@section('title', 'Parents/Guardians')

@section('head')
    @vite(['resources/js/staff-parents.js'])
@endsection

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Parents/Guardians</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Parents / Guardians</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Manage parent records and the students connected to each guardian.</p>
    </header>

    <section class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div id="parentsList" class="text-sm">
            @if(!empty($parents) && is_array($parents))
                <div class="space-y-3">
                    @foreach($parents as $p)
                        <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-slate-950">{{ $p['full_name'] ?? ($p['fullName'] ?? 'Unnamed') }}</div>
                                    <div class="text-xs text-slate-500">{{ $p['mobile_number'] ?? ($p['mobile'] ?? '') }}</div>
                                </div>
                                <div class="text-sm text-slate-600">{{ $p['relationship'] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p>Loading parents…</p>
            @endif
        </div>
    </section>
@endsection
