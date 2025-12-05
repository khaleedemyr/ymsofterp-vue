<?php

namespace App\Http\Controllers;

use App\Models\VideoTutorial;
use App\Models\VideoTutorialGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class VideoTutorialController extends Controller
{
    public function index(Request $request)
    {
        $query = VideoTutorial::with(['group', 'creator'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('video_name', 'like', "%{$search}%");
                });
            })
            ->when($request->group_id, function ($query, $groupId) {
                $query->where('group_id', $groupId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            });

        $videos = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $groups = VideoTutorialGroup::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('VideoTutorial/Index', [
            'videos' => $videos,
            'groups' => $groups,
            'filters' => $request->only(['search', 'group_id', 'status']),
        ]);
    }

    public function create()
    {
        $groups = VideoTutorialGroup::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('VideoTutorial/Create', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        // Debug: Log the request data
        \Log::info('VideoTutorial store request:', [
            'group_id' => $request->group_id,
            'title' => $request->title,
            'description' => $request->description,
            'has_video' => $request->hasFile('video'),
            'has_thumbnail' => $request->hasFile('thumbnail'),
            'video_size' => $request->hasFile('video') ? $request->file('video')->getSize() : 0,
        ]);
        
        $request->validate([
            'group_id' => 'required|exists:video_tutorial_groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimes:mp4,webm,avi,mov|max:102400', // 100MB max
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Upload video
            $videoFile = $request->file('video');
            $videoPath = $videoFile->store('video_tutorials', 'public');
            
            \Log::info('Video uploaded to: ' . $videoPath);
            
            // Generate thumbnail if not provided
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('video_tutorials/thumbnails', 'public');
                \Log::info('Thumbnail uploaded to: ' . $thumbnailPath);
            } else {
                // Skip auto-generate thumbnail for now (FFmpeg not installed)
                \Log::info('Skipping thumbnail generation - FFmpeg not available');
                $thumbnailPath = null;
            }

            // Get video duration (skip for now - FFmpeg not installed)
            $duration = null;
            \Log::info('Skipping duration extraction - FFmpeg not available');

            $data = [
                'group_id' => $request->group_id,
                'title' => $request->title,
                'description' => $request->description,
                'video_path' => $videoPath,
                'video_name' => $videoFile->getClientOriginalName(),
                'video_type' => $videoFile->getMimeType(),
                'video_size' => $videoFile->getSize(),
                'thumbnail_path' => $thumbnailPath,
                'duration' => $duration,
                'status' => 'A',
                'created_by' => auth()->id(),
            ];
            
            \Log::info('Creating VideoTutorial with data:', $data);
            
            $video = VideoTutorial::create($data);

            // Log activity
            $this->logActivity('create', $video);

            DB::commit();
            
            \Log::info('VideoTutorial created successfully:', $video->toArray());

            return redirect()->route('video-tutorials.index')
                ->with('success', 'Video tutorial berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Error creating VideoTutorial:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            // Clean up uploaded files on error
            if (isset($videoPath) && Storage::disk('public')->exists($videoPath)) {
                Storage::disk('public')->delete($videoPath);
            }
            if (isset($thumbnailPath) && Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function show(VideoTutorial $videoTutorial)
    {
        $videoTutorial->load(['group', 'creator']);
        
        return Inertia::render('VideoTutorial/Show', [
            'video' => $videoTutorial,
        ]);
    }

    public function edit(VideoTutorial $videoTutorial)
    {
        $groups = VideoTutorialGroup::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('VideoTutorial/Edit', [
            'video' => $videoTutorial->load(['group']),
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, VideoTutorial $videoTutorial)
    {
        $request->validate([
            'group_id' => 'required|exists:video_tutorial_groups,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'nullable|file|mimes:mp4,webm,avi,mov|max:102400',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required|in:A,N',
        ]);

        DB::beginTransaction();
        try {
            $oldData = $videoTutorial->toArray();
            
            $updateData = [
                'group_id' => $request->group_id,
                'title' => $request->title,
                'description' => $request->description,
                'status' => $request->status,
            ];

            // Handle video upload if provided
            if ($request->hasFile('video')) {
                $videoFile = $request->file('video');
                $videoPath = $videoFile->store('video_tutorials', 'public');
                
                // Delete old video
                if ($videoTutorial->video_path && Storage::disk('public')->exists($videoTutorial->video_path)) {
                    Storage::disk('public')->delete($videoTutorial->video_path);
                }

                $updateData = array_merge($updateData, [
                    'video_path' => $videoPath,
                    'video_name' => $videoFile->getClientOriginalName(),
                    'video_type' => $videoFile->getMimeType(),
                    'video_size' => $videoFile->getSize(),
                    'duration' => $this->getVideoDuration($videoPath),
                ]);
            }

            // Handle thumbnail upload if provided
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('video_tutorials/thumbnails', 'public');
                
                // Delete old thumbnail
                if ($videoTutorial->thumbnail_path && Storage::disk('public')->exists($videoTutorial->thumbnail_path)) {
                    Storage::disk('public')->delete($videoTutorial->thumbnail_path);
                }

                $updateData['thumbnail_path'] = $thumbnailPath;
            }

            $videoTutorial->update($updateData);

            // Log activity
            $this->logActivity('update', $videoTutorial, $oldData);

            DB::commit();

            return redirect()->route('video-tutorials.index')
                ->with('success', 'Video tutorial berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function destroy(VideoTutorial $videoTutorial)
    {
        DB::beginTransaction();
        try {
            // Delete video file
            if ($videoTutorial->video_path && Storage::disk('public')->exists($videoTutorial->video_path)) {
                Storage::disk('public')->delete($videoTutorial->video_path);
            }

            // Delete thumbnail file
            if ($videoTutorial->thumbnail_path && Storage::disk('public')->exists($videoTutorial->thumbnail_path)) {
                Storage::disk('public')->delete($videoTutorial->thumbnail_path);
            }

            $videoTutorial->delete();

            // Log activity
            $this->logActivity('delete', $videoTutorial);

            DB::commit();

            return redirect()->route('video-tutorials.index')
                ->with('success', 'Video tutorial berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function toggleStatus(VideoTutorial $videoTutorial)
    {
        $videoTutorial->update([
            'status' => $videoTutorial->status === 'A' ? 'N' : 'A'
        ]);

        $statusText = $videoTutorial->status === 'A' ? 'diaktifkan' : 'dinonaktifkan';
        
        return back()->with('success', "Video tutorial berhasil {$statusText}!");
    }

    public function gallery(Request $request)
    {
        $query = VideoTutorial::with(['group', 'creator'])
            ->where('status', 'A') // Only show active videos
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('video_name', 'like', "%{$search}%")
                      ->orWhereHas('group', function ($groupQuery) use ($search) {
                          $groupQuery->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                          $creatorQuery->where('nama_lengkap', 'like', "%{$search}%");
                      });
                });
            })
            ->when($request->group_id, function ($query, $groupId) {
                $query->where('group_id', $groupId);
            })
            ->when($request->sort, function ($query, $sort) {
                switch ($sort) {
                    case 'newest':
                        $query->orderBy('created_at', 'desc');
                        break;
                    case 'oldest':
                        $query->orderBy('created_at', 'asc');
                        break;
                    case 'title':
                        $query->orderBy('title', 'asc');
                        break;
                    case 'duration':
                        $query->orderBy('duration', 'asc');
                        break;
                    default:
                        $query->orderBy('created_at', 'desc');
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });

        $videos = $query->paginate(12)
            ->withQueryString();

        $groups = VideoTutorialGroup::active()
            ->withCount(['videos' => function ($query) {
                $query->where('status', 'A');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get video statistics
        $stats = [
            'total_videos' => VideoTutorial::where('status', 'A')->count(),
            'total_groups' => VideoTutorialGroup::active()->count(),
            'total_duration' => VideoTutorial::where('status', 'A')->sum('duration'),
        ];

        return Inertia::render('VideoTutorial/Gallery', [
            'videos' => $videos,
            'groups' => $groups,
            'stats' => $stats,
            'filters' => $request->only(['search', 'group_id', 'sort']),
        ]);
    }

    /**
     * API endpoint for mobile app - Video Tutorial Gallery
     */
    public function galleryApi(Request $request)
    {
        $query = VideoTutorial::with(['group', 'creator'])
            ->where('status', 'A') // Only show active videos
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('video_name', 'like', "%{$search}%")
                      ->orWhereHas('group', function ($groupQuery) use ($search) {
                          $groupQuery->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                          $creatorQuery->where('nama_lengkap', 'like', "%{$search}%");
                      });
                });
            })
            ->when($request->group_id, function ($query, $groupId) {
                $query->where('group_id', $groupId);
            })
            ->when($request->sort, function ($query, $sort) {
                switch ($sort) {
                    case 'newest':
                        $query->orderBy('created_at', 'desc');
                        break;
                    case 'oldest':
                        $query->orderBy('created_at', 'asc');
                        break;
                    case 'title':
                        $query->orderBy('title', 'asc');
                        break;
                    case 'duration':
                        $query->orderBy('duration', 'asc');
                        break;
                    default:
                        $query->orderBy('created_at', 'desc');
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });

        $videos = $query->paginate(12)
            ->withQueryString();

        $groups = VideoTutorialGroup::active()
            ->withCount(['videos' => function ($query) {
                $query->where('status', 'A');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get video statistics
        $stats = [
            'total_videos' => VideoTutorial::where('status', 'A')->count(),
            'total_groups' => VideoTutorialGroup::active()->count(),
            'total_duration' => VideoTutorial::where('status', 'A')->sum('duration'),
        ];

        return response()->json([
            'success' => true,
            'videos' => $videos,
            'groups' => $groups,
            'stats' => $stats,
        ]);
    }

    private function generateThumbnail($videoPath, $originalName)
    {
        try {
            // Set timeout for FFmpeg operations
            set_time_limit(60); // 60 seconds timeout
            
            // Try different FFmpeg paths for different operating systems
            $ffmpegPaths = [
                // Windows paths
                [
                    'ffmpeg.binaries' => 'C:\ffmpeg\bin\ffmpeg.exe',
                    'ffprobe.binaries' => 'C:\ffmpeg\bin\ffprobe.exe',
                ],
                [
                    'ffmpeg.binaries' => 'ffmpeg',
                    'ffprobe.binaries' => 'ffprobe',
                ],
                // Linux/macOS paths
                [
                    'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                    'ffprobe.binaries' => '/usr/bin/ffprobe',
                ],
                [
                    'ffmpeg.binaries' => '/usr/local/bin/ffmpeg',
                    'ffprobe.binaries' => '/usr/local/bin/ffprobe',
                ],
            ];
            
            $ffmpeg = null;
            $lastError = null;
            
            foreach ($ffmpegPaths as $paths) {
                try {
                    $ffmpeg = FFMpeg::create(array_merge($paths, [
                        'timeout' => 30, // 30 seconds timeout for FFmpeg
                    ]));
                    break; // If successful, break the loop
                } catch (\Exception $e) {
                    $lastError = $e;
                    continue;
                }
            }
            
            if (!$ffmpeg) {
                throw new \Exception('FFmpeg not found. Please install FFmpeg or check the paths. Last error: ' . $lastError->getMessage());
            }

            $video = $ffmpeg->open(storage_path('app/public/' . $videoPath));
            
            $thumbnailName = 'thumb_' . time() . '_' . pathinfo($originalName, PATHINFO_FILENAME) . '.jpg';
            $thumbnailPath = 'video_tutorials/thumbnails/' . $thumbnailName;
            
            // Create thumbnails directory if it doesn't exist
            $thumbnailDir = storage_path('app/public/video_tutorials/thumbnails');
            if (!file_exists($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            $video->frame(TimeCode::fromSeconds(1))
                  ->save(storage_path('app/public/' . $thumbnailPath));

            \Log::info('Thumbnail generated successfully: ' . $thumbnailPath);
            return $thumbnailPath;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to generate thumbnail: ' . $e->getMessage());
            // If thumbnail generation fails, return null
            return null;
        }
    }

    private function getVideoDuration($videoPath)
    {
        try {
            // Set timeout for FFmpeg operations
            set_time_limit(60); // 60 seconds timeout
            
            // Try different FFmpeg paths for different operating systems
            $ffmpegPaths = [
                // Windows paths
                [
                    'ffmpeg.binaries' => 'C:\ffmpeg\bin\ffmpeg.exe',
                    'ffprobe.binaries' => 'C:\ffmpeg\bin\ffprobe.exe',
                ],
                [
                    'ffmpeg.binaries' => 'ffmpeg',
                    'ffprobe.binaries' => 'ffprobe',
                ],
                // Linux/macOS paths
                [
                    'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                    'ffprobe.binaries' => '/usr/bin/ffprobe',
                ],
                [
                    'ffmpeg.binaries' => '/usr/local/bin/ffmpeg',
                    'ffprobe.binaries' => '/usr/local/bin/ffprobe',
                ],
            ];
            
            $ffmpeg = null;
            $lastError = null;
            
            foreach ($ffmpegPaths as $paths) {
                try {
                    $ffmpeg = FFMpeg::create(array_merge($paths, [
                        'timeout' => 30, // 30 seconds timeout for FFmpeg
                    ]));
                    break; // If successful, break the loop
                } catch (\Exception $e) {
                    $lastError = $e;
                    continue;
                }
            }
            
            if (!$ffmpeg) {
                throw new \Exception('FFmpeg not found. Please install FFmpeg or check the paths. Last error: ' . $lastError->getMessage());
            }

            $ffprobe = $ffmpeg->getFFProbe();
            $duration = $ffprobe->format(storage_path('app/public/' . $videoPath))->get('duration');
            
            \Log::info('Video duration extracted successfully: ' . $duration);
            return (int) $duration;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to get video duration: ' . $e->getMessage());
            // If duration extraction fails, return null
            return null;
        }
    }

    private function logActivity($action, $video, $oldData = null)
    {
        $activityData = [
            'user_id' => auth()->id(),
            'activity_type' => $action,
            'module' => 'video_tutorials',
            'description' => "Video tutorial: {$video->title}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ];

        if ($oldData) {
            $activityData['old_data'] = json_encode($oldData);
        }

        $activityData['new_data'] = json_encode($video->fresh()->toArray());

        DB::table('activity_logs')->insert($activityData);
    }
} 