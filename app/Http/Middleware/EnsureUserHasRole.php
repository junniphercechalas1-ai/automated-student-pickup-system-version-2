<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $expectedRole)
    {
        $user = $request->session()->get('supabase_user');

        if (! $user) {
            $token = $request->cookie('supabase_token');

            if ($token) {
                $supabaseUrl = env('VITE_SUPABASE_URL');
                $anonKey = env('VITE_SUPABASE_ANON_KEY');

                if ($supabaseUrl && $anonKey) {
                    $response = Http::withHeaders([
                        'apikey' => $anonKey,
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ])->get("{$supabaseUrl}/auth/v1/user");

                    if ($response->ok()) {
                        $user = $response->json();
                        $request->session()->put('supabase_user', $user);
                    }
                }
            }
        }

        if (! $user) {
            return $expectedRole === 'parent'
                ? redirect('/parent/login')
                : redirect('/staff/login');
        }

        $role = strtolower($user['user_metadata']['role'] ?? $user['role'] ?? 'parent');
        $email = strtolower($user['email'] ?? '');
        $isApprovedStaffInSupabase = false;
        $isApprovedParentInSupabase = false;
        $supabaseUrl = env('VITE_SUPABASE_URL');
        $serviceKey = env('SUPABASE_SERVICE_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY');

        if ($supabaseUrl && $serviceKey) {
            $staffQuery = [
                'select' => 'id,email,is_approved,is_active',
            ];

            if (! empty($user['id'])) {
                $staffQuery['id'] = 'eq.'.(string) $user['id'];
            }

            if (empty($user['id']) && $email !== '') {
                $staffQuery['email'] = 'eq.'.urlencode($email);
            }

            $staffResponse = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Accept' => 'application/json',
            ])->get("{$supabaseUrl}/rest/v1/staff", $staffQuery);

            if ($staffResponse->ok()) {
                $staffRows = $staffResponse->json();
                $staffRow = collect($staffRows)->first();

                if (is_array($staffRow)
                    && ($staffRow['is_approved'] ?? false) === true
                    && ($staffRow['is_active'] ?? false) === true) {
                    $isApprovedStaffInSupabase = true;
                }
            }

            if ($role === 'parent' || $expectedRole === 'parent') {
                $parentQuery = [
                    'select' => 'id,auth_user_id,email,is_approved,is_active',
                ];

                if (! empty($user['id'])) {
                    $parentQuery['auth_user_id'] = 'eq.'.(string) $user['id'];
                }

                if (empty($user['id']) && $email !== '') {
                    $parentQuery['email'] = 'eq.'.urlencode($email);
                }

                $parentResponse = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer '.$serviceKey,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/rest/v1/parents", $parentQuery);

                if ($parentResponse->ok()) {
                    $parentRows = $parentResponse->json();
                    $parentRow = collect($parentRows)->first();

                    if (is_array($parentRow)
                        && ($parentRow['is_approved'] ?? false) === true
                        && ($parentRow['is_active'] ?? false) !== false) {
                        $isApprovedParentInSupabase = true;
                    }
                }
            }
        }

        $isStaff = $role === 'admin' || $isApprovedStaffInSupabase;

        if ($expectedRole === 'admin') {
            if (! $isStaff) {
                return redirect('/parent/dashboard');
            }
        } elseif ($expectedRole === 'parent') {
            if ($isStaff) {
                return redirect('/staff/dashboard');
            }

            if (! $isApprovedParentInSupabase) {
                return redirect('/parent/login');
            }
        }

        return $next($request);
    }
}
