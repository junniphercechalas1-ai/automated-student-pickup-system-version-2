import QRCode from 'qrcode';

window.addEventListener('DOMContentLoaded', async () => {
    const qrCanvas = document.getElementById('parentQrCodeCanvas');
    if (!qrCanvas) {
        return;
    }

    const qrValue = qrCanvas.dataset.qrValue;
    if (!qrValue) {
        return;
    }

    const refreshButton = document.getElementById('refreshParentQr');
    const refreshStatus = document.getElementById('parentQrRefreshStatus');
    const expiryStatus = document.getElementById('parentQrExpiryStatus');
    const hasGeneratedQr = qrCanvas.dataset.generated === 'true';
    let qrToken = qrValue;

    try {
        qrToken = JSON.parse(qrValue).qr_token || qrValue;
    } catch (error) {
    }

    refreshButton?.addEventListener('click', () => {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Generating...';
        if (refreshStatus) {
            refreshStatus.textContent = 'Generating a new QR code and expiration time...';
        }
        window.sessionStorage.setItem('parentQrGenerated', 'true');
        window.sessionStorage.setItem('parentQrRefreshPending', 'true');
        const refreshUrl = new URL(window.location.href);
        refreshUrl.searchParams.set('qr_refresh', `${Date.now()}-${Math.random().toString(36).slice(2)}`);
        window.location.assign(refreshUrl.toString());
    });

    if (!hasGeneratedQr) {
        if (refreshButton) {
            refreshButton.textContent = 'Generate QR Code';
        }
        return;
    }

    qrCanvas.classList.remove('hidden');
    if (refreshButton) {
        refreshButton.textContent = 'Refresh QR Code';
    }

    const storedQrValue = window.sessionStorage.getItem('parentQrValue');
    const refreshPending = window.sessionStorage.getItem('parentQrRefreshPending') === 'true';
    const wasRefreshed = refreshPending || (storedQrValue && storedQrValue !== qrValue);

    if (refreshStatus && wasRefreshed) {
        refreshStatus.textContent = `QR code refreshed successfully at ${new Date().toLocaleTimeString()}`;
        refreshStatus.classList.remove('text-gray-500');
        refreshStatus.classList.add('text-green-600');
    }

    window.sessionStorage.removeItem('parentQrRefreshPending');
    window.sessionStorage.setItem('parentQrValue', qrValue);

    let expiresAt = qrCanvas.dataset.expiresAt;
    if (!expiresAt) {
        try {
            const encodedPayload = qrToken.split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
            const payload = JSON.parse(window.atob(encodedPayload.padEnd(Math.ceil(encodedPayload.length / 4) * 4, '=')));
            expiresAt = payload.expires_at;
        } catch (error) {
            console.warn('Unable to read QR expiry from token:', error);
        }
    }

    if (expiresAt && expiryStatus) {
        const expiryDate = new Date(expiresAt);
        expiryStatus.textContent = `QR Code expires at: ${expiryDate.toLocaleTimeString()}`;
        const updateExpiryLabel = () => {
            if (Date.now() >= expiryDate.getTime()) {
                expiryStatus.textContent = 'QR Code has expired.';
                expiryStatus.classList.remove('text-amber-600');
                expiryStatus.classList.add('text-rose-600');
                return;
            }

            expiryStatus.textContent = `QR Code expires at: ${expiryDate.toLocaleTimeString()}`;
        };

        updateExpiryLabel();
        window.setInterval(updateExpiryLabel, 1000);
    }

    try {
        await QRCode.toCanvas(qrCanvas, qrValue, {
            width: 240,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch (error) {
        console.error('Failed to render QR code:', error);
    }
});
