export function bindPhoneSignupVerification({
    form,
    phoneInput,
    accountType,
    normalizePhoneNumber,
    showMessage,
}) {
    if (!form || !phoneInput) {
        return () => null;
    }

    const sendButton = form.querySelector('[data-phone-otp-send]');
    const verifyButton = form.querySelector('[data-phone-otp-verify]');
    const otpSection = form.querySelector('[data-phone-otp-section]');
    const otpInput = form.querySelector('[data-phone-otp-input]');
    const status = form.querySelector('[data-phone-otp-status]');
    let flowId = null;
    let pendingPhone = null;
    let verifiedPhone = null;
    let verifiedAccountType = null;
    otpInput.required = false;

    if (!sendButton || !verifyButton || !otpSection || !otpInput || !status) {
        return () => null;
    }

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const postJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.message || 'Phone verification failed.');
        }
        return payload;
    };

    sendButton.addEventListener('click', async () => {
        if (!phoneInput.reportValidity()) {
            return;
        }

        pendingPhone = normalizePhoneNumber(phoneInput.value);
        sendButton.disabled = true;
        showMessage('Sending verification code by SMS...', false);

        try {
            const payload = await postJson('/supabase/phone-verification/request', {
                account_type: typeof accountType === 'function' ? accountType() : accountType,
                phone_number: pendingPhone,
            });
            flowId = payload.flow_id;
            verifiedPhone = null;
            verifiedAccountType = null;
            otpSection.classList.remove('hidden');
            otpInput.required = true;
            otpInput.focus();
            status.textContent = `Code sent to ${payload.masked_phone}.`;
            showMessage(payload.message || 'Enter the SMS code to verify your number.', false);
        } catch (error) {
            showMessage(error.message || 'Unable to send a verification code.', true);
        } finally {
            sendButton.disabled = false;
        }
    });

    verifyButton.addEventListener('click', async () => {
        if (!flowId || !pendingPhone) {
            showMessage('Request a verification code first.', true);
            return;
        }
        if (!otpInput.reportValidity()) {
            return;
        }

        verifyButton.disabled = true;
        showMessage('Verifying mobile number...', false);

        try {
            const payload = await postJson('/supabase/phone-verification/confirm', {
                flow_id: flowId,
                otp: otpInput.value,
            });
            verifiedPhone = pendingPhone;
            verifiedAccountType = typeof accountType === 'function' ? accountType() : accountType;
            otpSection.classList.add('hidden');
            otpInput.required = false;
            otpInput.value = '';
            sendButton.textContent = 'Send a new verification code';
            status.textContent = payload.message || 'Mobile number verified.';
            showMessage('Mobile number verified. You can now create your account.', false);
        } catch (error) {
            showMessage(error.message || 'The code could not be verified.', true);
        } finally {
            verifyButton.disabled = false;
        }
    });

    phoneInput.addEventListener('input', () => {
        const currentPhone = normalizePhoneNumber(phoneInput.value);
        if (currentPhone === pendingPhone) {
            return;
        }

        flowId = null;
        pendingPhone = null;
        verifiedPhone = null;
        verifiedAccountType = null;
        otpInput.value = '';
        otpInput.required = false;
        otpSection.classList.add('hidden');
        sendButton.textContent = 'Send verification code';
        status.textContent = '';
    });

    form.addEventListener('reset', () => {
        flowId = null;
        pendingPhone = null;
        verifiedPhone = null;
        verifiedAccountType = null;
        otpInput.value = '';
        otpInput.required = false;
        otpSection.classList.add('hidden');
        sendButton.textContent = 'Send verification code';
        status.textContent = '';
    });

    return (phoneValue) => {
        const normalizedPhone = normalizePhoneNumber(phoneValue);
        const currentAccountType = typeof accountType === 'function' ? accountType() : accountType;
        return verifiedPhone === normalizedPhone && verifiedAccountType === currentAccountType ? flowId : null;
    };
}
