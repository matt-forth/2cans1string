# Runs PHP with the extensions this project needs (pdo_mysql, mbstring) without touching the
# machine's php.ini. Usage: .\dev\php.ps1 <php arguments...>
$php = (Get-Command php -ErrorAction Stop).Source
$ext = Join-Path (Split-Path $php) 'ext'
& $php -d "extension_dir=$ext" -d extension=pdo_mysql -d extension=mbstring -d extension=openssl @args
exit $LASTEXITCODE
