<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Select Account Type - Orion Christian Academy</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen text-slate-950 flex items-center justify-center py-8 px-4" style="background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-attachment: fixed; background-position: center;">
        <div class="max-w-sm mx-auto">
            <!-- White Content Card -->
            <div class="rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 px-6 py-8">
                <!-- Logo -->
                <div class="mb-6 text-center">
                    <img src="/images/orion-logo.png" alt="Orion Christian Academy" class="h-16 w-auto max-w-[180px] mx-auto object-contain">
                </div>
                <!-- School Branding -->
                <div class="mb-6 text-center">
                    <p class="text-sm font-semibold text-slate-900">Orion Christian Academy</p>
                    <p class="mt-1 text-xs font-medium uppercase tracking-[0.35em] text-slate-500">Of The Philippines</p>
                </div>
                <!-- Title -->
                <h1 class="text-2xl font-bold text-slate-950 text-center">Select Account Type</h1>
                <p class="mt-2 text-sm text-slate-600 text-center">Choose the type of account you want to log in with.</p>

                <!-- Account Selection Stack -->
                <div class="mt-6 space-y-3">
                <!-- Parent/Guardian Option -->
                <a href="/parent/login" class="group block">
                    <div class="rounded-xl bg-white border border-slate-200 p-4 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 cursor-pointer flex items-center gap-3">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <h2 class="text-sm font-semibold text-slate-950">Parent / Guardian</h2>
                            <p class="mt-1 text-xs text-slate-600">Access your child's QR code.</p>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>

                <!-- Staff Option -->
                <a href="/staff/login" class="group block">
                    <div class="rounded-xl bg-white border border-slate-200 p-4 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 cursor-pointer flex items-center gap-3">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2zm9 7h-6v13h-2v-6h-2v6H9V9H3V7h18v2z"/>
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <h2 class="text-sm font-semibold text-slate-950">Staff</h2>
                            <p class="mt-1 text-xs text-slate-600">Scan QR codes at the gate.</p>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>

                <!-- Administrator Option -->
                <a href="/admin/login" class="group block">
                    <div class="rounded-xl bg-white border border-slate-200 p-4 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 cursor-pointer flex items-center gap-3">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 transition-colors">
                            <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <h2 class="text-sm font-semibold text-slate-950">Administrator</h2>
                            <p class="mt-1 text-xs text-slate-600">Manage system and staff.</p>
                        </div>

                        <!-- Arrow -->
                        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
            </div>

                <!-- Footer Info -->
                <div class="mt-6 rounded-lg bg-blue-50 border border-blue-200 p-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <p class="text-xs text-blue-800">This system is for authorized users only. All access is monitored and recorded.</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
