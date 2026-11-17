[CmdletBinding()]
param (
    [Parameter(Mandatory = $true)]
    [ValidateSet('msbd_tubes_kel7_staging', 'msbd_latihan_utama', 'msbd_tubes_kel7')]
    [string] $Database,

    [switch] $AllowTrainingClone,
    [switch] $ConfirmPrimary,
    [string] $ConfirmTarget,
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$environmentFile = Join-Path $repositoryRoot '.env'
$trainingDatabase = 'msbd_latihan_utama'
$primaryDatabase = 'msbd_tubes_kel7'

$Db = $Database
$backupPath = Join-Path $env:TEMP ('fase1_' + $Db + '_' + [guid]::NewGuid().ToString('N') + '.dump')

function Invoke-ExternalCommand {
    param (
        [Parameter(Mandatory = $true)]
        [string] $Executable,
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & $Executable @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }

    return [pscustomobject] @{
        Output = @($output)
        ExitCode = $exitCode
    }
}

if ($Db -eq $trainingDatabase -and -not $AllowTrainingClone) {
    throw 'Klon latihan memerlukan switch -AllowTrainingClone.'
}

if ($Db -eq $primaryDatabase) {
    if (-not $ConfirmPrimary -or $ConfirmTarget -cne $primaryDatabase) {
        throw 'Database utama memerlukan -ConfirmPrimary dan -ConfirmTarget msbd_tubes_kel7.'
    }
}

if ($Db -ne $primaryDatabase -and ($ConfirmPrimary -or -not [string]::IsNullOrWhiteSpace($ConfirmTarget))) {
    throw 'Konfirmasi utama hanya valid untuk msbd_tubes_kel7.'
}

if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw "File koneksi tidak ditemukan: $environmentFile"
}

$sqlFiles = @(
    'dcl\01_roles.sql',
    'dml\00_repair_status_ids.sql',
    'ddl\01_restore_constraints.sql',
    'ddl\02_constraint_tambahan.sql',
    'ddl\03_functions.sql',
    'ddl\04_procedures.sql',
    'ddl\05_trigger.sql',
    'ddl\06_views.sql',
    'dcl\02_privileges.sql',
    'dcl\03_hardening.sql',
    'dcl\90_hapus_role_guru.sql',
    'dml\01_sync_sequences.sql'
)
$resolvedSqlFiles = @()
foreach ($sqlFile in $sqlFiles) {
    $path = Join-Path $repositoryRoot ('database\sql\' + $sqlFile)
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw "File SQL paket tidak ditemukan: $path"
    }
    $resolvedSqlFiles += (Resolve-Path -LiteralPath $path).Path
}

$psql = (Get-Command psql -ErrorAction Stop).Source
$pgDump = (Get-Command pg_dump -ErrorAction Stop).Source
$pgRestore = (Get-Command pg_restore -ErrorAction Stop).Source
$databaseSettings = @{}

foreach ($line in Get-Content -LiteralPath $environmentFile) {
    if ($line -match '^\s*(DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)\s*$') {
        $value = $Matches[2].Trim()
        if ($value.Length -ge 2 -and $value[0] -eq $value[$value.Length - 1] -and ($value[0] -eq '"' -or $value[0] -eq "'")) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $databaseSettings[$Matches[1]] = $value
    }
}

$environmentMap = @{
    DB_HOST = 'PGHOST'
    DB_PORT = 'PGPORT'
    DB_USERNAME = 'PGUSER'
    DB_PASSWORD = 'PGPASSWORD'
}
$originalEnvironment = @{}
foreach ($key in $environmentMap.Keys) {
    $variable = $environmentMap[$key]
    $originalEnvironment[$variable] = [Environment]::GetEnvironmentVariable($variable, 'Process')
    if ($databaseSettings.ContainsKey($key)) {
        [Environment]::SetEnvironmentVariable($variable, $databaseSettings[$key], 'Process')
    }
}

try {
    $connectionArguments = @('-X', '-w', '-q', '-v', 'ON_ERROR_STOP=1', '-d', $Db)
    $databaseCheck = Invoke-ExternalCommand -Executable $psql -Arguments ($connectionArguments + @('-A', '-t', '-c', 'SELECT current_database();'))
    $actualDatabase = ($databaseCheck.Output | Out-String).Trim()
    if ($databaseCheck.ExitCode -ne 0 -or $actualDatabase -ne $Db) {
        throw "Database diminta $Db tetapi current_database() mengembalikan $actualDatabase."
    }

    if ($Db -eq $trainingDatabase) {
        $markerCheck = Invoke-ExternalCommand -Executable $psql -Arguments ($connectionArguments + @('-A', '-t', '-c', "SELECT COALESCE(shobj_description(oid, 'pg_database'), '') FROM pg_database WHERE datname = current_database();"))
        $trainingMarker = ($markerCheck.Output | Out-String).Trim()
        if ($markerCheck.ExitCode -ne 0 -or $trainingMarker -ne 'FASE1_TRAINING_CLONE') {
            throw 'Database latihan tidak memiliki marker FASE1_TRAINING_CLONE.'
        }
    }

    Write-Output "current_database=$actualDatabase"
    if ($DryRun) {
        $dryRunArguments = $connectionArguments + @('-c', 'BEGIN;', '-c', "SET LOCAL fase1.confirmed_target = '$Db';", '-c', 'SET LOCAL client_min_messages = warning;')
        foreach ($path in $resolvedSqlFiles) {
            $dryRunArguments += @('-f', $path)
        }
        $dryRunArguments += @('-c', 'ROLLBACK;')
        $dryRunResult = Invoke-ExternalCommand -Executable $psql -Arguments $dryRunArguments
        $dryRunResult.Output | ForEach-Object { Write-Output $_ }
        if ($dryRunResult.ExitCode -ne 0) {
            throw "Dry-run paket Fase 1 gagal pada $Db."
        }
        Write-Output 'transaction=ROLLED_BACK'
        return
    }

    $dump = Invoke-ExternalCommand -Executable $pgDump -Arguments @('-w', '--format=custom', '--no-owner', '--no-acl', '-f', $backupPath, '-d', $Db)
    if ($dump.ExitCode -ne 0) {
        throw "Backup gagal untuk $Db`: $($dump.Output | Out-String)"
    }
    $toc = Invoke-ExternalCommand -Executable $pgRestore -Arguments @('-l', $backupPath)
    if ($toc.ExitCode -ne 0 -or @($toc.Output).Count -lt 2) {
        throw "Backup tidak dapat diverifikasi dengan pg_restore -l: $backupPath"
    }
    $backupHash = (Get-FileHash -LiteralPath $backupPath -Algorithm SHA256).Hash.ToLowerInvariant()
    Write-Output "backup_path=$backupPath"
    Write-Output "backup_sha256=$backupHash"
    Write-Output "backup_toc_entries=$(@($toc.Output).Count - 1)"

    $executionArguments = $connectionArguments + @('--single-transaction', '-c', "SET LOCAL fase1.confirmed_target = '$Db';")
    foreach ($path in $resolvedSqlFiles) {
        $executionArguments += @('-f', $path)
    }
    $execution = Invoke-ExternalCommand -Executable $psql -Arguments $executionArguments
    $execution.Output | ForEach-Object { Write-Output $_ }
    if ($execution.ExitCode -ne 0) {
        throw "Paket Fase 1 gagal pada $Db; seluruh transaksi telah dibatalkan."
    }
    Write-Output 'transaction=COMMITTED'
} finally {
    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
}
