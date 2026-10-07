import { bindPhoneSignupVerification } from './phoneSignupVerification.js';

const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
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
const getVerifiedSignupFlowId = bindPhoneSignupVerification({
    form: registerForm,
    phoneInput: registerForm?.querySelector('[name="phone_number"]'),
    accountType: 'staff',
    normalizePhoneNumber,
    showMessage,
});
const getVerifiedUsernameSetupFlowId = bindPhoneSignupVerification({
    form: usernameSetupForm,
    phoneInput: usernameSetupForm?.querySelector('[name="phone_number"]'),
    accountType: 'staff',
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
        setTitle('Staff Registration');
        return;
    }

    if (name === 'reset' && resetSection) {
        resetSection.classList.remove('hidden');
        setTitle('Forgot Password');
        return;
    }

    if (loginSection) {
        loginSection.classList.remove('hidden');
        setTitle('Staff Access');
    }
}

function getInitialAuthView() {
    const path = window.location.pathname.replace(/\/$/, '');

    if (path === '/staff/login' || path === '/staff/') {
        return 'login';
    }

    if (path === '/staff/register') {
        return 'register';
    }

    if (path === '/staff/forgot-password') {
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
    const remember = formData.get('remember') === 'on';

    showMessage('Signing in...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let loginResponse;
    try {
        loginResponse = await fetch('/supabase/username-login', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({ username, password, account_type: 'staff' }),
        });
    } catch (error) {
        console.error('Staff sign-in request failed.', error);
        showMessage('Unable to reach the sign-in service. Check your connection and try again.', true);
        return;
    }

    const loginPayload = await loginResponse.json().catch(() => ({}));
    if (!loginResponse.ok) {
        const message = loginResponse.status === 419
            ? 'Your sign-in session expired. Refresh the page and try again.'
            : loginPayload.message || `The sign-in service returned an error (${loginResponse.status}). Please try again.`;
        showMessage(message, true);
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

    const user = await sessionResp.json();
    if (!user?.id) {
        showMessage('Signed in, but the account details could not be verified. Please try again.', true);
        return;
    }

    const userRole = user?.user_metadata?.role;
    let isStaffRecordAuthorized = false;
    try {
        const staffCheckResponse = await fetch(`/supabase/staff-check?user_id=${encodeURIComponent(user.id)}`);
        if (!staffCheckResponse.ok) {
            showMessage('Signed in, but staff approval could not be checked. Please try again or contact the administrator.', true);
            return;
        }
        const staffPayload = await staffCheckResponse.json();
        isStaffRecordAuthorized = Boolean(staffPayload.authorized);
    } catch {
        showMessage('Signed in, but staff approval could not be checked. Please try again or contact the administrator.', true);
        return;
    }

    const isStaffAuthorized = userRole === 'admin' || isStaffRecordAuthorized;

    if (!isStaffAuthorized) {
        showMessage('Your staff account is waiting for administrator approval.', true);
        return;
    }

    if (remember) {
        localStorage.setItem('staffUsername', username);
    }

    window.location.href = '/staff/pickup-verification';
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

async function handleRegister(event) {
    event.preventDefault();
    if (!registerForm) return;

    const formData = new FormData(registerForm);
    const firstName = String(formData.get('first_name') || '').trim();
    const middleName = String(formData.get('middle_name') || '').trim();
    const lastName = String(formData.get('last_name') || '').trim();
    const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ');
    const username = String(formData.get('username') || '').trim();
    const phoneNumber = formData.get('phone_number');
    const phoneVerificationFlowId = getVerifiedSignupFlowId(phoneNumber);
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    const termsAgree = formData.get('terms_agree');

    if (password !== confirmPassword) {
        showMessage('Passwords do not match.', true);
        return;
    }

    if (password.length < 8) {
        showMessage('Password must be at least 8 characters long.', true);
        return;
    }

    if (!termsAgree) {
        showMessage('You must agree to the staff code of conduct.', true);
        return;
    }

    if (!phoneVerificationFlowId) {
        showMessage('Verify your mobile number before creating your account.', true);
        return;
    }

    showMessage('Submitting staff registration for approval...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const requestBody = JSON.stringify({
        full_name: String(fullName),
        username,
        password: String(password),
        mobile_number: String(phoneNumber),
        phone_verification_flow_id: phoneVerificationFlowId,
        role: 'staff',
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
        const message = String(payload.message || 'Unable to create staff account right now.');
        showMessage(message, true);
        return;
    }

    showMessage('Account submitted. Your mobile number is verified. Please wait for administrator approval before signing in.', false);
    registerForm.reset();
    
    // Show login after 3 seconds
    setTimeout(() => {
        window.history.pushState({}, '', '/staff/login');
        showSection('login');
    }, 3000);
}

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
            account_type: 'staff',
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

if (loginForm) {
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

if (showRegister) {
    showRegister.addEventListener('click', () => {
        window.history.pushState({}, '', '/staff/register');
        showSection('register');
    });
}

if (showReset) {
    showReset.addEventListener('click', () => {
        window.location.assign('/staff/login#forgot-password');
    });
}

if (showLogin) {
    showLogin.addEventListener('click', () => {
        window.history.pushState({}, '', '/staff/login');
        showSection('login');
    });
}

if (showLoginFromReset) {
    showLoginFromReset.addEventListener('click', () => {
        window.history.pushState({}, '', '/staff/login');
        showSection('login');
    });
}

window.addEventListener('popstate', () => {
    showSection(getInitialAuthView());
});

showSection(getInitialAuthView());
