@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Supabase Parents Admin</h1>
    <div id="supabaseAdminRoot"></div>
    <p class="mt-4 text-sm text-gray-600">Actions here update Supabase via service role key.</p>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</div>
@endsection
