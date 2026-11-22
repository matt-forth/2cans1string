# Starts the site on http://localhost:9099 with PHP's built-in server (Ctrl+C stops it).
param([int]$Port = 9099)
$root = Split-Path $PSScriptRoot
& (Join-Path $PSScriptRoot 'php.ps1') -S "localhost:$Port" -t (Join-Path $root 'public') (Join-Path $root 'public\index.php')
