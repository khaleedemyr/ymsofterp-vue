-- Check FFmpeg Setup for Video Tutorial Feature
-- This script helps verify if FFmpeg is properly configured

-- Note: FFmpeg is required for automatic thumbnail generation and duration extraction
-- If FFmpeg is not available, the system will still work but without these features

-- To check if FFmpeg is installed on your system, run these commands in terminal:

-- For Windows:
-- ffmpeg -version
-- ffprobe -version

-- For Linux/Ubuntu:
-- which ffmpeg
-- which ffprobe

-- For macOS:
-- brew list ffmpeg

-- If FFmpeg is not installed:

-- Windows:
-- 1. Download from https://ffmpeg.org/download.html
-- 2. Extract to C:\ffmpeg
-- 3. Add C:\ffmpeg\bin to PATH environment variable

-- Linux/Ubuntu:
-- sudo apt update
-- sudo apt install ffmpeg

-- macOS:
-- brew install ffmpeg

-- After installation, update the paths in VideoTutorialController.php:
-- 'ffmpeg.binaries' => '/usr/bin/ffmpeg',  -- Linux/macOS
-- 'ffprobe.binaries' => '/usr/bin/ffprobe', -- Linux/macOS
-- 
-- For Windows, use:
-- 'ffmpeg.binaries' => 'C:\ffmpeg\bin\ffmpeg.exe',
-- 'ffprobe.binaries' => 'C:\ffmpeg\bin\ffprobe.exe',

-- Test FFmpeg installation
SELECT 
    'FFmpeg Setup Instructions' as message,
    'Please run the commands above to check FFmpeg installation' as instruction; 