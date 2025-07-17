# Video Tutorial Storage Guide

## Cara Kerja Penyimpanan Video

### 1. **File Storage (Physical Files)**
Video dan thumbnail disimpan di filesystem dengan struktur:
```
storage/app/public/
├── video_tutorials/
│   ├── video1.mp4          # File video asli
│   ├── video2.webm         # File video asli
│   └── thumbnails/
│       ├── thumb_1234567890_video1.jpg  # Thumbnail auto-generated
│       └── thumb_1234567891_video2.jpg  # Thumbnail manual upload
```

### 2. **Database Storage (Path References)**
Tabel `video_tutorials` menyimpan referensi ke file:
```sql
-- Contoh data di database:
INSERT INTO video_tutorials (
    group_id, title, description,
    video_path,           -- 'video_tutorials/video1.mp4'
    video_name,           -- 'tutorial_maintenance.mp4'
    video_type,           -- 'video/mp4'
    video_size,           -- 8144977 (bytes)
    thumbnail_path,       -- 'video_tutorials/thumbnails/thumb_1234567890_video1.jpg'
    duration,             -- 120 (seconds)
    status,               -- 'A'
    created_by            -- 1
) VALUES (...);
```

### 3. **URL Access**
File dapat diakses melalui URL:
- Video: `http://localhost:8000/storage/video_tutorials/video1.mp4`
- Thumbnail: `http://localhost:8000/storage/video_tutorials/thumbnails/thumb_1234567890_video1.jpg`

### 4. **Proses Upload**

#### Step 1: File Upload
```php
// File video diupload ke storage
$videoFile = $request->file('video');
$videoPath = $videoFile->store('video_tutorials', 'public');
// Result: 'video_tutorials/abc123.mp4'
```

#### Step 2: Thumbnail Processing
```php
// Jika user upload thumbnail manual
if ($request->hasFile('thumbnail')) {
    $thumbnailPath = $request->file('thumbnail')->store('video_tutorials/thumbnails', 'public');
}
// Jika tidak, generate otomatis dengan FFmpeg (jika tersedia)
```

#### Step 3: Database Insert
```php
VideoTutorial::create([
    'video_path' => $videoPath,        // Path relatif ke storage
    'video_name' => $videoFile->getClientOriginalName(),
    'video_type' => $videoFile->getMimeType(),
    'video_size' => $videoFile->getSize(),
    'thumbnail_path' => $thumbnailPath,
    // ... other fields
]);
```

### 5. **File Access di Frontend**

#### Model Accessors
```php
// Di VideoTutorial model
public function getVideoUrlAttribute(): string
{
    return Storage::url($this->video_path);
    // Returns: /storage/video_tutorials/video1.mp4
}

public function getThumbnailUrlAttribute(): ?string
{
    return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
}
```

#### Frontend Usage
```vue
<template>
  <video :src="video.video_url" controls></video>
  <img :src="video.thumbnail_url" alt="Thumbnail">
</template>
```

### 6. **Setup Requirements**

#### Storage Directories
```bash
# Create directories
mkdir -p storage/app/public/video_tutorials/thumbnails

# Create storage link
php artisan storage:link
```

#### Database Tables
```sql
-- Run setup script
source database/sql/setup_video_tutorial_complete.sql
```

#### FFmpeg (Optional)
```bash
# Windows - Run installer
install_ffmpeg_windows.bat

# Linux
sudo apt install ffmpeg

# macOS
brew install ffmpeg
```

### 7. **Security & Validation**

#### File Validation
```php
$request->validate([
    'video' => 'required|file|mimes:mp4,webm,avi,mov|max:102400', // 100MB
    'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',  // 2MB
]);
```

#### File Cleanup
```php
// Delete old files when updating/deleting
if ($videoTutorial->video_path && Storage::disk('public')->exists($videoTutorial->video_path)) {
    Storage::disk('public')->delete($videoTutorial->video_path);
}
```

### 8. **Troubleshooting**

#### Common Issues
1. **File not found**: Check storage link exists
2. **Permission denied**: Check directory permissions
3. **FFmpeg errors**: Install FFmpeg or skip thumbnail generation
4. **Large file upload**: Check PHP upload limits

#### Debug Commands
```bash
# Check storage link
ls -la public/storage

# Check file permissions
ls -la storage/app/public/video_tutorials/

# Check FFmpeg
ffmpeg -version

# Check Laravel logs
tail -f storage/logs/laravel.log
```

### 9. **Performance Considerations**

#### File Size Limits
- Video: 100MB max
- Thumbnail: 2MB max
- Supported formats: MP4, WebM, AVI, MOV

#### Storage Optimization
- Use CDN for production
- Implement video compression
- Generate multiple thumbnail sizes
- Consider video streaming for large files

### 10. **Backup Strategy**

#### File Backup
```bash
# Backup video files
tar -czf video_backup.tar.gz storage/app/public/video_tutorials/

# Backup database
mysqldump -u user -p database video_tutorials > video_tutorials_backup.sql
```

#### Restore Process
```bash
# Restore files
tar -xzf video_backup.tar.gz

# Restore database
mysql -u user -p database < video_tutorials_backup.sql
``` 