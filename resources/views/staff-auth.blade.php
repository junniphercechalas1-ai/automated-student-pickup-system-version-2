<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Staff Login - Pickup Verification</title>
        @vite(['resources/css/app.css', 'resources/js/staff-auth.js'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden">
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
                        <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                        </svg>
                    </div>
                </div>

                <h1 id="authTitle" class="text-2xl font-bold text-slate-950 text-center">Staff Access</h1>
                <p class="mt-1 text-sm text-slate-600 text-center">Authorized staff only. Log in with your school credentials.</p>

                <div id="authFeedback" class="mb-4 text-sm"></div>

                <div id="loginSection" class="space-y-3">
                    <form id="loginForm" class="space-y-3">
                        <div class="space-y-2 text-sm">
                            <label for="username" class="block font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" autocomplete="username" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password" class="block font-medium text-slate-700">Password</label>
                            <input id="password" name="password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Login</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showReset" class="font-semibold text-blue-600 hover:text-blue-700">Forgot password?</button>
                        <button id="showRegister" class="font-semibold text-blue-600 hover:text-blue-700">New staff account?</button>
                    </div>
                    <div class="pt-4 border-t border-slate-200">
                        <p class="text-xs text-slate-600 mb-3">Are you a parent/guardian?</p>
                        <a href="/login" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Go to parent login</a>
                    </div>
                </div>

                <div id="registerSection" class="hidden space-y-3">
                    <form id="registerForm" class="space-y-3">
                        <div class="rounded-2xl bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700">
                            <p class="font-semibold">Staff Registration</p>
                            <p class="text-xs mt-2">Submit your details and wait for an administrator to approve your account before signing in.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2 text-sm sm:col-span-2">
                                <label for="username_register" class="block font-medium text-slate-700">Username</label>
                                <input id="username_register" name="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,29}" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="Choose a username" />
                                <p class="text-xs text-slate-500">Use 3–30 characters. Your username will be used to sign in.</p>
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="first_name" class="block font-medium text-slate-700">First Name</label>
                                <input id="first_name" name="first_name" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="John" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="middle_name" class="block font-medium text-slate-700">Middle Name <span class="font-normal text-slate-400">(optional)</span></label>
                                <input id="middle_name" name="middle_name" type="text" class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="Michael" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="last_name" class="block font-medium text-slate-700">Last Name</label>
                                <input id="last_name" name="last_name" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="Smith" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="phone_number" class="block font-medium text-slate-700">Mobile Number</label>
                                <input id="phone_number" name="phone_number" type="tel" inputmode="tel" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="+63 917 123 4567" />
                                <button type="button" data-phone-otp-send class="text-sm font-semibold text-blue-600 hover:text-blue-700">Send verification code</button>
                                <div data-phone-otp-section class="hidden space-y-2">
                                    <label for="signup_phone_otp" class="block font-medium text-slate-700">SMS verification code</label>
                                    <input id="signup_phone_otp" data-phone-otp-input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm tracking-[0.2em] outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                    <button type="button" data-phone-otp-verify class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Verify number</button>
                                </div>
                                <p data-phone-otp-status class="text-xs text-slate-500" role="status"></p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2 text-sm">
                                <label for="password_register" class="block font-medium text-slate-700">Password</label>
                                <input id="password_register" name="password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="confirm_password" class="block font-medium text-slate-700">Confirm Password</label>
                                <input id="confirm_password" name="confirm_password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600">
                            <input id="terms_agree" name="terms_agree" type="checkbox" required class="h-4 w-4 text-blue-600" />
                            <label for="terms_agree" class="ml-2">I agree to the staff code of conduct and understand this account requires admin approval.</label>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Create Staff Account</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLogin" class="font-semibold text-blue-600 hover:text-blue-700">Back to login</button>
                    </div>
                </div>

                <div id="resetSection" class="hidden space-y-3">
                    <p class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">Staff password resets use SMS verification to the registered mobile number.</p>
                    <a href="/staff/login#forgot-password" class="block w-full rounded-lg bg-blue-600 px-6 py-3 text-center text-sm font-semibold text-white transition hover:bg-blue-700">Continue to SMS Password Reset</a>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLoginFromReset" class="font-semibold text-blue-600 hover:text-blue-700">Back to login</button>
                    </div>
                </div>
            </div>

        </div>

    </body>
</html>
