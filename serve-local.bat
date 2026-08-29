@echo off
REM Arranca el backend Laravel con PHP de Laragon (evita Herd, que no tiene pgsql).
set PHP_LARAGON=C:\laragon\bin\php\php-8.2.12-Win32-vs16-x64\php.exe

if not exist "%PHP_LARAGON%" (
  echo No se encontro PHP de Laragon en:
  echo   %PHP_LARAGON%
  exit /b 1
)

cd /d "%~dp0"
echo Usando: %PHP_LARAGON%
"%PHP_LARAGON%" artisan serve --host=127.0.0.1 --port=8000
