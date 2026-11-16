[CmdletBinding()]
param (
    [string] $Database = 'msbd_tubes_kel7_staging',
    [string] $ReportPath
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$environmentFile = Join-Path $repositoryRoot '.env'
$schemaBackupPath = Join-Path $repositoryRoot 'database\sql\schema_backup.sql'
$queryFile = Join-Path $PSScriptRoot '02_orphan_audit.sql'

if ($Database -ne 'msbd_tubes_kel7_staging') {
    throw 'Laporan orphan hanya boleh dibaca dari msbd_tubes_kel7_staging.'
}
if ([string]::IsNullOrWhiteSpace($ReportPath)) {
    $ReportPath = Join-Path $repositoryRoot 'database\sql\ORPHAN_REPORT.md'
} else {
    $ReportPath = [System.IO.Path]::GetFullPath($ReportPath)
}

foreach ($requiredPath in @($environmentFile, $schemaBackupPath, $queryFile, $ReportPath)) {
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "File yang dibutuhkan tidak ditemukan: $requiredPath"
    }
}

$psql = (Get-Command psql -ErrorAction Stop).Source
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

function Normalize-Columns {
    param ([string] $Columns)
    $normalized = @($Columns -split ',' | ForEach-Object { $_.Trim().Trim('"').ToLowerInvariant() })
    return ($normalized -join ',')
}

