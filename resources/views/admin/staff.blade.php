@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Staff Accounts</h1>
            <p class="text-gray-600 mt-1">Manage staff accounts who can verify QR codes.</p>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Total Staff Accounts -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Staff Accounts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $total ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">All Accounts</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Approved Accounts -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Approved Accounts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $approved ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Approved</p>
                </div>
                <div class="bg-green-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Accounts -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Accounts</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $pending ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Awaiting approval</p>
                </div>
                <div class="bg-yellow-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Staff</label>
                <div class="flex">
                    <input id="staffSearch" type="search" placeholder="Search by name, username, or email..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition" onclick="filterStaffRows()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">FULL NAME</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">EMAIL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PHONE</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">APPROVAL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STATUS</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ACTIONS</th>
                </tr>
            </thead>
            <tbody id="staffTable" class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading staff...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="staffEditModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" role="dialog" aria-modal="true" aria-labelledby="staffEditTitle">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 id="staffEditTitle" class="text-lg font-bold text-gray-900">Edit Staff</h3>
            <button type="button" onclick="closeStaffEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl" aria-label="Close">&times;</button>
        </div>
        <form id="staffEditForm" class="space-y-4">
            <input id="staffEditId" type="hidden">
            <div>
                <label for="staffEditName" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input id="staffEditName" type="text" required maxlength="255" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>
            <div>
                <label for="staffEditEmail" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="staffEditEmail" type="email" required maxlength="255" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>
            <div>
                <label for="staffEditPhone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                <input id="staffEditPhone" type="tel" maxlength="50" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeStaffEditModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterStaffRows() {
        const term = document.getElementById('staffSearch')?.value.trim().toLowerCase() ?? '';
        document.querySelectorAll('#staffTable tr').forEach((row) => {
            row.hidden = Boolean(term) && !row.textContent.toLowerCase().includes(term);
        });
    }

    document.getElementById('staffSearch')?.addEventListener('input', filterStaffRows);

    let staffRecords = [];

    // Load staff on page load
    document.addEventListener('DOMContentLoaded', loadStaff);
    document.getElementById('staffEditForm').addEventListener('submit', handleStaffEditSubmit);

    async function loadStaff() {
        try {
            const response = await fetch('{{ route("admin.staff") }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const staff = await response.json();
            staffRecords = Array.isArray(staff) ? staff : [];

            const tbody = document.getElementById('staffTable');
            tbody.innerHTML = '';

            if (staffRecords.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No staff found</td></tr>';
                return;
            }

            staffRecords.forEach(member => {
                tbody.innerHTML += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${member.full_name || 'N/A'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${member.email || 'N/A'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${member.phone_number || 'N/A'}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${member.is_approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${member.is_approved ? 'Approved' : 'Pending'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${member.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${member.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            ${member.is_pending_registration ? '' : `<button type="button" onclick="editStaff('${member.id}')" class="mr-3 text-blue-600 hover:text-blue-800">Edit</button>`}
                            ${member.is_approved
                                ? `<button onclick="deleteStaff('${member.id}')" class="text-rose-600 hover:text-rose-800">Delete Account</button>`
                                : `<button onclick="updateStaffApproval('${member.id}', true)" class="text-emerald-600 hover:text-emerald-800 mr-3">Approve</button>
                                   <button onclick="updateStaffApproval('${member.id}', false)" class="text-rose-600 hover:text-rose-800">Decline</button>`}
                        </td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading staff:', error);
            document.getElementById('staffTable').innerHTML =
                '<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error loading staff</td></tr>';
        }
    }

    function editStaff(id) {
        const member = staffRecords.find(record => String(record.id) === String(id));
        if (!member) {
            window.alert('Unable to load this staff account. Please refresh and try again.');
            return;
        }

        document.getElementById('staffEditId').value = member.id;
        document.getElementById('staffEditName').value = member.full_name ?? '';
        document.getElementById('staffEditEmail').value = member.email ?? '';
        document.getElementById('staffEditPhone').value = member.phone_number ?? '';
        document.getElementById('staffEditModal').classList.remove('hidden');
        document.getElementById('staffEditName').focus();
    }

    function closeStaffEditModal() {
        document.getElementById('staffEditModal').classList.add('hidden');
    }

    async function handleStaffEditSubmit(event) {
        event.preventDefault();
        const id = document.getElementById('staffEditId').value;

        try {
            const response = await fetch(`/admin/staff/${encodeURIComponent(id)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    full_name: document.getElementById('staffEditName').value.trim(),
                    email: document.getElementById('staffEditEmail').value.trim(),
                    phone_number: document.getElementById('staffEditPhone').value.trim() || null,
                }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.error || 'Unable to update staff account.');
            }

            closeStaffEditModal();
            await loadStaff();
        } catch (error) {
            console.error('Error updating staff account:', error);
            window.alert(error.message);
        }
    }

    async function updateStaffApproval(id, approved) {
        const action = approved ? 'approve' : 'decline';
        if (!window.confirm(`Are you sure you want to ${action} this staff account?`)) {
            return;
        }

        try {
            const response = await fetch(`/admin/staff/${encodeURIComponent(id)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
                ,body: JSON.stringify({
                    is_approved: approved,
                    is_active: approved,
                })
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.error || `Unable to ${action} staff account.`);
            }

            const payload = await response.json().catch(() => ({}));
            if (payload.notification_sent === false) {
                window.alert(payload.message || 'The account decision was saved, but the SMS notification could not be sent.');
            }

            await loadStaff();
        } catch (error) {
            console.error(`Error trying to ${action} staff account:`, error);
            window.alert(error.message);
        }
    }

    async function deleteStaff(id) {
        if (!window.confirm('Delete this approved staff account permanently?')) {
            return;
        }

        try {
            const response = await fetch(`/admin/staff/${encodeURIComponent(id)}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    , 'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.error || 'Unable to delete staff account.');
            }

            await loadStaff();
        } catch (error) {
            console.error('Error deleting staff account:', error);
            window.alert(error.message);
        }
    }

</script>
@endsection
