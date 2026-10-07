param(
    [Parameter(Mandatory = $true)][string]$PortName,
    [Parameter(Mandatory = $true)][int]$BaudRate,
    [Parameter(Mandatory = $true)][string]$Recipient,
    [Parameter(Mandatory = $false)][string]$Message,
    [Parameter(Mandatory = $false)][string]$MessageFile
)

if (-not [string]::IsNullOrEmpty($MessageFile)) {
    $Message = Get-Content -LiteralPath $MessageFile -Raw -Encoding UTF8
} elseif ([string]::IsNullOrEmpty($Message)) {
    $Message = $env:SMS_MESSAGE
}

$serial = New-Object System.IO.Ports.SerialPort $PortName, $BaudRate, None, 8, One
$serial.ReadTimeout = 2000
$serial.WriteTimeout = 2000

function Read-ModemResponse {
    param([int]$Milliseconds = 1500)

    Start-Sleep -Milliseconds $Milliseconds
    return $serial.ReadExisting()
}

try {
    $serial.Open()
    $serial.DiscardInBuffer()
    $serial.Write("AT`r`n")
    $response = Read-ModemResponse
    if ($response -notmatch 'OK') {
        throw "The GSM module did not respond on $PortName at $BaudRate baud."
    }

    $serial.Write("AT+CMGF=1`r`n")
    $response = Read-ModemResponse
    if ($response -notmatch 'OK') {
        throw 'The GSM module could not enter SMS text mode.'
    }

    $serial.Write("AT+CMGS=`"$Recipient`"`r`n")
    $response = Read-ModemResponse
    if ($response -notmatch '>') {
        throw 'The GSM module did not accept the SMS recipient.'
    }

    $serial.Write($Message)
    $serial.Write([char]26)

    $deadline = [DateTime]::UtcNow.AddSeconds(60)
    $deliveryConfirmed = $false
    $modemRejected = $false

    while ([DateTime]::UtcNow -lt $deadline) {
        try {
            $responseLine = $serial.ReadLine()

            if ($responseLine -match '\+CMGS:\s*\d+') {
                $deliveryConfirmed = $true
                break
            }

            if ($responseLine -match '\+CMS ERROR|\bERROR\b') {
                $modemRejected = $true
                break
            }
        } catch [System.TimeoutException] {
            continue
        }
    }

    if ($modemRejected) {
        throw 'The mobile network rejected the SMS. Check the SIM balance and SMS service.'
    }

    if (-not $deliveryConfirmed) {
        throw 'The GSM module did not confirm SMS submission before the timeout.'
    }

    Write-Output 'SMS accepted by GSM module.'
} finally {
    if ($serial.IsOpen) {
        $serial.Close()
    }
    $serial.Dispose()
}