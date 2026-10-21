<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $isApprovedStaffInSupabase = false;
        $isApprovedParentInSupabase = false;
        $supabaseUrl = env('VITE_SUPABASE_URL') ?: env('SUPABASE_URL') ?: getenv('SUPABASE_URL');
        $serviceKey = env('SUPABASE_SERVICE_KEY')
            ?: env('SUPABASE_SERVICE_ROLE_KEY')
            ?: getenv('SUPABASE_SERVICE_KEY')
            ?: getenv('SUPABASE_SERVICE_ROLE_KEY');

        if ($supabaseUrl && $serviceKey) {
            if (! empty($user['id'])) {
                $staffResponse = Http::withHeaders([
                    'apikey' => $serviceKey,
                    'Authorization' => 'Bearer '.$serviceKey,
                    'Accept' => 'application/json',
                ])->get("{$supabaseUrl}/rest/v1/staff", [
                    'select' => 'auth_users_id,is_approved,is_active',
                    'auth_users_id' => 'eq.'.(string) $user['id'],
                ]);

                if ($staffResponse->ok()) {
                    $staffRows = $staffResponse->json();
                    $staffRow = collect($staffRows)->first();

                    if (is_array($staffRow)
                        && ($staffRow['is_approved'] ?? false) === true
                        && ($staffRow['is_active'] ?? false) === true) {
                        $isApprovedStaffInSupabase = true;
                    }
                } else {
                    Log::warning('Unable to verify staff authorization from the staff profile.', [
                        'status' => $staffResponse->status(),
                    ]);
                }
            }

            if ($role === 'parent' || $expectedRole === 'parent') {
                if (! empty($user['id'])) {
                    $parentResponse = Http::withHeaders([
                        'apikey' => $serviceKey,
                        'Authorization' => 'Bearer '.$serviceKey,
                        'Accept' => 'application/json',
                    ])->get("{$supabaseUrl}/rest/v1/parents", [
                        'select' => 'id,auth_users_id,is_approved,is_active',
                        'auth_users_id' => 'eq.'.(string) $user['id'],
                    ]);

                    if ($parentResponse->ok()) {
                        $parentRows = $parentResponse->json();
                        $parentRow = collect($parentRows)->first();

                        if (is_array($parentRow)) {
                            $parentIsApproved = array_key_exists('is_approved', $parentRow)
                                ? (bool) ($parentRow['is_approved'] ?? false)
                                : true;

                            if ($parentIsApproved && ($parentRow['is_active'] ?? true) !== false) {
                                $isApprovedParentInSupabase = true;
                            }
                        }
                    } else {
                        Log::warning('Unable to verify parent authorization from the parent profile.', [
                            'status' => $parentResponse->status(),
                        ]);
                    }
                }
            }
        }

        $isStaff = $role === 'admin' || $isApprovedStaffInSupabase;

        if ($expectedRole === 'admin') {
            if (! $isStaff) {
                return redirect($role === 'staff' ? '/staff/login' : '/parent/dashboard');
            }
        } elseif ($expectedRole === 'parent') {
            if ($isStaff) {
                return redirect('/staff/dashboard');
            }

            if (! $isApprovedParentInSupabase) {
                return redirect($role === 'staff' ? '/staff/login' : '/parent/login');
            }
        }

        return $next($request);
    }
}
