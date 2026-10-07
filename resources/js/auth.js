import { bindPhoneSignupVerification } from './phoneSignupVerification.js';

const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const resetForm = document.getElementById('resetForm');
const authFeedback = document.getElementById('authFeedback');
const authTitle = document.getElementById('authTitle');

const loginSection = document.getElementById('loginSection');
const registerSection = document.getElementById('registerSection');
const resetSection = document.getElementById('resetSection');
const showRegister = document.getElementById('showRegister');
const showReset = document.getElementById('showReset');
const showLogin = document.getElementById('showLogin');
const showLoginFromReset = document.getElementById('showLoginFromReset');
const getVerifiedSignupFlowId = bindPhoneSignupVerification({
    form: registerForm,
    phoneInput: registerForm?.querySelector('[name="mobile_number"]'),
    accountType: () => registerForm?.querySelector('[name="role"]:checked')?.value || 'parent',
    normalizePhoneNumber,
    showMessage,
});
const studentSelect = document.getElementById('student_id');

function showMessage(message, isError = false) {
    if (!authFeedback) return;

    authFeedback.textContent = message;
    authFeedback.className = isError
        ? 'rounded-3xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700'
        : 'rounded-3xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700';
}

function setTitle(text) {
    if (authTitle) {
        authTitle.textContent = text;
    }
}

function hideAllSections() {
    [loginSection, registerSection, resetSection].forEach((section) => {
        if (section) {
            section.classList.add('hidden');
        }
    });
}

function showSection(name) {
    hideAllSections();

    if (name === 'register' && registerSection) {
        registerSection.classList.remove('hidden');
        setTitle('Register');
        return;
    }

    if (name === 'reset' && resetSection) {
        resetSection.classList.remove('hidden');
        setTitle('Forgot Password');
        return;
    }

    if (loginSection) {
        loginSection.classList.remove('hidden');
        setTitle('Login');
    }
}

function getInitialAuthView() {
    const path = window.location.pathname.replace(/\/$/, '');

    if (path === '/register') {
        return 'register';
    }

    if (path === '/forgot-password') {
        return 'reset';
    }

    return 'login';
}

import supabase from './supabaseClient'

async function handleLogin(event) {
    event.preventDefault();
    if (!loginForm) return;

    const formData = new FormData(loginForm);
    const phone = normalizePhoneNumber(formData.get('phone'));
    const password = String(formData.get('password'));

    showMessage('Signing in...', false);

    const { data, error } = await supabase.auth.signInWithPassword({ phone, password });
    if (error) {
        showMessage(error.message || 'Unable to sign in.', true);
        return;
    }

    const accessToken = data?.session?.access_token;
    if (!accessToken) {
        showMessage('Sign in succeeded but token missing.', true);
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
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
        showMessage(payload.message || 'Unable to create session.', true);
        return;
    }

    window.location.href = '/picker';
}

function normalizePhoneNumber(value) {
    const trimmed = String(value || '').trim();
    const digits = trimmed.replace(/\D/g, '');
    if (trimmed.startsWith('+')) return `+${digits}`;
    if (digits.startsWith('09') && digits.length === 11) return `+63${digits.slice(1)}`;
    if (digits.startsWith('639') && digits.length === 12) return `+${digits}`;
    if (digits.startsWith('9') && digits.length === 10) return `+63${digits}`;
    return trimmed;
}

async function saveParentProfile(profile) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch('/supabase/parent-profile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify(profile),
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload.message || 'Failed to save parent profile.');
    }

    return payload;
}

