<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Parent/Guardian Login - Orion Christian Academy</title>
        @vite(['resources/css/app.css', 'resources/js/parent-auth.js'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
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
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Title -->
                        <h1 class="text-2xl font-bold text-slate-950 text-center">Parent / Guardian Login</h1>
                        <p class="mt-1 text-sm text-slate-600 text-center">Access your child's QR code to gate.</p>

                        <div id="authFeedback" class="mb-4 text-sm"></div>

                        <!-- Login Section -->
                        <div id="loginSection" class="space-y-3">
                            <form id="loginForm" class="space-y-3">
                                <!-- Mobile Number Field -->
                                <div class="space-y-2">
                                    <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                                    <div class="relative">
                                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zm5 16h.01"/>
                                        </svg>
                                        <input id="username" name="username" type="text" autocomplete="username" required placeholder="Enter your username" class="w-full pl-12 pr-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
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
                                <button id="showUsernameSetup" type="button" class="text-xs text-blue-600 hover:text-blue-700 font-semibold">Already registered? Set up your username</button>

                                <!-- Login Button -->
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 active:bg-blue-800">Log In</button>
                            </form>
                            <!-- Navigation -->
                            <div class="border-t border-slate-200 pt-3">
                                <div class="flex items-center justify-between gap-3 text-xs text-slate-600">
                                    <span>New parent or guardian?</span>
                                    <a href="/parent/register" class="font-semibold text-blue-600 hover:text-blue-700">Create an account</a>
                                </div>
                                <p class="text-xs text-slate-600">Not a parent? <a href="/" class="font-semibold text-blue-600 hover:text-blue-700">Back to account selection</a></p>
                            </div>
                        </div>

                        <div id="usernameSetupSection" class="hidden space-y-3">
                            <p class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">For accounts registered before username sign-in, verify your registered number by SMS to choose a username. Your existing password stays the same.</p>
                            <form id="usernameSetupForm" class="space-y-3">
                                <div class="space-y-2">
                                    <label for="setup_phone_number" class="block text-sm font-medium text-slate-700">Registered Mobile Number</label>
                                    <input id="setup_phone_number" name="phone_number" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your registered number" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                    <button type="button" data-phone-otp-send class="text-sm font-semibold text-blue-600 hover:text-blue-700">Send verification code</button>
                                    <div data-phone-otp-section class="hidden space-y-2">
                                        <label for="setup_phone_otp" class="block text-sm font-medium text-slate-700">SMS verification code</label>
                                        <input id="setup_phone_otp" data-phone-otp-input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm tracking-[0.2em] outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                        <button type="button" data-phone-otp-verify class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Verify number</button>
                                    </div>
                                    <p data-phone-otp-status class="text-xs text-slate-500" role="status"></p>
                                </div>
                                <div class="space-y-2">
                                    <label for="setup_username" class="block text-sm font-medium text-slate-700">Choose a Username</label>
                                    <input id="setup_username" name="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,29}" required placeholder="3–30 letters, numbers, dots, underscores, or hyphens" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">Set Username</button>
                            </form>
                            <button id="backFromUsernameSetup" type="button" class="w-full text-sm font-semibold text-blue-600">Back to login</button>
                        </div>

                        <!-- Password Reset Section -->
                        <div id="resetSection" class="hidden space-y-3">
                            <form id="resetForm" class="space-y-3">
                                <div class="space-y-2">
                                    <label for="phone_reset" class="block text-sm font-medium text-slate-700">Account Number</label>
                                    <input id="phone_reset" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your number" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                    <p class="text-xs text-slate-500">The code is sent only if this matches your registered number.</p>
                                </div>
                                <button id="resetSubmitButton" type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Send Verification Code</button>
                            </form>
                            <form id="phoneResetForm" class="hidden space-y-3">
                                <p id="registeredPhoneDisplay" class="hidden rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800" role="status"></p>
                                <div class="space-y-2">
                                    <label for="reset_code" class="block text-sm font-medium text-slate-700">SMS Verification Code</label>
                                    <input id="reset_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm tracking-[0.2em] outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <div class="space-y-2">
                                    <label for="reset_new_password" class="block text-sm font-medium text-slate-700">New Password</label>
                                    <input id="reset_new_password" name="password" type="password" minlength="8" required placeholder="At least 8 characters" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <div class="space-y-2">
                                    <label for="reset_password_confirmation" class="block text-sm font-medium text-slate-700">Confirm New Password</label>
                                    <input id="reset_password_confirmation" name="password_confirmation" type="password" minlength="8" required placeholder="Enter the new password again" class="w-full px-4 py-3 rounded-2xl border border-slate-200 bg-slate-50 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                </div>
                                <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Verify Code &amp; Change Password</button>
                            </form>
                            <button id="backToLogin" type="button" class="w-full text-blue-600 hover:text-blue-700 font-semibold transition">Back to login</button>
                        </div>

                        <!-- Information Box -->
                        <div class="mt-4 rounded-lg bg-blue-50 border border-blue-200 p-3.5">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                </svg>
                                <p class="text-xs text-blue-800">Use your QR code at the school gate for a safe and quick entry.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Initialize form handling
            const loginForm = document.getElementById('loginForm');
            const resetForm = document.getElementById('resetForm');
            const phoneResetForm = document.getElementById('phoneResetForm');
            const registeredPhoneDisplay = document.getElementById('registeredPhoneDisplay');
            const authFeedback = document.getElementById('authFeedback');
            const loginSection = document.getElementById('loginSection');
            const resetSection = document.getElementById('resetSection');
            let phoneResetFlowId = null;

            // Password visibility toggle
            document.getElementById('togglePassword').addEventListener('click', function(e) {
                e.preventDefault();
                const passwordInput = document.getElementById('password');
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
            });

            // Forgot password button
            document.getElementById('showForgotPassword').addEventListener('click', function(e) {
                e.preventDefault();
                authFeedback.textContent = '';
                authFeedback.className = 'mb-4 text-sm';
                loginSection.classList.add('hidden');
                resetSection.classList.remove('hidden');
            });

            // Back to login button
            document.getElementById('backToLogin').addEventListener('click', function(e) {
                e.preventDefault();
                resetSection.classList.add('hidden');
                resetForm.classList.remove('hidden');
                phoneResetForm.classList.add('hidden');
                phoneResetForm.reset();
                phoneResetFlowId = null;
                registeredPhoneDisplay.classList.add('hidden');
                registeredPhoneDisplay.textContent = '';
                authFeedback.textContent = '';
                authFeedback.className = 'mb-4 text-sm';
                loginSection.classList.remove('hidden');
            });

            // Form submission
            if (loginForm) {
                loginForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(loginForm);
                    const username = String(formData.get('username') || '').trim();
                    const password = String(formData.get('password'));
                    const remember = formData.get('remember') === 'on';

                    if (!authFeedback) return;
                    authFeedback.textContent = 'Signing in...';
                    authFeedback.className = 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700';

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const loginResponse = await fetch('/supabase/username-login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                            },
                            body: JSON.stringify({ username, password, account_type: 'parent' }),
                        });
                        const loginPayload = await loginResponse.json().catch(() => ({}));
                        if (!loginResponse.ok) {
                            authFeedback.textContent = loginPayload.message || 'Unable to sign in.';
                            authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                            return;
                        }

                        const accessToken = loginPayload.access_token;
                        if (!accessToken) {
                            authFeedback.textContent = 'Sign in succeeded but token missing.';
                            authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                            return;
                        }

                        const sessionResp = await fetch('/supabase/session', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                            },
                            body: JSON.stringify({ access_token: accessToken }),
                        });

                        if (!sessionResp.ok) {
                            const payload = await sessionResp.json().catch(() => ({}));
                            authFeedback.textContent = payload.message || 'Unable to create session.';
                            authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                            return;
                        }

                        if (remember) {
                            localStorage.setItem('parentRememberMe', 'true');
                            localStorage.setItem('parentUsername', username);
                        }

                        window.location.href = '/picker';
                    } catch (err) {
                        console.error('Login error:', err);
                        authFeedback.textContent = 'An unexpected error occurred.';
                        authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                    }
                });
            }

            // Password reset form
            if (resetForm) {
                resetForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(resetForm);

                    if (!authFeedback) return;
                    authFeedback.textContent = 'Sending verification code...';
                    authFeedback.className = 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700';

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const response = await fetch('/supabase/reset-password', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                            },
                            body: JSON.stringify({
                                method: 'sms',
                                account_type: 'parent',
                                phone: String(formData.get('phone')),
                            }),
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            authFeedback.textContent = payload.message || 'Unable to send reset instructions.';
                            authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                            return;
                        }

                        phoneResetFlowId = payload.flow_id;
                        if (payload.masked_phone) {
                            resetForm.classList.add('hidden');
                            phoneResetForm.classList.remove('hidden');
                            registeredPhoneDisplay.textContent = `Code sent to ${payload.masked_phone}`;
                            registeredPhoneDisplay.classList.remove('hidden');
                        }
                        authFeedback.textContent = payload.message || 'If your account has a registered number, the code will be sent there.';
                        authFeedback.className = 'rounded-2xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700';
                    } catch (err) {
                        console.error('Reset error:', err);
                        authFeedback.textContent = 'An unexpected error occurred.';
                        authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                    }
                });
            }

            if (phoneResetForm) {
                phoneResetForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(phoneResetForm);
                    authFeedback.textContent = 'Verifying code and updating password...';
                    authFeedback.className = 'rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700';

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const response = await fetch('/supabase/reset-password/phone', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || '',
                            },
                            body: JSON.stringify({
                                flow_id: phoneResetFlowId,
                                code: String(formData.get('code')),
                                password: String(formData.get('password')),
                                password_confirmation: String(formData.get('password_confirmation')),
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            authFeedback.textContent = payload.message || 'Unable to change the password.';
                            authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                            return;
                        }

                        phoneResetForm.classList.add('hidden');
                        phoneResetForm.reset();
                        phoneResetFlowId = null;
                        authFeedback.textContent = payload.message || 'Your password has been changed. You can now sign in.';
                        authFeedback.className = 'rounded-2xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700';
                    } catch (err) {
                        console.error('Phone reset error:', err);
                        authFeedback.textContent = 'An unexpected error occurred.';
                        authFeedback.className = 'rounded-2xl bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700';
                    }
                });
            }
        </script>
    </body>
</html>
