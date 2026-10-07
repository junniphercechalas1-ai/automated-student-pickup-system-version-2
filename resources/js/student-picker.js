import jsQR from 'jsqr';

const video = document.getElementById('scannerVideo');
const canvas = document.getElementById('scannerCanvas');
const startScan = document.getElementById('startScan');
const stopScan = document.getElementById('stopScan');
const pickerStatus = document.getElementById('pickerStatus');
const lastCode = document.getElementById('lastCode');
const studentName = document.getElementById('studentName');
const studentId = document.getElementById('studentId');
const studentClass = document.getElementById('studentClass');
const pickupTime = document.getElementById('pickupTime');
const scanHistories = Array.from(document.querySelectorAll('[data-scan-history]'));

let stream = null;
let scanning = false;
let frameId = null;

function setStatus(text) {
    if (pickerStatus) pickerStatus.textContent = text;
}

function formatTime(date) {
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
    }).format(date);
}

function appendHistory(entry) {
    if (scanHistories.length === 0) return;

    scanHistories.forEach((historyList) => {
        const li = document.createElement('li');
        li.className = 'rounded-2xl bg-white p-3 border border-slate-200 shadow-sm';
        li.innerHTML = `
            <div class="font-medium">${entry.name}</div>
            <div class="text-xs text-slate-500">${entry.id} • ${entry.class} • ${entry.time}</div>
        `;

        historyList.prepend(li);
        while (historyList.children.length > 8) {
            historyList.removeChild(historyList.lastElementChild);
        }
    });
}

async function resolveStudent(code) {
    if (!code) {
        throw new Error('QR code is required to resolve a student.');
    }

    const response = await fetch(`/supabase/student?qr_code=${encodeURIComponent(code)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || 'Unable to resolve student.');
    }

    return payload;
}

async function markPickup(student) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const response = await fetch('/supabase/pickups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify({
            student_id: student.id,
        }),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        console.error('Failed to mark pickup:', payload.message || payload);
    }
}

async function onScanSuccess(code) {
    if (!scanning) return;

    setStatus('Processing QR code...');
    lastCode.textContent = code;

    try {
        const student = await resolveStudent(code);

        if (!student) {
            setStatus('Student not found.');
            return;
        }

        studentName.textContent = student.name || '-';
        studentId.textContent = student.id || '-';
        studentClass.textContent = student.class || '-';
        pickupTime.textContent = formatTime(new Date());
        appendHistory({
            name: student.name || 'Unknown student',
            id: student.id || 'N/A',
            class: student.class || 'N/A',
            time: formatTime(new Date()),
        });
        await markPickup(student);
        setStatus('Student pickup marked.');
    } catch (error) {
        console.error(error);
        setStatus(error.message || 'Scan failed.');
    }
}

function scanFrame() {
    if (!video || !canvas) return;
    const context = canvas.getContext('2d');
    if (!context) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height);

    if (code && code.data) {
        onScanSuccess(code.data);
    }

    if (scanning) {
        frameId = requestAnimationFrame(scanFrame);
    }
}

async function startCamera() {
    if (scanning) {
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = stream;
        await video.play();
        scanning = true;
        setStatus('Scanning active. Point camera at QR code.');
        frameId = requestAnimationFrame(scanFrame);
    } catch (error) {
        console.error('Camera start failed:', error);
        setStatus('Camera permission denied or not available.');
    }
}

function stopCamera() {
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

if (startScan) {
    startScan.addEventListener('click', startCamera);
}

if (stopScan) {
    stopScan.addEventListener('click', stopCamera);
}

window.addEventListener('beforeunload', stopCamera);
