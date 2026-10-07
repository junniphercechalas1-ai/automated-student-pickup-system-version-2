<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Staff') - Pickup Verification</title>
        @vite(['resources/css/app.css'])
        @yield('head')
    </head>
    <body class="staff-portal-shell">
        <script>
            if (localStorage.getItem('staff-dark-mode') === 'on') {
                document.body.classList.add('dark-mode');
            }
        </script>
        <div class="staff-portal">
            <aside class="staff-portal-sidebar">
                <div class="staff-portal-branding">
                    <div class="staff-portal-logo">
                        <img src="/images/orion-logo.png" alt="Orion Christian Academy" />
                    </div>
                    <div>
                        <p class="staff-portal-branding-label">Orion Christian Academy</p>
                    </div>
                </div>

                <nav class="staff-portal-nav" aria-label="Staff navigation">
                    <a href="/staff/pickup-verification" class="{{ request()->routeIs('staff.pickup-verification') ? 'active' : '' }}">
                        <span class="staff-portal-icon">⊕</span>
                        <span>QR Scanner</span>
                    </a>
                    <a href="/staff/pickup-records" class="{{ request()->routeIs('staff.pickup-records') ? 'active' : '' }}">
                        <span class="staff-portal-icon">▣</span>
                        <span>Scan History</span>
                    </a>
                    <a href="/staff/profile" class="{{ request()->routeIs('staff.profile') ? 'active' : '' }}">
                        <span class="staff-portal-icon">◌</span>
                        <span>My Account</span>
                    </a>
                    <form action="/logout" method="POST" class="staff-portal-logout-form">
                        @csrf
                        <button type="submit" class="staff-portal-logout">
                            <span class="staff-portal-icon">⇢</span>
                            <span>Logout</span>
                        </button>
                    </form>
                </nav>

                <div class="staff-portal-help">
                    <div class="staff-portal-help-icon">?</div>
                    <h3>Need Help?</h3>
                    <p>Contact the Administrator if you encounter any issues.</p>
                </div>
            </aside>

            <main class="staff-portal-main">
                @php
                    $staffSessionUser = session('supabase_user');
                    $layoutFullName = $staffSessionUser['user_metadata']['full_name'] ?? ($staffSessionUser['full_name'] ?? 'Staff Member');
                    $layoutRole = $staffSessionUser['user_metadata']['role'] ?? ($staffSessionUser['role'] ?? 'staff');
                    $layoutRoleLabel = ucfirst($layoutRole) === 'Staff' ? 'Gate Staff' : ucfirst($layoutRole);
                    $layoutInitial = strtoupper(substr(trim($layoutFullName), 0, 1) ?: 'S');
                @endphp
                <header class="staff-portal-header">
                    <div class="staff-portal-header-branding">
                        <div class="staff-portal-header-mark">
                            <img src="/images/orion-logo.png" alt="Orion Christian Academy" />
                        </div>
                        <div>
                            <span class="staff-portal-header-kicker">Orion Christian Academy</span>
                            <strong>Staff Portal</strong>
                        </div>
                    </div>

                    <div class="staff-portal-user-box">
                        <div class="staff-portal-user-avatar">{{ $layoutInitial }}</div>
                        <div class="staff-portal-user-meta">
                            <span class="staff-portal-user-name">{{ $layoutFullName }}</span>
                            <span class="staff-portal-user-role">{{ $layoutRoleLabel }}</span>
                        </div>
                        <form method="POST" action="/logout" class="staff-portal-header-logout-form">
                            @csrf
                            <button type="submit" class="staff-portal-header-logout">
                                <span class="staff-portal-header-logout-icon">⇢</span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </header>

                <div class="staff-portal-content">
                    @yield('content')
                </div>
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
