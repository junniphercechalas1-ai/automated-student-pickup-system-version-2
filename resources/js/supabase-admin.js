(function () {
    const adminRoot = document.getElementById('supabaseAdminRoot');
    if (!adminRoot) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    async function api(path, options = {}) {
        options.headers = Object.assign({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken || '' }, options.headers || {});
        const resp = await fetch(path, options);
        const body = await resp.json().catch(() => ({}));
        if (!resp.ok) throw { status: resp.status, body };
        return body;
    }

    function renderList(parents) {
        adminRoot.innerHTML = '';
        const table = document.createElement('table');
        table.className = 'w-full border-collapse';
        const thead = document.createElement('thead');
        thead.innerHTML = '<tr class="text-left"><th class="p-2">ID</th><th class="p-2">Name</th><th class="p-2">Mobile</th><th class="p-2">Actions</th></tr>';
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        parents.forEach((p) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="p-2">${p.id}</td><td class="p-2">${p.full_name || ''}</td><td class="p-2">${p.mobile_number || ''}</td><td class="p-2"><button data-id="${p.id}" class="edit-btn mr-2">Edit</button><button data-id="${p.id}" class="delete-btn text-rose-600">Delete</button></td>`;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        adminRoot.appendChild(table);

        adminRoot.querySelectorAll('.delete-btn').forEach((btn) => {
            btn.addEventListener('click', async (e) => {
                const id = e.target.dataset.id;
                if (!confirm('Delete parent ' + id + '?')) return;
                try {
                    await api('/supabase/admin/parents/' + id, { method: 'DELETE' });
                    await load();
                } catch (err) { alert('Delete failed: ' + (err.body?.message || err.status)); }
            });
        });

        adminRoot.querySelectorAll('.edit-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                const row = e.target.closest('tr');
                const name = row.children[1].textContent;
                const mobile = row.children[2].textContent;
                const newName = prompt('Full name', name);
                if (newName === null) return;
                const newMobile = prompt('Mobile number', mobile);
                if (newMobile === null) return;
                api('/supabase/admin/parents/' + id, { method: 'PUT', body: JSON.stringify({ full_name: newName, mobile_number: newMobile }) })
                    .then(() => load())
                    .catch((err) => alert('Update failed: ' + (err.body?.message || err.status)));
            });
        });
    }

    async function load() {
        adminRoot.innerHTML = '<p>Loading...</p>';
        try {
            const data = await api('/supabase/admin/parents');
            renderList(data || []);
        } catch (err) {
            adminRoot.innerHTML = '<p class="text-rose-600">Unable to load parents.</p>';
            console.error(err);
        }
    }

    load();
})();
