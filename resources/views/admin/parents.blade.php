@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Parents / Guardians</h1>
            <p class="text-gray-600 mt-1">View and manage parent or guardian accounts.</p>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Parents / Guardians -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Parents / Guardians</p>
                    <p id="totalParentsCount" class="text-3xl font-bold text-gray-900 mt-2">0</p>
                    <p class="text-xs text-gray-500 mt-1">Registered</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 11a6 6 0 00-5.86 0 3 3 0 00-6.14 0A6.993 6.993 0 0012 20a6.993 6.993 0 005.07-2.1A3 3 0 0017.93 11z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Approved Accounts -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Approved Accounts</p>
                    <p id="approvedParentsCount" class="text-3xl font-bold text-gray-900 mt-2">0</p>
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
                    <p id="pendingParentsCount" class="text-3xl font-bold text-gray-900 mt-2">0</p>
                    <p class="text-xs text-gray-500 mt-1">Awaiting approval</p>
                </div>
                <div class="bg-yellow-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Linked to Students -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Linked to Students</p>
                    <p id="linkedStudentsCount" class="text-3xl font-bold text-gray-900 mt-2">0</p>
                    <p class="text-xs text-gray-500 mt-1">With student assigned</p>
                </div>
                <div class="bg-purple-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 11a6 6 0 00-5.86 0 3 3 0 00-6.14 0A6.993 6.993 0 0012 20a6.993 6.993 0 005.07-2.1A3 3 0 0017.93 11z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Relationship</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Relationships</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Status</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Linked Student</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option>All Students</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Parent / Guardian</label>
                <div class="flex">
                    <input id="parentSearch" type="search" placeholder="Search by name or phone number..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition" onclick="filterParentRows()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Parents Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PARENT / GUARDIAN ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">FULL NAME</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">LINKED STUDENT</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">RELATIONSHIP</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">MOBILE NUMBER</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">EMAIL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">APPROVAL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ACTIONS</th>
                </tr>
            </thead>
            <tbody id="parentsTable" class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">Loading parents...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Parent Modal -->
