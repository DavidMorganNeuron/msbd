[CmdletBinding()]
param (
    [Parameter(Mandatory = $true)]
    [string] $OutputPath
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$environmentFile = Join-Path $repositoryRoot '.env'
$OutputPath = [System.IO.Path]::GetFullPath($OutputPath)
$temporaryRoot = [System.IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
if (-not $OutputPath.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Backup staging harus disimpan di direktori TEMP, bukan di repository.'
}
if ([System.IO.Path]::GetFileName($OutputPath) -notmatch '^fase1_staging_.*\.dump$') {
    throw 'Nama backup harus diawali fase1_staging_ dan berakhiran .dump.'
}
if (Test-Path -LiteralPath $OutputPath) {
    throw "File backup sudah ada; gunakan nama baru: $OutputPath"
}
if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw "File koneksi tidak ditemukan: $environmentFile"
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
    $currentDatabase = (& $psql -X -w -A -t -v ON_ERROR_STOP=1 -d msbd_tubes_kel7_staging -c 'SELECT current_database();' 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $currentDatabase -ne 'msbd_tubes_kel7_staging') {
        throw "Koneksi staging tidak terverifikasi; database aktual: $currentDatabase"
    }

    $dumpOutput = & $pgDump -w --format=custom --no-owner --no-acl -f $OutputPath -d msbd_tubes_kel7_staging 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw 'pg_dump gagal untuk msbd_tubes_kel7_staging.'
    }

    $tocOutput = & $pgRestore -l $OutputPath 2>&1
    $tocEntries = @($tocOutput | Where-Object { $_ -match '^\s*[0-9]+;\s' }).Count
    if ($LASTEXITCODE -ne 0 -or $tocEntries -eq 0) {
        throw 'pg_restore -l gagal memverifikasi backup staging.'
    }

    Write-Output 'source_database=msbd_tubes_kel7_staging'
    Write-Output "backup_dump=$OutputPath"
    Write-Output ('backup_bytes={0}' -f (Get-Item -LiteralPath $OutputPath).Length)
    Write-Output "toc_entries=$tocEntries"
} catch {
    if (Test-Path -LiteralPath $OutputPath -PathType Leaf) {
        Remove-Item -LiteralPath $OutputPath -Force
    }
    throw
} finally {
    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
}
