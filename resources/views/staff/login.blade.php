<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Staff Login - Orion Christian Academy</title>
        @vite(['resources/css/app.css', 'resources/js/staff-auth.js'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
        <div class="max-w-sm mx-auto">
            <div class="rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden">
                <div class="p-5">
                <div class="mb-4 text-center">
                    <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-16 w-auto max-w-[180px] mx-auto object-contain">
                </div>
                <div class="mb-4 text-center">
                    <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                    <p class="mt-1 text-xs font-medium uppercase tracking-[0.35em] text-slate-500">Of The Philippines</p>
                </div>
                <div class="mb-1 flex justify-center">
                    <div class="w-14 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                        </svg>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-slate-950 text-center">Staff Login</h1>
                <p class="mt-1 text-sm text-slate-600 text-center">Scan and verify parent/guardian QR codes.</p>
                <div id="authFeedback" class="mb-4 text-sm" aria-live="polite"></div>

                <section id="loginSection" class="space-y-3">
                    <form id="loginForm" class="space-y-3">
                        <div class="space-y-2">
                            <label for="username" class="block font-medium text-slate-700">Username</label>
                            <div class="relative">
                                <svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m8-10a4 4 0 100-8 4 4 0 000 8z"/>
                                </svg>
                                <input id="username" name="username" type="text" autocomplete="username" required placeholder="Enter your username" class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="password" class="block font-medium text-slate-700">Password</label>
                            <div class="relative">
                                <svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password" class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-12 pr-12 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center">
                                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                                <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
                            </div>
                            <button id="showForgotPassword" type="button" class="text-sm font-semibold text-blue-600 transition hover:text-blue-700">Forgot password?</button>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 active:bg-blue-800">Log In</button>
                    </form>
                    <div class="border-t border-slate-200 pt-3">
                        <div class="flex items-center justify-between gap-3 text-xs text-slate-600">
                            <span>New staff member?</span>
                            <a href="/staff/register" class="font-semibold text-blue-600 hover:text-blue-700">Create an account</a>
                        </div>
                        <p class="text-xs text-slate-600">Not a staff member? <a href="/" class="font-semibold text-blue-600 hover:text-blue-700">Back to account selection</a></p>
                    </div>
                </section>

                <section id="resetSection" class="hidden space-y-3">
                    <form id="requestCodeForm" class="space-y-3">
                        <div class="space-y-2 text-sm">
                            <label for="phone_number" class="block font-medium text-slate-700">Account Number</label>
                            <input id="phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your number" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            <p class="text-xs text-slate-500">The verification code is sent only to the number registered to your staff account.</p>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Send Verification Code</button>
                    </form>

                    <form id="verifyCodeForm" class="hidden space-y-3">
                        <p id="registeredPhoneDisplay" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800" role="status"></p>
                        <div class="space-y-2 text-sm">
                            <label for="otp" class="block font-medium text-slate-700">6-Digit Verification Code</label>
                            <input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="Enter the code" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm tracking-[0.2em] outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Verify Code</button>
                    </form>

                    <form id="newPasswordForm" class="hidden space-y-3">
                        <div class="space-y-2 text-sm">
                            <label for="new_password" class="block font-medium text-slate-700">New Password</label>
                            <div class="relative">
                                <input id="new_password" name="password" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-12 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                <button type="button" data-password-toggle="new_password" aria-label="Show new password" aria-pressed="false" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password_confirmation" class="block font-medium text-slate-700">Confirm New Password</label>
                            <div class="relative">
                                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-12 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                <button type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation" aria-pressed="false" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064-2.943-9.542-7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Change Password</button>
                    </form>

                    <button id="backToLogin" type="button" class="w-full text-blue-600 hover:text-blue-700 font-semibold transition">Back to login</button>
                </section>

                <div class="mt-4 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3.5">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <p class="text-xs text-blue-800">Scan parent/guardian QR codes at the school gate to verify pickups.</p>
                </div>
            </div>
        </div>

        <script>
            const loginForm = document.getElementById('loginForm');
            const requestCodeForm = document.getElementById('requestCodeForm');
            const verifyCodeForm = document.getElementById('verifyCodeForm');
            const newPasswordForm = document.getElementById('newPasswordForm');
            const loginSection = document.getElementById('loginSection');
            const resetSection = document.getElementById('resetSection');
            const authFeedback = document.getElementById('authFeedback');
            let resetFlowId = null;
            let resetToken = null;

            const showFeedback = (message, type = 'info') => {
                const styles = {
                    info: 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700',
                    success: 'rounded-2xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700',
                    error: 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700',
                };
                authFeedback.textContent = message;
                authFeedback.className = styles[type];
            };

            if (window.location.hash === '#forgot-password') {
                loginSection.classList.add('hidden');
                resetSection.classList.remove('hidden');
            }

            document.getElementById('showForgotPassword').addEventListener('click', function() {
                loginSection.classList.add('hidden');
                resetSection.classList.remove('hidden');
                showFeedback('Enter the mobile number registered to your staff account.');
            });

            document.getElementById('backToLogin').addEventListener('click', function() {
                resetSection.classList.add('hidden');
                loginSection.classList.remove('hidden');
                requestCodeForm.reset();
                verifyCodeForm.reset();
                newPasswordForm.reset();
                requestCodeForm.classList.remove('hidden');
                verifyCodeForm.classList.add('hidden');
                newPasswordForm.classList.add('hidden');
                resetFlowId = null;
                resetToken = null;
                authFeedback.textContent = '';
                authFeedback.className = 'mb-4 text-sm';
            });

            requestCodeForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                showFeedback('Sending verification code by SMS...');
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch('/staff/password-reset/request', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ phone_number: String(new FormData(requestCodeForm).get('phone_number')) }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        showFeedback(payload.message || 'Unable to send the verification code.', 'error');
                        return;
                    }

                    resetFlowId = payload.flow_id;
                    document.getElementById('registeredPhoneDisplay').textContent = `Verification code sent to ${payload.masked_phone}`;
                    requestCodeForm.classList.add('hidden');
                    verifyCodeForm.classList.remove('hidden');
                    showFeedback(payload.message, 'success');
                } catch (error) {
                    console.error('Staff SMS reset request failed:', error);
                    showFeedback('Unable to send the verification code. Please try again.', 'error');
                }
            });

            verifyCodeForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                showFeedback('Verifying code...');
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch('/staff/password-reset/verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({
                            flow_id: resetFlowId,
                            otp: String(new FormData(verifyCodeForm).get('otp')),
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        showFeedback(payload.message || 'The code could not be verified.', 'error');
                        return;
                    }

                    resetToken = payload.reset_token;
                    verifyCodeForm.classList.add('hidden');
                    newPasswordForm.classList.remove('hidden');
                    showFeedback(payload.message, 'success');
                } catch (error) {
                    console.error('Staff OTP verification failed:', error);
                    showFeedback('The code could not be verified. Please try again.', 'error');
                }
            });

            newPasswordForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                showFeedback('Updating password...');
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const formData = new FormData(newPasswordForm);
                    const response = await fetch('/staff/password-reset/password', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({
                            flow_id: resetFlowId,
                            reset_token: resetToken,
                            password: String(formData.get('password')),
                            password_confirmation: String(formData.get('password_confirmation')),
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        showFeedback(payload.message || 'Unable to update the password.', 'error');
                        return;
                    }

                    resetFlowId = null;
                    resetToken = null;
                    showFeedback(payload.message, 'success');
                    window.setTimeout(() => window.location.assign('/staff/login'), 900);
                } catch (error) {
                    console.error('Staff password update failed:', error);
                    showFeedback('Unable to update the password. Please try again.', 'error');
                }
            });
        </script>
    </body>
</html>