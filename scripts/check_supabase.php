<?php
$env = file_get_contents(__DIR__ . '/../.env');
preg_match('/VITE_SUPABASE_URL=(.*)/', $env, $m1);
preg_match('/SUPABASE_SERVICE_ROLE_KEY=(.*)/', $env, $m2);
$url = isset($m1[1]) ? trim(trim($m1[1]), "\"\'\r\n") : '';
$key = isset($m2[1]) ? trim(trim($m2[1]), "\"\'\r\n") : '';
if (empty($url) || empty($key)) {
    echo "MISSING\n";
    exit(0);
}
$endpoint = rtrim($url, '/') . '/rest/v1/parents?select=*&limit=1';
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "apikey: $key\r\nAuthorization: Bearer $key\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
        'timeout' => 10,
    ],
];
$ctx = stream_context_create($opts);
$res = @file_get_contents($endpoint, false, $ctx);
$code = '?';
if (isset($http_response_header) && is_array($http_response_header) && preg_match('/HTTP\/.* ([0-9]+)/', $http_response_header[0], $c)) {
    $code = $c[1];
}
echo "STATUS:$code\n";
echo $res . "\n";
