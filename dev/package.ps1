# Builds the folder to upload to shared hosting (docs/deploy.md section 3). Run from anywhere:
#
#   powershell -ExecutionPolicy Bypass -File C:\MATT\VS\2cans1string\dev\package.ps1
#   powershell -ExecutionPolicy Bypass -File C:\MATT\VS\2cans1string\dev\package.ps1 -Layout inside -WebRoot www
#
# -Layout above  (default, preferred): build\upload\ mirrors your hosting home folder. The code sits
#                beside the web root, so the web can never reach it.
# -Layout inside (host only allows uploads into the web root): the code goes in <web root>\app\,
#                locked by deploy\app.htaccess.
# It never copies config.local.php (yours has local settings); create the host's one from
# config.local.php.example, as docs/deploy.md says.
param(
    [ValidateSet('above', 'inside')][string]$Layout = 'above',
    [string]$WebRoot = 'public_html'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot
$out = Join-Path $root 'build\upload'

if (Test-Path $out) { Remove-Item -Recurse -Force $out }
$web = Join-Path $out $WebRoot
New-Item -ItemType Directory -Force $web | Out-Null

# Web root: everything in public\, dotfiles included (.htaccess, .user.ini).
Get-ChildItem -Force (Join-Path $root 'public') | Copy-Item -Destination $web -Recurse -Force

$app = if ($Layout -eq 'above') { $out } else { Join-Path $web 'app' }
New-Item -ItemType Directory -Force $app | Out-Null
foreach ($item in 'src', 'bin', 'config.default.php', 'config.local.php.example') {
    Copy-Item (Join-Path $root $item) -Destination $app -Recurse -Force
}
if ($Layout -eq 'inside') {
    Copy-Item (Join-Path $root 'deploy\app.htaccess') (Join-Path $app '.htaccess')
}

Write-Host "Built $out ($Layout layout, web root '$WebRoot'):"
Get-ChildItem -Force -Recurse $out -Directory | Where-Object { $_.FullName -notmatch '\\src\\' } | ForEach-Object { Write-Host ('  ' + $_.FullName.Substring($out.Length + 1)) }
Write-Host ''
Write-Host "Next: put config.local.php in $(if ($Layout -eq 'above') { $out } else { $app }) (docs/deploy.md), then upload the contents of $out."
