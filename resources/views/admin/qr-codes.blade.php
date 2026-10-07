@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">QR Codes</h1>
            <p class="text-gray-600 mt-1">Manage QR codes assigned to parents/guardians.</p>
        </div>
        <button onclick="generateQRModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Generate QR Code</span>
        </button>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total QR Codes -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total QR Codes</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">198</p>
                    <p class="text-xs text-gray-500 mt-1">All QR Codes</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Active QR Codes -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Active QR Codes</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">187</p>
                    <p class="text-xs text-gray-500 mt-1">Active</p>
                </div>
                <div class="bg-green-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Inactive QR Codes -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Inactive QR Codes</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">11</p>
                    <p class="text-xs text-gray-500 mt-1">Inactive</p>
                </div>
                <div class="bg-yellow-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Expired QR Codes -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Expired QR Codes</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">0</p>
                    <p class="text-xs text-gray-500 mt-1">Expired</p>
                </div>
                <div class="bg-red-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 2.523a6 6 0 008.367 8.367zM14.89 6.523a6 6 0 00-8.367-8.367 6 6 0 008.367 8.367z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                    <option>Expired</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Linked Student</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Students</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Relationship</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Relationships</option>
                    <option>Mother</option>
                    <option>Father</option>
                    <option>Guardian</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Parent / Guardian</label>
                <div class="flex">
                    <input id="qrSearch" type="search" placeholder="Search by name or phone number..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition" onclick="filterQrRows()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Codes Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">QR ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PARENT / GUARDIAN</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">RELATIONSHIP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">LINKED STUDENT</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">QR STATUS</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">DATE CREATED</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ACTIONS</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">QR-001</td>
                    <td class="px-6 py-4 text-sm text-gray-900">Maria Santos<br><span class="text-xs text-gray-500">0917 123 4567</span></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-7a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"></path>
                            </svg>
                            Mother
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">Juan Dela Cruz<br><span class="text-xs text-gray-500">Grade 5 - Mabini</span></td>
                    <td class="px-6 py-4 text-sm"><span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">Active</span></td>
                    <td class="px-6 py-4 text-sm text-gray-600">May 10, 2024<br><span class="text-xs">9:15 AM</span></td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex space-x-2">
                            <button class="text-blue-600 hover:text-blue-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <button class="text-red-600 hover:text-red-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">QR-002</td>
                    <td class="px-6 py-4 text-sm text-gray-900">Rafael Reyes<br><span class="text-xs text-gray-500">0918 987 6543</span></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-7a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"></path>
                            </svg>
                            Father
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">Sophia Reyes<br><span class="text-xs text-gray-500">Grade 3 - Rizal</span></td>
                    <td class="px-6 py-4 text-sm"><span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">Active</span></td>
                    <td class="px-6 py-4 text-sm text-gray-600">May 10, 2024<br><span class="text-xs">9:20 AM</span></td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex space-x-2">
                            <button class="text-blue-600 hover:text-blue-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <button class="text-red-600 hover:text-red-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">QR-003</td>
                    <td class="px-6 py-4 text-sm text-gray-900">Linda Cruz<br><span class="text-xs text-gray-500">0920 111 2222</span></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-7a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"></path>
                            </svg>
                            Mother
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">Miguel Lim<br><span class="text-xs text-gray-500">Grade 4 - Bonifacio</span></td>
                    <td class="px-6 py-4 text-sm"><span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">Active</span></td>
                    <td class="px-6 py-4 text-sm text-gray-600">May 11, 2024<br><span class="text-xs">10:05 AM</span></td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex space-x-2">
                            <button class="text-blue-600 hover:text-blue-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <button class="text-red-600 hover:text-red-700 font-medium">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="bg-white px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-600">
                Showing 1 to 5 of 198 entries
            </div>
            <div class="flex items-center space-x-2">
                <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">←</button>
                <button class="px-3 py-1 bg-blue-600 text-white rounded-lg">1</button>
                <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">2</button>
                <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">3</button>
                <span class="text-gray-600">...</span>
                <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">40</button>
                <button class="px-3 py-1 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">→</button>
            </div>
            <div>
                <select class="px-3 py-1 border border-gray-300 rounded-lg">
                    <option>10</option>
                    <option selected>10</option>
                </select>
                <span class="text-gray-600 text-sm ml-2">rows per page</span>
            </div>
        </div>
    </div>
</div>

<script>
    function filterQrRows() {
        const term = document.getElementById('qrSearch')?.value.trim().toLowerCase() ?? '';
        document.querySelectorAll('.bg-white > table tbody tr').forEach((row) => {
            row.hidden = Boolean(term) && !row.textContent.toLowerCase().includes(term);
        });
    }

    document.getElementById('qrSearch')?.addEventListener('input', filterQrRows);

    function generateQRModal() {
        console.log('Generate QR Code modal');
    }
</script>
@endsection
