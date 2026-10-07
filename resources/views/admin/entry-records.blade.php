@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Entry Records</h1>
                <p class="text-gray-600 mt-1">View all successful QR code verifications (gate entries).</p>
            </div>
            <form method="POST" action="{{ route('admin.entry-records.delete-all') }}" onsubmit="return confirm('Delete all entry records? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" style="background-color: #dc2626; color: #ffffff;" class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-8 0h10"></path>
                    </svg>
                    Delete All Records
                </button>
            </form>
        </div>
        @if (session('success'))
            <div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Total Successful Entries -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Successful Entries</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalSuccessfulEntries ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">This Month</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Today's Entries -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Today's Entries</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $todayEntries ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ \Carbon\Carbon::today()->format('F j, Y') }}</p>
                </div>
                <div class="bg-green-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2a2 2 0 012-2h2zm13 2a1 1 0 100 2h1a1 1 0 100-2h-1zm0 4a1 1 0 100 2h1a1 1 0 100-2h-1zM5 15a1 1 0 100 2h1a1 1 0 100-2H5zm0 3a1 1 0 100 2h1a1 1 0 100-2H5z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Entry Time -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Average Entry Time</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $averageEntryTime ?? '0:00 AM' }}</p>
                    <p class="text-xs text-gray-500 mt-1">This Month</p>
                </div>
                <div class="bg-yellow-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.entry-records') }}" class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                <select name="date_range" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="all" {{ $selectedDate == 'all' || $selectedDate == '' ? 'selected' : '' }}>All Dates</option>
                    @foreach ($dateOptions ?? [] as $option)
                        <option value="{{ $option['value'] ?? '' }}" {{ $selectedDate == ($option['value'] ?? '') ? 'selected' : '' }}>{{ $option['label'] ?? $option['value'] ?? 'Date' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Parent / Guardian</label>
                <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Parents / Guardians</option>
                    @foreach ($parentOptions ?? [] as $option)
                        <option value="{{ $option['value'] ?? '' }}" {{ $selectedParentId == ($option['value'] ?? '') ? 'selected' : '' }}>{{ $option['label'] ?? 'Parent' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Linked Student</label>
                <select name="student_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Students</option>
                    @foreach ($studentOptions ?? [] as $option)
                        <option value="{{ $option['value'] ?? '' }}" {{ $selectedStudentId == ($option['value'] ?? '') ? 'selected' : '' }}>{{ $option['label'] ?? 'Student' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Verified By (Staff)</label>
                <select name="verified_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Staff</option>
                    @foreach ($staffOptions ?? [] as $staff)
                        <option value="{{ $staff }}" {{ $selectedStaff == $staff ? 'selected' : '' }}>{{ $staff }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <div class="flex">
                    <input type="text" name="search" value="{{ $searchTerm ?? '' }}" placeholder="Search by name, QR ID, or student..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Entry Records Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">DATE</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">TIME</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PARENT / GUARDIAN</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STUDENT</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">VERIFIED BY</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ACTION</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($entryRows ?? [] as $index => $entry)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $index + 1 }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $entry['date'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $entry['time'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-purple-300 flex items-center justify-center text-white text-xs font-bold mr-3">{{ strtoupper(substr((string) ($entry['parent_name'] ?? 'N'), 0, 1)) }}</div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $entry['parent_name'] ?? 'N/A' }}</p>
                                    @if (! empty($entry['parent_mobile']))
                                        <p class="text-xs text-gray-500">{{ $entry['parent_mobile'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $entry['student_name'] ?? 'N/A' }}
                            @if (! empty($entry['student_class']))
                                <br><span class="text-xs text-gray-500">{{ $entry['student_class'] }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $entry['verified_by'] ?? 'N/A' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <form method="POST" action="{{ route('admin.entry-records.delete', ['source' => $entry['source'] ?? 'entry-records', 'id' => $entry['id']]) }}" onsubmit="return confirm('Delete this entry record?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No entry records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
