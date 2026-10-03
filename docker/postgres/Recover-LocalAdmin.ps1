$ErrorActionPreference = 'Stop'

$windowsIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$windowsPrincipal = New-Object Security.Principal.WindowsPrincipal($windowsIdentity)
if (-not $windowsPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this script from PowerShell as Administrator.'
}

$workspacePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$serviceName = 'postgresql-x64-18'
$dataPath = 'C:\Program Files\PostgreSQL\18\data'
$psqlPath = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
$hbaPath = Join-Path $dataPath 'pg_hba.conf'
$credentialPath = Join-Path $workspacePath '.postgres-admin.credential.xml'
$backupPath = Join-Path $workspacePath '.postgres-recovery'

if (Test-Path -LiteralPath $credentialPath) {
    throw 'A saved admin credential already exists. Recovery was not repeated.'
}

$serviceConfig = Get-CimInstance Win32_Service -Filter "Name='$serviceName'"
if (-not $serviceConfig -or -not $serviceConfig.PathName.Contains($dataPath)) {
    throw 'The PostgreSQL service does not match the expected installation.'
}

if (-not (Test-Path -LiteralPath $psqlPath) -or -not (Test-Path -LiteralPath $hbaPath)) {
    throw 'The expected PostgreSQL installation is incomplete.'
}

$originalHbaBytes = [IO.File]::ReadAllBytes($hbaPath)
$originalHbaText = [IO.File]::ReadAllText($hbaPath)
if ((Get-Service -Name $serviceName).Status -ne 'Running') {
    Start-Service -Name $serviceName
}
(Get-Service -Name $serviceName).WaitForStatus('Running', [TimeSpan]::FromSeconds(30))

New-Item -ItemType Directory -Path $backupPath -Force | Out-Null
[IO.File]::WriteAllBytes((Join-Path $backupPath 'pg_hba.conf.original'), $originalHbaBytes)

$randomBytes = New-Object byte[] 24
$randomGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $randomGenerator.GetBytes($randomBytes)
} finally {
    $randomGenerator.Dispose()
}
$newPassword = [BitConverter]::ToString($randomBytes).Replace('-', '')
$securePassword = ConvertTo-SecureString -String $newPassword -AsPlainText -Force
$credential = New-Object System.Management.Automation.PSCredential('postgres', $securePassword)
$credential | Export-Clixml -LiteralPath $credentialPath

$processInfo = New-Object Diagnostics.ProcessStartInfo
$processInfo.FileName = $psqlPath
$processInfo.Arguments = '-X -w -h 127.0.0.1 -p 5432 -U postgres -d postgres -v ON_ERROR_STOP=1'
$processInfo.UseShellExecute = $false
$processInfo.CreateNoWindow = $true
$processInfo.RedirectStandardInput = $true
$processInfo.RedirectStandardOutput = $true
$processInfo.RedirectStandardError = $true

try {
    $temporaryHba = "host postgres postgres 127.0.0.1/32 trust`r`n" + $originalHbaText
    [IO.File]::WriteAllText($hbaPath, $temporaryHba, (New-Object Text.UTF8Encoding($false)))

    $process = New-Object Diagnostics.Process
    $process.StartInfo = $processInfo
    try {
        [void]$process.Start()
        $process.StandardInput.WriteLine("SET password_encryption = 'scram-sha-256'; ALTER ROLE postgres PASSWORD '$newPassword';")
        $process.StandardInput.Close()
        $null = $process.StandardOutput.ReadToEnd()
        $null = $process.StandardError.ReadToEnd()
        $process.WaitForExit()
        if ($process.ExitCode -ne 0) {
            throw 'Admin password recovery failed. The original authentication configuration will be restored.'
        }
    } finally {
        $process.Dispose()
    }
} finally {
    [IO.File]::WriteAllBytes($hbaPath, $originalHbaBytes)
    $newPassword = $null
    $temporaryHba = $null
}

Write-Output 'Admin password recovered; original authentication configuration restored.'
Write-Output 'Encrypted credential saved in .postgres-admin.credential.xml.'
