@extends('layouts.admin-layout')

@section('content')
<main class="min-h-screen bg-gray-50 px-6 py-8 lg:px-8">
    <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Pending Registrations</h1>
            <p class="mt-1 text-gray-600">Review parent and staff accounts waiting for approval.</p>
        </div>
        <button id="refreshPending" type="button" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Refresh</button>
    </header>

    <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Pending registration totals">
        <div class="rounded-lg bg-white p-5 shadow">
            <p class="text-sm font-medium text-gray-600">Total pending</p>
            <p id="pendingTotal" class="mt-1 text-3xl font-bold text-gray-900">—</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <p class="text-sm font-medium text-gray-600">Parents / Guardians</p>
            <p id="pendingParents" class="mt-1 text-3xl font-bold text-gray-900">—</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <p class="text-sm font-medium text-gray-600">Staff</p>
            <p id="pendingStaff" class="mt-1 text-3xl font-bold text-gray-900">—</p>
        </div>
    </section>

    <div id="pendingNotice" class="mb-4 hidden rounded-lg border px-4 py-3 text-sm" role="status" aria-live="polite"></div>

    <section class="overflow-hidden rounded-lg bg-white shadow">
        <div class="border-b border-gray-200 p-4">
            <label for="pendingSearch" class="mb-2 block text-sm font-medium text-gray-700">Search pending accounts</label>
            <input id="pendingSearch" type="search" placeholder="Search by name, account type, phone, or student..."
                class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Account</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Phone</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Details</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Submitted</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody id="pendingRows" class="divide-y divide-gray-200">
                    <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Loading pending registrations...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
    const pendingEndpoints = {
        parents: @json(route('admin.parents')),
        staff: @json(route('admin.staff')),
        parentAction: @json(url('/admin/parents')),
        staffAction: @json(url('/admin/staff')),
    };
    let pendingAccounts = [];

    function showPendingNotice(message, isError = false) {
        const notice = document.getElementById('pendingNotice');
        notice.textContent = message;
        notice.className = `mb-4 rounded-lg border px-4 py-3 text-sm ${isError
            ? 'border-red-200 bg-red-50 text-red-800'
            : 'border-blue-200 bg-blue-50 text-blue-800'}`;
    }

    async function fetchPendingSource(url, label) {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.error || payload.message || `Unable to load ${label} (${response.status}).`);
        }
        return response.json();
    }

    async function loadPendingRegistrations() {
        const rows = document.getElementById('pendingRows');
        rows.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">Loading pending registrations...</td></tr>';

        try {
            const [parentPayload, staffPayload] = await Promise.all([
                fetchPendingSource(`${pendingEndpoints.parents}?format=json`, 'parent registrations'),
                fetchPendingSource(`${pendingEndpoints.staff}?format=json`, 'staff registrations'),
            ]);
            const parents = Array.isArray(parentPayload) ? parentPayload : parentPayload.parents;
            const staff = Array.isArray(staffPayload) ? staffPayload : staffPayload.staff;
            if (!Array.isArray(parents) || !Array.isArray(staff)) {
                throw new Error('The server returned an unexpected pending-account list.');
            }

            pendingAccounts = [
                ...parents.filter(account => account.is_approved !== true && account.is_active !== false)
                    .map(account => ({ ...account, accountType: 'Parent / Guardian', role: 'parent' })),
                ...staff.filter(account => account.is_approved !== true && account.is_active !== false)
                    .map(account => ({ ...account, accountType: 'Staff', role: 'staff' })),
            ].sort((left, right) => (Date.parse(right.created_at ?? '') || 0) - (Date.parse(left.created_at ?? '') || 0));

            document.getElementById('pendingTotal').textContent = pendingAccounts.length;
            document.getElementById('pendingParents').textContent = pendingAccounts.filter(account => account.role === 'parent').length;
            document.getElementById('pendingStaff').textContent = pendingAccounts.filter(account => account.role === 'staff').length;
            renderPendingRows();
        } catch (error) {
            console.error('Unable to load pending registrations:', error);
            showPendingNotice(error instanceof Error ? error.message : 'Unable to load pending registrations.', true);
            rows.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-red-600">Pending registrations could not be loaded.</td></tr>';
        }
    }

    function renderPendingRows() {
        const rows = document.getElementById('pendingRows');
        const term = document.getElementById('pendingSearch').value.trim().toLowerCase();
        const visibleAccounts = pendingAccounts.filter(account => [
            account.full_name, account.accountType, account.phone_number, account.mobile_number,
            account.linked_student_name, account.relationship, account.username,
        ].join(' ').toLowerCase().includes(term));

        rows.replaceChildren();
        if (visibleAccounts.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'px-5 py-8 text-center text-gray-500';
            cell.textContent = pendingAccounts.length === 0
                ? 'There are no pending registrations.'
                : 'No pending accounts match your search.';
            row.appendChild(cell);
            rows.appendChild(row);
            return;
        }

        visibleAccounts.forEach(account => {
            const row = document.createElement('tr');
            const accountCell = document.createElement('td');
            accountCell.className = 'px-5 py-4';
            const name = document.createElement('div');
            name.className = 'font-semibold text-gray-900';
            name.textContent = account.full_name || 'Name not provided';
            const accountType = document.createElement('div');
            accountType.className = 'mt-1 text-sm text-gray-500';
            accountType.textContent = account.accountType;
            accountCell.append(name, accountType);

            const phoneCell = document.createElement('td');
            phoneCell.className = 'whitespace-nowrap px-5 py-4 text-sm text-gray-700';
            phoneCell.textContent = account.phone_number || account.mobile_number || 'Not provided';

            const detailsCell = document.createElement('td');
            detailsCell.className = 'px-5 py-4 text-sm text-gray-700';
            detailsCell.textContent = account.role === 'parent'
                ? [account.relationship, account.linked_student_name ? `Student: ${account.linked_student_name}` : null].filter(Boolean).join(' · ') || '—'
                : (account.username ? `Username: ${account.username}` : '—');

            const dateCell = document.createElement('td');
            dateCell.className = 'whitespace-nowrap px-5 py-4 text-sm text-gray-600';
            const submittedAt = account.created_at ? new Date(account.created_at) : null;
            dateCell.textContent = submittedAt && !Number.isNaN(submittedAt.valueOf())
                ? submittedAt.toLocaleString()
                : '—';

            const actionsCell = document.createElement('td');
            actionsCell.className = 'whitespace-nowrap px-5 py-4';
            const actions = document.createElement('div');
            actions.className = 'flex items-center gap-2';
            actions.append(
                createDecisionButton('Approve', 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200', account, true),
                createDecisionButton('Decline', 'bg-rose-100 text-rose-800 hover:bg-rose-200', account, false),
            );
            actionsCell.appendChild(actions);
            row.append(accountCell, phoneCell, detailsCell, dateCell, actionsCell);
            rows.appendChild(row);
        });
    }

    function createDecisionButton(label, classes, account, approved) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `rounded px-3 py-1.5 text-sm font-semibold ${classes}`;
        button.textContent = label;
        button.addEventListener('click', () => reviewPendingAccount(account, approved));
        return button;
    }

    async function reviewPendingAccount(account, approved) {
        const action = approved ? 'approve' : 'decline';
        if (!window.confirm(`Are you sure you want to ${action} ${account.accountType.toLowerCase()} account for ${account.full_name || 'this applicant'}?`)) {
            return;
        }

        const baseUrl = account.role === 'parent' ? pendingEndpoints.parentAction : pendingEndpoints.staffAction;
        try {
            const response = await fetch(`${baseUrl}/${encodeURIComponent(account.id)}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ is_approved: approved, is_active: approved }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(payload.error || payload.message || `Unable to ${action} this account.`);
            }

            showPendingNotice(payload.message || `Account ${approved ? 'approved' : 'declined'}.`);
            await loadPendingRegistrations();
        } catch (error) {
            console.error(`Unable to ${action} pending account:`, error);
            showPendingNotice(error instanceof Error ? error.message : `Unable to ${action} this account.`, true);
        }
    }

    document.getElementById('pendingSearch').addEventListener('input', renderPendingRows);
    document.getElementById('refreshPending').addEventListener('click', loadPendingRegistrations);
    document.addEventListener('DOMContentLoaded', loadPendingRegistrations);
</script>
@endsection
