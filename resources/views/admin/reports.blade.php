@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Pickup Reports</h1>
                    <p class="text-gray-600 mt-1"><a href="{{ route('admin.dashboard') }}"
                            class="text-blue-600 hover:text-blue-800">← Back to Dashboard</a></p>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Summary Card -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Total Pickups</h2>
                    <p class="text-3xl font-bold text-blue-600" id="totalPickups">-</p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z">
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pickups by Date -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Pickups by Date</h2>
            </div>

            <div id="pickupsList" class="divide-y divide-gray-200">
                <div class="px-6 py-4 text-center text-gray-500">Loading pickup data...</div>
            </div>
        </div>

        <!-- No Data State -->
        <div id="noDataState" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-8 text-center mt-8">
            <svg class="h-12 w-12 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-lg font-semibold text-blue-900">No Pickups Found</h3>
            <p class="text-blue-700 mt-2">There are no pickup records yet.</p>
        </div>
    </main>
</div>

<script>
    // Load reports on page load
    document.addEventListener('DOMContentLoaded', loadReports);

    async function loadReports() {
        try {
            const response = await fetch('{{ route("admin.reports.pickups") }}');
            const data = await response.json();

            if (data.error) {
                console.error('Error:', data.error);
                document.getElementById('pickupsList').innerHTML =
                    '<div class="px-6 py-4 text-center text-red-500">Error loading pickups</div>';
                return;
            }

            // Update total pickups
            document.getElementById('totalPickups').textContent = data.total || 0;

            if (!data.by_date || Object.keys(data.by_date).length === 0) {
                document.getElementById('pickupsList').innerHTML =
                    '<div class="px-6 py-4 text-center text-gray-500">No pickup records found</div>';
                return;
            }

            // Group and display by date
            let html = '';
            for (const [date, pickups] of Object.entries(data.by_date)) {
                html += `
                    <div class="px-6 py-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-900">
                                ${new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                            </h3>
                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                                ${pickups.length} pickups
                            </span>
                        </div>
                        <div class="space-y-2">
                `;
                
                pickups.forEach(pickup => {
                    const time = new Date(pickup.picked_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    html += `
                        <div class="flex items-center justify-between text-sm text-gray-600 bg-gray-50 p-2 rounded">
                            <span>${pickup.students?.name || 'Unknown Student'}</span>
                            <div class="flex items-center space-x-3">
                                <span class="text-xs text-gray-500">${pickup.students?.class || 'N/A'}</span>
                                <span class="font-mono text-xs font-semibold text-gray-700">${time}</span>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }

            document.getElementById('pickupsList').innerHTML = html;
        } catch (error) {
            console.error('Error loading reports:', error);
            document.getElementById('pickupsList').innerHTML =
                '<div class="px-6 py-4 text-center text-red-500">Error loading pickup data</div>';
        }
    }
</script>
@endsection
