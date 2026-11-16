[CmdletBinding()]
param (
    [Parameter(Mandatory = $true)]
    [string] $BeforeJunit,

    [Parameter(Mandatory = $true)]
    [string] $AfterJunit,

    [Parameter(Mandatory = $true)]
    [string] $BeforeLog,

    [Parameter(Mandatory = $true)]
    [string] $AfterLog,
    [string] $OutputPath,
    [string] $SummaryPath
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $repositoryRoot 'database\sql\FASE1_BASELINE_GAGAL.txt'
} else {
    $OutputPath = [System.IO.Path]::GetFullPath($OutputPath)
}

if ([string]::IsNullOrWhiteSpace($SummaryPath)) {
    $SummaryPath = Join-Path $repositoryRoot 'database\sql\FASE1_BASELINE.md'
} else {
    $SummaryPath = [System.IO.Path]::GetFullPath($SummaryPath)
}

foreach ($junitPath in @($BeforeJunit, $AfterJunit)) {
    if (-not (Test-Path -LiteralPath $junitPath -PathType Leaf)) {
        throw "JUnit tidak ditemukan: $junitPath"
    }
}

function Get-TestCases {
    param ([string] $JunitPath)

    [xml] $document = Get-Content -LiteralPath $JunitPath -Raw -Encoding UTF8
    $nodes = @($document.SelectNodes('//testcase'))
    if ($nodes.Count -eq 0) {
        throw "JUnit tidak memuat testcase: $JunitPath"
    }

    $occurrences = @{}
    $cases = @()
    foreach ($node in $nodes) {
        $className = [string] $node.classname
        $testName = [string] $node.name
        $baseKey = $className + '::' + $testName
        if (-not $occurrences.ContainsKey($baseKey)) {
            $occurrences[$baseKey] = 0
        }
        $occurrences[$baseKey]++
        $key = $baseKey + '#' + $occurrences[$baseKey]

        $failure = $node.SelectSingleNode('./failure')
        $error = $node.SelectSingleNode('./error')
        $skipped = $node.SelectSingleNode('./skipped')
        $result = 'passed'
        $diagnostic = ''
        $incomplete = $false

        if ($error) {
            $result = 'error'
            $diagnostic = [string] $error.message
            if ([string]::IsNullOrWhiteSpace($diagnostic)) {
                $diagnostic = [string] $error.InnerText
            }
            $incomplete = ([string] $error.type -match 'IncompleteTest|Incomplete')
        } elseif ($failure) {
            $result = 'failed'
            $diagnostic = [string] $failure.message
            if ([string]::IsNullOrWhiteSpace($diagnostic)) {
                $diagnostic = [string] $failure.InnerText
            }
            $incomplete = ([string] $failure.type -match 'IncompleteTest|Incomplete')
        } elseif ($skipped) {
            $result = 'skipped'
            $diagnostic = [string] $skipped.message
            if ([string]::IsNullOrWhiteSpace($diagnostic)) {
                $diagnostic = [string] $skipped.InnerText
            }
            $incomplete = ([string] $skipped.type -match 'IncompleteTest|Incomplete') -or ($diagnostic -match 'incomplete')
        }

        if ($incomplete) {
            $result += '+incomplete'
        }

        $diagnostic = $diagnostic -replace 'fase1_test_[a-z0-9]{12}', '[TEST_DB]'
        $diagnostic = $diagnostic -replace '(?i)\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{20,}', '[REDACTED_HASH]'
        $diagnostic = $diagnostic -replace '\b[0-9]{10,16}\b', '[REDACTED_IDENTIFIER]'
        $diagnostic = $diagnostic -replace '(?i)\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b', '[REDACTED_EMAIL]'
        $diagnostic = $diagnostic -replace '[\r\n\t|]+', ' '
        $diagnostic = $diagnostic.Trim()
        if ($diagnostic.Length -gt 500) {
            $diagnostic = $diagnostic.Substring(0, 500)
        }

        $cases += [pscustomobject] @{
            Key = $key
            ClassName = $className
            TestName = $testName
            Result = $result
            IsIncomplete = $incomplete
            Diagnostic = $diagnostic
        }
    }

    return ,$cases
}

