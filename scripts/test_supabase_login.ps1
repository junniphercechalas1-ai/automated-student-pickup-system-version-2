$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$resp = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login' -WebSession $session -UseBasicParsing
if ($resp.StatusCode -ne 200) { Write-Output "GET /login status: $($resp.StatusCode)"; exit 1 }
if ($resp.Content -match 'name="csrf-token" content="([^"]+)"') { $token=$matches[1] } else { Write-Output "CSRF token not found"; exit 1 }
$headers = @{ 'X-CSRF-TOKEN' = $token }
$body = @{ email='vinjayparan@gmail.com'; password='120625' } | ConvertTo-Json
try {
    $loginResp = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/supabase/login' -Method POST -WebSession $session -Body $body -ContentType 'application/json' -Headers $headers -UseBasicParsing -ErrorAction Stop
    Write-Output "Status: $($loginResp.StatusCode)"
    Write-Output $loginResp.Content
} catch {
    Write-Output "Request failed: $($_.Exception.Message)"
    if ($_.Exception.Response) { try { $text = $_.Exception.Response.GetResponseStream(); $sr = New-Object System.IO.StreamReader($text); Write-Output $sr.ReadToEnd() } catch {} }
    exit 1
}
