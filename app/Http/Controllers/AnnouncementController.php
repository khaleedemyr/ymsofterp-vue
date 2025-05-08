<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use DB;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::with(['targets', 'files']);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('startDate')) {
            $query->whereDate('created_at', '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->whereDate('created_at', '<=', $request->endDate);
        }

        $announcements = $query->orderBy('created_at', 'desc')->get();

        // Tambahkan data target
        $users = User::where('status', 'A')->select('id', 'nama_lengkap', 'id_jabatan', 'division_id', 'id_outlet')->get();
        $jabatans = \DB::table('tbl_data_jabatan')->select('id_jabatan', 'nama_jabatan')->get();
        $divisis = \DB::table('tbl_data_divisi')->select('id', 'nama_divisi')->get();
        $levels = \DB::table('tbl_data_level')->select('id', 'nama_level')->get();
        $outlets = \DB::table('tbl_data_outlet')->select('id_outlet', 'nama_outlet')->get();

        // Tambahkan target_name untuk setiap target di setiap announcement
        foreach ($announcements as $a) {
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

        return redirect()->route('announcement.index')->with('success', 'Announcement berhasil dibuat!');
    }

    public function show($id)
    {
        $a = \DB::table('announcements')->where('id', $id)->first();
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

        // Kirim notifikasi ke semua user target (logic sama seperti store)
        $userIds = collect();
        $targets = \DB::table('announcement_targets')->where('announcement_id', $id)->get();
        foreach ($targets as $target) {
            if ($target->target_type == 'user') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id', $target->target_id)->pluck('id')
                );
            } elseif ($target->target_type == 'jabatan') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id_jabatan', $target->target_id)->pluck('id')
                );
            } elseif ($target->target_type == 'divisi') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('division_id', $target->target_id)->pluck('id')
                );
            } elseif ($target->target_type == 'level') {
                $userIds = $userIds->merge(
                    \DB::table('users')
                        ->join('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                        ->where('users.status', 'A')
                        ->where('tbl_data_jabatan.id_level', $target->target_id)
                        ->pluck('users.id')
                );
            } elseif ($target->target_type == 'outlet') {
                $userIds = $userIds->merge(
                    \DB::table('users')->where('status', 'A')->where('id_outlet', $target->target_id)->pluck('id')
                );
            }
        }
        $userIds = $userIds->unique();

        foreach ($userIds as $userId) {
            \DB::table('notifications')->insert([
                'user_id' => $userId,
                'title' => 'Pengumuman Baru',
                'message' => 'Terdapat pengumuman baru: "' . $announcement->title . '". Silakan cek halaman pengumuman untuk informasi lebih lanjut.',
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
