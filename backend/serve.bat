@echo off
REM Refresh PATH so PHP and Composer are found (e.g. after winget install)
set "PATH=%PATH%;%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe;%APPDATA%\Composer"
REM If PHP is on system PATH, this will work; otherwise use full path below
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
  set "PHP=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
) else (
  set "PHP=php"
)
echo Starting Laravel development server...
"%PHP%" artisan serve
