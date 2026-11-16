[CmdletBinding()]
param ()

$ErrorActionPreference = 'Stop'
$database = 'msbd_tubes_kel7_staging'
$runner = Join-Path $PSScriptRoot 'jalankan.ps1'
$files = @()
$files += Get-ChildItem -LiteralPath (Join-Path $PSScriptRoot 'ddl') -Filter '*.sql' -File | Sort-Object Name
$excludedDclFile = '90_hapus_role_guru.sql'
$dclFiles = Get-ChildItem -LiteralPath (Join-Path $PSScriptRoot 'dcl') -Filter '*.sql' -File
$files += $dclFiles | Where-Object { $_.Name -ne $excludedDclFile } |
    Sort-Object Name

if (@($dclFiles | Where-Object { $_.Name -eq $excludedDclFile }).Count -gt 0) {
    Write-Output "SKIP: dcl\$excludedDclFile (tidak dijalankan atau di-dry-run)."
}

$failedFiles = @()
$successfulFiles = @()
foreach ($file in $files) {
    $relativeFile = $file.FullName.Substring((Resolve-Path $PSScriptRoot).Path.Length + 1)
    $databaseRelativeFile = Join-Path 'database\sql' $relativeFile
    $setupFilesForRun = @($successfulFiles)
    if ($file.Name -ne '00_preflight_audit.sql') {
        $statusRepairFile = Join-Path 'database\sql' 'dml\00_repair_status_ids.sql'
        $setupFilesForRun = @($statusRepairFile) + $setupFilesForRun
    }

    Write-Output "DRY-RUN: $relativeFile"

    try {
        & $runner -Db $database -File $databaseRelativeFile -SetupFiles $setupFilesForRun -DryRun
        if ($LASTEXITCODE -ne 0) {
            $failedFiles += $relativeFile
            Write-Output "FAIL: $relativeFile (exit code $LASTEXITCODE)"
        } else {
            Write-Output "PASS: $relativeFile"
            $successfulFiles += $databaseRelativeFile
        }
    } catch {
        $failedFiles += $relativeFile
        Write-Output ('FAIL: {0} - {1}' -f $relativeFile, $_.Exception.Message)
    }
}

if ($failedFiles.Count -gt 0) {
    Write-Output ('Dry-run gagal untuk {0} file:' -f $failedFiles.Count)
    $failedFiles | ForEach-Object { Write-Output " - $_" }
    exit 1
}

Write-Output ('Dry-run lulus untuk seluruh {0} file DDL/DCL yang dipilih.' -f $files.Count)
