<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Administrator Login - Orion Christian Academy</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body style="background-image: url('/images/bg.png'); background-size: cover; background-position: center; background-attachment: fixed;" class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4">  
        <div class="max-w-sm mx-auto">
            <!-- White Content Card -->
            <div class="rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden">
                <div class="grid grid-cols-1">
                    <!-- Content -->
                    <div class="p-5">
                        <!-- Logo -->
                        <div class="mb-4 text-center">
                            <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-16 w-auto max-w-[180px] mx-auto object-contain">
                        </div>
                        <!-- School Branding -->
                        <div class="mb-4 text-center">
                            <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                            <p class="mt-1 text-xs font-medium uppercase tracking-[0.35em] text-slate-500">Of The Philippines</p>
                        </div>
                        <!-- Role Icon -->
                        <div class="mb-1 flex justify-center">
                            <div class="w-14 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Title -->
                        <h1 class="text-2xl font-bold text-slate-950 text-center">Administrator Login</h1>
                        <p class="mt-1 text-sm text-slate-600 text-center">Manage students, parents, staff and system.</p>

                        <div id="authFeedback" class="mb-4 text-sm">
                            @if ($errors->any())
                                <div class="rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700" role="alert">
                                    {{ $errors->first() }}
                                </div>
                            @elseif (session('status'))
                                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700" role="status">
                                    {{ session('status') }}
                                </div>
                            @endif
                        </div>

                        <!-- Login Section -->
                        <div id="loginSection" class="space-y-3">
                            <form id="loginForm" action="{{ route('admin.login.post') }}" method="POST" class="space-y-3">
                                @csrf
                                <!-- Mobile Number Field -->
                                <div class="space-y-2">
                                    <label for="phone" class="block text-sm font-medium text-slate-700">Mobile Number</label>
                                    <div class="relative">
                                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zm5 16h.01"/>
                                        </svg>
                                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required value="{{ old('phone') }}" placeholder="Enter your mobile number" class="w-full pl-12 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                    </div>
                                </div>

                                <!-- Password Field -->
                                <div class="space-y-2">
                                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                                    <div class="relative">
                                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <input id="password" name="password" type="password" required placeholder="Enter your password" class="w-full pl-12 pr-12 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                        <button type="button" id="togglePassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Remember Me + Forgot Password -->
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center">
                                    <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                    <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
                                </div>
                                <button id="showForgotPassword" type="button" class="text-sm text-blue-600 hover:text-blue-700 font-semibold transition">Forgot password?</button>
                                </div>

                                <!-- Login Button -->
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 active:bg-blue-800">Log In</button>
                            </form>

                            <!-- Navigation -->
                            <div class="border-t border-slate-200 pt-3">
                                <p class="text-xs text-slate-600">Not an administrator? <a href="/" class="font-semibold text-blue-600 hover:text-blue-700">Back to account selection</a></p>
                            </div>
                        </div>

                        <!-- Password Reset Section -->
                        <div id="resetSection" class="hidden space-y-3">
                            <form id="resetForm" class="space-y-3">
                                <div class="space-y-2">
                                    <label for="reset_phone" class="block text-sm font-medium text-slate-700">Mobile Number</label>
                                    <input id="reset_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your registered mobile number" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Send SMS Code</button>
                            </form>
                            <form id="resetCodeForm" class="hidden space-y-3">
                                <div class="space-y-2">
                                    <label for="reset_code" class="block text-sm font-medium text-slate-700">SMS Verification Code</label>
                                    <input id="reset_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm tracking-[0.2em] outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <div class="space-y-2">
                                    <label for="reset_password" class="block text-sm font-medium text-slate-700">New Password</label>
                                    <input id="reset_password" name="password" type="password" minlength="8" required placeholder="At least 8 characters" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <div class="space-y-2">
                                    <label for="reset_password_confirmation" class="block text-sm font-medium text-slate-700">Confirm New Password</label>
                                    <input id="reset_password_confirmation" name="password_confirmation" type="password" minlength="8" required placeholder="Enter the new password again" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Verify Code &amp; Reset Password</button>
                            </form>
                            <button id="backToLogin" type="button" class="w-full text-blue-600 hover:text-blue-700 font-semibold transition">Back to login</button>
                        </div>

                        <!-- Information Box -->
                        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-200 p-3.5">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                                <p class="text-xs text-blue-800">Manage system settings, users, and all school data securely.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('togglePassword').addEventListener('click', function(e) {
                e.preventDefault();
                const passwordInput = document.getElementById('password');
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
            });

            document.getElementById('showForgotPassword').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('loginSection').classList.add('hidden');
                document.getElementById('resetSection').classList.remove('hidden');
            });

            document.getElementById('backToLogin').addEventListener('click', function(e) {
                e.preventDefault();
                adminResetFlowId = null;
                document.getElementById('resetForm').reset();
                document.getElementById('resetCodeForm').reset();
                document.getElementById('resetCodeForm').classList.add('hidden');
                document.getElementById('resetForm').classList.remove('hidden');
                document.getElementById('resetSection').classList.add('hidden');
                document.getElementById('loginSection').classList.remove('hidden');
            });

            let adminResetFlowId = null;
            document.getElementById('resetForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const phone = new FormData(e.currentTarget).get('phone');
                const authFeedback = document.getElementById('authFeedback');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                authFeedback.textContent = 'Sending SMS verification code...';
                authFeedback.className = 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700';

                try {
                    const response = await fetch('{{ route("admin.password-reset.request") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            phone: String(phone),
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to send SMS verification code.');
                    }

                    adminResetFlowId = payload.flow_id;
                    document.getElementById('resetForm').classList.add('hidden');
                    document.getElementById('resetCodeForm').classList.remove('hidden');
                    authFeedback.textContent = payload.message || 'If this number belongs to an active administrator, a verification code will be sent by SMS.';
                } catch (error) {
                    authFeedback.textContent = error.message || 'Unable to send SMS verification code.';
                    authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                }
            });

            document.getElementById('resetCodeForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(e.currentTarget);
                const authFeedback = document.getElementById('authFeedback');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                authFeedback.textContent = 'Verifying code and updating password...';
                authFeedback.className = 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700';

                try {
                    const response = await fetch('{{ route("admin.password-reset.complete") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            flow_id: adminResetFlowId,
                            code: String(formData.get('code')),
                            password: String(formData.get('password')),
                            password_confirmation: String(formData.get('password_confirmation')),
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to reset the password.');
                    }

                    authFeedback.textContent = payload.message || 'Password reset successfully. You can now sign in.';
                    authFeedback.className = 'rounded-2xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700';
                    document.getElementById('resetCodeForm').reset();
                    document.getElementById('resetCodeForm').classList.add('hidden');
                    document.getElementById('resetForm').classList.remove('hidden');
                    document.getElementById('resetForm').reset();
                    adminResetFlowId = null;
                } catch (error) {
                    authFeedback.textContent = error.message || 'Unable to reset the password.';
                    authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                }
            });
        </script>
    </body>
</html>
