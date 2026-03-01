# Install PHP and Composer on Windows (run in PowerShell; winget installs PHP, then Composer)
# Requires: winget. If OpenSSL is missing after PHP install, ensure php.ini has extension_dir set to PHP\ext.

$ErrorActionPreference = "Stop"

Write-Host "Step 1: Installing PHP 8.2 via winget..." -ForegroundColor Cyan
winget install PHP.PHP.8.2 --accept-source-agreements --accept-package-agreements --silent
if ($LASTEXITCODE -ne 0) {
    Write-Warning "Winget PHP install had non-zero exit. Trying to continue..."
}

# Refresh PATH for current session (winget adds PHP to user PATH)
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")

Write-Host "Step 2: Checking PHP..." -ForegroundColor Cyan
$phpPath = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $phpPath) {
    Write-Host "PHP not found in PATH. You may need to restart PowerShell or add PHP manually." -ForegroundColor Yellow
    Write-Host "Typical PHP location: C:\tools\php or C:\Program Files\PHP" -ForegroundColor Yellow
    exit 1
}
php -v

Write-Host "Step 3: Downloading Composer installer..." -ForegroundColor Cyan
$installerUrl = "https://getcomposer.org/installer"
$installerPath = "$env:TEMP\composer-setup.php"
Invoke-WebRequest -Uri $installerUrl -OutFile $installerPath -UseBasicParsing

Write-Host "Step 4: Installing Composer globally..." -ForegroundColor Cyan
# Install Composer to a directory in PATH (e.g. user's local bin or project)
$composerDir = "$env:APPDATA\Composer"
if (-not (Test-Path $composerDir)) { New-Item -ItemType Directory -Path $composerDir -Force | Out-Null }
php $installerPath --install-dir=$composerDir --filename=composer.phar

# Create composer.bat so you can run "composer" from anywhere
$batPath = "$composerDir\composer.bat"
Set-Content -Path $batPath -Value "@php `"$composerDir\composer.phar`" %*"
# Add to user PATH if not already
$userPath = [System.Environment]::GetEnvironmentVariable("Path","User")
if ($userPath -notlike "*$composerDir*") {
    [System.Environment]::SetEnvironmentVariable("Path", "$userPath;$composerDir", "User")
    $env:Path += ";$composerDir"
    Write-Host "Added $composerDir to user PATH." -ForegroundColor Green
}

Remove-Item $installerPath -Force -ErrorAction SilentlyContinue
Write-Host "Done. Restart your terminal and run: composer --version" -ForegroundColor Green
