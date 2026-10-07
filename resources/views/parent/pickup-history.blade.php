@extends('parent.layout')

@section('title', 'Pickup History')

@section('content')
    <div class="parent-profile-hero">
        <div class="parent-profile-hero-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12h2l2-6 4 12 2-6h8"></path>
                <path d="M17 3v4"></path>
                <path d="M14 5h6"></path>
                <path d="M7 18h10"></path>
            </svg>
        </div>
        <div class="parent-profile-hero-content">
            <h1 class="parent-profile-hero-title">Pickup History</h1>
            <p class="parent-profile-hero-subtitle">View your pickup records and verification details.</p>
        </div>
    </div>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
                        <path d="M3 12h18"></path>
                        <path d="M12 3v18"></path>
                        <rect x="5" y="5" width="14" height="14" rx="2"></rect>
                    </svg>
                </div>
                <p class="text-sm uppercase tracking-[0.28em] text-blue-600 font-semibold">Total Pickups</p>
            </div>
            <p class="text-4xl font-bold text-slate-950">{{ $totalPickups ?? 0 }}</p>
            <p class="mt-2 text-xs text-slate-600">{{ empty($pickupRecords) ? 'No records yet' : 'This student' }}</p>
        </div>

        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
                        <path d="M5 12.5l4 4L19 2.5"></path>
                    </svg>
                </div>
                <p class="text-sm uppercase tracking-[0.28em] text-blue-600 font-semibold">Verified</p>
            </div>
            <p class="text-4xl font-bold text-emerald-600">{{ $verifiedCount ?? 0 }}</p>
            <p class="mt-2 text-xs text-slate-600">{{ ($verifiedCount ?? 0) === 0 ? 'No verified records' : 'Successful pickups' }}</p>
        </div>

        <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <path d="M3 10h18"></path>
                    </svg>
                </div>
                <p class="text-sm uppercase tracking-[0.28em] text-blue-600 font-semibold">Last Pickup</p>
            </div>
            @if(! empty($lastPickup))
                <p class="text-lg font-semibold text-slate-950">{{ $lastPickup['date'] }}</p>
                <p class="mt-2 text-xs text-slate-600">{{ $lastPickup['time'] }}</p>
            @else
                <p class="text-lg font-semibold text-slate-950">—</p>
                <p class="mt-2 text-xs text-slate-600">No pickup record</p>
            @endif
        </div>
    </section>

    <section class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="parent-pickup-history-header mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
                        <path d="M8 6h13"></path>
                        <path d="M8 12h13"></path>
                        <path d="M8 18h13"></path>
                        <path d="M3 6h.01"></path>
                        <path d="M3 12h.01"></path>
                        <path d="M3 18h.01"></path>
                    </svg>
                </div>
                <p class="text-sm uppercase tracking-[0.28em] text-blue-600 font-semibold">Recent Pickups</p>
            </div>
            <div class="parent-pickup-filter-controls flex items-center gap-2">
                <input id="parentPickupFilterDate" type="date" class="parent-pickup-filter-date rounded-2xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-700 outline-none ring-0 transition focus:border-blue-400 focus:bg-white" aria-label="Filter pickups by date" />
                <button id="parentPickupFilterButton" type="button" class="parent-pickup-filter-button rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">Filter</button>
            </div>
        </div>
        <p id="parentPickupFilterMessage" class="parent-pickup-filter-message" role="status" aria-live="polite" hidden></p>

        @if(empty($pickupRecords))
            <div class="flex items-center justify-center gap-3 rounded-[28px] border border-sky-200 bg-sky-50 p-10 text-center text-sm text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18" class="text-blue-500" aria-hidden="true">
                    <path d="M8 6h13"></path>
                    <path d="M8 12h13"></path>
                    <path d="M8 18h13"></path>
                    <path d="M3 6h.01"></path>
                    <path d="M3 12h.01"></path>
                    <path d="M3 18h.01"></path>
                </svg>
                <span>No pickup records found for this student.</span>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="parent-pickup-history-table w-full text-sm">
                    <thead class="border-b border-slate-200">
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-[0.05em]">
                            <th class="pb-4 font-semibold">Date</th>
                            <th class="pb-4 font-semibold">Time</th>
                            <th class="parent-pickup-verified-by pb-4 font-semibold">Verified By</th>
                            <th class="pb-4 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="space-y-2">
                        @foreach($pickupRecords as $pickup)
                            <tr class="parent-pickup-record-row border-b border-slate-100 hover:bg-slate-50" data-pickup-date="{{ $pickup['date_key'] ?? '' }}">
                                <td class="py-4">{{ $pickup['date'] ?? 'N/A' }}</td>
                                <td class="py-4">{{ $pickup['time'] ?? 'N/A' }}</td>
                                <td class="parent-pickup-verified-by py-4">{{ $pickup['verified_by'] ?? 'N/A' }}</td>
                                <td class="py-4">
                                    @php
                                        $status = $pickup['status'] ?? 'Verified';
                                        $statusLower = strtolower($status);
                                        
                                        if (strpos($statusLower, 'pending') !== false) {
                                            $badgeClasses = 'bg-amber-100 text-amber-700';
                                        } elseif (strpos($statusLower, 'failed') !== false || strpos($statusLower, 'rejected') !== false) {
                                            $badgeClasses = 'bg-red-100 text-red-700';
                                        } else {
                                            $badgeClasses = 'bg-emerald-100 text-emerald-700';
                                        }
                                    @endphp
                                    <span class="inline-block rounded-full px-3 py-1 font-semibold {{ $badgeClasses }}">
                                        {{ $status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <script>
        (() => {
            const dateInput = document.getElementById('parentPickupFilterDate');
            const filterButton = document.getElementById('parentPickupFilterButton');
            const message = document.getElementById('parentPickupFilterMessage');
            const rows = Array.from(document.querySelectorAll('.parent-pickup-record-row'));

            filterButton?.addEventListener('click', () => {
                const selectedDate = dateInput?.value ?? '';
                let visibleCount = 0;

                rows.forEach((row) => {
                    const matchesDate = selectedDate === '' || row.dataset.pickupDate === selectedDate;
                    row.hidden = !matchesDate;
                    if (matchesDate) visibleCount += 1;
                });

                if (message) {
                    message.hidden = visibleCount > 0;
                    message.textContent = selectedDate
                        ? `No pickup records found for ${selectedDate}.`
                        : 'No pickup records found.';
                }
            });
        })();
    </script>
@endsection
