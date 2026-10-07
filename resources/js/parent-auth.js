import { bindPhoneSignupVerification } from './phoneSignupVerification.js';

const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const resetForm = document.getElementById('resetForm');
const authFeedback = document.getElementById('authFeedback');
const authTitle = document.getElementById('authTitle');

const loginSection = document.getElementById('loginSection');
const registerSection = document.getElementById('registerSection');
const resetSection = document.getElementById('resetSection');
const usernameSetupSection = document.getElementById('usernameSetupSection');
const usernameSetupForm = document.getElementById('usernameSetupForm');
const showUsernameSetup = document.getElementById('showUsernameSetup');
const backFromUsernameSetup = document.getElementById('backFromUsernameSetup');
const showRegister = document.getElementById('showRegister');
const showReset = document.getElementById('showReset');
const showLogin = document.getElementById('showLogin');
const showLoginFromReset = document.getElementById('showLoginFromReset');
const studentSelect = document.getElementById('student_id');
const relationshipSelect = document.getElementById('relationship');
const otherRelationshipInput = document.getElementById('otherRelationship');
const getVerifiedSignupFlowId = bindPhoneSignupVerification({
    form: registerForm,
    phoneInput: registerForm?.querySelector('[name="phone_number"]'),
    accountType: 'parent',
    normalizePhoneNumber,
    showMessage,
});
const getVerifiedUsernameSetupFlowId = bindPhoneSignupVerification({
    form: usernameSetupForm,
    phoneInput: usernameSetupForm?.querySelector('[name="phone_number"]'),
    accountType: 'parent',
    normalizePhoneNumber,
    showMessage,
});

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
        setTitle('Welcome Parents');
    }
}

function getInitialAuthView() {
    const path = window.location.pathname.replace(/\/$/, '');

    if (path === '/login' || path === '/') {
        return 'login';
    }

    if (path === '/register' || path === '/parent/register') {
        return 'register';
    }

    if (path === '/forgot-password') {
        return 'reset';
    }

    return 'login';
}

async function handleLogin(event) {
    event.preventDefault();
    if (!loginForm) return;

    const formData = new FormData(loginForm);
    const username = String(formData.get('username') || '').trim();
    const password = String(formData.get('password'));

    showMessage('Signing in...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const loginResponse = await fetch('/supabase/username-login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify({ username, password, account_type: 'parent' }),
    });
    const loginPayload = await loginResponse.json().catch(() => ({}));
    if (!loginResponse.ok) {
        showMessage(loginPayload.message || 'Unable to sign in.', true);
        return;
    }

    const accessToken = loginPayload.access_token;
    if (!accessToken) {
        showMessage('Sign in succeeded but token missing.', true);
        return;
    }

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
    const firstName = String(formData.get('first_name') || '').trim();
    const middleName = String(formData.get('middle_name') || '').trim();
    const lastName = String(formData.get('last_name') || '').trim();
    const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
    const username = String(formData.get('username') || '').trim();
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    const phoneNumber = String(formData.get('phone_number') || '').trim();
    const phoneVerificationFlowId = getVerifiedSignupFlowId(phoneNumber);
    const studentId = formData.get('student_id');
    const relationship = formData.get('relationship');
    const otherRelationship = String(formData.get('other_relationship') || '').trim();
    const selectedOption = studentSelect?.selectedOptions?.[0];
    const studentName = selectedOption?.dataset?.name || '';
    const studentClass = selectedOption?.dataset?.studentClass || '';

    if (password !== confirmPassword) {
        showMessage('Passwords do not match.', true);
        return;
    }

    if (!studentId) {
        showMessage('Please select the student you are connected to.', true);
        return;
    }

    if (!phoneVerificationFlowId) {
        showMessage('Verify your mobile number before creating your account.', true);
        return;
    }

    if (relationship === 'Other' && !otherRelationship) {
        showMessage('Please specify your relationship to the student.', true);
        otherRelationshipInput?.focus();
        return;
    }

    showMessage('Creating account...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const requestBody = JSON.stringify({
        full_name: String(fullName),
        username,
        password: String(password),
        phone_number: phoneNumber,
        phone_verification_flow_id: phoneVerificationFlowId,
        role: 'parent',
        relationship: relationship === 'Other' ? otherRelationship : String(relationship || ''),
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

    showMessage('Account submitted. Your mobile number is verified. Please wait for administrator approval before signing in.', false);
    registerForm.reset();

    setTimeout(() => {
        window.history.pushState({}, '', '/parent/login');
        showSection('login');
    }, 3000);
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

    if (!response.ok) {
        showMessage(payload.message || 'Unable to send the password reset email.', true);
        return;
    }

    showMessage('Password reset email sent. Check your inbox.', false);
    resetForm.reset();
}

