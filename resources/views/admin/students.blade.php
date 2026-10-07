@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Students</h1>
            <p class="text-gray-600 mt-1">View, add, edit, and manage student information.</p>
        </div>
        <button onclick="openStudentModal('create')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>Add New Student</span>
        </button>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 gap-6 mb-8">
        <!-- Total Students -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Students</p>
                    <p id="totalStudentsCount" class="text-3xl font-bold text-gray-900 mt-2">0</p>
                    <p class="text-xs text-gray-500 mt-1">Registered</p>
                </div>
                <div class="bg-blue-100 rounded-lg p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Grade Level</label>
                <select id="gradeLevelFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Grade Levels</option>
                    <option value="Grade 1">Grade 1</option>
                    <option value="Grade 2">Grade 2</option>
                    <option value="Grade 3">Grade 3</option>
                    <option value="Grade 4">Grade 4</option>
                    <option value="Grade 5">Grade 5</option>
                    <option value="Grade 6">Grade 6</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                <select id="sectionFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Sections</option>
                    <option value="Section A">A</option>
                    <option value="Section B">B</option>
                    <option value="Section C">C</option>
                    <option value="Section D">D</option>
                    <option value="Section E">E</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Student</label>
                <div class="flex">
                    <input id="studentSearch" type="search" placeholder="Search by name, ID, or guardian..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition" onclick="filterStudentRows()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STUDENT ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">FULL NAME</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">GRADE LEVEL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">SECTION</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">PARENT / GUARDIAN</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">STATUS</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">ACTIONS</th>
                </tr>
            </thead>
            <tbody id="studentsTable" class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">Loading students...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Student Modal -->
