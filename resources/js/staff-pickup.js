import jsQR from 'jsqr';

// Defer DOM-dependent initialization until the document is ready
let stream = null;
let scanning = false;
let frameId = null;
let lastScannedCode = null;
let scanCooldownActive = false;
let processingScan = false;
let resultMessageActive = false;
const SCAN_COOLDOWN_MS = 2000; // 2 second cooldown after successful scan
const RESULT_MESSAGE_MS = 2500;

function setStatusElementText(element, text, name) {
    if (element) {
        element.textContent = text;
    } else {
        console.warn(`⚠️  ${name} element not found in DOM`);
    }
}

function formatTime(date) {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
    }).format(date);
}

function formatScannedQrValue(code) {
    try {
        const payload = JSON.parse(code);
        const identifiers = [
            payload.parent_id ? `Parent #${payload.parent_id}` : null,
            payload.student_id ? `Student #${payload.student_id}` : null,
        ].filter(Boolean);

        if (payload.qr_token) {
            return identifiers.length ? identifiers.join(' • ') : 'Signed parent QR token';
        }

        return identifiers.length ? identifiers.join(' • ') : 'QR payload detected';
    } catch (error) {
        return code;
    }
}

async function resolveStudent(studentId) {
    console.log('🔍 resolveStudent() called with student ID:', studentId);
    
    const response = await fetch(`/supabase/student?id=${encodeURIComponent(studentId)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    });

    console.log('📡 resolveStudent response status:', response.status);
    
    const payload = await response.json().catch(() => ({}));
    console.log('📦 resolveStudent payload:', payload);
    
    if (!response.ok) {
        throw new Error(payload.message || 'Unable to resolve student.');
    }

    return payload;
}

async function resolveParent(parentIdentifier) {
    if (!parentIdentifier) {
        console.log('⚠️  No parent ID provided, skipping parent lookup');
        return null;
    }

    console.log('🔍 resolveParent() called with identifier:', parentIdentifier);

    const query = `id=${encodeURIComponent(parentIdentifier)}`;
    
    const response = await fetch(`/supabase/parent?${query}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    });

    console.log('📡 resolveParent response status:', response.status);
    
    const payload = await response.json().catch(() => ({}));
    console.log('📦 resolveParent payload:', payload);
    
    if (!response.ok) {
        console.warn('⚠️  Unable to resolve parent:', payload.message);
        return null;
    }

    return payload;
}

async function verifyParentQrToken(token) {
    const response = await fetch(`/supabase/verify-parent-qr?token=${encodeURIComponent(token)}`, {
        headers: { 'Accept': 'application/json' },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(payload.message || 'QR verification failed.');
        error.status = response.status;
        error.isExpired = response.status === 410 || /expired/i.test(error.message);
        throw error;
    }
    return payload;
}

async function markPickup(student, parentId = null) {
    console.log('📝 markPickup() called for student:', student, 'parent_id:', parentId);
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const pickupPayload = {
        student_id: student.id,
    };

    // Include parent_id if available (from QR scan)
    if (parentId) {
        pickupPayload.parent_id = parentId;
        console.log('✓ Parent ID included in pickup payload');
    } else {
        console.warn('⚠️  No parent ID found in QR code');
    }

    const response = await fetch('/supabase/pickups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify(pickupPayload),
    });

    console.log('📡 markPickup response status:', response.status);
    
    const payload = await response.json().catch(() => ({}));
    console.log('📦 markPickup payload:', payload);

    if (!response.ok) {
        console.error('❌ Failed to mark pickup:', payload.message || payload);
        const error = new Error(payload.message || 'Unable to record pickup.');
        error.status = response.status;
        throw error;
    }
}

