<?php

namespace App\Http\Controllers;

use App\Models\VideoTutorialGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VideoTutorialGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = VideoTutorialGroup::with(['creator'])
            ->withCount(['videos', 'activeVideos'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            });

        $groups = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('VideoTutorialGroup/Index', [
            'groups' => $groups,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('VideoTutorialGroup/Create');
    }

    public function store(Request $request)
    {
        // Debug: Log the request data
        \Log::info('VideoTutorialGroup store request:', $request->all());
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $data = [
                'name' => $request->name,
                'description' => $request->description,
                'status' => 'A',
                'created_by' => auth()->id(),
            ];
            
            // Debug: Log the data being created
            \Log::info('Creating VideoTutorialGroup with data:', $data);
            
            $group = VideoTutorialGroup::create($data);

            // Log activity
            $this->logActivity('create', $group);

            DB::commit();
            
            // Debug: Log success
            \Log::info('VideoTutorialGroup created successfully:', $group->toArray());

            return redirect()->route('video-tutorial-groups.index')
                ->with('success', 'Group video tutorial berhasil ditambahkan!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Debug: Log the error
            \Log::error('Error creating VideoTutorialGroup:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function show(VideoTutorialGroup $videoTutorialGroup)
    {
        $videoTutorialGroup->load(['creator', 'videos.creator']);
        
        return Inertia::render('VideoTutorialGroup/Show', [
            'group' => $videoTutorialGroup,
        ]);
    }

    public function edit(VideoTutorialGroup $videoTutorialGroup)
    {
        return Inertia::render('VideoTutorialGroup/Edit', [
            'group' => $videoTutorialGroup,
        ]);
    }

    public function update(Request $request, VideoTutorialGroup $videoTutorialGroup)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:A,N',
        ]);

        DB::beginTransaction();
        try {
            $oldData = $videoTutorialGroup->toArray();
            
            $videoTutorialGroup->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            // Log activity
            $this->logActivity('update', $videoTutorialGroup, $oldData);

            DB::commit();

            return redirect()->route('video-tutorial-groups.index')
                ->with('success', 'Group video tutorial berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function destroy(VideoTutorialGroup $videoTutorialGroup)
    {
        // Check if group has videos
        if ($videoTutorialGroup->videos()->count() > 0) {
            return back()->withErrors(['error' => 'Tidak dapat menghapus group yang masih memiliki video tutorial!']);
        }

        DB::beginTransaction();
        try {
            $videoTutorialGroup->delete();

            // Log activity
            $this->logActivity('delete', $videoTutorialGroup);

            DB::commit();

            return redirect()->route('video-tutorial-groups.index')
                ->with('success', 'Group video tutorial berhasil dihapus!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function toggleStatus(VideoTutorialGroup $videoTutorialGroup)
    {
        $videoTutorialGroup->update([
            'status' => $videoTutorialGroup->status === 'A' ? 'N' : 'A'
        ]);

        $statusText = $videoTutorialGroup->status === 'A' ? 'diaktifkan' : 'dinonaktifkan';
        
        return back()->with('success', "Group video tutorial berhasil {$statusText}!");
    }

    private function logActivity($action, $group, $oldData = null)
    {
        $activityData = [
            'user_id' => auth()->id(),
            'activity_type' => $action,
            'module' => 'video_tutorial_groups',
            'description' => "Group video tutorial: {$group->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ];

        if ($oldData) {
            $activityData['old_data'] = json_encode($oldData);
        }

        $activityData['new_data'] = json_encode($group->fresh()->toArray());

        DB::table('activity_logs')->insert($activityData);
    }
} 