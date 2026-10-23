@echo off
setlocal
set "PHP_EXE=F:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
  echo PHP was not found at %PHP_EXE%.
  echo Update PHP_EXE in this file to match your XAMPP installation.
  pause
  exit /b 1
)
pushd "%~dp0"
start "Cricket API test page" http://127.0.0.1:8000/website/
echo Local server running at http://127.0.0.1:8000/website/
echo Keep this window open while using the page. Press Ctrl+C to stop.
"%PHP_EXE%" -S 127.0.0.1:8000 -t "%CD%"
popd
