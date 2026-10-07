@extends('parent.layout')

@section('title', 'My Student')

@section('content')
    @php
        $parentFirstName = trim((string) explode(' ', trim($fullName ?? ($user['user_metadata']['full_name'] ?? 'Parent')))[0]);
        $parentFirstName = $parentFirstName !== '' ? $parentFirstName : 'Parent';
    @endphp

    <section class="parent-welcome-hero">
        <div class="parent-welcome-copy">
            <h1>Welcome, {{ $parentFirstName }}!</h1>
            <p>Use your QR code at the school gate for a safe and quick entry.</p>
        </div>

        <div class="parent-welcome-illustration" aria-hidden="true">
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

    @php
        $studentName = trim((string) ($student['name'] ?? 'Student'));
        $studentInitials = collect(preg_split('/\s+/', $studentName))
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
        $studentId = trim((string) ($student['id'] ?? ($student['student_id'] ?? 'N/A')));
        $studentGradeSection = trim((string) ($student['class'] ?? 'N/A'));
    @endphp

    <section class="parent-student-layout">
        <div class="parent-student-card">
            <div class="parent-student-card-header">
                <span class="parent-student-card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3.75c2.26 0 4.1 1.84 4.1 4.1 0 2.26-1.84 4.1-4.1 4.1-2.26 0-4.1-1.84-4.1-4.1 0-2.26 1.84-4.1 4.1-4.1Zm-6.45 12.6c.9-2.18 3.03-3.5 6.45-3.5 3.42 0 5.55 1.32 6.45 3.5v2.15H5.55v-2.15Zm13.8 0v1.85H4.65v-1.85c1.2-1.62 3.3-2.64 7.35-2.64 4.05 0 6.15 1.02 7.35 2.64Z" fill="currentColor"/>
                    </svg>
                </span>
                <h3>Your Student</h3>
            </div>

            <div class="parent-student-card-divider"></div>

            <div class="parent-student-card-body">
                <div class="parent-student-summary">
                    <div class="parent-student-avatar">{{ $studentInitials ?: 'S' }}</div>
                    <div class="parent-student-meta">
                        <h4>{{ $studentName }}</h4>
                        <p>{{ $studentGradeSection }}</p>
                        <span class="parent-student-id-badge">Student ID: {{ $studentId }}</span>
                    </div>
                </div>

                <div class="parent-student-authorized-panel">
                    <div class="parent-student-authorized-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2.75 18.5 5v5.7c0 3.67-2.02 7.17-6.5 10.55-4.48-3.38-6.5-6.88-6.5-10.55V5L12 2.75Zm-1.1 11.18 6.35-6.35 1.4 1.4-7.75 7.75-4.1-4.1 1.4-1.4 2.7 2.7Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <div>
                        <h4>Authorized Parent</h4>
                        <p>You are registered as the authorized parent/guardian for this student.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="parent-quick-actions-card">
            <div class="parent-quick-actions-header">
                <span class="parent-quick-actions-title-icon">⚡</span>
                <span>Quick Actions</span>
            </div>

            <div class="parent-quick-actions-grid">
                <a href="/parent/qr-code" class="parent-quick-action parent-quick-action-blue">
                    <span class="parent-quick-action-icon" aria-hidden="true">◫</span>
                    <div class="parent-quick-action-copy">
                        <span>My QR Code</span>
                        <small>Show your QR code at the school gate.</small>
                    </div>
                    <span class="parent-quick-action-arrow">›</span>
                </a>

                <a href="/parent/student" class="parent-quick-action parent-quick-action-green">
                    <span class="parent-quick-action-icon" aria-hidden="true">◌</span>
                    <div class="parent-quick-action-copy">
                        <span>My Student</span>
                        <small>View your student details.</small>
                    </div>
                    <span class="parent-quick-action-arrow">›</span>
                </a>

                <a href="/parent/profile" class="parent-quick-action parent-quick-action-purple">
                    <span class="parent-quick-action-icon" aria-hidden="true">◉</span>
                    <div class="parent-quick-action-copy">
                        <span>My Profile</span>
                        <small>Manage your account information.</small>
                    </div>
                    <span class="parent-quick-action-arrow">›</span>
                </a>
            </div>
        </div>

        <div class="parent-reminder-card" role="note" aria-label="Reminder">
            <div class="parent-reminder-icon" aria-hidden="true">🔔</div>
            <div class="parent-reminder-content">
                <h3>Reminder</h3>
                <p>You will receive an SMS reminder about dismissal 30 minutes before the dismissal time. Please be on time. Thank you!</p>
            </div>
        </div>

        <section class="parent-how-it-works" aria-label="How It Works">
            <div class="parent-how-it-works-header">
                <span class="parent-how-it-works-icon" aria-hidden="true">ℹ</span>
                <h3>How It Works</h3>
            </div>

            <div class="parent-how-it-works-steps">
                <div class="parent-how-it-works-step">
                    <div class="parent-step-number">1</div>
                    <div class="parent-step-icon" aria-hidden="true">💬</div>
                    <h4>Receive SMS Reminder</h4>
                    <p>Receive an SMS reminder about dismissal 30 minutes before dismissal.</p>
                </div>

                <div class="parent-how-it-works-step">
                    <div class="parent-step-number">2</div>
                    <div class="parent-step-icon" aria-hidden="true">◫</div>
                    <h4>Generate Your QR Code</h4>
                    <p>Open My QR Code and tap Generate QR Code to display it.</p>
                </div>

                <div class="parent-how-it-works-step">
                    <div class="parent-step-number">3</div>
                    <div class="parent-step-icon" aria-hidden="true">▣</div>
                    <h4>QR Code Verification</h4>
                    <p>Let the guard or authorized staff scan your QR code at the school gate.</p>
                </div>

                <div class="parent-how-it-works-step">
                    <div class="parent-step-number">4</div>
                    <div class="parent-step-icon" aria-hidden="true">✓</div>
                    <h4>Pickup Recorded</h4>
                    <p>After successful verification, the pickup/entry will be recorded.</p>
                </div>
            </div>
        </section>
    </section>
@endsection
