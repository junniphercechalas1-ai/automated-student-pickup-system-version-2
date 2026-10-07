@extends('parent.layout')

@section('title', 'My QR Code')

@section('content')
    @php
        $parentFirstName = trim((string) explode(' ', trim($fullName ?? ($user['user_metadata']['full_name'] ?? 'Parent')))[0]);
        $studentName = trim((string) ($student['name'] ?? 'Student'));
        $studentInitials = collect(preg_split('/\s+/', $studentName))
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
        $studentId = trim((string) ($student['id'] ?? ($student['student_id'] ?? 'N/A')));
        $studentGradeSection = trim((string) ($student['class'] ?? 'N/A'));
    @endphp

    <!-- Dark Blue Hero Section -->
    <section class="parent-qr-hero">
        <div class="parent-qr-hero-content">
            <a href="javascript:history.back()" class="parent-qr-back-arrow" aria-label="Go back">←</a>
            <h1>My QR Code</h1>
            <p>Present this QR code to the guard or staff at the school gate.</p>
        </div>
        <div class="parent-qr-hero-illustration" aria-hidden="true">
            <div class="parent-welcome-cloud cloud-one"></div>
            <div class="parent-welcome-cloud cloud-two"></div>
            <div class="parent-welcome-school">
                <div class="school-flag"></div>
                <div class="school-roof"></div>
                <div class="school-windows">
                    <span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Student & Authorized Parent Card -->
    <section class="parent-qr-info-card">
        <div class="parent-qr-student-section">
            <div class="parent-qr-student-avatar">{{ $studentInitials ?: 'S' }}</div>
            <div class="parent-qr-student-details">
                <h3>{{ $studentName }}</h3>
                <p class="parent-qr-grade">{{ $studentGradeSection }}</p>
                <span class="parent-qr-student-id">Student ID: {{ $studentId }}</span>
            </div>
        </div>

        <div class="parent-qr-divider"></div>

        <div class="parent-qr-parent-section">
            <div class="parent-qr-parent-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2.75 18.5 5v5.7c0 3.67-2.02 7.17-6.5 10.55-4.48-3.38-6.5-6.88-6.5-10.55V5L12 2.75Zm-1.1 11.18 6.35-6.35 1.4 1.4-7.75 7.75-4.1-4.1 1.4-1.4 2.7 2.7Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="parent-qr-parent-details">
                <h4>Authorized Parent</h4>
                <p class="parent-qr-parent-name">{{ $fullName ?? 'Parent/Guardian' }}</p>
                <p class="parent-qr-relationship">{{ $relationship ?? 'Parent/Guardian' }}</p>
                <p class="parent-qr-mobile">{{ $mobile ?? 'N/A' }}</p>
            </div>
        </div>
    </section>

    <!-- QR Code Display Section -->
    <section class="parent-qr-code-section">
        <h2>Your QR Code</h2>
        <p class="parent-qr-code-subtitle">Use this code for verification at the school entrance.</p>

        <div class="parent-qr-code-container">
            <div class="parent-qr-code-display">
                <canvas id="parentQrCodeCanvas" data-qr-value='{{ json_encode(["qr_token" => $parent_qr_token ?? null], JSON_UNESCAPED_SLASHES) }}' data-expires-at="{{ $parent_qr_expires_at ?? '' }}" data-generated="{{ request()->filled('qr_refresh') ? 'true' : 'false' }}" class="parent-qr-canvas hidden"></canvas>
            </div>
        </div>

        <div class="mt-4 flex flex-col items-center text-center">
            <button id="refreshParentQr" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                Generate QR Code
            </button>
            <p id="parentQrRefreshStatus" class="mt-2 text-sm font-semibold text-gray-500" aria-live="polite">Click Generate QR Code to display your code.</p>
            <p id="parentQrExpiryStatus" class="mt-1 text-base font-bold text-amber-600" aria-live="polite"></p>
        </div>

        <!-- Security Notice -->
        <div class="parent-qr-security-notice">
            <div class="parent-qr-security-icon">🔒</div>
            <div class="parent-qr-security-content">
                <h4>Security Notice</h4>
                <p>This QR code is temporary and expires after 10 minutes. Do not share or screenshot it.</p>
            </div>
        </div>

        <!-- Important Information -->
        <div class="parent-qr-important-box">
            <div class="parent-qr-important-icon">✦</div>
            <div class="parent-qr-important-content">
                <h4>Important</h4>
                <ul>
                    <li>Show the current QR code directly from this page at the school gate.</li>
                    <li>If it expires, refresh this page to generate a new code.</li>
                    <li>Do not share your QR code with other people.</li>
                    <li>Use this code only for your child.</li>
                    <li>Ask the guard or staff for assistance if the code cannot be displayed.</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
