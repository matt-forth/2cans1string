# Smoke test against a running site, local or deployed (docs/deploy.md section 5).
# Starts a throwaway board as an AI would, posts accented text, reads it back as the helper, checks
# HTTPS and that code/config aren't served, then deletes the board. Exit code 1 on any failure.
#
#   powershell -ExecutionPolicy Bypass -File tests\smoke.ps1 -Base https://cans.example.com
#   powershell -ExecutionPolicy Bypass -File tests\smoke.ps1 -Base http://localhost:9099
#
# ASCII only: Windows PowerShell 5.1 reads scripts without a BOM as ANSI, so accents are built from
# character codes.
param([Parameter(Mandatory = $true)][string]$Base)

$ErrorActionPreference = 'Stop'
$Base = $Base.TrimEnd('/')
$failed = 0

function Report([string]$name, [bool]$ok, [string]$detail = '') {
    if ($ok) { Write-Host "  pass  $name" } else { Write-Host "  FAIL  $name $detail" -ForegroundColor Red; $script:failed++ }
}

function Fetch([string]$url, [string]$accept = 'text/plain', [string]$method = 'GET') {
    try {
        return Invoke-WebRequest -UseBasicParsing -Uri $url -Method $method -Headers @{ Accept = $accept } -UserAgent 'cans-smoke-test'
    } catch {
        if ($_.Exception.Response) { return $_.Exception.Response }
        throw
    }
}

function Status($response) { if ($response.StatusCode -is [int]) { return $response.StatusCode } return [int]$response.StatusCode }

# 1. The pages answer.
$homePage = Fetch "$Base/" 'text/html'
Report 'home page (HTML)' ((Status $homePage) -eq 200 -and $homePage.Content -match 'Let your AI assistants ask each other')
$ai = Fetch "$Base/ai"
Report 'AI explanation (/ai)' ((Status $ai) -eq 200 -and $ai.Content -match 'FOR AN AI ASSISTANT READING THIS')

# 2. An AI starts a board with a helper.
$start = (Fetch "$Base/start?name=smoke-lead&invite=smoke-helper&title=Smoke%20test&goal=Deploy%20smoke%20test").Content
$watch = [regex]::Match($start, '/w/([a-z2-9]{20})').Groups[1].Value
$lead = [regex]::Match($start, 'TO POST: fetch \S+/a/([a-z2-9]{20})/post').Groups[1].Value
$helper = [regex]::Match($start, 'Please fetch \S+/a/([a-z2-9]{20}),').Groups[1].Value
Report 'start a board (/start)' ($watch -ne '' -and $lead -ne '' -and $helper -ne '') 'could not find the watch, lead and helper links'
if ($failed -gt 0) { Write-Host "`nStopping: no board to test with."; exit 1 }

# 3. Accented text survives the round trip (the encoding bug class from the first trial).
$text = '@smoke-helper Smoke test: Zo' + [char]0xEB + ' & "caf' + [char]0xE9 + '" #42 ' + [char]0x2013 + ' 100% ' + [char]0x2713
$posted = (Fetch ("$Base/a/$lead/post?message=" + [uri]::EscapeDataString($text))).Content
Report 'post as the lead' ($posted -match 'Posted as #\d+')
$read = (Fetch "$Base/a/$helper").Content
Report 'helper reads it back intact' ($read.Contains($text)) "expected to find: $text"

# 4. The board page shows it, escaped.
$page = Fetch "$Base/w/$watch" 'text/html'
Report 'board page' ((Status $page) -eq 200 -and $page.Content -match 'Smoke test')

# 5. HTTPS, and nothing private is served.
if ($Base.StartsWith('https://')) {
    $plain = $null
    try { $plain = Invoke-WebRequest -UseBasicParsing -Uri ('http://' + $Base.Substring(8) + '/ai') -MaximumRedirection 0 -UserAgent 'cans-smoke-test' } catch { $plain = $_.Exception.Response }
    $location = if ($plain -and $plain.Headers) { [string]$plain.Headers['Location'] } else { '' }
    Report 'http redirects to https' ((Status $plain) -in 301, 302, 307, 308 -and $location.StartsWith('https://')) "got $(Status $plain) $location"
}
foreach ($private in '/config.local.php', '/app/config.local.php', '/app/src/App.php', '/.user.ini', '/.htaccess') {
    $r = Fetch "$Base$private"
    Report "not served: $private" ((Status $r) -ne 200 -or $r.Content.Length -eq 0 -or $r.Content -match '^Not found') "status $(Status $r)"
}

# 6. Clean up: delete the board; its links stop working.
$null = Fetch "$Base/w/$watch/delete" 'text/html' 'POST'
$gone = (Fetch "$Base/a/$lead").Content
Report 'delete the board' ($gone -match "This link isn't recognised")

Write-Host ''
if ($failed -gt 0) { Write-Host "$failed check(s) FAILED" -ForegroundColor Red; exit 1 }
Write-Host 'All smoke checks passed.'
