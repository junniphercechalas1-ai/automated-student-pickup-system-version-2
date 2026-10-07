@extends('staff.layout')

@section('title', 'My Account')

@php
    $staffPhoto = $staff['photo_url'] ?? ($user['user_metadata']['avatar_url'] ?? null);
    $staffPhoto = $staffPhoto ?: '/images/default-avatar.svg';
    $fullName = $staff['full_name'] ?? ($user['user_metadata']['full_name'] ?? $user['full_name'] ?? '');
    $email = $staff['email'] ?? ($user['email'] ?? '');
    $phoneNumber = $staff['phone_number'] ?? ($user['user_metadata']['phone_number'] ?? '');
    $isApproved = (bool) ($staff['is_approved'] ?? ($user['user_metadata']['is_approved'] ?? true));
    $roleLabel = 'Gate Staff';
    $staffCreatedAt = $staff['created_at'] ?? ($user['created_at'] ?? null);
    $staffSinceYear = is_string($staffCreatedAt) && trim($staffCreatedAt) !== ''
        ? \Carbon\Carbon::parse($staffCreatedAt)->setTimezone(config('app.timezone'))->format('Y')
        : null;
@endphp

@section('content')
    <section class="staff-profile-page">
        <header class="staff-profile-header">
            <div>
                <h2 class="staff-profile-title">My Account</h2>
                <p class="staff-profile-subtitle">View and update your account information.</p>
            </div>
            <button id="editProfileBtn" type="button" class="staff-profile-edit-button">Edit</button>
        </header>

        <div class="staff-profile-summary-card">
            <div class="staff-profile-summary-main">
                <div class="staff-profile-photo-wrap">
                    <svg class="staff-profile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M6 20c0-3.314 2.686-6 6-6s6 2.686 6 6"></path>
                    </svg>
                </div>

                <div class="staff-profile-summary-info">
                    <h3>{{ $fullName ?: 'Not provided' }}</h3>
                    <div class="staff-profile-role-badge">
                        <span class="staff-profile-role-dot"></span>
                        <span>{{ $roleLabel }}</span>
                    </div>
                    <div class="staff-profile-meta-list">
                        <div class="staff-profile-meta-item">
                            <span class="staff-profile-meta-icon">✉</span>
                            <span>{{ $email ?: 'Not provided' }}</span>
                        </div>
                        <div class="staff-profile-meta-item">
                            <span class="staff-profile-meta-icon">☎</span>
                            <span>{{ $phoneNumber ?: 'Not provided' }}</span>
                        </div>
                        <div class="staff-profile-meta-item">
                            <span class="staff-profile-meta-icon">🗓</span>
                            <span>{{ $staffSinceYear ? 'Staff since '.$staffSinceYear : 'Staff start date unavailable' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="staff-profile-status-card">
                <div class="staff-profile-status-header">Account Status</div>
                <div class="staff-profile-status-pill {{ $isApproved ? 'is-active' : 'is-pending' }}">
                    <span class="staff-profile-status-check">✓</span>
                    <span>{{ $isApproved ? 'Active' : 'Pending' }}</span>
                </div>
                <p>{{ $isApproved ? 'Your account is active and in good standing.' : 'Your account is pending review.' }}</p>
            </div>
        </div>

        <form id="staffProfileForm" class="staff-profile-form">
            @csrf

            <div class="staff-profile-grid">
                <div class="staff-profile-card staff-profile-card--info">
                    <div id="profileView" class="staff-profile-view">
                        <h3>Account Information</h3>
                        <p>Update your personal information.</p>

                        <div class="staff-profile-field">
                            <label>Full Name</label>
                            <div class="staff-profile-value-box">{{ $fullName ?: 'Not provided' }}</div>
                        </div>

                        <div class="staff-profile-field">
                            <label>Email Address</label>
                            <div class="staff-profile-value-box break-all">{{ $email ?: 'Not provided' }}</div>
                        </div>

                        <div class="staff-profile-field">
                            <label>Contact Number</label>
                            <div class="staff-profile-value-box">{{ $phoneNumber ?: 'Not provided' }}</div>
                        </div>

                        <div class="staff-profile-field">
                            <label>Username</label>
                            <div class="staff-profile-value-box">{{ $user['user_metadata']['username'] ?? $user['email'] ?? 'Not provided' }}</div>
                        </div>
                    </div>

                    <div id="profileEdit" class="staff-profile-edit hidden">
                        <h3>Account Information</h3>
                        <p>Update your personal information.</p>

                        <div class="staff-profile-field">
                            <label for="full_name">Full Name</label>
                            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $fullName) }}" />
                        </div>

                        <div class="staff-profile-field">
                            <label for="email">Email Address</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" />
                        </div>

                        <div class="staff-profile-field">
                            <label for="phone_number">Contact Number</label>
                            <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number', $phoneNumber) }}" />
                        </div>

                        <div class="staff-profile-field">
                            <label for="is_approved">Account State</label>
                            <div class="staff-profile-toggle-row">
                                <input id="is_approved" name="is_approved" type="checkbox" value="1" {{ $isApproved ? 'checked' : '' }} />
                                <span>{{ $isApproved ? 'Approved' : 'Pending' }}</span>
                            </div>
                        </div>

                        <div class="staff-profile-actions">
                            <button type="button" class="staff-profile-save-button" id="saveProfile">Save Changes</button>
                        </div>
                    </div>
                </div>

                <div class="staff-profile-side-column">
                    <div class="staff-profile-card staff-profile-card--role">
                        <h3>Role Information</h3>
                        <div class="staff-profile-role-list">
                            <div class="staff-profile-role-item">
                                <span>Role</span>
                                <strong>{{ $roleLabel }}</strong>
                            </div>
                            <div class="staff-profile-role-item">
                                <span>Access Level</span>
                                <strong>Staff</strong>
                            </div>
                            <div class="staff-profile-role-item">
                                <span>Permissions</span>
                                <strong>QR Scanning, View History</strong>
                            </div>
                        </div>
                    </div>

                    <div class="staff-profile-card staff-profile-card--preferences">
                        <h3>Preferences</h3>
                        <div class="staff-profile-preference-item">
                            <div>
                                <label>Dark Mode</label>
                                <p>Use dark mode interface</p>
                            </div>
                            <button type="button" id="darkModeToggle" class="staff-profile-toggle" aria-label="Toggle dark mode"><span></span></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="staff-profile-status-row">
            <div class="staff-profile-system-status">
                <div class="staff-profile-system-badge">✓</div>
                <div>
                    <p class="staff-profile-status-label">System Status: Online</p>
                    <p class="staff-profile-status-text">Scanner is connected and ready.</p>
                </div>
            </div>

            <div class="staff-profile-date-box">
                <div class="staff-profile-date-item">
                    <span>📅</span>
                    <span>{{ now()->format('F j, Y') }}</span>
                </div>
                <div class="staff-profile-date-item">
                    <span>◔</span>
                    <span>{{ now()->format('h:i A') }}</span>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            const editBtn = document.getElementById('editProfileBtn');
            const form = document.getElementById('staffProfileForm');
            const profileView = document.getElementById('profileView');
            const profileEdit = document.getElementById('profileEdit');
            const saveBtn = document.getElementById('saveProfile');
            const darkModeToggle = document.getElementById('darkModeToggle');

            function setDarkMode(enabled) {
                document.body.classList.toggle('dark-mode', enabled);
                darkModeToggle?.classList.toggle('is-on', enabled);
                darkModeToggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                localStorage.setItem('staff-dark-mode', enabled ? 'on' : 'off');
            }

            setDarkMode(localStorage.getItem('staff-dark-mode') === 'on');

            darkModeToggle?.addEventListener('click', function () {
                setDarkMode(!document.body.classList.contains('dark-mode'));
            });

            function setEditing(isEditing) {
                profileView.classList.toggle('hidden', isEditing);
                profileEdit.classList.toggle('hidden', !isEditing);
                editBtn.textContent = isEditing ? 'Cancel' : 'Edit';
                if (saveBtn) {
                    saveBtn.disabled = !isEditing;
                    saveBtn.classList.toggle('opacity-60', !isEditing);
                }
            }

            if (editBtn) {
                editBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    const isEditing = !profileEdit.classList.contains('hidden');
                    setEditing(!isEditing);
                });
            }

            if (saveBtn) {
                saveBtn.addEventListener('click', async function () {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
                    const formData = new FormData(form);
                    const userId = '{{ $user['id'] ?? '' }}';

                    if (userId) {
                        formData.set('user_id', userId);
                    }

                    try {
                        const response = await fetch('/supabase/staff-profile', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken || '',
                            },
                            body: formData,
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            const message = payload.message || 'Failed to save profile.';
                            const details = payload.details && typeof payload.details === 'object'
                                ? JSON.stringify(payload.details)
                                : '';
                            alert(details ? message + '\n' + details : message);
                            console.error('Staff profile save failed:', payload);
                            return;
                        }

                        alert('Profile saved successfully.');
                        window.location.reload();
                    } catch (error) {
                        console.error('Staff profile save request failed:', error);
                        alert(error?.message || 'Failed to save profile.');
                    }
                });
            }

            setEditing(false);
        })();
    </script>
@endsection
