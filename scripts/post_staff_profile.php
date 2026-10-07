<?php
if ($argc < 2) { echo "Usage: php post_staff_profile.php payload.json\n"; exit(1); }
$payload = file_get_contents($argv[1]);
$url = 'http://127.0.0.1:8000/supabase/staff-profile';
$opts = [
  'http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
    'content' => $payload,
    'ignore_errors' => true,
    'timeout' => 15,
  ],
];
$ctx = stream_context_create($opts);
$res = @file_get_contents($url, false, $ctx);
$code='?';
if (isset($http_response_header) && preg_match('/HTTP\/.* ([0-9]+)/', $http_response_header[0], $c)) $code=$c[1];
echo "STATUS:$code\n";
echo $res . "\n";
foreach($http_response_header as $h) echo $h."\n";
