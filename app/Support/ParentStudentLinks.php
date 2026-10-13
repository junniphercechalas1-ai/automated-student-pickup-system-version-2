<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ParentStudentLinks
{
    public static function forParent(string $supabaseUrl, string $serviceKey, int|string $parentId): Response
    {
        return self::request($supabaseUrl, $serviceKey)
            ->get("{$supabaseUrl}/rest/v1/parents_students", [
                'select' => 'parent_id,student_id,relationship',
                'parent_id' => 'eq.'.$parentId,
                'order' => 'student_id.asc',
            ]);
    }

    public static function forParents(string $supabaseUrl, string $serviceKey, array $parentIds): Response
    {
        $parentIds = array_values(array_unique(array_map('intval', $parentIds)));
        if ($parentIds === []) {
            $parentIds = [0];
        }

        return self::request($supabaseUrl, $serviceKey)
            ->get("{$supabaseUrl}/rest/v1/parents_students", [
                'select' => 'parent_id,student_id,relationship',
                'parent_id' => 'in.('.implode(',', $parentIds).')',
                'order' => 'student_id.asc',
            ]);
    }

    public static function upsert(
        string $supabaseUrl,
        string $serviceKey,
        int|string $parentId,
        int|string $studentId,
        ?string $relationship
    ): Response {
        return self::request($supabaseUrl, $serviceKey)
            ->withHeaders(['Prefer' => 'resolution=merge-duplicates,return=representation'])
            ->post("{$supabaseUrl}/rest/v1/parents_students?on_conflict=parent_id,student_id", [[
                'parent_id' => (int) $parentId,
                'student_id' => (int) $studentId,
                'relationship' => $relationship,
            ]]);
    }

    public static function delete(
        string $supabaseUrl,
        string $serviceKey,
        int|string $parentId,
        int|string $studentId
    ): Response {
        return self::request($supabaseUrl, $serviceKey)
            ->delete("{$supabaseUrl}/rest/v1/parents_students?parent_id=eq.".urlencode((string) $parentId)
                .'&student_id=eq.'.urlencode((string) $studentId));
    }

    private static function request(string $supabaseUrl, string $serviceKey)
    {
        return Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }
}
