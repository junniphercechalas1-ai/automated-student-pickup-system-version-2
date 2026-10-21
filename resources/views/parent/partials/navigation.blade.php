<nav id="{{ $navigationId }}" class="{{ $navigationClass }}" aria-label="Parent portal navigation">
    <a href="/parent/dashboard" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
        <span class="parent-portal-nav-icon">⌂</span>
        <span>Dashboard</span>
    </a>
    <a href="/parent/qr-code" class="{{ request()->routeIs('parent.qr-code') ? 'active' : '' }}">
        <span class="parent-portal-nav-icon">◫</span>
        <span>My QR Code</span>
    </a>
    <a href="/parent/pickup-history" class="{{ request()->routeIs('parent.pickup-history') ? 'active' : '' }}">
        <span class="parent-portal-nav-icon">▣</span>
        <span>Pickup History</span>
    </a>
    <a href="/parent/profile" class="{{ request()->routeIs('parent.profile') || request()->routeIs('parent.profile.edit') ? 'active' : '' }}">
        <span class="parent-portal-nav-icon">◉</span>
        <span>Profile</span>
    </a>
</nav>
