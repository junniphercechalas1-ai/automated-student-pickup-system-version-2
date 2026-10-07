<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Auth - Pickup Verification</title>
        @vite(['resources/css/app.css', 'resources/js/auth.js'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-6 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
        <div class="w-full max-w-5xl rounded-[32px] bg-white shadow-2xl ring-1 ring-slate-200 overflow-hidden lg:grid lg:grid-cols-[1.2fr_0.9fr]">
            <div class="p-8 lg:p-12">
                <div class="mb-8">
                    <div class="mb-8 text-center">
                        <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-[0.35em] text-slate-500">Of The Philippines</p>
                    </div>
                    <h1 id="authTitle" class="mt-4 text-4xl font-semibold text-slate-950">Welcome</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Log in, register, or reset your password. Your account will be managed through Supabase Auth.</p>
                </div>

                <div id="authFeedback" class="mb-6 text-sm"></div>

                <div id="loginSection" class="space-y-6">
                    <form id="loginForm" class="space-y-5">
                        <div class="space-y-2 text-sm">
                            <label for="phone" class="block font-medium text-slate-700">Mobile Number</label>
                            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Enter your mobile number" class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                        </div>
                        <div class="space-y-2 text-sm">
                            <label for="password" class="block font-medium text-slate-700">Password</label>
                            <input id="password" name="password" type="password" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                        </div>
                        <button type="submit" class="w-full rounded-3xl bg-amber-500 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Login</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showRegister" class="font-semibold text-amber-500 hover:text-amber-600">Create an account</button>
                        <button id="showReset" class="font-semibold text-amber-500 hover:text-amber-600">Forgot password?</button>
                    </div>
                </div>

                <div id="registerSection" class="hidden space-y-6">
                    <form id="registerForm" class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2 text-sm">
                                <label for="full_name" class="block font-medium text-slate-700">Full Name</label>
                                <input id="full_name" name="full_name" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="mobile_number" class="block font-medium text-slate-700">Mobile Number</label>
                                <input id="mobile_number" name="mobile_number" type="tel" inputmode="tel" placeholder="+63 917 123 4567" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                                <button type="button" data-phone-otp-send class="text-sm font-semibold text-amber-600 hover:text-amber-700">Send verification code</button>
                                <div data-phone-otp-section class="hidden space-y-2">
                                    <label for="signup_phone_otp" class="block font-medium text-slate-700">SMS verification code</label>
                                    <input id="signup_phone_otp" data-phone-otp-input type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm tracking-[0.2em] outline-none focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                                    <button type="button" data-phone-otp-verify class="rounded-3xl bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400">Verify number</button>
                                </div>
                                <p data-phone-otp-status class="text-xs text-slate-500" role="status"></p>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
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
                            <p class="block font-medium text-slate-700">Account type</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex cursor-pointer items-center gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <input id="roleParent" type="radio" name="role" value="parent" checked class="h-4 w-4 text-amber-500" />
                                    <span>Parent / Guardian</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <input id="roleStaff" type="radio" name="role" value="staff" class="h-4 w-4 text-amber-500" />
                                    <span>School staff</span>
                                </label>
                            </div>
                        </div>
                        <div id="staffInviteSection" class="hidden space-y-2 text-sm">
                            <label for="staff_code" class="block font-medium text-slate-700">Staff access code</label>
                            <input id="staff_code" name="staff_code" type="text" class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                            <p class="text-xs text-slate-500">Enter the staff registration code to create a staff account.</p>
                        </div>
                        <div id="parentFields">
                            <div class="space-y-2 text-sm">
                                <label for="relationship" class="block font-medium text-slate-700">Relationship</label>
                                <input id="relationship" name="relationship" type="text" required class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100" />
                            </div>
                            <div class="space-y-2 text-sm">
                                <label for="student_id" class="block font-medium text-slate-700">Student</label>
                                <select id="student_id" name="student_id" required class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                                    <option value="">Select your child</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-3xl bg-amber-500 px-6 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Register</button>
                    </form>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLogin" class="font-semibold text-amber-500 hover:text-amber-600">Back to login</button>
                    </div>
                </div>

                <div id="resetSection" class="hidden space-y-6">
                    <p class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Reset your password with an SMS code sent to your registered mobile number.</p>
                    <a href="/parent/login#forgot-password" class="block w-full rounded-3xl bg-amber-500 px-6 py-3 text-center text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Continue to SMS Password Reset</a>
                    <div class="flex items-center justify-between text-sm text-slate-500">
                        <button id="showLoginFromReset" class="font-semibold text-amber-500 hover:text-amber-600">Back to login</button>
                    </div>
                </div>
            </div>

            <div class="hidden lg:flex flex-col justify-center bg-amber-500 p-12 text-white">
                <div class="space-y-6">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-white/80">System overview</p>
                        <h2 class="mt-4 text-4xl font-semibold">Pickup verification with QR and SMS</h2>
                    </div>
                    <p class="text-sm leading-7 text-white/90">Register parents and guardians, sign in securely, and manage student pickups with Supabase as the backend service.</p>
                </div>
            </div>
        </div>

    </body>
</html>