async function onScanSuccess(code) {
    console.log('✅ onScanSuccess() - QR detected:', code);
    
    if (!scanning) {
        console.warn('⚠️  Scanner not in scanning mode, ignoring detection');
        return;
    }

    // Prevent duplicate scans from the same QR code
    if (lastScannedCode === code) {
        console.log('⏭️  Duplicate scan detected, skipping');
        return;
    }
    lastScannedCode = code;

    // Parse QR payload - can be JSON object or plain string
    let qrPayload = null;
    let parentId = null;
    let studentId = null;

    try {
        qrPayload = JSON.parse(code);
        console.log('📋 QR payload parsed as JSON:', qrPayload);
        parentId = qrPayload.parent_id || null;
        studentId = qrPayload.student_id || null;
        if (parentId) {
            console.log('✓ Parent ID extracted from QR: ', parentId);
        }
    } catch (e) {
        // QR is not JSON, treat as plain student qr_code (backward compatibility)
        console.log('📋 QR is plain text (not JSON), treating as student qr_code');
    }

    if (qrPayload?.qr_token) {
        const verified = await verifyParentQrToken(qrPayload.qr_token);
        parentId = verified.parent?.id || null;
        studentId = verified.student?.id || null;
    }

    setStatus('Processing QR code...');
    
    if (lastCode) {
        studentId = code;
        lastCode.textContent = studentId;
    } else {
        console.warn('⚠️  lastCode element not found');
    }

    try {
        const student = await resolveStudent(studentId);
        console.log('📚 Student resolved:', student);

        if (!student) {
            setStatus('Student not found.');
            return;
        }

        console.log('🎯 Updating UI with student data');
        
        if (studentNameText) {
            studentNameText.textContent = student.name || '-';
        } else {
            console.warn('⚠️  studentNameText element not found');
        }
        
        if (studentName) {
            studentName.textContent = student.name ? student.name.substring(0, 1).toUpperCase() : 'S';
        } else {
            console.warn('⚠️  studentName element not found');
        }
        
        if (studentId) {
            studentId.textContent = student.id || '-';
        } else {
            console.warn('⚠️  studentId element not found');
        }
        
        if (studentClass) {
            studentClass.textContent = student.class || '-';
        } else {
            console.warn('⚠️  studentClass element not found');
        }
        
        if (pickupTime) {
            pickupTime.textContent = formatTime(new Date());
        } else {
            console.warn('⚠️  pickupTime element not found');
        }
        
        console.log('📤 Sending pickup record to backend');
        await markPickup(student, parentId);
        setStatus('Student pickup marked.');
    } catch (error) {
        console.error('❌ Scan error:', error);
        setStatus(error.message || 'Scan failed.');
    }
}

function scanFrame() {
    if (!video || !canvas) {
        console.error('❌ Video or canvas element missing');
        return;
    }
    
    const context = canvas.getContext('2d');
    if (!context) {
        console.error('❌ Canvas context not available');
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height);

    if (code && code.data) {
        console.log('🎯 QR code detected by jsQR library');
        onScanSuccess(code.data);
    }

    if (scanning) {
        frameId = requestAnimationFrame(scanFrame);
    }
}

async function startCamera() {
    if (scanning) {
        console.log('⚠️  Camera already scanning');
        return;
    }

    try {
        console.log('📹 Requesting camera access...');
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        console.log('✅ Camera stream acquired');
        
        video.srcObject = stream;
        await video.play();
        console.log('▶️  Video playback started');
        
        scanning = true;
        setStatus('Scanning active. Point camera at QR code.');
        
        console.log('🔄 Starting frame scan loop');
        frameId = requestAnimationFrame(scanFrame);
    } catch (error) {
        console.error('❌ Camera start failed:', error);
        setStatus('Camera permission denied or not available.');
    }
}

function stopCamera() {
    console.log('⏹️  Stopping camera');
    scanning = false;
    if (frameId) {
        cancelAnimationFrame(frameId);
        frameId = null;
    }

    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    setStatus('Scanner stopped.');
}

