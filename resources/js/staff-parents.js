async function fetchParents() {
    const container = document.getElementById('parentsList');
    if (!container) return;

    // If the server already rendered parent items, do not re-fetch.
    const hasServerRendered = container.querySelector('.rounded-3xl');
    if (hasServerRendered) return;

    container.innerHTML = '<p>Loading parents…</p>';

    try {
        const resp = await fetch('/supabase/admin/parents', { credentials: 'same-origin' });
        if (!resp.ok) {
            const text = await resp.text();
            container.innerHTML = `<p class="text-rose-600">Failed to load parents: ${resp.status} ${resp.statusText}</p><pre class="mt-2 text-xs text-slate-500">${escapeHtml(text)}</pre>`;
            return;
        }

        const contentType = String(resp.headers.get('content-type') || '');
        if (!contentType.includes('application/json')) {
            const text = await resp.text();
            container.innerHTML = '<p class="text-amber-600">Unable to load admin parents: authentication required.</p>';
            console.warn('Non-JSON response from /supabase/admin/parents, likely a login page:', text.slice(0, 800));
            return;
        }

        let data;
        try {
            data = await resp.json();
        } catch (err) {
            const text = await resp.text();
            container.innerHTML = `<p class="text-rose-600">Failed to parse parents response.</p><pre class="mt-2 text-xs text-slate-500">${escapeHtml(text)}</pre>`;
            console.error(err);
            return;
        }
        if (!Array.isArray(data) || data.length === 0) {
            container.innerHTML = '<p>No parents found.</p>';
            return;
        }

        const list = document.createElement('div');
        list.className = 'space-y-3';

        data.forEach((p) => {
            const item = document.createElement('div');
            item.className = 'rounded-3xl bg-slate-50 p-4 ring-1 ring-slate-200';
            item.innerHTML = `
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-950">${escapeHtml(p.full_name || p.fullName || 'Unnamed')}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(p.mobile_number || p.mobile || '')}</div>
                    </div>
                    <div class="text-sm text-slate-600">${p.relationship ? escapeHtml(p.relationship) : ''}</div>
                </div>
            `;
            list.appendChild(item);
        });

        container.innerHTML = '';
        container.appendChild(list);
    } catch (e) {
        container.innerHTML = `<p class="text-rose-600">Error fetching parents: ${escapeHtml(String(e.message || e))}</p>`;
        console.error(e);
    }
}

function escapeHtml(s) {
    return String(s || '').replace(/[&<>"'`]/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '`': '&#96;'
    })[c]);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fetchParents);
} else {
    fetchParents();
}