function Get-Summary {
    param (
        [string] $JunitPath,
        [string] $LogPath
    )

    [xml] $document = Get-Content -LiteralPath $JunitPath -Raw -Encoding UTF8
    $suite = $document.SelectSingleNode('/testsuites/testsuite')
    if (-not $suite) {
        throw "JUnit tidak memiliki testsuite root: $JunitPath"
    }

    if (-not $LogPath -or -not (Test-Path -LiteralPath $LogPath -PathType Leaf)) {
        throw "Log runner wajib tersedia untuk membandingkan hasil: $LogPath"
    }

    $logText = Get-Content -LiteralPath $LogPath -Raw -Encoding UTF8
    if ($logText -notmatch '(?m)^TEST_EXIT_CODE=([0-9]+)\s*$') {
        throw "Exit code runner tidak ditemukan di log: $LogPath"
    }
    $exitCode = $Matches[1]

    $reportText = $logText -replace '(?m)^RUNNER=.*?\r?\n', ''
    $reportText = $reportText -replace '(?m)^TEST_EXIT_CODE=[0-9]+\s*$', ''
    try {
        $runnerReport = ConvertFrom-Json -InputObject $reportText
    } catch {
        throw "Ringkasan JSON runner tidak valid di log $LogPath`: $($_.Exception.Message)"
    }

    foreach ($requiredField in @('tests', 'passed', 'failed', 'error_details', 'incomplete', 'assertions', 'duration_ms')) {
        if ($null -eq $runnerReport.PSObject.Properties[$requiredField]) {
            throw "Field '$requiredField' tidak ada pada ringkasan runner: $LogPath"
        }
    }

    $junitFailures = [int] $suite.failures
    $junitErrors = [int] $suite.errors
    $junitSkipped = [int] $suite.skipped
    $failed = [int] $runnerReport.failed
    $errors = @($runnerReport.error_details).Count
    $incomplete = [int] $runnerReport.incomplete
    if ($junitFailures -ne $failed -or $junitErrors -ne $errors -or $junitSkipped -ne $incomplete) {
        throw "Keluaran JUnit tidak cocok dengan ringkasan runner untuk $JunitPath."
    }
    $tests = [int] $runnerReport.tests
    $passed = [int] $runnerReport.passed
    $assertions = [int] $runnerReport.assertions
    $duration = [math]::Round(([double] $runnerReport.duration_ms / 1000), 3)

    return [pscustomobject] @{
        Tests = $tests
        Passed = $passed
        Failed = $failed
        Errors = $errors
        Incomplete = $incomplete
        JunitTests = [int] $suite.tests
        JunitFailures = $junitFailures
        JunitErrors = $junitErrors
        JunitSkipped = $junitSkipped
        JunitCaseNodes = @($document.SelectNodes('//testcase')).Count
        Assertions = $assertions
        Duration = $duration
        ExitCode = $exitCode
        Sha256 = (Get-FileHash -LiteralPath $JunitPath -Algorithm SHA256).Hash.ToLowerInvariant()
    }
}

