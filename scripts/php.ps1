param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $PhpArgs
)

$gd3Portable = Join-Path $PSScriptRoot '../storage/app/php83/php.exe'
$gd3PhpBinary = $env:PHP_BIN
if (-not $gd3PhpBinary) {
    if (Test-Path -LiteralPath $gd3Portable) {
        $gd3PhpBinary = (Resolve-Path -LiteralPath $gd3Portable).Path
    } else {
        $gd3PhpBinary = (Get-Command php -ErrorAction Stop).Source
    }
}
& $gd3PhpBinary -r 'exit(PHP_VERSION_ID >= 80300 && PHP_INT_SIZE === 8 ? 0 : 1);'
if ($LASTEXITCODE -ne 0) {
    throw 'SneakerShop requires PHP 8.3+ x64. Set PHP_BIN to a compatible php.exe.'
}
$gd3PreviousPath = $env:PATH
if ($PhpArgs.Count -ge 2 -and $PhpArgs[0] -eq 'artisan' -and $PhpArgs[1] -eq 'serve' -and $PhpArgs -notcontains '--no-reload') {
    # Laravel's reloader filters environment variables from the child process.
    $PhpArgs += '--no-reload'
}
try {
    $env:PATH = (Split-Path -Parent $gd3PhpBinary) + [IO.Path]::PathSeparator + $gd3PreviousPath
    & $gd3PhpBinary @PhpArgs
    $gd3ExitCode = $LASTEXITCODE
} finally {
    $env:PATH = $gd3PreviousPath
}
exit $gd3ExitCode