const isDedicatedParentLogin = window.location.pathname.replace(/\/$/, '') === '/parent/login';

async function handleUsernameSetup(event) {
    event.preventDefault();
    if (!usernameSetupForm) return;

    const formData = new FormData(usernameSetupForm);
    const phoneNumber = String(formData.get('phone_number') || '');
    const phoneVerificationFlowId = getVerifiedUsernameSetupFlowId(phoneNumber);
    if (!phoneVerificationFlowId) {
        showMessage('Verify your registered mobile number before setting a username.', true);
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch('/supabase/username-activate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify({
            username: String(formData.get('username') || ''),
            account_type: 'parent',
            phone_verification_flow_id: phoneVerificationFlowId,
        }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        showMessage(payload.message || 'Unable to set your username.', true);
        return;
    }

    usernameSetupForm.reset();
    usernameSetupSection?.classList.add('hidden');
    loginSection?.classList.remove('hidden');
    const loginUsername = loginForm?.querySelector('[name="username"]');
    if (loginUsername) loginUsername.value = String(formData.get('username') || '');
    showMessage(payload.message || 'Username set. You can now sign in.', false);
}

if (loginForm && !isDedicatedParentLogin) {
    loginForm.addEventListener('submit', handleLogin);
}

if (registerForm) {
    registerForm.addEventListener('submit', handleRegister);
}

if (usernameSetupForm) {
    usernameSetupForm.addEventListener('submit', handleUsernameSetup);
}

showUsernameSetup?.addEventListener('click', () => {
    loginSection?.classList.add('hidden');
    resetSection?.classList.add('hidden');
    usernameSetupSection?.classList.remove('hidden');
    showMessage('', false);
});

backFromUsernameSetup?.addEventListener('click', () => {
    usernameSetupSection?.classList.add('hidden');
    loginSection?.classList.remove('hidden');
    showMessage('', false);
});

if (resetForm && !isDedicatedParentLogin) {
    resetForm.addEventListener('submit', handleReset);
}

if (relationshipSelect) {
    relationshipSelect.addEventListener('change', () => {
        const isOther = relationshipSelect.value === 'Other';
        otherRelationshipInput?.classList.toggle('hidden', !isOther);
        otherRelationshipInput?.toggleAttribute('required', isOther);
        if (!isOther && otherRelationshipInput) {
            otherRelationshipInput.value = '';
        }
    });
}

if (showRegister) {
    showRegister.addEventListener('click', async () => {
        window.history.pushState({}, '', '/parent/register');
        await loadStudentOptions();
        showSection('register');
    });
}

if (showReset) {
    showReset.addEventListener('click', () => {
        window.history.pushState({}, '', '/forgot-password');
        showSection('reset');
    });
}

if (showLogin) {
    showLogin.addEventListener('click', () => {
        window.location.href = '/parent/login';
    });
}

if (showLoginFromReset) {
    showLoginFromReset.addEventListener('click', () => {
        window.location.href = '/parent/login';
    });
}

window.addEventListener('popstate', () => {
    showSection(getInitialAuthView());
});

if (getInitialAuthView() === 'register') {
    loadStudentOptions();
}

showSection(getInitialAuthView());
