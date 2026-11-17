[CmdletBinding()]
param (
    [string] $SourceDatabase,
    [string] $SourceDump,
    [string] $JunitPath,
    [string] $LogPath,
    [switch] $AllowPrimarySource,
    [switch] $AllowTrainingClone,
    [switch] $UsePestRunner
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$environmentFile = Join-Path $repositoryRoot '.env'
$runId = [guid]::NewGuid().ToString('N')
$cloneDatabase = 'fase1_test_' + $runId.Substring(0, 12)
$temporaryDump = Join-Path $env:TEMP ($cloneDatabase + '.dump')

if ([string]::IsNullOrWhiteSpace($SourceDatabase) -and [string]::IsNullOrWhiteSpace($SourceDump)) {
    $SourceDatabase = 'msbd_tubes_kel7_staging'
}

if (-not [string]::IsNullOrWhiteSpace($SourceDatabase) -and -not [string]::IsNullOrWhiteSpace($SourceDump)) {
    throw 'Gunakan salah satu dari -SourceDatabase atau -SourceDump.'
}

if ($SourceDatabase -and $SourceDatabase -notin @('msbd_tubes_kel7_staging', 'msbd_tubes_kel7', 'msbd_latihan_utama')) {
    throw 'Sumber database hanya boleh staging, klon latihan bertanda, atau database utama dengan -AllowPrimarySource.'
}

if ($SourceDatabase -eq 'msbd_tubes_kel7' -and -not $AllowPrimarySource) {
    throw 'Klon database utama ditolak tanpa -AllowPrimarySource.'
}

if ($SourceDatabase -eq 'msbd_latihan_utama' -and -not $AllowTrainingClone) {
    throw 'Klon latihan ditolak tanpa -AllowTrainingClone.'
}

if ($SourceDump) {
    $SourceDump = [System.IO.Path]::GetFullPath($SourceDump)
    $temporaryRoot = [System.IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
    if (-not $SourceDump.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Arsip sumber harus berada di direktori TEMP; jangan menaruh dump data di repository.'
    }

    $dumpName = [System.IO.Path]::GetFileName($SourceDump)
    if ($dumpName -notmatch '^fase1_staging_' -and -not $AllowPrimarySource) {
        throw 'Arsip sumber bukan dump staging yang dikenali; database utama memerlukan -AllowPrimarySource setelah seluruh gerbang A/B/C lulus.'
    }
}

if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw "File koneksi tidak ditemukan: $environmentFile"
}

if ([string]::IsNullOrWhiteSpace($JunitPath)) {
    $JunitPath = Join-Path $env:TEMP ($cloneDatabase + '.junit.xml')
}

if ([string]::IsNullOrWhiteSpace($LogPath)) {
    $LogPath = [System.IO.Path]::ChangeExtension($JunitPath, '.log')
}

$JunitPath = [System.IO.Path]::GetFullPath($JunitPath)
$LogPath = [System.IO.Path]::GetFullPath($LogPath)
$temporaryRoot = [System.IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
foreach ($artifactPath in @($JunitPath, $LogPath)) {
    if (-not $artifactPath.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'JUnit dan log tes harus disimpan di direktori TEMP, bukan di repository.'
    }
}

$psql = (Get-Command psql -ErrorAction Stop).Source
$pgDump = (Get-Command pg_dump -ErrorAction Stop).Source
$pgRestore = (Get-Command pg_restore -ErrorAction Stop).Source
$php = (Get-Command php -ErrorAction Stop).Source
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
$applicationEnvironment = @{}
foreach ($variable in @('DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'APP_ENV')) {
    $applicationEnvironment[$variable] = [Environment]::GetEnvironmentVariable($variable, 'Process')
}
foreach ($key in $environmentMap.Keys) {
    $variable = $environmentMap[$key]
    $originalEnvironment[$variable] = [Environment]::GetEnvironmentVariable($variable, 'Process')
    if ($databaseSettings.ContainsKey($key)) {
        [Environment]::SetEnvironmentVariable($variable, $databaseSettings[$key], 'Process')
    }
}

$cloneCreated = $false
$ownsTemporaryDump = $false
$connectionCheckFile = Join-Path $env:TEMP ($cloneDatabase + '.php')
$ownsConnectionCheckFile = $false
$testExitCode = $null

try {
    Push-Location $repositoryRoot

    if ($SourceDatabase) {
        if ($SourceDatabase -eq 'msbd_latihan_utama') {
            $trainingMarker = (& $psql -X -w -A -t -v ON_ERROR_STOP=1 -d postgres -c "SELECT COALESCE(shobj_description(oid, 'pg_database'), '') FROM pg_database WHERE datname = 'msbd_latihan_utama';" 2>&1 | Out-String).Trim()
            if ($LASTEXITCODE -ne 0 -or $trainingMarker -ne 'FASE1_TRAINING_CLONE') {
                throw 'Sumber msbd_latihan_utama tidak memiliki marker FASE1_TRAINING_CLONE yang valid.'
            }
        }

        $ownsTemporaryDump = $true
        $dumpOutput = & $pgDump -w --format=custom --no-owner --no-acl -f $temporaryDump -d $SourceDatabase 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "pg_dump gagal untuk sumber $SourceDatabase`: $($dumpOutput | Out-String)"
        }
        $restoreSource = $temporaryDump
    } else {
        if (-not (Test-Path -LiteralPath $SourceDump -PathType Leaf)) {
            throw "Arsip sumber tidak ditemukan: $SourceDump"
        }
        $restoreSource = $SourceDump
    }

    $createOutput = & $psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "CREATE DATABASE `"$cloneDatabase`";" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Database test sementara gagal dibuat: $($createOutput | Out-String)"
    }
    $cloneCreated = $true

    $restoreOutput = & $pgRestore -w --exit-on-error --no-owner --no-acl -d $cloneDatabase $restoreSource 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "pg_restore gagal untuk database test sementara: $($restoreOutput | Out-String)"
    }

    $actualDatabase = (& $psql -X -w -A -t -v ON_ERROR_STOP=1 -d $cloneDatabase -c 'SELECT current_database();' 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $actualDatabase -ne $cloneDatabase) {
        throw "Target klon tidak cocok; diminta $cloneDatabase, aktual $actualDatabase."
    }

    $env:DB_CONNECTION = 'pgsql'
    $env:DB_DATABASE = $cloneDatabase
    $env:DB_URL = ''
    $env:APP_ENV = 'testing'

    $connectionCheck = @'
<?php
require getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$app = require getcwd() . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo \Illuminate\Support\Facades\DB::selectOne('SELECT current_database() AS database_name')->database_name;
'@
    Set-Content -LiteralPath $connectionCheckFile -Value $connectionCheck -Encoding ASCII
    $ownsConnectionCheckFile = $true
    $laravelDatabase = (& $php $connectionCheckFile 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $laravelDatabase -ne $cloneDatabase) {
        throw "Koneksi efektif Laravel bukan database klon; diminta $cloneDatabase, aktual $laravelDatabase."
    }

    if ($UsePestRunner) {
        $testOutput = & $php 'vendor\bin\pest' "--log-junit=$JunitPath" --display-incomplete 2>&1
        $runnerName = 'vendor/bin/pest'
    } else {
        $testOutput = & $php artisan test "--log-junit=$JunitPath" --display-incomplete 2>&1
        $runnerName = 'php artisan test'
    }
    $testExitCode = $LASTEXITCODE
    Set-Content -LiteralPath $LogPath -Value @("RUNNER=$runnerName") -Encoding UTF8
    Add-Content -LiteralPath $LogPath -Value @($testOutput) -Encoding UTF8
    Add-Content -LiteralPath $LogPath -Value "TEST_EXIT_CODE=$testExitCode" -Encoding UTF8

    if (-not (Test-Path -LiteralPath $JunitPath -PathType Leaf)) {
        throw "Test runner tidak menghasilkan JUnit: $JunitPath. Lihat $LogPath."
    }

    Write-Output "source_database=$SourceDatabase"
    if ($SourceDump) {
        Write-Output "source_dump=$SourceDump"
    }
    Write-Output "test_database=$cloneDatabase"
    Write-Output "effective_laravel_database=$laravelDatabase"
    Write-Output "runner=$runnerName"
    Write-Output "test_exit_code=$testExitCode"
    Write-Output "junit=$JunitPath"
    Write-Output "log=$LogPath"
} finally {
    if ($cloneCreated) {
        $dropOutput = & $psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "DROP DATABASE IF EXISTS `"$cloneDatabase`" WITH (FORCE);" 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Database test sementara gagal dihapus: $($dropOutput | Out-String)"
        }
    }

    if ($ownsTemporaryDump -and (Test-Path -LiteralPath $temporaryDump -PathType Leaf)) {
        Remove-Item -LiteralPath $temporaryDump -Force
    }

    if ($ownsConnectionCheckFile -and (Test-Path -LiteralPath $connectionCheckFile -PathType Leaf)) {
        Remove-Item -LiteralPath $connectionCheckFile -Force
    }

    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
    foreach ($variable in $applicationEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $applicationEnvironment[$variable], 'Process')
    }
    if ((Get-Location).Path -eq $repositoryRoot) {
        Pop-Location
    }
}

if ($testExitCode -ne $null) {
    exit $testExitCode
}
