<nav class="mb-6 overflow-x-auto rounded-lg bg-white p-2 shadow" aria-label="User and Student Records">
    <div class="flex min-w-max gap-2">
        <a href="{{ route('admin.students') }}"
            @class(['rounded-md px-4 py-2 text-sm font-semibold transition', 'bg-blue-600 text-white' => request()->routeIs('admin.students'), 'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin.students')])>
            Students
        </a>
        <a href="{{ route('admin.parents') }}"
            @class(['rounded-md px-4 py-2 text-sm font-semibold transition', 'bg-blue-600 text-white' => request()->routeIs('admin.parents*'), 'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin.parents*')])>
            Parents / Guardians
        </a>
        <a href="{{ route('admin.staff') }}"
            @class(['rounded-md px-4 py-2 text-sm font-semibold transition', 'bg-blue-600 text-white' => request()->routeIs('admin.staff*'), 'text-gray-700 hover:bg-gray-100' => ! request()->routeIs('admin.staff*')])>
            Staff Accounts
        </a>
    </div>
</nav>
