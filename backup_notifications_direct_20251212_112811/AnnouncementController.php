<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use DB;
use App\Models\Announcement;
use App\Services\NotificationService;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('announcements');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->endDate);
        }

        $announcements = $query->orderBy('created_at', 'desc')->paginate(10);

        // Tambahkan data target
        $users = User::where('status', 'A')->select('id', 'nama_lengkap', 'id_jabatan', 'division_id', 'id_outlet')->get();
        $jabatans = \DB::table('tbl_data_jabatan')->select('id_jabatan', 'nama_jabatan')->get();
        $divisis = \DB::table('tbl_data_divisi')->select('id', 'nama_divisi')->get();
        $levels = \DB::table('tbl_data_level')->select('id', 'nama_level')->get();
        $outlets = \DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        // Tambahkan data target dan files untuk setiap announcement
        foreach ($announcements->items() as $a) {
            // Get targets
            $a->targets = DB::table('announcement_targets')
                ->where('announcement_id', $a->id)
                ->get();
            
            // Get files
            $a->files = DB::table('announcement_files')
                ->where('announcement_id', $a->id)
                ->get();
            
            // Add target names
            foreach ($a->targets as $t) {
                if ($t->target_type == 'user') {
                    $t->target_name = \DB::table('users')->where('id', $t->target_id)->value('nama_lengkap');
                } elseif ($t->target_type == 'jabatan') {
                    $t->target_name = \DB::table('tbl_data_jabatan')->where('id_jabatan', $t->target_id)->value('nama_jabatan');
                } elseif ($t->target_type == 'divisi') {
                    $t->target_name = \DB::table('tbl_data_divisi')->where('id', $t->target_id)->value('nama_divisi');
                } elseif ($t->target_type == 'level') {
                    $t->target_name = \DB::table('tbl_data_level')->where('id', $t->target_id)->value('nama_level');
                } elseif ($t->target_type == 'outlet') {
                    $t->target_name = \DB::table('tbl_data_outlet')->where('id_outlet', $t->target_id)->value('nama_outlet');
                }
            }
        }

        return Inertia::render('Announcement/Index', [
            'announcements' => $announcements,
            'users' => $users,
            'jabatans' => $jabatans,
            'divisis' => $divisis,
            'levels' => $levels,
            'outlets' => $outlets,
        ]);
    }

    public function create()
    {
        // Ambil data untuk pilihan target
        $users = User::where('status', 'A')->select('id', 'nama_lengkap', 'id_jabatan', 'division_id', 'id_outlet')->get();
        $jabatans = DB::table('tbl_data_jabatan')->select('id_jabatan', 'nama_jabatan')->get();
        $divisis = DB::table('tbl_data_divisi')->select('id', 'nama_divisi')->get();
        $levels = DB::table('tbl_data_level')->select('id', 'nama_level')->get();
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        return Inertia::render('Announcement/Create', [
            'users' => $users,
            'jabatans' => $jabatans,
            'divisis' => $divisis,
            'levels' => $levels,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'files.*' => 'nullable|file|max:5120',
            'targets' => 'required|array|min:1',
            'targets.*.type' => 'required|in:user,jabatan,divisi,level,outlet',
            'targets.*.id' => 'required|integer',
        ]);

        // Simpan announcement
        $announcement = \DB::table('announcements')->insertGetId([
            'title' => $request->title,
            'content' => $request->content,
            'image_path' => $request->file('image') ? $request->file('image')->store('announcement_images', 'public') : null,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'status' => 'DRAFT',
        ]);

        // Simpan files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                \DB::table('announcement_files')->insert([
                    'announcement_id' => $announcement,
                    'file_path' => $file->store('announcement_files', 'public'),
                    'file_name' => $file->getClientOriginalName(),
                    'uploaded_at' => now(),
                ]);
            }
        }

        // Simpan targets
        foreach ($request->targets as $target) {
            \DB::table('announcement_targets')->insert([
                'announcement_id' => $announcement,
                'target_type' => $target['type'],
                'target_id' => $target['id'],
            ]);
        }

        // Send notification to target users when announcement is created
        $this->sendAnnouncementNotification($announcement, $request->targets, 'created');

        return redirect()->route('announcement.index')->with('success', 'Announcement berhasil dibuat!');
    }

    public function show($id)
    {
        $a = \DB::table('announcements')
            ->leftJoin('users as creators', 'announcements.created_by', '=', 'creators.id')
            ->select(
                'announcements.*',
                'creators.nama_lengkap as creator_name',
                'creators.id as creator_id',
                'creators.avatar as creator_avatar'
            )
            ->where('announcements.id', $id)
            ->first();
            
        $a->files = \DB::table('announcement_files')->where('announcement_id', $id)->get();
        $a->targets = \DB::table('announcement_targets')->where('announcement_id', $id)->get();

        // Ambil nama target (opsional, bisa dioptimasi join)
        foreach ($a->targets as $t) {
            if ($t->target_type == 'user') {
                $t->target_name = \DB::table('users')->where('id', $t->target_id)->value('nama_lengkap');
            } elseif ($t->target_type == 'jabatan') {
                $t->target_name = \DB::table('tbl_data_jabatan')->where('id_jabatan', $t->target_id)->value('nama_jabatan');
            } elseif ($t->target_type == 'divisi') {
                $t->target_name = \DB::table('tbl_data_divisi')->where('id', $t->target_id)->value('nama_divisi');
            } elseif ($t->target_type == 'level') {
                $t->target_name = \DB::table('tbl_data_level')->where('id', $t->target_id)->value('nama_level');
            } elseif ($t->target_type == 'outlet') {
                $t->target_name = \DB::table('tbl_data_outlet')->where('id_outlet', $t->target_id)->value('nama_outlet');
            }
        }

        return Inertia::render('Announcement/Show', ['announcement' => $a]);
    }

    public function edit($id)
    {
        $a = \DB::table('announcements')->where('id', $id)->first();
        $a->files = \DB::table('announcement_files')->where('announcement_id', $id)->get();
        $a->targets = \DB::table('announcement_targets')->where('announcement_id', $id)->get();

        // Data pilihan
        $users = User::where('status', 'A')->select('id', 'nama_lengkap', 'id_jabatan', 'division_id', 'id_outlet')->get();
        $jabatans = DB::table('tbl_data_jabatan')->select('id_jabatan', 'nama_jabatan')->get();
        $divisis = DB::table('tbl_data_divisi')->select('id', 'nama_divisi')->get();
        $levels = DB::table('tbl_data_level')->select('id', 'nama_level')->get();
        $outlets = DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        return Inertia::render('Announcement/Edit', [
            'announcement' => $a,
            'users' => $users,
            'jabatans' => $jabatans,
            'divisis' => $divisis,
            'levels' => $levels,
            'outlets' => $outlets,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'files.*' => 'nullable|file|max:5120',
            'targets' => 'required|array|min:1',
            'targets.*.type' => 'required|in:user,jabatan,divisi,level,outlet',
            'targets.*.id' => 'required|integer',
        ]);

        // Update announcement
        $data = [
            'title' => $request->title,
            'content' => $request->content,
        ];
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('announcement_images', 'public');
        }
        \DB::table('announcements')->where('id', $id)->update($data);

        // Tambah file baru
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                \DB::table('announcement_files')->insert([
                    'announcement_id' => $id,
                    'file_path' => $file->store('announcement_files', 'public'),
                    'file_name' => $file->getClientOriginalName(),
                    'uploaded_at' => now(),
                ]);
            }
        }

        // Update targets
        \DB::table('announcement_targets')->where('announcement_id', $id)->delete();
        foreach ($request->targets as $target) {
            \DB::table('announcement_targets')->insert([
                'announcement_id' => $id,
                'target_type' => $target['type'],
                'target_id' => $target['id'],
            ]);
        }

        return redirect()->route('announcement.show', $id)->with('success', 'Announcement berhasil diupdate!');
    }

    public function destroy($id)
    {
        \DB::table('announcement_targets')->where('announcement_id', $id)->delete();
        \DB::table('announcement_files')->where('announcement_id', $id)->delete();
        \DB::table('announcements')->where('id', $id)->delete();

        return redirect()->route('announcement.index')->with('success', 'Announcement berhasil dihapus!');
    }

    public function publish($id)
    {
        $announcement = \DB::table('announcements')->where('id', $id)->first();
        if (!$announcement) {
            return response()->json(['error' => 'Announcement not found'], 404);
        }
        if ($announcement->status === 'Publish') {
            return response()->json(['error' => 'Already published'], 400);
        }

        // Update status
        \DB::table('announcements')->where('id', $id)->update(['status' => 'Publish']);

        // Get targets and send notification
        $targets = \DB::table('announcement_targets')->where('announcement_id', $id)->get();
        $targetArray = $targets->map(function($target) {
            return ['type' => $target->target_type, 'id' => $target->target_id];
        })->toArray();
        
        // Send notification using helper method
        $this->sendAnnouncementNotification($id, $targetArray, 'published');

        return response()->json(['success' => true]);
    }

    /**
     * Get announcements relevant to the authenticated user
     */
    public function getUserAnnouncements(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'announcements' => []]);
        }

        // Get user's data for targeting
        $userId = $user->id;
        $userJabatan = $user->id_jabatan;
        $userDivisi = $user->division_id;
        $userOutlet = $user->id_outlet;

        // Get user's level through jabatan
        $userLevel = null;
        if ($userJabatan) {
            $userLevel = DB::table('tbl_data_jabatan')
                ->where('id_jabatan', $userJabatan)
                ->value('id_level');
        }

        // Build query for announcements that target this user
        $query = DB::table('announcements')
            ->leftJoin('users as creators', 'announcements.created_by', '=', 'creators.id')
            ->select(
                'announcements.*',
                'creators.nama_lengkap as creator_name',
                'creators.id as creator_id',
                'creators.avatar as creator_avatar'
            )
            ->where('announcements.status', 'Publish')
            ->whereExists(function ($query) use ($userId, $userJabatan, $userDivisi, $userLevel, $userOutlet) {
                $query->select(DB::raw(1))
                    ->from('announcement_targets')
                    ->whereColumn('announcement_targets.announcement_id', 'announcements.id')
                    ->where(function ($subQuery) use ($userId, $userJabatan, $userDivisi, $userLevel, $userOutlet) {
                        $subQuery->where(function ($q) use ($userId) {
                            $q->where('target_type', 'user')
                              ->where('target_id', $userId);
                        })
                        ->orWhere(function ($q) use ($userJabatan) {
                            $q->where('target_type', 'jabatan')
                              ->where('target_id', $userJabatan);
                        })
                        ->orWhere(function ($q) use ($userDivisi) {
                            $q->where('target_type', 'divisi')
                              ->where('target_id', $userDivisi);
                        })
                        ->orWhere(function ($q) use ($userLevel) {
                            $q->where('target_type', 'level')
                              ->where('target_id', $userLevel);
                        })
                        ->orWhere(function ($q) use ($userOutlet) {
                            $q->where('target_type', 'outlet')
                              ->where('target_id', $userOutlet);
                        });
                    });
            });

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('target')) {
            $query->whereExists(function ($subQuery) use ($request) {
                $subQuery->select(DB::raw(1))
                    ->from('announcement_targets')
                    ->whereColumn('announcement_targets.announcement_id', 'announcements.id')
                    ->where('target_type', $request->target);
            });
        }

        // Get pagination parameters
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        // If no pagination requested (for home page), limit to 3
        if (!$request->has('page') && !$request->has('per_page')) {
            $announcements = $query->orderBy('created_at', 'desc')->limit(3)->get();
        } else {
            // Use pagination
            $announcements = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        }

        // Add target names and format data
        $announcementList = $announcements instanceof \Illuminate\Pagination\LengthAwarePaginator ? $announcements->items() : $announcements;
        
        foreach ($announcementList as $announcement) {
            // Get files
            $announcement->files = DB::table('announcement_files')
                ->where('announcement_id', $announcement->id)
                ->get();

            // Get targets with names
            $targets = DB::table('announcement_targets')
                ->where('announcement_id', $announcement->id)
                ->get();

            foreach ($targets as $target) {
                if ($target->target_type == 'user') {
                    $target->target_name = DB::table('users')->where('id', $target->target_id)->value('nama_lengkap');
                } elseif ($target->target_type == 'jabatan') {
                    $target->target_name = DB::table('tbl_data_jabatan')->where('id_jabatan', $target->target_id)->value('nama_jabatan');
                } elseif ($target->target_type == 'divisi') {
                    $target->target_name = DB::table('tbl_data_divisi')->where('id', $target->target_id)->value('nama_divisi');
                } elseif ($target->target_type == 'level') {
                    $target->target_name = DB::table('tbl_data_level')->where('id', $target->target_id)->value('nama_level');
                } elseif ($target->target_type == 'outlet') {
                    $target->target_name = DB::table('tbl_data_outlet')->where('id_outlet', $target->target_id)->value('nama_outlet');
                }
            }

            $announcement->targets = $targets;
            
            // Format created_at
            $announcement->created_at_formatted = \Carbon\Carbon::parse($announcement->created_at)->format('d M Y, H:i');
        }

        return response()->json([
            'success' => true,
            'announcements' => $announcements
        ]);
    }

    /**
     * Send notification to target users
     */
    private function sendAnnouncementNotification($announcementId, $targets, $action = 'created')
    {
        $announcement = \DB::table('announcements')->where('id', $announcementId)->first();
        if (!$announcement) return;

        // Get user IDs based on targets
        $userIds = collect();
        foreach ($targets as $target) {
            if ($target['type'] == 'user') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id', $target['id'])->pluck('id')
                );
            } elseif ($target['type'] == 'jabatan') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id_jabatan', $target['id'])->pluck('id')
                );
            } elseif ($target['type'] == 'divisi') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('division_id', $target['id'])->pluck('id')
                );
            } elseif ($target['type'] == 'level') {
                $userIds = $userIds->merge(
                    \DB::table('users')
                        ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                        ->where('users.status', 'A')
                        ->where('tbl_data_jabatan.id_level', $target['id'])
                        ->pluck('users.id')
                );
            } elseif ($target['type'] == 'outlet') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id_outlet', $target['id'])->pluck('id')
                );
            }
        }
        $userIds = $userIds->unique();

        // Get creator name
        $creator = auth()->user();
        
        // Prepare notification message based on action
        $title = $action === 'created' ? 'Pengumuman Baru Dibuat' : 'Pengumuman Baru Dipublish';
        $message = $action === 'created' 
            ? "Pengumuman baru telah dibuat:\n\nJudul: {$announcement->title}\n\nDibuat oleh: {$creator->nama_lengkap}\n\nStatus: DRAFT\n\nSilakan tunggu hingga dipublish untuk melihat detail lengkap."
            : "Pengumuman baru telah dipublish:\n\nJudul: {$announcement->title}\n\nDibuat oleh: {$creator->nama_lengkap}\n\nSilakan cek halaman pengumuman untuk informasi lebih lanjut.";

        foreach ($userIds as $userId) {
            \DB::table('notifications')->insert([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'url' => config('app.url') . '/announcement/' . $announcementId,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
