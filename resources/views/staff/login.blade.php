<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Staff Login - Orion Christian Academy</title>
        @vite(['resources/css/app.css', 'resources/js/staff-auth.js'])
    </head>
    <body class="min-h-dvh text-slate-950 flex items-start justify-center px-3 py-4 sm:items-center sm:px-4 sm:py-8" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: scroll; background-position: center;">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="mb-3 text-center sm:mb-4">
                    <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-14 w-auto max-w-[160px] mx-auto object-contain sm:h-16 sm:max-w-[180px]">
                </div>
                <div class="mb-3 text-center sm:mb-4">
                    <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                    <p class="mt-1 text-[0.65rem] font-medium uppercase tracking-[0.25em] text-slate-500 sm:text-xs sm:tracking-[0.35em]">Of The Philippines</p>
                </div>
                <div class="mb-1 flex justify-center">
                    <div class="w-14 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                        </svg>
                    </div>
                </div>

                <h1 class="text-xl font-bold text-slate-950 text-center sm:text-2xl">Staff Login</h1>
                <p class="mt-1 text-sm text-slate-600 text-center">Scan and verify parent/guardian QR codes.</p>
                <div id="authFeedback" class="mb-3 text-sm sm:mb-4" aria-live="polite"></div>

                <section id="loginSection" class="space-y-4">
                    <form id="loginForm" class="space-y-4">
                        <div class="space-y-2 text-sm">
                            <label for="username" class="block font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" autocomplete="username" required placeholder="Enter your username" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100 sm:text-sm" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password" class="block font-medium text-slate-700">Password</label>
                            <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100 sm:text-sm" />
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <label class="flex min-h-11 items-center gap-2 text-slate-600"><input name="remember" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-blue-600">Remember me</label>
                            <button id="showForgotPassword" type="button" class="min-h-11 font-semibold text-blue-600 hover:text-blue-700">Forgot password?</button>
                        </div>
                        <button id="showUsernameSetup" type="button" class="min-h-11 text-left text-sm font-semibold text-blue-600 hover:text-blue-700">Already registered? Set up your username</button>
                        <button type="submit" class="min-h-12 w-full rounded-lg bg-blue-600 px-6 py-3 text-base font-semibold text-white transition hover:bg-blue-700 sm:text-sm">Login</button>
                    </form>
                    <div class="flex min-h-11 items-center text-sm text-slate-500">
                        <a href="/staff/register" class="font-semibold text-blue-600 hover:text-blue-700">New staff account?</a>
                    </div>
                    <div class="border-t border-slate-200 pt-3 sm:pt-4">
                        <p class="text-xs text-slate-600 mb-3">Are you a parent/guardian?</p>
                        <a href="/login" class="inline-flex min-h-11 items-center text-sm font-semibold text-blue-600 hover:text-blue-700">Go to parent login</a>
                    </div>
                </section>

                <section id="usernameSetupSection" class="hidden space-y-4">
                    <p class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-700">For accounts registered before username sign-in, verify your registered number by SMS to choose a username. Your existing password stays the same.</p>
                    <form id="usernameSetupForm" class="space-y-4">
                        <div class="space-y-2 text-sm">
                            <label for="setup_phone_number" class="block font-medium text-slate-700">Registered Mobile Number</label>
                            <input id="setup_phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your registered number" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 sm:text-sm" />
                            <button type="button" data-phone-otp-send class="min-h-11 text-sm font-semibold text-blue-600 hover:text-blue-700">Send verification code</button>
                            <div data-phone-otp-section class="hidden space-y-2">
                                <label for="setup_phone_otp" class="block font-medium text-slate-700">SMS verification code</label>
                                <input id="setup_phone_otp" data-phone-otp-input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base tracking-[0.2em] outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 sm:text-sm" />
                                <button type="button" data-phone-otp-verify class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Verify number</button>
                            </div>
                            <p data-phone-otp-status class="text-xs text-slate-500" role="status"></p>
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="setup_username" class="block font-medium text-slate-700">Choose a Username</label>
                            <input id="setup_username" name="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,29}" required placeholder="Choose a username" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 sm:text-sm" />
                        </div>
                        <button type="submit" class="min-h-12 w-full rounded-lg bg-blue-600 px-6 py-3 text-base font-semibold text-white hover:bg-blue-700 sm:text-sm">Set Username</button>
                    </form>
                    <button id="backFromUsernameSetup" type="button" class="min-h-11 w-full text-sm font-semibold text-blue-600">Back to login</button>
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
                            <input id="new_password" name="password" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password_confirmation" class="block font-medium text-slate-700">Confirm New Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Change Password</button>
                    </form>

                    <button id="backToLogin" type="button" class="w-full text-blue-600 hover:text-blue-700 font-semibold transition">Back to login</button>
                </section>

                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3.5">
                    <p class="text-xs text-blue-800">Scan QR codes and manage entry verification at the gate.</p>
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