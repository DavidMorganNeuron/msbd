param (
    [string] $Db,

    [string] $File,

    [string[]] $SetupFiles,

    [switch] $Konfirmasi,

    [switch] $DryRun,

    [switch] $Atomic
)

$ErrorActionPreference = 'Stop'
$stagingDatabase = 'msbd_tubes_kel7_staging'
$trainingDatabase = 'msbd_latihan_utama'

if ([string]::IsNullOrWhiteSpace($Db) -or $Db -notmatch '^[A-Za-z0-9_]+$') {
    throw 'Parameter -Db wajib diisi dengan nama database yang valid.'
}

if ([string]::IsNullOrWhiteSpace($File)) {
    throw 'Parameter -File wajib diisi.'
}

if ($DryRun -and $Atomic) {
    throw 'Gunakan salah satu dari -DryRun atau -Atomic, bukan keduanya.'
}

if (-not $DryRun -and $SetupFiles.Count -gt 0) {
    throw '-SetupFiles hanya dapat digunakan bersama -DryRun.'
}

if ($Db -notin @($stagingDatabase, $trainingDatabase)) {
    throw "Runner file tunggal hanya mengizinkan $stagingDatabase atau $trainingDatabase; database utama memakai jalankan_fase1.ps1."
}

if ($Db -eq $trainingDatabase -and -not $Konfirmasi) {
    throw "Klon latihan memerlukan switch -Konfirmasi."
}

if ($Db -eq $trainingDatabase -and -not $DryRun -and -not $Atomic) {
    throw 'Eksekusi pada klon latihan wajib memakai -Atomic atau -DryRun.'
}

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$sqlRoot = (Resolve-Path $PSScriptRoot).Path.TrimEnd('\')

if ([System.IO.Path]::IsPathRooted($File)) {
    $requestedFile = $File
} else {
    $requestedFile = Join-Path $repositoryRoot $File
}

if (-not (Test-Path -LiteralPath $requestedFile -PathType Leaf)) {
    throw "File SQL tidak ditemukan: $requestedFile"
}

$resolvedFile = (Resolve-Path -LiteralPath $requestedFile).Path
if (-not $resolvedFile.StartsWith($sqlRoot + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'File SQL harus berada di dalam database\sql.'
}

$resolvedSetupFiles = @()
foreach ($setupFile in $SetupFiles) {
    if ([System.IO.Path]::IsPathRooted($setupFile)) {
        $requestedSetupFile = $setupFile
    } else {
        $requestedSetupFile = Join-Path $repositoryRoot $setupFile
    }

    if (-not (Test-Path -LiteralPath $requestedSetupFile -PathType Leaf)) {
        throw "File setup SQL tidak ditemukan: $requestedSetupFile"
    }

    $resolvedSetupFile = (Resolve-Path -LiteralPath $requestedSetupFile).Path
    if (-not $resolvedSetupFile.StartsWith($sqlRoot + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'File setup SQL harus berada di dalam database\sql.'
    }

    $resolvedSetupFiles += $resolvedSetupFile
}

$psqlCommand = Get-Command psql -ErrorAction Stop
$environmentFile = Join-Path $repositoryRoot '.env'
$databaseSettings = @{}

if (Test-Path -LiteralPath $environmentFile -PathType Leaf) {
    foreach ($line in Get-Content -LiteralPath $environmentFile) {
        if ($line -match '^\s*(DB_HOST|DB_PORT|DB_USERNAME|DB_PASSWORD)=(.*)$') {
            $value = $Matches[2].Trim()
            if ($value.Length -ge 2 -and $value[0] -eq $value[$value.Length - 1] -and ($value[0] -eq '"' -or $value[0] -eq "'")) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $databaseSettings[$Matches[1]] = $value
        }
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
    $actualDatabase = (& $psqlCommand.Source @connectionArguments -A -t -c 'SELECT current_database();' | Out-String).Trim()

    if ($LASTEXITCODE -ne 0) {
        throw "Tidak dapat memverifikasi database target $Db dengan psql."
    }

    if ($actualDatabase -ne $Db) {
        throw "Database diminta $Db tetapi current_database() mengembalikan $actualDatabase."
    }

    if ($Db -eq $trainingDatabase) {
        $trainingMarker = (& $psqlCommand.Source @connectionArguments -A -t -c "SELECT COALESCE(shobj_description(oid, 'pg_database'), '') FROM pg_database WHERE datname = current_database();" | Out-String).Trim()
        if ($LASTEXITCODE -ne 0 -or $trainingMarker -ne 'FASE1_TRAINING_CLONE') {
            throw 'Klon latihan tidak memiliki marker FASE1_TRAINING_CLONE.'
        }
    }

    Write-Output "current_database=$actualDatabase"

    if ($DryRun) {
        $dryRunArguments = $connectionArguments + @('-c', 'BEGIN;', '-c', 'SET LOCAL client_min_messages = warning;')
        if ($Db -eq $trainingDatabase) {
            $dryRunArguments += @('-c', "SET LOCAL fase1.confirmed_target = '$Db';")
        }

        foreach ($resolvedSetupFile in $resolvedSetupFiles) {
            $dryRunArguments += @('-f', $resolvedSetupFile)
        }

        $dryRunArguments += @('-f', $resolvedFile, '-c', 'ROLLBACK;')
        & $psqlCommand.Source @dryRunArguments 2>&1
    } elseif ($Atomic) {
        $atomicArguments = $connectionArguments + @('--single-transaction')
        if ($Db -eq $trainingDatabase) {
            $atomicArguments += @('-c', "SET LOCAL fase1.confirmed_target = '$Db';")
        }
        $atomicArguments += @('-f', $resolvedFile)
        & $psqlCommand.Source @atomicArguments 2>&1
    } else {
        & $psqlCommand.Source @connectionArguments -f $resolvedFile 2>&1
    }

    $executionExitCode = $LASTEXITCODE
    if ($executionExitCode -ne 0) {
        throw "Eksekusi SQL gagal untuk $resolvedFile (exit code $executionExitCode)."
    }
} finally {
    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
}
