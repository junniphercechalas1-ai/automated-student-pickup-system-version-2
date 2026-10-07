@extends('layouts.admin-layout')

@section('content')
<div class="min-h-screen bg-gray-50 px-6 py-8 lg:px-8">
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Parent Messages</h1>
            <p class="mt-1 text-gray-600">Review account support requests from parents and guardians.</p>
        </div>
        <p class="text-sm font-medium text-amber-700">{{ $openCount }} unresolved</p>
    </header>

    @if (session('status'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</div>
    @endif

    <section class="divide-y divide-gray-200 overflow-hidden rounded-lg bg-white shadow">
        @forelse ($messages as $supportMessage)
            <article class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $supportMessage->subject }}</h2>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $supportMessage->status === 'resolved' ? 'bg-green-100 text-green-800' : ($supportMessage->status === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">{{ str_replace('_', ' ', ucfirst($supportMessage->status)) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $supportMessage->parent_name ?: 'Parent / Guardian' }}
                            @if ($supportMessage->parent_email)
                                · <a href="mailto:{{ $supportMessage->parent_email }}" class="text-blue-700 hover:underline">{{ $supportMessage->parent_email }}</a>
                            @endif
                            · {{ $supportMessage->created_at->format('M j, Y g:i A') }}
                        </p>
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-800">{{ $supportMessage->message }}</p>
                        @foreach ($supportMessage->replies as $reply)
                            <div class="mt-4 rounded-lg {{ $reply->sender_type === 'admin' ? 'bg-blue-50' : 'bg-gray-50' }} p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $reply->sender_type === 'admin' ? 'text-blue-700' : 'text-gray-600' }}">{{ $reply->sender_type === 'admin' ? 'Admin' : ($supportMessage->parent_name ?: 'Parent') }} · {{ $reply->created_at->format('M j, Y g:i A') }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-800">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                        <form method="POST" action="{{ route('admin.support-messages.reply', $supportMessage) }}" class="mt-4 space-y-2">
                            @csrf
                            <label for="admin-reply-{{ $supportMessage->id }}" class="block text-sm font-semibold text-gray-700">Reply to parent</label>
                            <textarea id="admin-reply-{{ $supportMessage->id }}" name="reply" required maxlength="5000" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('reply') }}</textarea>
                            @error('reply')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button type="submit" class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Send Reply</button>
                        </form>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.support-messages.status', $supportMessage) }}" class="flex items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="support-status-{{ $supportMessage->id }}">Message status</label>
                        <select id="support-status-{{ $supportMessage->id }}" name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800">
                            <option value="open" @selected($supportMessage->status === 'open')>Open</option>
                            <option value="in_progress" @selected($supportMessage->status === 'in_progress')>In Progress</option>
                            <option value="resolved" @selected($supportMessage->status === 'resolved')>Resolved</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Update</button>
                    </form>
                    <form method="POST" action="{{ route('admin.support-messages.delete', $supportMessage) }}" onsubmit="return confirm('Delete this message and its replies? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete Message</button>
                    </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="p-8 text-center text-sm text-gray-600">No parent messages have been received.</p>
        @endforelse
    </section>

    <div class="mt-6">{{ $messages->links() }}</div>
</div>
@endsection