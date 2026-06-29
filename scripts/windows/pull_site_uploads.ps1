[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^https://')]
    [string]$SiteBaseUrl,

    [string]$ApiKey = $env:SOBHAN_BACKUP_API_KEY,
    [string]$ApiKeyFile = '',

    [string]$DestinationRoot = 'D:\SobhanBackups\WebsiteUploads',
    [string]$LogRoot = 'D:\SobhanBackups\Logs',

    [ValidateRange(1, 500)]
    [int]$BatchSize = 100,

    [ValidateRange(1, 100)]
    [int]$MaxBatches = 20
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

if ([string]::IsNullOrWhiteSpace($ApiKey) -and -not [string]::IsNullOrWhiteSpace($ApiKeyFile)) {
    $ApiKey = (Get-Content -LiteralPath $ApiKeyFile -Raw).Trim()
}
if ([string]::IsNullOrWhiteSpace($ApiKey)) {
    throw 'API Key الزامی است؛ آن را در SOBHAN_BACKUP_API_KEY یا ApiKeyFile امن قرار دهید.'
}

$SiteBaseUrl = $SiteBaseUrl.TrimEnd('/')
$headers = @{ 'X-Backup-Api-Key' = $ApiKey; 'Accept' = 'application/json' }
$destinationRootFull = [IO.Path]::GetFullPath($DestinationRoot).TrimEnd('\')
$tempRoot = Join-Path $env:TEMP 'SobhanBackupDownloads'
[IO.Directory]::CreateDirectory($destinationRootFull) | Out-Null
[IO.Directory]::CreateDirectory($LogRoot) | Out-Null
[IO.Directory]::CreateDirectory($tempRoot) | Out-Null
$logFile = Join-Path $LogRoot ("website-uploads-{0}.log" -f (Get-Date -Format 'yyyy-MM-dd'))

function Write-BackupLog {
    param([string]$Level, [string]$Message)
    $line = '{0} [{1}] {2}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Level.ToUpperInvariant(), $Message
    Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8
    Write-Host $line
}

function Invoke-BackupJsonPost {
    param([string]$Path, [hashtable]$Body)
    $json = $Body | ConvertTo-Json -Compress -Depth 5
    return Invoke-RestMethod -Uri ($SiteBaseUrl + $Path) -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body $json
}

function Get-SafeDestinationPath {
    param([string]$RelativePath)
    if ([string]::IsNullOrWhiteSpace($RelativePath)) { throw 'مسیر نسبی فایل خالی است.' }
    $normalized = $RelativePath.Replace('/', '\')
    if ([IO.Path]::IsPathRooted($normalized) -or $normalized.Contains(':')) { throw 'مسیر مطلق یا درایو در relative_path مجاز نیست.' }
    $segments = $normalized.Split('\', [StringSplitOptions]::RemoveEmptyEntries)
    if ($segments -contains '..') { throw 'Path traversal در relative_path شناسایی شد.' }
    $destination = [IO.Path]::GetFullPath((Join-Path $destinationRootFull $normalized))
    $requiredPrefix = $destinationRootFull + '\'
    if (-not $destination.StartsWith($requiredPrefix, [StringComparison]::OrdinalIgnoreCase)) { throw 'مسیر مقصد خارج از ریشه بکاپ است.' }
    return $destination
}

function Confirm-FileIntegrity {
    param([string]$Path, [long]$ExpectedSize, [string]$ExpectedHash)
    $actualSize = (Get-Item -LiteralPath $Path).Length
    if ($actualSize -ne $ExpectedSize) { throw "عدم تطابق حجم؛ انتظار: $ExpectedSize، دریافت: $actualSize" }
    $actualHash = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
    if (-not [string]::IsNullOrWhiteSpace($ExpectedHash) -and $actualHash -ne $ExpectedHash.ToLowerInvariant()) { throw 'عدم تطابق SHA-256 فایل.' }
    return $actualHash
}

Write-BackupLog 'info' 'شروع Pull یک‌طرفه فایل‌های سایت؛ هیچ عملیات حذف یا mirror روی مقصد اجرا نمی‌شود.'

for ($batch = 1; $batch -le $MaxBatches; $batch++) {
    try {
        $pending = Invoke-RestMethod -Uri ("{0}/api/file-backup/pending.php?limit={1}" -f $SiteBaseUrl, $BatchSize) -Method Get -Headers $headers
        if (-not $pending.ok) { throw ('پاسخ pending ناموفق بود: ' + ($pending.error.message | Out-String)) }
        $files = @($pending.data)
        if ($files.Count -eq 0) { Write-BackupLog 'info' 'فایل دیگری در صف بکاپ وجود ندارد.'; break }
    } catch {
        Write-BackupLog 'error' ('دریافت صف pending ناموفق بود: ' + $_.Exception.Message)
        throw
    }

    foreach ($file in $files) {
        $fileId = [long]$file.id
        $tempPath = Join-Path $tempRoot ("{0}-{1}.part" -f $fileId, [Guid]::NewGuid().ToString('N'))
        try {
            $destination = Get-SafeDestinationPath -RelativePath ([string]$file.relative_path)
            $destinationDirectory = Split-Path -Parent $destination
            [IO.Directory]::CreateDirectory($destinationDirectory) | Out-Null

            if (Test-Path -LiteralPath $destination) {
                $existingHash = Confirm-FileIntegrity -Path $destination -ExpectedSize ([long]$file.file_size) -ExpectedHash ([string]$file.file_hash)
                $ack = Invoke-BackupJsonPost -Path '/api/file-backup/ack.php' -Body @{ file_id = $fileId; file_hash = $existingHash }
                if (-not $ack.ok) { throw 'تأیید فایل موجود در مقصد ناموفق بود.' }
                Write-BackupLog 'info' ("فایل از قبل با صحت کامل موجود بود و ACK شد: {0}" -f $file.relative_path)
                continue
            }

            $downloadUrl = "{0}/api/file-backup/download.php?file_id={1}" -f $SiteBaseUrl, $fileId
            Invoke-WebRequest -Uri $downloadUrl -Method Get -Headers $headers -OutFile $tempPath -UseBasicParsing
            $actualHash = Confirm-FileIntegrity -Path $tempPath -ExpectedSize ([long]$file.file_size) -ExpectedHash ([string]$file.file_hash)

            if (Test-Path -LiteralPath $destination) { throw 'فایل مقصد همزمان ایجاد شد؛ برای جلوگیری از overwrite عملیات متوقف شد.' }
            Move-Item -LiteralPath $tempPath -Destination $destination

            $ack = Invoke-BackupJsonPost -Path '/api/file-backup/ack.php' -Body @{ file_id = $fileId; file_hash = $actualHash }
            if (-not $ack.ok) { throw 'فایل ذخیره شد اما ACK سایت ناموفق بود؛ اجرای بعدی صحت فایل موجود را دوباره بررسی می‌کند.' }
            Write-BackupLog 'success' ("بکاپ و تأیید شد: {0}" -f $file.relative_path)
        } catch {
            $message = $_.Exception.Message
            Write-BackupLog 'error' ("خطا برای file_id={0}: {1}" -f $fileId, $message)
            try {
                $errorResponse = Invoke-BackupJsonPost -Path '/api/file-backup/error.php' -Body @{ file_id = $fileId; error_message = $message }
                if (-not $errorResponse.ok) { Write-BackupLog 'warning' ("ثبت خطا در سایت برای file_id={0} ناموفق بود." -f $fileId) }
            } catch {
                Write-BackupLog 'warning' ("API خطا برای file_id={0} در دسترس نبود: {1}" -f $fileId, $_.Exception.Message)
            }
            if (Test-Path -LiteralPath $tempPath) { Remove-Item -LiteralPath $tempPath -Force }
        }
    }
}

Write-BackupLog 'info' 'پایان اجرای Pull بکاپ فایل‌های سایت.'
