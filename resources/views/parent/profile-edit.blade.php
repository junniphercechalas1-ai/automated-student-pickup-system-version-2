@extends('parent.layout')

@section('title', 'Edit Profile')

@section('content')
    <div class="parent-profile-hero">
        <div class="parent-profile-hero-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
        </div>
        <div class="parent-profile-hero-content">
            <h1 class="parent-profile-hero-title">Edit Profile</h1>
            <p class="parent-profile-hero-subtitle">Update your profile information and account details.</p>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-[32px] bg-emerald-50 p-6 text-sm text-emerald-900 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-[32px] bg-rose-50 p-6 text-sm text-rose-900 ring-1 ring-rose-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid gap-6 lg:grid-cols-3">
        <!-- Profile Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Personal Information Card -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-blue-600">
                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-blue-600">Personal Information</h3>
                </div>
                
                <form method="POST" action="{{ route('parent.profile.update') }}" class="mt-6 space-y-6">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-slate-950">Full Name</label>
                        <input name="full_name" type="text" value="{{ old('full_name', $fullName ?? 'John Doe') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 placeholder-slate-400 transition-colors focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-200" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-950">Contact Email <span class="font-normal text-slate-500">(optional; not used to sign in)</span></label>
                        <input name="email" type="email" value="{{ old('email', $email ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 placeholder-slate-400 transition-colors focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-200" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-950">Mobile Number</label>
                        <input name="mobile_number" type="tel" inputmode="tel" placeholder="+63 917 123 4567" value="{{ old('mobile_number', $mobile) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 placeholder-slate-400 transition-colors focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-200" />
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-950">Relationship to Student</label>
                        @php $relationshipValue = old('relationship', $relationship); @endphp
                        <select name="relationship" class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-950 transition-colors focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-200">
                            <option value="Father" {{ $relationshipValue === 'Father' ? 'selected' : '' }}>Father</option>
                            <option value="Mother" {{ $relationshipValue === 'Mother' ? 'selected' : '' }}>Mother</option>
                            <option value="Guardian" {{ $relationshipValue === 'Guardian' ? 'selected' : '' }}>Guardian</option>
                            <option value="Grandparent" {{ $relationshipValue === 'Grandparent' ? 'selected' : '' }}>Grandparent</option>
                            <option value="Uncle/Aunt" {{ $relationshipValue === 'Uncle/Aunt' ? 'selected' : '' }}>Uncle/Aunt</option>
                            <option value="Other" {{ $relationshipValue === 'Other' ? 'selected' : '' }}>Other</option>
                            @if (! in_array($relationshipValue, ['Father', 'Mother', 'Guardian', 'Grandparent', 'Uncle/Aunt', 'Other'], true))
                                <option value="{{ $relationshipValue }}" selected>{{ $relationshipValue }}</option>
                            @endif
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button type="submit" class="rounded-3xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-700">Save Changes</button>
                        <a href="/parent/profile" class="rounded-3xl border-2 border-slate-200 bg-white px-6 py-3 text-center text-sm font-semibold text-slate-950 transition-colors hover:bg-slate-50">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-4">
            <!-- Account Status -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10zm0-2a8 8 0 100-16 8 8 0 000 16zm0-9a1 1 0 011 1v4a1 1 0 11-2 0v-4a1 1 0 011-1zm0-4a1 1 0 110 2 1 1 0 010-2z"></path>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Account Status</p>
                </div>
                <div class="space-y-4">
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Account Type</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">Parent/Guardian</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Member Since</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">May 15, 2026</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Last Login</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">Today, 8:30 AM</p>
                    </div>
                </div>
            </div>

            <!-- Linked Student -->
            <div class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Linked Student</p>
                </div>
                <div class="space-y-4">
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Student Name</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">{{ $student['name'] }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.05em] text-slate-500">Grade / Section</p>
                        <p class="mt-2 text-base font-semibold text-slate-950">{{ $student['class'] }}</p>
                    </div>
                    <a href="{{ route('parent.student') }}" class="mt-2 block w-full rounded-2xl bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white transition-colors hover:bg-blue-700">View Student Info</a>
                </div>
            </div>

            <!-- Info Box -->
            <div class="rounded-[32px] bg-blue-50 p-6 shadow-sm ring-1 ring-blue-200">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 9v5"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </div>
                    <p class="text-sm uppercase tracking-[0.3em] text-blue-600 font-semibold">Tip</p>
                </div>
                <p class="text-sm text-blue-900">Make sure to review your information before saving changes. These details help us keep your account secure.</p>
            </div>
        </aside>
    </section>
@endsection
