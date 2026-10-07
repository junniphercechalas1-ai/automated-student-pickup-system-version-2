@extends('staff.layout')

@section('title', 'Notifications')

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-amber-500">Notifications</p>
        <h2 class="mt-2 text-3xl font-semibold text-slate-950">Notifications</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">Manage SMS notifications and alerts.</p>
    </header>

    <section class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Staff accounts</p>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Notify staff members</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Send an approval notification to selected staff members.</p>
            </div>
            <span class="text-sm text-slate-500">{{ count($smsStaff) }} staff member(s) with phone numbers</span>
        </div>

        <form method="POST" action="{{ route('staff.notifications.send-to-staff') }}" class="mt-6" onsubmit="return confirm('Send this account notification to the selected staff members?');">
            @csrf
            <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"><input id="selectAllStaff" type="checkbox" aria-label="Select all staff" class="h-4 w-4 rounded border-slate-300 text-amber-500"></th>
                            <th class="px-4 py-3">Staff member</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Email</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($smsStaff as $staff)
                            <tr class="hover:bg-amber-50/40">
                                <td class="px-4 py-3"><input type="checkbox" name="staff_ids[]" value="{{ $staff['id'] }}" class="staff-checkbox h-4 w-4 rounded border-slate-300 text-amber-500"></td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $staff['full_name'] ?: 'Unnamed staff member' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $staff['sms_phone'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $staff['email'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No active staff members with phone numbers were found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5 grid gap-4 sm:max-w-2xl">
                <label class="grid gap-2 text-sm font-medium text-slate-700">
                    Approval notification
                    <textarea name="message" required maxlength="320" rows="3" class="rounded-2xl border border-slate-300 px-4 py-3 text-slate-950 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ old('message', 'Your Orion Christian Academy staff account has been successfully approved by the administrator. You may now sign in.') }}</textarea>
                </label>
                <button type="submit" class="w-fit rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-amber-400">Send account notification</button>
            </div>
        </form>
    </section>

    <section class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">SMS test</p>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Send a test notification</h3>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Use an international phone number format. The current driver is <strong>{{ $smsDriver }}</strong>.</p>
            </div>
        </div>

        @if (session('sms_success'))
            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('sms_success') }}</div>
        @endif
        @error('sms')
            <div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror
        @if (session('sms_error'))
            <div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-800" role="alert">{{ session('sms_error') }}</div>
        @endif
        @error('parent_ids')
            <div class="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('staff.notifications.test-sms') }}" class="mt-6 grid gap-5 sm:max-w-xl">
            @csrf
            <label class="grid gap-2 text-sm font-medium text-slate-700">
                Recipient phone
                <input name="recipient_phone" value="{{ old('recipient_phone') }}" required maxlength="30" placeholder="+639171234567" class="rounded-2xl border border-slate-300 px-4 py-3 text-slate-950 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">
                @error('recipient_phone')<span class="text-xs font-normal text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-slate-700">
                Message
                <textarea name="message" required maxlength="320" rows="4" class="rounded-2xl border border-slate-300 px-4 py-3 text-slate-950 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ old('message', 'This is a test SMS from the Orion Christian Academy admin portal.') }}</textarea>
                @error('message')<span class="text-xs font-normal text-red-600">{{ $message }}</span>@enderror
            </label>
            <button type="submit" class="w-fit rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-amber-400">Send test SMS</button>
        </form>
    </section>

    <section class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-amber-500">Parent reminders</p>
                <h3 class="mt-2 text-xl font-semibold text-slate-950">Send to selected parents</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Choose recipients, write one reminder, then send it through the connected GSM module.</p>
            </div>
            <span class="text-sm text-slate-500">{{ count($smsParents) }} SMS-capable parent(s)</span>
        </div>

        <form method="POST" action="{{ route('staff.notifications.send-to-parents') }}" class="mt-6" onsubmit="return confirm('Send this SMS reminder to all selected parents?');">
            @csrf
            <div class="overflow-x-auto rounded-2xl ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"><input id="selectAllParents" type="checkbox" aria-label="Select all parents" class="h-4 w-4 rounded border-slate-300 text-amber-500"></th>
                            <th class="px-4 py-3">Parent / Guardian</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Relationship</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($smsParents as $parent)
                            <tr class="hover:bg-amber-50/40">
                                <td class="px-4 py-3"><input type="checkbox" name="parent_ids[]" value="{{ $parent['id'] }}" class="parent-checkbox h-4 w-4 rounded border-slate-300 text-amber-500"></td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $parent['full_name'] ?: 'Unnamed parent' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $parent['sms_phone'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $parent['relationship'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No active parents with phone numbers were found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 grid gap-4 sm:max-w-2xl">
                <label class="grid gap-2 text-sm font-medium text-slate-700">
                    Reminder message
                    <textarea name="message" required maxlength="320" rows="3" class="rounded-2xl border border-slate-300 px-4 py-3 text-slate-950 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200">{{ old('message', 'Reminder: Please be ready for your child’s scheduled pickup at 3:30 PM at Orion Christian Academy of the Philippines. Log in to your account and present the generated QR code before it expires in 10 minutes.') }}</textarea>
                </label>
                <button type="submit" class="w-fit rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-amber-400">Send reminder to selected parents</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        document.getElementById('selectAllParents')?.addEventListener('change', (event) => {
            document.querySelectorAll('.parent-checkbox').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });
        document.getElementById('selectAllStaff')?.addEventListener('change', (event) => {
            document.querySelectorAll('.staff-checkbox').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });
    </script>
@endpush
