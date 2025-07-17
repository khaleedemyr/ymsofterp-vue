-- Setup Storage Directories for Video Tutorial Feature
-- This script ensures the required storage directories exist

-- Note: These directories should be created in the Laravel storage/app/public folder
-- The actual file system directories need to be created manually or via Laravel commands

-- Required directories:
-- storage/app/public/video_tutorials/
-- storage/app/public/video_tutorials/thumbnails/

-- To create these directories, run these commands in your Laravel project root:

-- For Windows (PowerShell):
-- New-Item -ItemType Directory -Path "storage\app\public\video_tutorials" -Force
-- New-Item -ItemType Directory -Path "storage\app\public\video_tutorials\thumbnails" -Force

-- For Linux/macOS:
-- mkdir -p storage/app/public/video_tutorials
-- mkdir -p storage/app/public/video_tutorials/thumbnails

-- Also ensure the storage link is created:
-- php artisan storage:link

-- This will create a symbolic link from public/storage to storage/app/public
-- so files can be accessed via /storage/video_tutorials/filename.mp4

-- File Storage Structure:
-- storage/app/public/
-- ├── video_tutorials/
-- │   ├── video1.mp4
-- │   ├── video2.webm
-- │   └── thumbnails/
-- │       ├── thumb_1234567890_video1.jpg
-- │       └── thumb_1234567891_video2.jpg

-- Database Storage:
-- The video_tutorials table stores:
-- - video_path: 'video_tutorials/filename.mp4' (relative to storage/app/public)
-- - thumbnail_path: 'video_tutorials/thumbnails/thumb_filename.jpg' (relative to storage/app/public)
-- - video_name: Original filename (e.g., 'tutorial.mp4')
-- - video_type: MIME type (e.g., 'video/mp4')
-- - video_size: File size in bytes
-- - duration: Video duration in seconds

SELECT 
    'Storage Setup Instructions' as message,
    'Please run the commands above to create required directories' as instruction; 