try {
    $queryOutput = & $psql -X -w -v ON_ERROR_STOP=1 -A -t -F "`t" -d $Database -f $queryFile 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Audit orphan gagal pada $Database dengan exit code $LASTEXITCODE."
    }

    $rows = @()
    foreach ($line in $queryOutput) {
        $fields = ([string] $line) -split "`t"
        if ($fields.Count -ne 9) {
            continue
        }
        $rows += [pscustomobject] @{
            Constraint = $fields[0]
            ChildTable = $fields[1]
            ChildColumns = $fields[2]
            ParentTable = $fields[3]
            ParentColumns = $fields[4]
            OnDelete = $fields[5]
            Status = $fields[6]
            MatchType = $fields[7]
            Orphans = [long] $fields[8]
        }
    }
    if ($rows.Count -eq 0) {
        throw 'Audit orphan tidak menghasilkan baris FK yang dapat dibaca.'
    }

    $schemaLines = Get-Content -LiteralPath $schemaBackupPath
    $baselineKeys = @{}
    $currentTable = $null
    foreach ($line in $schemaLines) {
        if ($line -match '^\s*ALTER TABLE ONLY public\.([^\s]+)\s*$') {
            $currentTable = $Matches[1].Trim('"')
            continue
        }
        if ($line -match '^\s*ADD CONSTRAINT\s+"?(?<constraint>[^"\s]+)"?\s+FOREIGN KEY\s*\((?<childColumns>[^)]+)\)\s+REFERENCES\s+(?:public\.)?"?(?<parentTable>[^("]+)"?\s*\((?<parentColumns>[^)]+)\)\s+ON DELETE\s+(?<onDelete>[^;]+);') {
            if ([string]::IsNullOrWhiteSpace($currentTable)) {
                throw 'Constraint FK pada schema_backup.sql tidak memiliki tabel child yang dapat dipetakan.'
            }
            $constraintName = $Matches['constraint'].Trim('"')
            $key = $currentTable + '|' + $constraintName
            if ($baselineKeys.ContainsKey($key)) {
                throw "FK duplikat pada schema_backup.sql: $key"
            }
            $baselineKeys[$key] = [pscustomobject] @{
                ChildTable = $currentTable
                ChildColumns = Normalize-Columns $Matches['childColumns']
                ParentTable = $Matches['parentTable'].Trim('"').ToLowerInvariant()
                ParentColumns = Normalize-Columns $Matches['parentColumns']
                OnDelete = $Matches['onDelete'].Trim().ToUpperInvariant()
            }
        }
    }
    if ($baselineKeys.Count -ne 83) {
        throw "Jumlah FK pada schema_backup.sql berbeda dari baseline terverifikasi 83: $($baselineKeys.Count)."
    }

    $actualKeys = @{}
    $definitionMismatches = @()
    foreach ($row in $rows) {
        $childTable = ($row.ChildTable -split '\.')[-1].Trim('"').ToLowerInvariant()
        $key = $childTable + '|' + $row.Constraint
        if ($actualKeys.ContainsKey($key)) {
            throw "FK duplikat pada katalog staging: $key"
        }
        $actualKeys[$key] = $row

        if (-not $baselineKeys.ContainsKey($key)) {
            $baselineMatch = 'Tidak'
        } else {
            $baseline = $baselineKeys[$key]
            $parentTable = ($row.ParentTable -split '\.')[-1].Trim('"').ToLowerInvariant()
            $definitionsMatch = (Normalize-Columns $row.ChildColumns) -eq $baseline.ChildColumns `
                -and $parentTable -eq $baseline.ParentTable `
                -and (Normalize-Columns $row.ParentColumns) -eq $baseline.ParentColumns `
                -and $row.OnDelete -eq $baseline.OnDelete
            if ($definitionsMatch) {
                $baselineMatch = 'Ya'
            } else {
                $baselineMatch = 'Berbeda'
                $definitionMismatches += $key
            }
        }
        $row | Add-Member -NotePropertyName BaselineMatch -NotePropertyValue $baselineMatch
    }

    $matchedCount = @($rows | Where-Object { $_.BaselineMatch -eq 'Ya' }).Count
    $missingBaseline = @($baselineKeys.Keys | Where-Object { -not $actualKeys.ContainsKey($_) } | Sort-Object)
    $postOnlyCount = @($rows | Where-Object { $_.BaselineMatch -eq 'Tidak' }).Count
    $validCount = @($rows | Where-Object { $_.Status -eq 'VALID' }).Count
    $notValidCount = @($rows | Where-Object { $_.Status -eq 'NOT VALID' }).Count
    $orphanRows = ($rows | Measure-Object -Property Orphans -Sum).Sum
    $baselineNotes = @()
    if ($missingBaseline.Count -eq 0) {
        $baselineNotes += 'Tidak ada FK baseline yang hilang dari katalog staging.'
    } else {
        $baselineNotes += 'FK baseline yang tidak ditemukan: ' + (($missingBaseline | ForEach-Object { '`' + $_.Replace('|', '.') + '`' }) -join ', ')
    }
    if ($definitionMismatches.Count -eq 0) {
        $baselineNotes += 'Tidak ada perbedaan tabel/kolom/parent/ON DELETE pada FK baseline yang ditemukan.'
    } else {
        $baselineNotes += 'Definisi FK berbeda dari schema_backup.sql: ' + (($definitionMismatches | ForEach-Object { '`' + $_.Replace('|', '.') + '`' }) -join ', ')
    }

    $summaryLines = @(
        "- Basis `schema_backup.sql`: **$($baselineKeys.Count) FK**; definisi cocok penuh dengan katalog staging: **$matchedCount**; definisi berbeda: **$($definitionMismatches.Count)**; tidak ditemukan: **$($missingBaseline.Count)**; FK katalog di luar basis: **$postOnlyCount**.",
        ('- Katalog staging: **{0} FK** ({1} `VALID`, {2} `NOT VALID`); anti-join menemukan **{3} baris yatim**.' -f $rows.Count, $validCount, $notValidCount, $orphanRows)
    )
    foreach ($baselineNote in $baselineNotes) {
        $summaryLines += '- ' + $baselineNote
    }
    $summaryLines += '- Tidak ada row yatim yang diperbaiki/dihapus oleh audit ini. FK `NOT VALID` dipertahankan sampai pemetaan bisnis dan pembersihan data disetujui; menjalankan `VALIDATE CONSTRAINT` sebelum orphan menjadi nol tidak dilakukan.'

    $tableLines = @(
        '| FK | Definisi cocok dengan schema_backup.sql | Tabel child | Kolom child | Tabel parent | Kolom parent | ON DELETE | Status | MATCH | Baris yatim | Catatan hasil/kebijakan |',
        '| --- | --- | --- | --- | --- | --- | --- | --- | --- | ---: | --- |'
    )
    foreach ($row in $rows) {
        if ($row.Orphans -gt 0 -and $row.Status -eq 'NOT VALID') {
            $reason = 'NOT VALID dipertahankan: orphan masih ada; tidak ada row diubah.'
        } elseif ($row.Status -eq 'NOT VALID') {
            $reason = 'NOT VALID; anti-join saat audit=0; validasi ditunda sampai ditinjau.'
        } elseif ($row.Orphans -gt 0) {
            $reason = 'Anomali: FK VALID tetapi orphan terhitung; wajib investigasi.'
        } else {
            $reason = 'VALID; anti-join tidak menemukan orphan.'
        }

        $tableLines += '| `{0}` | {1} | `{2}` | `{3}` | `{4}` | `{5}` | {6} | {7} | {8} | {9} | {10} |' -f `
            $row.Constraint, $row.BaselineMatch, $row.ChildTable, $row.ChildColumns, $row.ParentTable, $row.ParentColumns, `
            $row.OnDelete, $row.Status, $row.MatchType, $row.Orphans, $reason
    }

    $report = Get-Content -LiteralPath $ReportPath -Raw -Encoding UTF8
    $regions = @(
        @{
            Begin = '<!-- BEGIN GENERATED FK SUMMARY -->'
            End = '<!-- END GENERATED FK SUMMARY -->'
            Lines = $summaryLines
        },
        @{
            Begin = '<!-- BEGIN GENERATED FK DETAILS -->'
            End = '<!-- END GENERATED FK DETAILS -->'
            Lines = $tableLines
        }
    )
    foreach ($region in $regions) {
        $pattern = [regex]::Escape($region.Begin) + '.*?' + [regex]::Escape($region.End)
        if (-not [regex]::IsMatch($report, $pattern, [System.Text.RegularExpressions.RegexOptions]::Singleline)) {
            throw "Penanda region otomatis tidak ditemukan: $($region.Begin)"
        }
        $replacement = $region.Begin + [Environment]::NewLine + ($region.Lines -join [Environment]::NewLine) + [Environment]::NewLine + $region.End
        $report = [regex]::Replace(
            $report,
            $pattern,
            [System.Text.RegularExpressions.MatchEvaluator] { param($match) $replacement },
            [System.Text.RegularExpressions.RegexOptions]::Singleline
        )
    }
    Set-Content -LiteralPath $ReportPath -Value $report -Encoding UTF8

    Write-Output "database=$Database"
    Write-Output "fk_catalog=$($rows.Count) valid=$validCount not_valid=$notValidCount orphan_rows=$orphanRows"
    Write-Output "schema_backup_fk=$($baselineKeys.Count) exact_match=$matchedCount definition_mismatch=$($definitionMismatches.Count) missing=$($missingBaseline.Count) catalog_only=$postOnlyCount"
    Write-Output "report=$ReportPath"
} finally {
    foreach ($variable in $originalEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($variable, $originalEnvironment[$variable], 'Process')
    }
}