async function loadStudentOptions() {
    if (!studentSelect) {
        return;
    }

    studentSelect.innerHTML = '<option value="">Loading students...</option>';

    try {
        const response = await fetch('/supabase/students', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            studentSelect.innerHTML = '<option value="">Unable to load students</option>';
            console.error('Failed to load students:', data);
            return;
        }

        if (!data || data.length === 0) {
            studentSelect.innerHTML = '<option value="">No students available</option>';
            return;
        }

        studentSelect.innerHTML = '<option value="">Select your child</option>';
        data.forEach((student) => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.name} (${student.class || 'No class'})`;
            option.dataset.name = student.name;
            option.dataset.studentClass = student.class || '';
            studentSelect.appendChild(option);
        });
    } catch (error) {
        studentSelect.innerHTML = '<option value="">Unable to load students</option>';
        console.error('Failed to load students:', error);
    }
}

async function handleRegister(event) {
    event.preventDefault();
    if (!registerForm) return;

    const formData = new FormData(registerForm);
    const fullName = formData.get('full_name');
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    const mobileNumber = formData.get('mobile_number');
    const accountRole = formData.get('role') || 'parent';
    const phoneVerificationFlowId = getVerifiedSignupFlowId(mobileNumber);
    const staffCode = formData.get('staff_code');
    const studentId = formData.get('student_id');
    const selectedOption = studentSelect?.selectedOptions?.[0];
    const studentName = selectedOption?.dataset?.name || '';
    const studentClass = selectedOption?.dataset?.studentClass || '';

    if (password !== confirmPassword) {
        showMessage('Passwords do not match.', true);
        return;
    }

    if (accountRole === 'parent' && !studentId) {
        showMessage('Please select the student you are connected to.', true);
        return;
    }

    if (accountRole === 'staff' && staffCode !== 'STAFF2026') {
        showMessage('Invalid staff access code.', true);
        return;
    }

    if (!phoneVerificationFlowId) {
        showMessage('Verify your mobile number before creating your account.', true);
        return;
    }

    showMessage('Creating account...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const requestBody = JSON.stringify({
        full_name: String(fullName),
        password: String(password),
        mobile_number: String(mobileNumber),
        phone_verification_flow_id: phoneVerificationFlowId,
        role: accountRole,
        relationship: String(formData.get('relationship') || ''),
        student_id: String(studentId),
        student_name: String(studentName),
        student_class: String(studentClass),
    });

    const response = await fetch('/supabase/register-user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: requestBody,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        showMessage(payload.message || 'Registration failed.', true);
        return;
    }

    showMessage('Account created successfully. You can sign in now.', false);
    registerForm.reset();
}

async function handleReset(event) {
    event.preventDefault();
    if (!resetForm) return;

    const formData = new FormData(resetForm);
    const email = formData.get('email');

    showMessage('Sending password reset email...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch('/supabase/reset-password', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify({
            email: String(email),
            redirect_to: `${window.location.origin}/login`,
        }),
    });

    const payload = await response.json().catch(() => ({}));

    if (! response.ok) {
        showMessage(payload.message || 'Unable to send the password reset email.', true);
        return;
    }

    showMessage('Password reset email sent. Check your inbox.', false);
    resetForm.reset();
}

if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
}

if (registerForm) {
    registerForm.addEventListener('submit', handleRegister);
}

if (resetForm) {
    resetForm.addEventListener('submit', handleReset);
}

if (showRegister) {
    showRegister.addEventListener('click', async () => {
        window.history.pushState({}, '', '/register');
        await loadStudentOptions();
        showSection('register');
    });
}

const roleParent = document.getElementById('roleParent');
const roleStaff = document.getElementById('roleStaff');
const parentFields = document.getElementById('parentFields');
const staffInviteSection = document.getElementById('staffInviteSection');

function updateRegisterFormForRole() {
    if (roleStaff && roleStaff.checked) {
        parentFields?.classList.add('hidden');
        staffInviteSection?.classList.remove('hidden');
        if (studentSelect) {
            studentSelect.required = false;
        }
        const relationshipField = document.getElementById('relationship');
        if (relationshipField) {
            relationshipField.required = false;
        }
    } else {
        parentFields?.classList.remove('hidden');
        staffInviteSection?.classList.add('hidden');
        if (studentSelect) {
            studentSelect.required = true;
        }
        const relationshipField = document.getElementById('relationship');
        if (relationshipField) {
            relationshipField.required = true;
        }
    }
}

if (roleParent) {
    roleParent.addEventListener('change', updateRegisterFormForRole);
}

if (roleStaff) {
    roleStaff.addEventListener('change', updateRegisterFormForRole);
}

updateRegisterFormForRole();

if (showReset) {
    showReset.addEventListener('click', () => {
        window.history.pushState({}, '', '/forgot-password');
        showSection('reset');
    });
}

if (showLogin) {
    showLogin.addEventListener('click', () => {
        window.history.pushState({}, '', '/login');
        showSection('login');
    });
}

if (showLoginFromReset) {
    showLoginFromReset.addEventListener('click', () => {
        window.history.pushState({}, '', '/login');
        showSection('login');
    });
}

window.addEventListener('popstate', () => {
    showSection(getInitialAuthView());
});

if (getInitialAuthView() === 'register') {
    loadStudentOptions();
}

showSection(getInitialAuthView());