function Get-IncompleteCandidates {
    param (
        [string] $TestsPath,
        [object[]] $Cases
    )

    $normalizeName = {
        param ([string] $Name)
        return (($Name -replace '^test_', '' -replace '_', ' ') -replace '[^a-zA-Z0-9 ]', '').ToLowerInvariant().Trim()
    }

    $candidates = @()
    foreach ($file in Get-ChildItem -LiteralPath $TestsPath -Filter '*.php' -Recurse -File) {
        $source = Get-Content -LiteralPath $file.FullName -Raw -Encoding UTF8
        $namespaceMatch = [regex]::Match($source, '(?m)^\s*namespace\s+([^;]+);')
        $classMatch = [regex]::Match($source, '(?m)^\s*(?:final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)')
        if (-not $namespaceMatch.Success -or -not $classMatch.Success) {
            continue
        }

        $className = $namespaceMatch.Groups[1].Value.Trim().Replace('\', '.') + '.' + $classMatch.Groups[1].Value
        foreach ($marker in [regex]::Matches($source, '->markTestIncomplete\s*\(')) {
            $prefix = $source.Substring(0, $marker.Index)
            $methods = [regex]::Matches($prefix, '(?m)^\s*(?:public|protected|private)\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(')
            if ($methods.Count -eq 0) {
                throw "Tidak dapat mengaitkan markTestIncomplete ke method: $($file.FullName)"
            }

            $methodName = $methods[$methods.Count - 1].Groups[1].Value
            $normalizedMethod = & $normalizeName $methodName
            $matchingCase = @($Cases | Where-Object {
                $_.ClassName -eq $className -and (& $normalizeName $_.TestName) -eq $normalizedMethod
            })
            if ($matchingCase.Count -gt 0) {
                continue
            }

            $relativePath = $file.FullName.Substring($TestsPath.TrimEnd('\').Length).TrimStart('\')
            $candidates += [pscustomobject] @{
                ClassName = $className
                MethodName = $methodName
                Test = $className + '::' + $methodName
                Source = $relativePath
            }
        }
    }

    return ,$candidates
}

$beforeCases = Get-TestCases -JunitPath $BeforeJunit
$afterCases = Get-TestCases -JunitPath $AfterJunit
$beforeSummary = Get-Summary -JunitPath $BeforeJunit -LogPath $BeforeLog
$afterSummary = Get-Summary -JunitPath $AfterJunit -LogPath $AfterLog
$testsPath = Join-Path $repositoryRoot 'tests'
$incompleteCandidates = Get-IncompleteCandidates -TestsPath $testsPath -Cases $afterCases
if ($incompleteCandidates.Count -ne $afterSummary.Incomplete -or $beforeSummary.Incomplete -ne $afterSummary.Incomplete) {
    throw "Jumlah markTestIncomplete yang tidak tercatat sebagai testcase JUnit tidak cocok dengan ringkasan runner: kandidat=$($incompleteCandidates.Count), baseline=$($beforeSummary.Incomplete), sesudah=$($afterSummary.Incomplete)."
}

$beforeByKey = @{}
$afterByKey = @{}
foreach ($case in $beforeCases) {
    $beforeByKey[$case.Key] = $case
}
foreach ($case in $afterCases) {
    $afterByKey[$case.Key] = $case
}

$allKeys = @($beforeByKey.Keys + $afterByKey.Keys | Sort-Object -Unique)
$rows = @()
$newFailures = 0
$resolved = 0
$diagnosticsChanged = 0
foreach ($key in $allKeys) {
    $beforeCase = $beforeByKey[$key]
    $afterCase = $afterByKey[$key]
    $beforeStatus = if ($beforeCase) { $beforeCase.Result } else { 'absent' }
    $afterStatus = if ($afterCase) { $afterCase.Result } else { 'absent' }
    $beforeBad = $beforeStatus -like 'failed*' -or $beforeStatus -like 'error*'
    $afterBad = $afterStatus -like 'failed*' -or $afterStatus -like 'error*'
    $diagnostic = if ($afterCase) { $afterCase.Diagnostic } elseif ($beforeCase) { $beforeCase.Diagnostic } else { '' }
    $changedDiagnostic = $beforeBad -and $afterBad -and $beforeCase.Diagnostic -ne $afterCase.Diagnostic
    if ($changedDiagnostic) {
        $diagnosticsChanged++
    }

    $change = 'unchanged'
    if (-not $beforeBad -and $afterBad) {
        $change = 'new_failure'
        $newFailures++
    } elseif ($beforeBad -and -not $afterBad) {
        $change = 'resolved'
        $resolved++
    } elseif ($changedDiagnostic) {
        $change = 'diagnostic_changed'
    } elseif ($beforeStatus -ne $afterStatus) {
        $change = 'status_changed'
    }

    if ($beforeStatus -ne 'passed' -or $afterStatus -ne 'passed') {
        $rows += [pscustomobject] @{
            Baseline = $beforeStatus
            Sesudah = $afterStatus
            Perubahan = $change
            Test = $key
            Diagnosis = $diagnostic
        }
    }
}

$incompleteRows = @()
foreach ($candidate in $incompleteCandidates) {
    $incompleteRows += [pscustomobject] @{
        Baseline = 'incomplete'
        Sesudah = 'incomplete'
        Perubahan = 'unchanged'
        Test = $candidate.Test
        Diagnosis = "Incomplete from $($candidate.Source); no testcase node in JUnit."
    }
}
$rows += $incompleteRows

$expectedAfterIssueCount = $afterSummary.Failed + $afterSummary.Errors + $afterSummary.Incomplete
$actualAfterIssueCount = @($rows | Where-Object {
    $_.Sesudah -like 'failed*' -or $_.Sesudah -like 'error*' -or $_.Sesudah -like 'incomplete*'
}).Count
if ($actualAfterIssueCount -ne $expectedAfterIssueCount) {
    throw "Daftar issue tidak lengkap: baris=$actualAfterIssueCount, jumlah runner=$expectedAfterIssueCount."
}

$outputLines = @(
    '# Generated from PHPUnit JUnit XML; diagnostics redact identifier-like values.',
    "before_sha256=$($beforeSummary.Sha256)",
    "after_sha256=$($afterSummary.Sha256)",
    "new_failed_or_error=$newFailures",
    "resolved_failed_or_error=$resolved",
    "changed_diagnostic=$diagnosticsChanged",
    "after_runner_tests=$($afterSummary.Tests)",
    "after_junit_testcase_nodes=$($afterSummary.JunitCaseNodes)",
    'baseline_status' + "`t" + 'after_status' + "`t" + 'change' + "`t" + 'test' + "`t" + 'diagnostic'
)
foreach ($row in $rows) {
    $outputLines += ('{0}{5}{1}{5}{2}{5}{3}{5}{4}' -f $row.Baseline, $row.Sesudah, $row.Perubahan, $row.Test, $row.Diagnosis, "`t")
}
Set-Content -LiteralPath $OutputPath -Value $outputLines -Encoding UTF8

$summaryLines = @(
    '| Eksekusi | Test (runner) | Passed | Failed | Errors | Incomplete | JUnit testcases | JUnit testcase nodes | JUnit failures | JUnit errors | JUnit skipped | Assertions | Durasi (s) | Exit | JUnit SHA256 |',
    '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |',
    ('| Baseline | {0} | {1} | {2} | {3} | {4} | {5} | {6} | {7} | {8} | {9} | {10} | {11} | {12} | `{13}` |' -f $beforeSummary.Tests, $beforeSummary.Passed, $beforeSummary.Failed, $beforeSummary.Errors, $beforeSummary.Incomplete, $beforeSummary.JunitTests, $beforeSummary.JunitCaseNodes, $beforeSummary.JunitFailures, $beforeSummary.JunitErrors, $beforeSummary.JunitSkipped, $beforeSummary.Assertions, $beforeSummary.Duration, $beforeSummary.ExitCode, $beforeSummary.Sha256),
    ('| Sesudah | {0} | {1} | {2} | {3} | {4} | {5} | {6} | {7} | {8} | {9} | {10} | {11} | {12} | `{13}` |' -f $afterSummary.Tests, $afterSummary.Passed, $afterSummary.Failed, $afterSummary.Errors, $afterSummary.Incomplete, $afterSummary.JunitTests, $afterSummary.JunitCaseNodes, $afterSummary.JunitFailures, $afterSummary.JunitErrors, $afterSummary.JunitSkipped, $afterSummary.Assertions, $afterSummary.Duration, $afterSummary.ExitCode, $afterSummary.Sha256),
    '',
    "Runner melaporkan $($afterSummary.Tests) test; JUnit suite melaporkan $($afterSummary.JunitTests) test dan memuat $($afterSummary.JunitCaseNodes) node testcase. Selisih runner vs node JUnit adalah $($afterSummary.Tests - $afterSummary.JunitCaseNodes); seluruh counter failed/errors/incomplete runner cocok dengan JUnit suite, tetapi successful testcase yang tidak diserialisasi tidak dapat diidentifikasi satu per satu dari XML.",
    '',
    '| Perbandingan identifier | Jumlah |',
    '| --- | ---: |',
    "| Test baru gagal/error | $newFailures |",
    "| Test gagal/error terselesaikan | $resolved |",
    "| Diagnosis berubah pada test gagal/error yang sama | $diagnosticsChanged |"
)

if ($newFailures -eq 0) {
    $summaryLines += ''
    $summaryLines += 'Tidak ada test baru gagal/error; akar masalah baru tidak berlaku. Perubahan diagnosis yang sama tetap harus ditinjau pada tabel baseline dan dilaporkan sebagai perubahan penyebab, bukan regresi identifier.'
} else {
    $summaryLines += ''
    $summaryLines += 'Akar masalah untuk setiap test baru gagal/error harus dianalisis dari detail JUnit yang sudah disanitasi sebelum laporan dianggap selesai.'
}

$summary = Get-Content -LiteralPath $SummaryPath -Raw -Encoding UTF8
$beginMarker = '<!-- BEGIN GENERATED TEST COMPARISON -->'
$endMarker = '<!-- END GENERATED TEST COMPARISON -->'
$pattern = [regex]::Escape($beginMarker) + '.*?' + [regex]::Escape($endMarker)
if (-not [regex]::IsMatch($summary, $pattern, [System.Text.RegularExpressions.RegexOptions]::Singleline)) {
    throw 'Penanda perbandingan test otomatis tidak ditemukan pada FASE1_BASELINE.md.'
}
$replacement = $beginMarker + [Environment]::NewLine + ($summaryLines -join [Environment]::NewLine) + [Environment]::NewLine + $endMarker
$summary = [regex]::Replace(
    $summary,
    $pattern,
    [System.Text.RegularExpressions.MatchEvaluator] { param($match) $replacement },
    [System.Text.RegularExpressions.RegexOptions]::Singleline
)
Set-Content -LiteralPath $SummaryPath -Value $summary -Encoding UTF8

Write-Output "new_failed_or_error=$newFailures"
Write-Output "resolved_failed_or_error=$resolved"
Write-Output "changed_diagnostic=$diagnosticsChanged"
Write-Output "failure_list=$OutputPath"
Write-Output "summary=$SummaryPath"
