@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 py-8 lg:px-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">SMS Notifications</h1>
        <p class="mt-1 text-gray-600">Send pickup reminders to parent and guardian phone numbers.</p>
    </div>

    @if (session('sms_success'))
        <div class="mb-6 rounded-lg bg-green-100 p-4 text-sm text-green-800" role="status">{{ session('sms_success') }}</div>
    @endif
    @if (session('sms_error'))
        <div class="mb-6 rounded-lg bg-red-100 p-4 text-sm text-red-800" role="alert">{{ session('sms_error') }}</div>
    @endif
    @error('parent_ids')
        <div class="mb-6 rounded-lg bg-red-100 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <div class="mb-6 flex flex-wrap gap-2 rounded-lg bg-white p-2 shadow" role="tablist" aria-label="SMS recipient type">
        <button type="button" id="showParentNotifications" role="tab" aria-selected="true" class="notification-tab rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Parent reminders</button>
    </div>

    <form id="parentNotificationsPanel" method="POST" action="{{ route('admin.notifications.send-to-parents') }}" onsubmit="return confirm('Send this SMS reminder to all selected parents?');" class="rounded-lg bg-white p-6 shadow">
        @csrf
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Connected driver: {{ $smsDriver }}</p>
                <h2 class="mt-1 text-xl font-bold text-gray-900">Select recipients</h2>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
                <span class="text-sm text-gray-500">{{ count($smsParents) }} parent(s) with phone numbers</span>
                <label class="relative block w-full sm:w-72">
                    <span class="sr-only">Search parents</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"></path>
                    </svg>
                    <input id="adminParentSearch" type="search" placeholder="Search parents..." autocomplete="off" class="admin-recipient-search w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                </label>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-12 px-4 py-3"><input id="adminSelectAllParents" type="checkbox" aria-label="Select all parents" class="h-4 w-4 rounded border-gray-300 text-blue-600"></th>
                        <th class="px-4 py-3">Parent / Guardian</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Relationship</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($smsParents as $parent)
                        <tr class="hover:bg-blue-50">
                            <td class="px-4 py-3"><input type="checkbox" name="parent_ids[]" value="{{ $parent['id'] }}" class="admin-parent-checkbox h-4 w-4 rounded border-gray-300 text-blue-600"></td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $parent['full_name'] ?: 'Unnamed parent' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $parent['sms_phone'] }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $parent['relationship'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No active parents with phone numbers were found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 max-w-2xl">
            <label for="adminSmsMessage" class="mb-2 block text-sm font-medium text-gray-700">Reminder message</label>
            <textarea id="adminSmsMessage" name="message" required maxlength="320" rows="4" class="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-900 focus:border-blue-500 focus:ring-blue-500">{{ old('message', 'Reminder: Please be ready for your child’s scheduled pickup at 3:30 PM at Orion Christian Academy of the Philippines. Log in to your account and present the generated QR code before it expires in 10 minutes.') }}</textarea>
            <button type="submit" class="mt-4 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">Send reminder to selected parents</button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
    function setupRecipientFilter(searchId, checkboxSelector, selectAllId) {
        const search = document.getElementById(searchId);
        const selectAll = document.getElementById(selectAllId);
        const checkboxes = () => Array.from(document.querySelectorAll(checkboxSelector));

        const updateSelectAll = () => {
            const visibleCheckboxes = checkboxes().filter((checkbox) => !checkbox.closest('tr').hidden);
            selectAll.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.every((checkbox) => checkbox.checked);
            selectAll.indeterminate = visibleCheckboxes.some((checkbox) => checkbox.checked) && !selectAll.checked;
        };

        search?.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();

            checkboxes().forEach((checkbox) => {
                const row = checkbox.closest('tr');
                const matches = row.textContent.toLowerCase().includes(query);
                row.hidden = !matches;
                if (!matches) {
                    checkbox.checked = false;
                }
            });

            updateSelectAll();
        });

        selectAll?.addEventListener('change', () => {
            checkboxes().forEach((checkbox) => {
                if (!checkbox.closest('tr').hidden) {
                    checkbox.checked = selectAll.checked;
                }
            });
            updateSelectAll();
        });

        checkboxes().forEach((checkbox) => checkbox.addEventListener('change', updateSelectAll));
    }

    setupRecipientFilter('adminParentSearch', '.admin-parent-checkbox', 'adminSelectAllParents');
</script>
@endpush