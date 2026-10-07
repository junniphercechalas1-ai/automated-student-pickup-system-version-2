@extends('layouts.admin-layout')

@section('content')
<div class="admin-dashboard-page min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600 mt-1">Welcome back, Administrator! Here's what's happening today.</p>
    </div>

    <!-- Date Display -->
    <div class="mb-6">
        <span class="text-sm font-medium text-gray-600">
            📅 {{ \Carbon\Carbon::now()->format('l, F j, Y') }}
        </span>
    </div>

    <!-- Statistics Grid -->
    <div class="admin-dashboard-stats grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Students -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Students</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalStudents ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Registered</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Parents / Guardians -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Parents / Guardians</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $totalParents ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Registered</p>
                </div>
                <div class="bg-green-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 11a6 6 0 00-5.86 0 3 3 0 00-6.14 0A6.993 6.993 0 0012 20a6.993 6.993 0 005.07-2.1A3 3 0 0017.93 11z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Successful Entries -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Successful Entries</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $successfulEntries ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total</p>
                </div>
                <div class="bg-yellow-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Entry Records -->
    <div class="grid grid-cols-1 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Entry Records</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">DATE & TIME</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PARENT/GUARDIAN</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STUDENT</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">VERIFIED BY</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STATUS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($recentEntries ?? [] as $entry)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    {{ isset($entry['entry_time']) ? \Carbon\Carbon::parse($entry['entry_time'])->setTimezone(config('app.timezone'))->format('M d, Y') : 'N/A' }}<br>
                                    <span class="text-xs text-gray-500">{{ isset($entry['entry_time']) ? \Carbon\Carbon::parse($entry['entry_time'])->setTimezone(config('app.timezone'))->format('g:i A') : '' }}</span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    @if (isset($entry['parent']) && is_array($entry['parent']) && $entry['parent'])
                                        {{ $entry['parent']['full_name'] ?? 'N/A' }}<br>
                                        <span class="text-xs text-gray-500">{{ $entry['parent']['mobile_number'] ?? '' }}</span>
                                    @else
                                        <span class="text-gray-500">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    @if (isset($entry['student']) && is_array($entry['student']) && $entry['student'])
                                        {{ $entry['student']['full_name'] ?? 'N/A' }}<br>
                                        <span class="text-xs text-gray-500">
                                            {{ collect([$entry['student']['grade_level'] ?? null, $entry['student']['section'] ?? null])->filter()->implode(' - ') }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">N/A</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-900">{{ $entry['verified_by'] ?? 'Gate Staff' }}</td>
                                <td class="px-4 py-4 text-sm">
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">
                                        {{ isset($entry['status']) ? ucfirst($entry['status']) : 'Verified' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No entry records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <a href="{{ route('admin.entry-records') }}" class="text-blue-600 hover:text-blue-700 font-medium text-sm">View All Entry Records →</a>
            </div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('admin.students') }}" class="flex items-center space-x-3 p-4 border border-gray-200 rounded-lg hover:bg-blue-50 transition">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">Add New Student</p>
                    <p class="text-sm text-gray-600">Register a new student</p>
                </div>
            </a>
            <a href="{{ route('admin.parents') }}" class="flex items-center space-x-3 p-4 border border-gray-200 rounded-lg hover:bg-green-50 transition">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 11a6 6 0 00-5.86 0 3 3 0 00-6.14 0A6.993 6.993 0 0012 20a6.993 6.993 0 005.07-2.1A3 3 0 0017.93 11z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">Add New Parent / Guardian</p>
                    <p class="text-sm text-gray-600">Register a new parent/guardian</p>
                </div>
            </a>
            <a href="{{ route('admin.entry-records') }}" class="flex items-center space-x-3 p-4 border border-gray-200 rounded-lg hover:bg-yellow-50 transition">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-gray-900">View Entry Records</p>
                    <p class="text-sm text-gray-600">View all verification records</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
