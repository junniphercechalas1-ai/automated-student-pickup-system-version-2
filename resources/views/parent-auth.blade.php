<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Parent/Guardian Login - Pickup Verification</title>
        @vite(['resources/css/app.css', 'resources/js/parent-auth.js'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 overflow-hidden">
            <div class="p-5">
                <div class="mb-4">
                    <div class="mb-4 text-center">
                        <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-16 w-auto max-w-[180px] mx-auto object-contain">
                        <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-[0.35em] text-slate-500">Of The Philippines</p>
                    </div>
                    <div class="mb-1 flex justify-center">
                        <div class="w-14 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                            <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                    </div>
                    <h1 id="authTitle" class="text-2xl font-bold text-slate-950 text-center">Welcome Parents</h1>
                    <p class="mt-1 text-sm leading-6 text-slate-600 text-center">Log in, register, or reset your password.</p>
                </div>

                <div id="authFeedback" class="mb-4 text-sm"></div>

                <div id="loginSection" class="space-y-3">
                    <form id="loginForm" class="space-y-3">
                        <div class="space-y-2 text-sm">
                            <label for="username" class="block font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" autocomplete="username" required placeholder="Enter your username" class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password" class="block font-medium text-slate-700">Password</label>
                            <input id="password" name="password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Login</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showRegister" class="font-semibold text-blue-600 hover:text-blue-700">Create an account</button>
                        <button id="showReset" class="font-semibold text-blue-600 hover:text-blue-700">Forgot password?</button>
                    </div>
                    <div class="pt-4 border-t border-slate-200">
                        <a href="/" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Back to account selection</a>
                    </div>
                </div>

                <div id="registerSection" class="hidden space-y-3">
                    <form id="registerForm" class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2 text-sm sm:col-span-2">
                                <label for="username_register" class="block font-medium text-slate-700">Username</label>
                                <input id="username_register" name="username" type="text" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,29}" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" placeholder="Choose a username (letters, numbers, dots, underscores, hyphens)" />
                                <p class="text-xs text-slate-500">Use 3–30 characters. Your username will be used to sign in.</p>
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="first_name" class="block font-medium text-slate-700">First Name</label>
                                <input id="first_name" name="first_name" type="text" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="middle_name" class="block font-medium text-slate-700">Middle Name <span class="font-normal text-slate-400">(optional)</span></label>
                                <input id="middle_name" name="middle_name" type="text" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="last_name" class="block font-medium text-slate-700">Last Name</label>
                                <input id="last_name" name="last_name" type="text" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="phone_number" class="block font-medium text-slate-700">Mobile Number</label>
                                <input id="phone_number" name="phone_number" type="tel" inputmode="tel" placeholder="+63 917 123 4567" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                <button type="button" data-phone-otp-send class="text-sm font-semibold text-blue-600 hover:text-blue-700">Send verification code</button>
                                <div data-phone-otp-section class="hidden space-y-2">
                                    <label for="signup_phone_otp" class="block font-medium text-slate-700">SMS verification code</label>
                                    <input id="signup_phone_otp" data-phone-otp-input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm tracking-[0.2em] outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                                    <button type="button" data-phone-otp-verify class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Verify number</button>
                                </div>
                                <p data-phone-otp-status class="text-xs text-slate-500" role="status"></p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2 text-sm">
                                <label for="password_register" class="block font-medium text-slate-700">Password</label>
                                <input id="password_register" name="password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="confirm_password" class="block font-medium text-slate-700">Confirm Password</label>
                                <input id="confirm_password" name="confirm_password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="relationship" class="block font-medium text-slate-700">Relationship</label>
                            <select id="relationship" name="relationship" required class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                                <option value="">Select relationship</option>
                                <option value="Mother">Mother</option>
                                <option value="Father">Father</option>
                                <option value="Grandmother">Grandmother</option>
                                <option value="Grandfather">Grandfather</option>
                                <option value="Other">Other</option>
                            </select>
                            <input id="otherRelationship" name="other_relationship" type="text" placeholder="Specify relationship" class="hidden w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="student_id" class="block font-medium text-slate-700">Student</label>
                            <select id="student_id" name="student_id" required class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                                <option value="">Select your child</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Register</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLogin" class="font-semibold text-blue-600 hover:text-blue-700">Back to login</button>
                    </div>
                </div>

                <div id="resetSection" class="hidden space-y-3">
                    <p class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">Reset your password with an SMS code sent to your registered mobile number.</p>
                    <a href="/parent/login#forgot-password" class="block w-full rounded-lg bg-blue-600 px-6 py-3 text-center text-sm font-semibold text-white transition hover:bg-blue-700">Continue to SMS Password Reset</a>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLoginFromReset" class="font-semibold text-blue-600 hover:text-blue-700">Back to login</button>
                    </div>
                </div>
            </div>

        </div>

    </body>
</html>
