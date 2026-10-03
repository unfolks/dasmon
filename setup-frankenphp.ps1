$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$toolsPath = Join-Path $PSScriptRoot '.tools'
$runtimePath = Join-Path $toolsPath 'frankenphp'
$releaseVersion = 'v1.12.7'

New-Item -ItemType Directory -Path $toolsPath -Force | Out-Null
$releaseInfo = Invoke-RestMethod -Uri "https://api.github.com/repos/php/frankenphp/releases/tags/$releaseVersion" -Headers @{
    'User-Agent' = 'dashboard-monitor-setup'
}
$windowsAsset = $releaseInfo.assets | Where-Object { $_.name -eq 'frankenphp-windows-x86_64.zip' }
if (-not $windowsAsset -or -not $windowsAsset.digest) {
    throw 'The official Windows release or its checksum is unavailable.'
}

$archivePath = Join-Path $toolsPath 'frankenphp-windows.zip'
Invoke-WebRequest -Uri $windowsAsset.browser_download_url -UseBasicParsing -OutFile $archivePath
$archiveHash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLower()
if ($windowsAsset.digest -ne "sha256:$archiveHash") {
    throw 'FrankenPHP checksum mismatch. The archive was not extracted.'
}

Expand-Archive -LiteralPath $archivePath -DestinationPath $runtimePath -Force
$certificatePath = Join-Path $runtimePath 'extras\ssl\cacert.pem'
Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -UseBasicParsing -OutFile $certificatePath
$extensionPath = (Join-Path $runtimePath 'ext').Replace('\', '/')
$certificatePath = $certificatePath.Replace('\', '/')

$phpConfiguration = @"
extension_dir = "$extensionPath"
extension=curl
extension=fileinfo
extension=intl
extension=mbstring
extension=openssl
extension=pdo_pgsql
extension=pgsql
extension=zip
memory_limit=256M
error_reporting=E_ALL
display_errors=On
log_errors=On
date.timezone=Asia/Bangkok
curl.cainfo="$certificatePath"
openssl.cafile="$certificatePath"
opcache.enable=1
opcache.validate_timestamps=1
opcache.revalidate_freq=0
"@
[IO.File]::WriteAllText((Join-Path $runtimePath 'php.ini'), $phpConfiguration, (New-Object Text.UTF8Encoding($false)))

& (Join-Path $runtimePath 'frankenphp.exe') version
if ($LASTEXITCODE -ne 0) {
    throw 'The downloaded FrankenPHP runtime could not start.'
}
Write-Output 'FrankenPHP Windows is ready in .tools/frankenphp.'