<div id="parentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900" id="parentModalTitle">Add Parent</h3>
            <button onclick="closeParentModal()"
                class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
        </div>

        <form id="parentForm" class="space-y-4">
            <input type="hidden" id="parentId">

            <div>
                <label for="fullName" class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" id="fullName" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>

            <div>
                <label for="mobileNumber" class="block text-sm font-medium text-gray-700">Mobile Number</label>
                <input type="tel" id="mobileNumber" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="email"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>

            <div>
                <label for="relationship" class="block text-sm font-medium text-gray-700">Relationship</label>
                <select id="relationship" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    <option value="">Select relationship</option>
                    <option value="Mother">Mother</option>
                    <option value="Father">Father</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label for="studentId" class="block text-sm font-medium text-gray-700">Student ID</label>
                <input type="number" id="studentId" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeParentModal()"
                    class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Save Parent
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterParentRows() {
        const term = document.getElementById('parentSearch')?.value.trim().toLowerCase() ?? '';
        document.querySelectorAll('#parentsTable tr').forEach((row) => {
            row.hidden = Boolean(term) && !row.textContent.toLowerCase().includes(term);
        });
    }

    document.getElementById('parentSearch')?.addEventListener('input', filterParentRows);

    // Load parents on page load
    document.addEventListener('DOMContentLoaded', loadParents);

    // Form submission
    document.getElementById('parentForm').addEventListener('submit', handleParentSubmit);

    const parentsById = new Map();

    async function loadParents() {
        try {
            const response = await fetch('{{ route("admin.parents") }}?format=json', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || payload.error || `Failed to load parents (${response.status}).`);
            }

            const payload = await response.json();
            const parents = Array.isArray(payload) ? payload : (payload.parents || []);
            const counts = payload.counts || {
                total_parents: parents.length,
                approved_parents: parents.filter(parent => parent.is_approved === true).length,
                pending_parents: parents.filter(parent => parent.is_approved !== true && parent.is_active !== false).length,
                linked_to_students: parents.filter(parent => (parent.linked_student_name ?? 'N/A') !== 'N/A').length,
            };

            document.getElementById('totalParentsCount').textContent = counts.total_parents ?? 0;
            document.getElementById('approvedParentsCount').textContent = counts.approved_parents ?? 0;
            document.getElementById('pendingParentsCount').textContent = counts.pending_parents ?? 0;
            document.getElementById('linkedStudentsCount').textContent = counts.linked_to_students ?? 0;

            const tbody = document.getElementById('parentsTable');
            tbody.innerHTML = '';
            parentsById.clear();

            if (parents.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">No parents found</td></tr>';
                return;
            }

            parents.forEach(parent => {
                const parentId = parent.parent_id ?? parent.id ?? 'N/A';
                const recordId = parent.id ?? parent.parent_id;
                parentsById.set(String(recordId), parent);
                const fullName = parent.full_name ?? 'N/A';
                const linkedStudentName = parent.linked_student_name ?? 'N/A';
                const relationship = parent.relationship ?? 'N/A';
                const mobileNumber = parent.phone_number ?? parent.mobile_number ?? 'N/A';
                const email = parent.email ?? 'N/A';
                const isApproved = parent.is_approved === true;
                const isDeclined = !isApproved && parent.is_active === false;
                const approvalLabel = isApproved ? 'Approved' : (isDeclined ? 'Declined' : 'Pending');
                const approvalClass = isApproved
                    ? 'bg-green-100 text-green-800'
                    : (isDeclined ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');

                tbody.innerHTML += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${parentId}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${fullName}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${linkedStudentName}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${relationship}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${mobileNumber}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${email}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${approvalClass}">
                                ${approvalLabel}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            ${parent.is_pending_registration
                                ? ''
                                : `<button type="button" onclick="editParent('${recordId}')" class="text-blue-600 hover:text-blue-800">Edit</button>`}
                            ${isApproved || isDeclined
                                ? `<button onclick="deleteParent('${recordId}')" class="text-red-600 hover:text-red-900">Delete Account</button>`
                                : `<button onclick="updateParentApproval('${recordId}', true)" class="text-emerald-600 hover:text-emerald-800">Approve</button>
                                   <button onclick="updateParentApproval('${recordId}', false)" class="text-rose-600 hover:text-rose-800">Decline</button>`}
                        </td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading parents:', error);
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 8;
            cell.className = 'px-6 py-4 text-center text-red-500';
            cell.textContent = error instanceof Error ? error.message : 'Error loading parents. Please try again.';
            row.appendChild(cell);
            document.getElementById('parentsTable').replaceChildren(row);
        }
    }

    function openParentModal(action) {
        document.getElementById('parentId').value = '';
        document.getElementById('parentForm').reset();
        document.getElementById('parentModalTitle').textContent = action === 'create' ? 'Add Parent' :
            'Edit Parent';
        document.getElementById('parentModal').classList.remove('hidden');
    }

    function editParent(id) {
        const parent = parentsById.get(String(id));
        if (!parent) {
            window.alert('Unable to load this parent account. Please refresh and try again.');
            return;
        }

        document.getElementById('parentForm').reset();
        document.getElementById('parentId').value = id;
        document.getElementById('parentModalTitle').textContent = 'Edit Parent';
        document.getElementById('fullName').value = parent.full_name ?? '';
        document.getElementById('mobileNumber').value = parent.phone_number ?? parent.mobile_number ?? '';
        document.getElementById('email').value = parent.email ?? '';
        document.getElementById('relationship').value = parent.relationship ?? '';
        document.getElementById('studentId').value = parent.student_id ?? '';
        document.getElementById('parentModal').classList.remove('hidden');
    }

    function closeParentModal() {
        document.getElementById('parentModal').classList.add('hidden');
    }

    async function handleParentSubmit(e) {
        e.preventDefault();
        const parentId = document.getElementById('parentId').value;
        const data = {
            full_name: document.getElementById('fullName').value,
            phone_number: document.getElementById('mobileNumber').value,
            relationship: document.getElementById('relationship').value,
            email: document.getElementById('email').value || null,
            student_id: Number(document.getElementById('studentId').value)
        };

        try {
            const url = parentId ? 
                `/admin/parents/${parentId}` : 
                '{{ route("admin.parents.store") }}';
            
            const method = parentId ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeParentModal();
                loadParents();
            } else {
                alert('Error saving parent');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving parent');
        }
    }

    async function updateParentApproval(id, approved) {
        const action = approved ? 'approve' : 'decline';
        if (!window.confirm(`Are you sure you want to ${action} this parent account?`)) {
            return;
        }

        try {
            const response = await fetch(`/admin/parents/${encodeURIComponent(id)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    is_approved: approved,
                    is_active: approved,
                })
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.error || `Unable to ${action} parent account.`);
            }

            const payload = await response.json().catch(() => ({}));
            if (payload.notification_sent === false) {
                window.alert(payload.message || 'The account decision was saved, but the SMS notification could not be sent.');
            }

            await loadParents();
        } catch (error) {
            console.error(`Error trying to ${action} parent account:`, error);
            window.alert(error.message);
        }
    }

    async function deleteParent(id) {
        if (confirm('Are you sure you want to delete this parent?')) {
            try {
                const response = await fetch(`/admin/parents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    loadParents();
                } else {
                    alert('Error deleting parent');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting parent');
            }
        }
    }
</script>
@endsection