// Initialize after DOM is ready to avoid null element lookups
window.addEventListener('DOMContentLoaded', () => {
    const video = document.getElementById('scannerVideo');
    const canvas = document.getElementById('scannerCanvas');
    const startScan = document.getElementById('startScan');
    const stopScan = document.getElementById('stopScan');
    const scanFrame = document.querySelector('.staff-portal-scan-frame');
    const scanBox = document.getElementById('scanBox');
    const scanHud = document.getElementById('scanHud');
    const pickerStatus = document.getElementById('pickerStatus');
    const verificationIndicator = document.getElementById('verificationIndicator');
    const lastCode = document.getElementById('lastCode');
    const studentName = document.getElementById('studentName');
    const studentNameText = document.getElementById('studentNameText');
    const studentIdElement = document.getElementById('studentId');
    const studentClass = document.getElementById('studentClass');
    const pickupTime = document.getElementById('pickupTime');
    const scanResult = document.getElementById('scanResult');

    // Local wrapper for setStatus using the DOM element
    function setStatus(text, state = 'neutral') {
        console.log('🔔 setStatus:', text);
        setStatusElementText(pickerStatus, text, 'pickerStatus');
        if (pickerStatus) {
            pickerStatus.className = `staff-portal-picker-status ${state}`;
        }
    }

    function setVerificationIndicator(state) {
        if (!verificationIndicator) {
            return;
        }

        verificationIndicator.className = `staff-portal-verification-indicator ${state}`;
        verificationIndicator.setAttribute('aria-label', `QR verification: ${state}`);
    }

    function setScanResult(text, state) {
        if (!scanResult) {
            return;
        }

        scanResult.textContent = text;
        scanResult.className = `staff-portal-status ${state}`;
    }

    function setScanOverlay(state, message = '') {
        if (scanBox) {
            scanBox.classList.toggle('aligned', state === 'aligned' || state === 'success');
            scanBox.classList.toggle('misaligned', state === 'misaligned');
            scanBox.classList.toggle('verifying', state === 'verifying');
            scanBox.classList.toggle('invalid', state === 'invalid');
            scanBox.classList.toggle('success', state === 'success');
        }

        if (scanHud) {
            scanHud.textContent = message;
            scanHud.classList.toggle('visible', Boolean(message));
            scanHud.classList.toggle('success', state === 'success');
        }
    }

    function isQrAligned(code) {
        if (!scanFrame || !scanBox || !code?.location) {
            return false;
        }

        const frameRect = scanFrame.getBoundingClientRect();
        const boxRect = scanBox.getBoundingClientRect();
        const scale = Math.max(video.clientWidth / video.videoWidth, video.clientHeight / video.videoHeight);
        const offsetX = (video.clientWidth - video.videoWidth * scale) / 2;
        const offsetY = (video.clientHeight - video.videoHeight * scale) / 2;
        const corners = [
            code.location.topLeftCorner,
            code.location.topRightCorner,
            code.location.bottomRightCorner,
            code.location.bottomLeftCorner,
        ];

        const points = corners.map((corner) => ({
            x: frameRect.left + video.offsetLeft + offsetX + corner.x * scale,
            y: frameRect.top + video.offsetTop + offsetY + corner.y * scale,
        }));
        const qrBounds = points.reduce((bounds, point) => ({
            left: Math.min(bounds.left, point.x),
            right: Math.max(bounds.right, point.x),
            top: Math.min(bounds.top, point.y),
            bottom: Math.max(bounds.bottom, point.y),
        }), { left: Infinity, right: -Infinity, top: Infinity, bottom: -Infinity });
        const qrCenterX = (qrBounds.left + qrBounds.right) / 2;
        const qrCenterY = (qrBounds.top + qrBounds.bottom) / 2;
        const boxCenterX = (boxRect.left + boxRect.right) / 2;
        const boxCenterY = (boxRect.top + boxRect.bottom) / 2;
        const qrWidth = qrBounds.right - qrBounds.left;
        const qrHeight = qrBounds.bottom - qrBounds.top;
        const centerToleranceX = boxRect.width * 0.22;
        const centerToleranceY = boxRect.height * 0.22;
        const fullyInside = points.every((point) => (
            point.x >= boxRect.left && point.x <= boxRect.right
            && point.y >= boxRect.top && point.y <= boxRect.bottom
        ));

        return fullyInside
            && Math.abs(qrCenterX - boxCenterX) <= centerToleranceX
            && Math.abs(qrCenterY - boxCenterY) <= centerToleranceY
            && qrWidth >= boxRect.width * 0.18
            && qrHeight >= boxRect.height * 0.18;
    }

    // Override functions that need DOM elements to close over them
    async function onScanSuccessWithDom(code) {
        console.log('✅ onScanSuccess() - QR detected:', code);

        if (!scanning) {
            console.warn('⚠️  Scanner not in scanning mode, ignoring detection');
            return;
        }

        if (processingScan) {
            return;
        }

        // Check if cooldown is active (prevent double scans)
        if (scanCooldownActive) {
            console.log('⏳ Scan cooldown active, ignoring detection');
            return;
        }

        if (lastScannedCode === code) {
            console.log('⏭️  Duplicate scan detected, skipping');
            return;
        }
        lastScannedCode = code;
        processingScan = true;
        setScanOverlay('verifying', 'VERIFYING');
        setVerificationIndicator('processing');
        setStatus('Verifying QR Code...');
        setStatusElementText(lastCode, formatScannedQrValue(code), 'lastCode');
        if (lastCode) {
            lastCode.title = code;
        }

        // Parse QR payload - can be JSON object or plain string
        let qrPayload = null;
        let parentId = null;
        let studentId = null;

        try {
            qrPayload = JSON.parse(code);
            console.log('📋 QR payload parsed as JSON:', qrPayload);
            parentId = qrPayload.parent_id || null;
            studentId = qrPayload.student_id || null;
            if (parentId) {
                console.log('✓ Parent ID extracted from QR:', parentId);
            }
        } catch (e) {
            // QR is not JSON, treat as plain student qr_code (backward compatibility)
            console.log('📋 QR is plain text (not JSON), treating as student qr_code');
        }

        try {
            setVerificationIndicator('processing');
            if (qrPayload?.qr_token) {
                const verified = await verifyParentQrToken(qrPayload.qr_token);
                parentId = verified.parent?.id || null;
                studentId = verified.student?.id || null;
            }

            studentId = studentId || code;

            const student = await resolveStudent(studentId);
            console.log('📚 Student resolved:', student);

            if (!student) {
                throw new Error('Student not found.');
            }

            console.log('🎯 Updating UI with student data');
            setStatusElementText(studentNameText, student.name || '-', 'studentNameText');
            setStatusElementText(studentName, student.name ? student.name.substring(0, 1).toUpperCase() : 'S', 'studentName');
            setStatusElementText(studentIdElement, student.id || '-', 'studentId');
            setStatusElementText(studentClass, student.class || '-', 'studentClass');
            setStatusElementText(pickupTime, formatTime(new Date()), 'pickupTime');

            // If we have a parent_id, look up the guardian name
            if (parentId) {
                const parent = await resolveParent(parentId);
                if (parent && parent.full_name) {
                    console.log('👨‍👩‍👧 Guardian name resolved:', parent.full_name);
                    const guardianNameElement = document.getElementById('guardianName');
                    setStatusElementText(guardianNameElement, parent.full_name, 'guardianName');
                } else {
                    console.warn('⚠️  Could not resolve guardian name for parent_id:', parentId);
                    const guardianNameElement = document.getElementById('guardianName');
                    setStatusElementText(guardianNameElement, '-', 'guardianName');
                }
            } else {
                const guardianNameElement = document.getElementById('guardianName');
                setStatusElementText(guardianNameElement, '-', 'guardianName');
            }

            console.log('📤 Sending pickup record to backend');
            await markPickup(student, parentId);
            setVerificationIndicator('valid');
            setScanResult('Success', 'success');
            setScanOverlay('success', 'SUCCESS');
            setStatus('QR Code Verified', 'success');
            if (frameId) {
                cancelAnimationFrame(frameId);
                frameId = null;
            }

            // Keep the verified result visible and pause until the next explicit start.
            scanCooldownActive = true;
            console.log('⏸️ Scanner paused after successful verification');
        } catch (error) {
            console.error('❌ Scan error:', error);
            const expired = error.isExpired || error.status === 410 || /expired/i.test(error.message || '');
            setVerificationIndicator(expired ? 'expired' : 'invalid');
            setScanResult('Failed', 'error');
            setScanOverlay('invalid', expired ? 'FAILED' : 'INVALID');
            setStatus(expired ? 'QR Code Expired - Failed' : 'Invalid QR Code - Failed', 'error');
            lastScannedCode = null;
            processingScan = false;
            resultMessageActive = true;
            window.setTimeout(() => {
                resultMessageActive = false;
            }, RESULT_MESSAGE_MS);
        }
    }

    // Replace scanFrame to use the DOM-scoped onScanSuccess
    function scanFrameDomScoped() {
        if (!video || !canvas) {
            console.error('❌ Video or canvas element missing');
            return;
        }

        if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || video.videoWidth === 0 || video.videoHeight === 0) {
            if (scanning) {
                frameId = requestAnimationFrame(scanFrameDomScoped);
            }
            return;
        }

        const context = canvas.getContext('2d');
        if (!context) {
            console.error('❌ Canvas context not available');
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'attemptBoth',
        });

        if (processingScan || resultMessageActive) {
            if (scanning) {
                frameId = requestAnimationFrame(scanFrameDomScoped);
            }
            return;
        }

        if (code && code.data) {
            console.log('🎯 QR code detected by jsQR library');
            const aligned = isQrAligned(code);
            setScanOverlay(aligned ? 'aligned' : 'misaligned');
            setVerificationIndicator(aligned ? 'ready' : 'invalid');
            setStatus(aligned ? 'QR Code Ready — Hold Steady' : 'Please align the QR code.');
            if (aligned) {
                onScanSuccessWithDom(code.data);
            }
        } else {
            setScanOverlay('idle');
            setVerificationIndicator('invalid');
            setStatus('Position QR code inside the frame.');
        }

        if (scanning) {
            frameId = requestAnimationFrame(scanFrameDomScoped);
        }
    }

    // Start camera now refers to DOM-scoped scanFrame
    async function startCameraDomScoped() {
        if (scanning) {
            console.log('⚠️  Camera already scanning');
            return;
        }

        if (!window.isSecureContext) {
            setStatus('Camera access needs HTTPS. Open the staff portal using its secure HTTPS address on this phone.', 'error');
            setVerificationIndicator('invalid');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus('This browser does not support camera access. Open the portal in an up-to-date browser.', 'error');
            setVerificationIndicator('invalid');
            return;
        }

        try {
            console.log('📹 Requesting camera access...');
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            console.log('✅ Camera stream acquired');

            video.srcObject = stream;
            video.muted = true;
            video.playsInline = true;
            await video.play();
            console.log('▶️  Video playback started');

            scanning = true;
            processingScan = false;
            resultMessageActive = false;
            scanCooldownActive = false;
            lastScannedCode = null;
            setScanOverlay('idle');
            setVerificationIndicator('invalid');
            setStatus('Position QR code inside the frame.');

            console.log('🔄 Starting frame scan loop');
            frameId = requestAnimationFrame(scanFrameDomScoped);
        } catch (error) {
            console.error('❌ Camera start failed:', error);
            stream?.getTracks().forEach((track) => track.stop());
            stream = null;
            const messages = {
                NotAllowedError: 'Camera permission is blocked. Allow camera access for this site in your browser settings, then try again.',
                NotFoundError: 'No camera was found on this device.',
                NotReadableError: 'The camera is busy in another app. Close other camera apps and try again.',
                OverconstrainedError: 'The camera does not support the requested settings. Reload the page and try again.',
            };
            setStatus(messages[error?.name] || 'Unable to start the camera. Check browser permissions and try again.', 'error');
            setVerificationIndicator('invalid');
        }
    }

    function stopCameraDomScoped() {
        console.log('⏹️  Stopping camera');
        scanning = false;
        processingScan = false;
        if (frameId) {
            cancelAnimationFrame(frameId);
            frameId = null;
        }

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        setScanOverlay('idle');
        setVerificationIndicator('neutral');
        setStatus('Scanner stopped.');
    }

    if (startScan) {
        console.log('📌 startScan button found, attaching listener');
        startScan.addEventListener('click', startCameraDomScoped);
    } else {
        console.error('❌ startScan button not found');
    }

    if (stopScan) {
        console.log('📌 stopScan button found, attaching listener');
        stopScan.addEventListener('click', stopCameraDomScoped);
    } else {
        console.error('❌ stopScan button not found');
    }

    window.addEventListener('beforeunload', stopCameraDomScoped);

    console.log('✅ staff-pickup.js initialized after DOMContentLoaded');
});
