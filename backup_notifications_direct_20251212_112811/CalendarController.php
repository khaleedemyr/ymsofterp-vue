<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Holiday;
use App\Models\Reminder;
use Carbon\Carbon;
use App\Services\NotificationService;

class CalendarController extends Controller
{
    public function getHolidays()
    {
        try {
            $holidays = DB::table('tbl_kalender_perusahaan')
                ->select('id', 'tgl_libur', 'keterangan')
                ->orderBy('tgl_libur')
                ->get();

            return response()->json($holidays);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch holidays'], 500);
        }
    }

    public function getReminders(Request $request)
    {
        try {
            $userId = auth()->id();
            
            $reminders = DB::table('reminders')
                ->leftJoin('users as creator', 'reminders.created_by', '=', 'creator.id')
                ->where('reminders.user_id', $userId)
                ->select(
                    'reminders.*',
                    'creator.nama_lengkap as created_by_name',
                    'creator.email as created_by_email'
                )
                ->orderBy('reminders.date')
                ->orderBy('reminders.created_at')
                ->get();

            return response()->json($reminders);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch reminders'], 500);
        }
    }

    public function storeReminder(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'time' => 'nullable|date_format:H:i',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'target_users' => 'required|array|min:1',
                'target_users.*' => 'required|integer|exists:users,id'
            ]);

            $createdBy = auth()->id();
            $targetUsers = $request->target_users;
            $reminderId = null;

            // Create reminder for each target user
            foreach ($targetUsers as $userId) {
                $reminder = DB::table('reminders')->insertGetId([
                    'user_id' => $userId,
                    'created_by' => $createdBy,
                    'date' => $request->date,
                    'time' => $request->time,
                    'title' => $request->title,
                    'description' => $request->description,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Return the first reminder for the current user
                if ($userId == $createdBy && !$reminderId) {
                    $reminderId = $reminder;
                }
            }

            // Kirim notifikasi ke semua user yang mendapatkan reminder
            $creator = auth()->user();
            $reminderDate = Carbon::parse($request->date)->format('d/m/Y');
            $reminderTime = $request->time ? Carbon::parse($request->time)->format('H:i') : '';

            foreach ($targetUsers as $userId) {
                // Buat pesan notifikasi
                $message = "Anda mendapat reminder baru:\n\n";
                $message .= "Judul: {$request->title}\n";
                $message .= "Tanggal: {$reminderDate}\n";
                if ($reminderTime) {
                    $message .= "Waktu: {$reminderTime}\n";
                }
                if ($request->description) {
                    $message .= "Deskripsi: {$request->description}\n";
                }
                $message .= "\nDibuat oleh: {$creator->nama_lengkap}";

                // Insert notifikasi
                DB::table('notifications')->insert([
                    'user_id' => $userId,
                    'type' => 'reminder_created',
                    'message' => $message,
                    'url' => config('app.url') . '/home', // Redirect ke home page
                    'is_read' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // If current user is not in target users, return the first reminder
            if (!$reminderId) {
                $reminderId = DB::table('reminders')
                    ->where('created_by', $createdBy)
                    ->where('date', $request->date)
                    ->where('title', $request->title)
                    ->orderBy('created_at', 'desc')
                    ->value('id');
            }

            $newReminder = DB::table('reminders')->where('id', $reminderId)->first();

            return response()->json($newReminder, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save reminder'], 500);
        }
    }

    public function getUsersData()
    {
        try {
            // Get all users with their related data
            $users = DB::table('users')
                ->leftJoin('tbl_data_outlet', 'users.id_outlet', '=', 'tbl_data_outlet.id_outlet')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->select(
                    'users.id',
                    'users.nama_lengkap',
                    'users.email',
                    'users.id_outlet',
                    'users.division_id',
                    'users.id_jabatan',
                    'tbl_data_outlet.nama_outlet as outlet',
                    'tbl_data_divisi.nama_divisi as divisi',
                    'tbl_data_jabatan.nama_jabatan as jabatan'
                )
                ->where('users.status', 'A')
                ->orderBy('users.nama_lengkap')
                ->get();

            // Get all jabatans
            $jabatans = DB::table('tbl_data_jabatan')
                ->select('id_jabatan as id', 'nama_jabatan as name')
                ->orderBy('nama_jabatan')
                ->get();

            // Get all divisis
            $divisis = DB::table('tbl_data_divisi')
                ->select('id as id', 'nama_divisi as name')
                ->orderBy('nama_divisi')
                ->get();

            // Get all outlets
            $outlets = DB::table('tbl_data_outlet')
                ->select('id_outlet as id', 'nama_outlet as name')
                ->orderBy('nama_outlet')
                ->get();

            return response()->json([
                'users' => $users,
                'jabatans' => $jabatans,
                'divisis' => $divisis,
                'outlets' => $outlets
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get users data'], 500);
        }
    }

    public function deleteReminder($id)
    {
        try {
            $userId = auth()->id();
            
            $deleted = DB::table('reminders')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted) {
                return response()->json(['message' => 'Reminder deleted successfully']);
            } else {
                return response()->json(['error' => 'Reminder not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete reminder'], 500);
        }
    }
}