<div id="studentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900" id="studentModalTitle">Add Student</h3>
            <button onclick="closeStudentModal()"
                class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
        </div>

        <form id="studentForm" class="space-y-4">
            <input type="hidden" id="studentId">

            <div>
                <label for="studentFirstName" class="block text-sm font-medium text-gray-700">First Name</label>
                <input type="text" id="studentFirstName" required maxlength="100"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="studentMiddleName" class="block text-sm font-medium text-gray-700">Middle Name</label>
                <input type="text" id="studentMiddleName" maxlength="100"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="studentLastName" class="block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" id="studentLastName" required maxlength="100"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label for="studentGradeLevel" class="block text-sm font-medium text-gray-700">Grade Level</label>
                <select id="studentGradeLevel" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select grade level</option>
                    <option value="Grade 1">Grade 1</option>
                    <option value="Grade 2">Grade 2</option>
                    <option value="Grade 3">Grade 3</option>
                    <option value="Grade 4">Grade 4</option>
                    <option value="Grade 5">Grade 5</option>
                    <option value="Grade 6">Grade 6</option>
                </select>
            </div>

            <div>
                <label for="studentSection" class="block text-sm font-medium text-gray-700">Section</label>
                <select id="studentSection"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select section</option>
                    <option value="Section A">A</option>
                    <option value="Section B">B</option>
                    <option value="Section C">C</option>
                    <option value="Section D">D</option>
                    <option value="Section E">E</option>
                </select>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeStudentModal()"
                    class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Save Student
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterStudentRows() {
        const term = document.getElementById('studentSearch')?.value.trim().toLowerCase() ?? '';
        const grade = document.getElementById('gradeLevelFilter')?.value.toLowerCase() ?? '';
        const section = document.getElementById('sectionFilter')?.value.toLowerCase() ?? '';
        document.querySelectorAll('#studentsTable tr').forEach((row) => {
            const rowText = row.textContent.toLowerCase();
            const gradeSection = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() ?? '';
            row.hidden = (Boolean(term) && !rowText.includes(term))
                || (Boolean(grade) && !gradeSection.includes(grade))
                || (Boolean(section) && !gradeSection.includes(section));
        });
    }

    document.getElementById('studentSearch')?.addEventListener('input', filterStudentRows);
    document.getElementById('gradeLevelFilter')?.addEventListener('change', filterStudentRows);
    document.getElementById('sectionFilter')?.addEventListener('change', filterStudentRows);

    let studentRecords = [];

    // Load students on page load
    document.addEventListener('DOMContentLoaded', loadStudents);

    // Form submission
    document.getElementById('studentForm').addEventListener('submit', handleStudentSubmit);

    async function loadStudents() {
        try {
            const response = await fetch('{{ route("admin.students") }}?format=json', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load students');
            }

            const payload = await response.json();
            const students = Array.isArray(payload) ? payload : (payload.students || []);
            studentRecords = students;
            const counts = payload.counts || {
                total_students: students.length,
            };

            document.getElementById('totalStudentsCount').textContent = counts.total ?? counts.total_students ?? 0;

            const tbody = document.getElementById('studentsTable');
            tbody.innerHTML = '';

            if (students.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No students found</td></tr>';
                return;
            }

            students.forEach(student => {
                const studentId = student.student_id ?? student.id ?? 'N/A';
                const studentName = student.full_name || 'N/A';
                const gradeLevel = student.grade_level || 'N/A';
                const section = student.section || 'N/A';
                const parentName = student.parent_name ?? 'N/A';
                const isActive = student.is_active !== false;
                const status = student.status || (isActive ? 'Active' : 'Inactive');

                tbody.innerHTML += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${studentId}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${studentName}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${gradeLevel}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${section}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${parentName}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${status}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <button onclick="editStudent(${student.id ?? student.student_id ?? 0})" class="text-blue-600 hover:text-blue-900">Edit</button>
                            <button onclick="deleteStudent(${student.id ?? student.student_id ?? 0})" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading students:', error);
            document.getElementById('studentsTable').innerHTML =
                '<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error loading students</td></tr>';
        }
    }

    function openStudentModal(action) {
        document.getElementById('studentId').value = '';
        document.getElementById('studentForm').reset();
        document.getElementById('studentModalTitle').textContent = action === 'create' ? 'Add Student' :
            'Edit Student';
        document.getElementById('studentModal').classList.remove('hidden');
    }

    function closeStudentModal() {
        document.getElementById('studentModal').classList.add('hidden');
    }

    async function handleStudentSubmit(e) {
        e.preventDefault();
        const studentId = document.getElementById('studentId').value;
        const data = {
            first_name: document.getElementById('studentFirstName').value.trim(),
            middle_name: document.getElementById('studentMiddleName').value.trim() || null,
            last_name: document.getElementById('studentLastName').value.trim(),
            grade_level: document.getElementById('studentGradeLevel').value,
            section: document.getElementById('studentSection').value,
        };

        try {
            const url = studentId ? 
                `/admin/students/${studentId}` : 
                '{{ route("admin.students.store") }}';
            
            const method = studentId ? 'PATCH' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeStudentModal();
                loadStudents();
            } else {
                const payload = await response.json().catch(() => ({}));
                alert(payload.error || payload.message || 'Error saving student');
            }
        } catch (error) {
            console.error('Error:', error);
            alert(error.message || 'Error saving student');
        }
    }

    function editStudent(id) {
        const student = studentRecords.find((record) => String(record.id) === String(id));
        if (!student) {
            alert('Student record could not be found. Please refresh the page and try again.');
            return;
        }

        document.getElementById('studentId').value = student.id;
        const legacyNameParts = (student.full_name || '').trim().split(/\s+/).filter(Boolean);
        document.getElementById('studentFirstName').value = student.first_name || legacyNameParts[0] || '';
        document.getElementById('studentMiddleName').value = student.middle_name || (legacyNameParts.length > 2 ? legacyNameParts.slice(1, -1).join(' ') : '');
        document.getElementById('studentLastName').value = student.last_name || (legacyNameParts.length > 1 ? legacyNameParts[legacyNameParts.length - 1] : '');
        document.getElementById('studentGradeLevel').value = student.grade_level || '';
        document.getElementById('studentSection').value = student.section || '';
        document.getElementById('studentModalTitle').textContent = 'Edit Student';
        document.getElementById('studentModal').classList.remove('hidden');
    }

    async function deleteStudent(id) {
        if (confirm('Are you sure you want to delete this student?')) {
            try {
                const response = await fetch(`/admin/students/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    loadStudents();
                } else {
                    alert('Error deleting student');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting student');
            }
        }
    }
</script>
@endsection
