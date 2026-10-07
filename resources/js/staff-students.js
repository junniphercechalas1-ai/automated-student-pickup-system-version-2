const studentForm = document.getElementById('studentForm');
const studentMessage = document.getElementById('studentMessage');
const studentsList = document.getElementById('studentsList');
const refreshStudents = document.getElementById('refreshStudents');

function showMessage(message, isError = false) {
    if (!studentMessage) return;
    studentMessage.textContent = message;
    studentMessage.className = isError
        ? 'text-sm text-rose-600'
        : 'text-sm text-emerald-600';
}

async function loadStudents() {
    if (!studentsList) return;

    studentsList.innerHTML = 'Loading students...';

    try {
        const response = await fetch('/supabase/students', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });

        const data = await response.json();
        if (!response.ok) {
            studentsList.innerHTML = '<div class="text-sm text-rose-600">Failed to load students.</div>';
            console.error('Failed to load students:', data);
            return;
        }

        if (!data.length) {
            studentsList.innerHTML = '<div class="text-sm text-slate-500">No students added yet.</div>';
            return;
        }

        studentsList.innerHTML = data.map((student) => `
            <div class="rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200">
                <p class="font-semibold text-slate-950">${student.full_name || 'N/A'}</p>
                <p class="text-slate-600">Grade: ${[student.grade_level, student.section].filter(Boolean).join(' - ') || 'N/A'}</p>
            </div>
        `).join('');
    } catch (error) {
        studentsList.innerHTML = '<div class="text-sm text-rose-600">Failed to load students.</div>';
        console.error('Failed to load students:', error);
    }
}

async function handleStudentSubmit(event) {
    event.preventDefault();
    if (!studentForm) return;

    const formData = new FormData(studentForm);
    const fullName = formData.get('full_name');
    const gradeLevel = formData.get('grade_level');

    if (!fullName || !gradeLevel) {
        showMessage('Please fill all fields.', true);
        return;
    }

    showMessage('Saving student...', false);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const submitData = new FormData();
    submitData.append('full_name', String(fullName));
    submitData.append('grade_level', String(gradeLevel));
    submitData.append('section', String(formData.get('section') || ''));

    const response = await fetch('/supabase/students', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: submitData,
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        showMessage(payload.message || 'Failed to save student.', true);
        console.error('Insert student error:', payload);
        return;
    }

    showMessage('Student added successfully.');
    studentForm.reset();
    loadStudents();
}

if (studentForm) {
    studentForm.addEventListener('submit', handleStudentSubmit);
}

if (refreshStudents) {
    refreshStudents.addEventListener('click', () => {
        loadStudents();
    });
}

loadStudents();
