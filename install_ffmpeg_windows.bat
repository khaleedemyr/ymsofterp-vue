@echo off
echo Installing FFmpeg for Windows...
echo.

REM Create FFmpeg directory
if not exist "C:\ffmpeg" mkdir "C:\ffmpeg"
if not exist "C:\ffmpeg\bin" mkdir "C:\ffmpeg\bin"

echo Downloading FFmpeg...
powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-master-latest-win64-gpl.zip' -OutFile 'C:\ffmpeg\ffmpeg.zip'}"

echo Extracting FFmpeg...
powershell -Command "& {Expand-Archive -Path 'C:\ffmpeg\ffmpeg.zip' -DestinationPath 'C:\ffmpeg' -Force}"

echo Moving files...
powershell -Command "& {Move-Item -Path 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl\bin\*' -Destination 'C:\ffmpeg\bin' -Force}"

echo Cleaning up...
powershell -Command "& {Remove-Item -Path 'C:\ffmpeg\ffmpeg.zip' -Force}"
powershell -Command "& {Remove-Item -Path 'C:\ffmpeg\ffmpeg-master-latest-win64-gpl' -Recurse -Force}"

echo Adding to PATH...
setx PATH "%PATH%;C:\ffmpeg\bin" /M

echo.
echo FFmpeg installation completed!
echo Please restart your terminal/command prompt for PATH changes to take effect.
echo.
echo To verify installation, run: ffmpeg -version
pause 