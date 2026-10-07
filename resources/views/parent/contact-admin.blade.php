@extends('parent.layout')

@section('title', 'Contact Admin')

@section('content')
    <header class="rounded-[32px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm font-medium uppercase tracking-[0.3em] text-blue-600">Account Help</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-950">Contact Admin</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Describe the account problem and the admin team will review your message.</p>
    </header>

    @if (session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
    @endif

    <section class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <form method="POST" action="{{ route('parent.contact-admin.store') }}" class="space-y-4 rounded-[24px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf
            <div>
                <label for="subject" class="mb-1 block text-sm font-semibold text-slate-700">Subject</label>
                <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="120" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message" class="mb-1 block text-sm font-semibold text-slate-700">What happened?</label>
                <textarea id="message" name="message" required maxlength="5000" rows="7" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Send Message</button>
        </form>

        <section class="rounded-[24px] bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-slate-950">Your Messages</h2>
            <div class="mt-4 divide-y divide-slate-200">
                @forelse ($messages as $supportMessage)
                    <article class="py-4 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-slate-900">{{ $supportMessage->subject }}</h3>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $supportMessage->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ str_replace('_', ' ', ucfirst($supportMessage->status)) }}</span>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $supportMessage->message }}</p>
                        <p class="mt-2 text-xs text-slate-500">Sent {{ $supportMessage->created_at->format('M j, Y g:i A') }}</p>
                        @foreach ($supportMessage->replies as $reply)
                            <div class="mt-4 rounded-xl {{ $reply->sender_type === 'admin' ? 'bg-blue-50' : 'bg-slate-50' }} p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $reply->sender_type === 'admin' ? 'text-blue-700' : 'text-slate-600' }}">{{ $reply->sender_type === 'admin' ? 'Admin' : 'You' }} · {{ $reply->created_at->format('M j, Y g:i A') }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                        <form method="POST" action="{{ route('parent.contact-admin.reply', $supportMessage) }}" class="mt-4 space-y-2">
                            @csrf
                            <label for="parent-reply-{{ $supportMessage->id }}" class="block text-sm font-semibold text-slate-700">Reply to this message</label>
                            <textarea id="parent-reply-{{ $supportMessage->id }}" name="reply" required maxlength="5000" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('reply') }}</textarea>
                            @error('reply')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button type="submit" class="rounded-xl bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Send Reply</button>
                        </form>
                    </article>
                @empty
                    <p class="py-4 text-sm text-slate-600">You haven’t sent any messages yet.</p>
                @endforelse
            </div>
        </section>
    </section>
@endsection