[CmdletBinding()]
param (
    [string] $BeforeDump,
    [string] $AfterDatabase = 'msbd_tubes_kel7_staging',
    [string] $BeforeSnapshotPath,
    [string] $ReportPath,
    [switch] $KonfirmasiUtama
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$environmentFile = Join-Path $repositoryRoot '.env'
$queryFile = Join-Path $PSScriptRoot '00_hitung_objek.sql'

if ([string]::IsNullOrWhiteSpace($BeforeSnapshotPath)) {
    $BeforeSnapshotPath = Join-Path $PSScriptRoot 'katalog_sebelum.txt'
}

if ([string]::IsNullOrWhiteSpace($ReportPath)) {
    $ReportPath = Join-Path $PSScriptRoot '..\FASE1_LAPORAN.md'
}

if ($AfterDatabase -eq 'msbd_tubes_kel7') {
    if (-not $KonfirmasiUtama) {
        throw 'Database utama memerlukan switch -KonfirmasiUtama dan hanya boleh digunakan setelah seluruh gerbang A/B/C lulus.'
    }
} elseif ($AfterDatabase -ne 'msbd_tubes_kel7_staging') {
    throw 'Database sesudah hanya boleh msbd_tubes_kel7_staging atau msbd_tubes_kel7 dengan -KonfirmasiUtama.'
}

if (-not (Test-Path -LiteralPath $queryFile -PathType Leaf)) {
    throw "Query katalog tidak ditemukan: $queryFile"
}

if (-not (Test-Path -LiteralPath $environmentFile -PathType Leaf)) {
    throw "File koneksi tidak ditemukan: $environmentFile"
}

$psql = (Get-Command psql -ErrorAction Stop).Source
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

$beforeDatabase = $null
$createdBeforeDatabase = $false

try {
    $beforeCounts = @{}

    if (Test-Path -LiteralPath $BeforeSnapshotPath -PathType Leaf) {
        foreach ($line in Get-Content -LiteralPath $BeforeSnapshotPath) {
            if ($line -match '^([^#|][^|]*)\|([0-9]+)$') {
                $beforeCounts[$Matches[1]] = [long] $Matches[2]
            }
        }

        if ($beforeCounts.Count -eq 0) {
            throw "Snapshot katalog sebelum kosong atau tidak valid: $BeforeSnapshotPath"
        }
    } else {
        if ([string]::IsNullOrWhiteSpace($BeforeDump) -or -not (Test-Path -LiteralPath $BeforeDump -PathType Leaf)) {
            throw 'Sediakan -BeforeDump dari backup sebelum Tahap 1 saat katalog_sebelum.txt belum ada.'
        }

        $beforeDatabase = 'fase1_catalog_pre_' + [guid]::NewGuid().ToString('N').Substring(0, 12)
        $listOutput = & $pgRestore -l $BeforeDump 2>&1
        if ($LASTEXITCODE -ne 0 -or $listOutput.Count -lt 2) {
            throw "Backup sebelum tidak dapat dibaca oleh pg_restore: $BeforeDump"
        }

        $createOutput = & $psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "CREATE DATABASE `"$beforeDatabase`";" 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Database katalog sementara tidak dapat dibuat: $($createOutput | Out-String)"
        }
        $createdBeforeDatabase = $true

        $restoreOutput = & $pgRestore -w --exit-on-error --no-owner --no-acl -d $beforeDatabase $BeforeDump 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Backup katalog sebelum gagal dipulihkan: $($restoreOutput | Out-String)"
        }

        $beforeOutput = & $psql -X -w -v ON_ERROR_STOP=1 -A -t -F '|' -d $beforeDatabase -f $queryFile 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Query katalog sebelum gagal: $($beforeOutput | Out-String)"
        }

        foreach ($line in $beforeOutput) {
            if ($line -match '^([^|]+)\|([0-9]+)$') {
                $beforeCounts[$Matches[1]] = [long] $Matches[2]
            }
        }

        if ($beforeCounts.Count -eq 0) {
            throw 'Query katalog sebelum tidak menghasilkan baris yang dapat dibaca.'
        }

        $snapshotLines = @('# Snapshot katalog dari pg_catalog pada backup sebelum Tahap 1')
        foreach ($key in $beforeCounts.Keys | Sort-Object) {
            $snapshotLines += '{0}|{1}' -f $key, $beforeCounts[$key]
        }
        $snapshotDirectory = Split-Path -Parent $BeforeSnapshotPath
        if (-not (Test-Path -LiteralPath $snapshotDirectory -PathType Container)) {
            New-Item -ItemType Directory -Path $snapshotDirectory -Force | Out-Null
        }
        Set-Content -LiteralPath $BeforeSnapshotPath -Value $snapshotLines -Encoding UTF8
    }

    $afterOutput = & $psql -X -w -v ON_ERROR_STOP=1 -A -t -F '|' -d $AfterDatabase -f $queryFile 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Query katalog sesudah pada $AfterDatabase gagal: $($afterOutput | Out-String)"
    }

    $afterCounts = @{}
    foreach ($line in $afterOutput) {
        if ($line -match '^([^|]+)\|([0-9]+)$') {
            $afterCounts[$Matches[1]] = [long] $Matches[2]
        }
    }

    if ($afterCounts.Count -eq 0) {
        throw 'Query katalog sesudah tidak menghasilkan baris yang dapat dibaca.'
    }

    $objectNames = @(
        'Tabel',
        'Primary key',
        'Foreign key',
        'View',
        'Stored function non-trigger',
        'Trigger function',
        'Trigger non-internal',
        'Procedure',
        'Role cluster',
        'Role Fase 1',
        'GRANT ACL privilege entries',
        'UNIQUE constraint',
        'CHECK constraint'
    )
    $tableLines = @(
        '| Objek | Sebelum | Sesudah |',
        '| --- | ---: | ---: |'
    )

    foreach ($objectName in $objectNames) {
        if (-not $beforeCounts.ContainsKey($objectName) -or -not $afterCounts.ContainsKey($objectName)) {
            throw "Katalog tidak menghasilkan kategori yang diwajibkan: $objectName"
        }
        $tableLines += '| {0} | {1} | {2} |' -f $objectName, $beforeCounts[$objectName], $afterCounts[$objectName]
    }

    if (-not (Test-Path -LiteralPath $ReportPath -PathType Leaf)) {
        throw "Laporan tidak ditemukan: $ReportPath"
    }

    $report = Get-Content -LiteralPath $ReportPath -Raw -Encoding UTF8
    $beginMarker = '<!-- BEGIN GENERATED KATALOG COUNTS -->'
    $endMarker = '<!-- END GENERATED KATALOG COUNTS -->'
    $pattern = [regex]::Escape($beginMarker) + '.*?' + [regex]::Escape($endMarker)
    if (-not [regex]::IsMatch($report, $pattern, [System.Text.RegularExpressions.RegexOptions]::Singleline)) {
        throw 'Penanda tabel katalog otomatis tidak ditemukan pada laporan.'
    }

    $replacement = $beginMarker + [Environment]::NewLine + ($tableLines -join [Environment]::NewLine) + [Environment]::NewLine + $endMarker
    $report = [regex]::Replace(
        $report,
        $pattern,
        [System.Text.RegularExpressions.MatchEvaluator] { param($match) $replacement },
        [System.Text.RegularExpressions.RegexOptions]::Singleline
    )
    Set-Content -LiteralPath $ReportPath -Value $report -Encoding UTF8

    $tableLines | ForEach-Object { Write-Output $_ }
    Write-Output "Snapshot sebelum: $BeforeSnapshotPath"
    Write-Output "Katalog sesudah dibaca dari pg_catalog pada $AfterDatabase."
} finally {
    if ($createdBeforeDatabase -and $beforeDatabase) {
        $dropOutput = & $psql -X -w -v ON_ERROR_STOP=1 -d postgres -c "DROP DATABASE IF EXISTS `"$beforeDatabase`" WITH (FORCE);" 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Database katalog sementara gagal dihapus: $($dropOutput | Out-String)"
        }
    }

    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
}
