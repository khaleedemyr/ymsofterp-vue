<?php

namespace App\Http\Controllers;

use App\Models\TrainingSchedule;
use App\Models\TrainingInvitation;
use App\Models\Course;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


class TrainingScheduleController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $schedules = TrainingSchedule::with([
            'course',
            'trainer.jabatan',
            'trainer.divisi',
            'outlet',
            'invitations.user.jabatan',
            'invitations.user.divisi',
            'scheduleTrainers.trainer.jabatan',
            'scheduleTrainers.trainer.divisi'
        ])
        ->whereYear('scheduled_date', $year)
        ->whereMonth('scheduled_date', $month)
        ->orderBy('scheduled_date')
        ->orderBy('start_time')
        ->get();

        // Check permissions for creating schedules
        $canCreateSchedule = auth()->user()->is_admin || 
                           auth()->user()->hasPermission('lms-schedules-create');

        // Participants will be loaded dynamically per training via API
        // No need to load all participants here

        // Get available trainers (users who can be trainers) - only active users
        $availableTrainers = User::where('status', 'A')
            ->with(['jabatan.level', 'divisi'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'nik', 'id_jabatan', 'division_id', 'avatar']);

        // Get divisions, jabatans, and levels for filters
        $divisions = \App\Models\Divisi::active()
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        $jabatans = \App\Models\Jabatan::active()
            ->with(['divisi', 'level'])
            ->orderBy('nama_jabatan')
            ->get(['id_jabatan', 'nama_jabatan', 'id_divisi', 'id_level']);

        $levels = \App\Models\DataLevel::active()
            ->orderBy('nama_level')
            ->get(['id', 'nama_level']);

        // Get courses and outlets for quick schedule modal
        // Ambil semua course yang aktif (tidak draft atau deleted)
        $courses = \App\Models\LmsCourse::whereNotIn('status', ['draft', 'deleted'])
            ->orderBy('title')
            ->get();
        $outlets = Outlet::where('status', 'A')->get();

        // Get holiday dates for the month
        $holidays = \App\Models\KalenderPerusahaan::forMonth($year, $month)
            ->select('tgl_libur', 'keterangan')
            ->get()
            ->map(function ($holiday) {
                return [
                    'date' => $holiday->tgl_libur->format('Y-m-d'),
                    'description' => $holiday->keterangan
                ];
            });
        
        // Debug: Log courses count
        \Log::info('Courses count: ' . $courses->count());
        \Log::info('Outlets count: ' . $outlets->count());
        \Log::info('Sample course: ' . $courses->first());
        \Log::info('Sample outlet: ' . $outlets->first());
        \Log::info('Schedules count: ' . $schedules->count());
        \Log::info('Schedules data: ' . $schedules->toJson());
        \Log::info('Query year: ' . $year . ', month: ' . $month);
        \Log::info('Current date: ' . now()->format('Y-m-d'));

        return Inertia::render('Lms/TrainingSchedule/Index', [
            'schedules' => $schedules,
            'canCreateSchedule' => $canCreateSchedule,
            'currentMonth' => $month,
            'currentYear' => $year,
            'availableTrainers' => $availableTrainers,
            'divisions' => $divisions,
            'jabatans' => $jabatans,
            'levels' => $levels,
            'courses' => $courses,
            'outlets' => $outlets,
            'holidays' => $holidays
        ]);
    }

    public function create()
    {
        $courses = \App\Models\LmsCourse::whereNotIn('status', ['draft', 'deleted'])
            ->orderBy('title')
            ->get();
        $outlets = Outlet::where('status', 'A')->get();

        return Inertia::render('Lms/TrainingSchedule/Create', [
            'courses' => $courses,
            'outlets' => $outlets
        ]);
    }

    public function store(Request $request)
    {
        try {
            // Debug: Log incoming data
            \Log::info('Training Schedule Store Request:', $request->all());
            
            $request->validate([
                'course_id' => 'required|exists:lms_courses,id',
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'scheduled_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'notes' => 'nullable|string'
            ]);

            // Check if user can create schedule for this course
            $course = \App\Models\LmsCourse::findOrFail($request->course_id);
            if (!$this->canManageTraining($course)) {
                return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menjadwalkan training ini']);
            }

            // Debug: Log course data
            \Log::info('Course found:', [
                'id' => $course->id,
                'title' => $course->title,
                'instructor_id' => $course->instructor_id,
                'trainer_type' => $course->trainer_type,
                'external_trainer_name' => $course->external_trainer_name
            ]);

            $scheduleData = [
                'course_id' => $request->course_id,
                'trainer_id' => $course->instructor_id, // Use instructor from course
                'external_trainer_name' => $course->trainer_type === 'external' ? $course->external_trainer_name : null,
                'outlet_id' => $request->outlet_id,
                'scheduled_date' => $request->scheduled_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'status' => 'draft',
                'notes' => $request->notes,
                'created_by' => auth()->id()
            ];

            // Debug: Log schedule data
            \Log::info('Schedule data to create:', $scheduleData);

            $schedule = TrainingSchedule::create($scheduleData);

            // Assign primary trainer from course
            if ($course->instructor_id) {
                \App\Models\TrainingScheduleTrainer::create([
                    'schedule_id' => $schedule->id,
                    'trainer_id' => $course->instructor_id,
                    'trainer_type' => 'internal',
                    'is_primary_trainer' => true,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            // Debug: Log created schedule
            \Log::info('Schedule created successfully:', [
                'id' => $schedule->id,
                'course_id' => $schedule->course_id,
                'outlet_id' => $schedule->outlet_id,
                'scheduled_date' => $schedule->scheduled_date,
                'qr_code_url' => $schedule->qr_code_url
            ]);

            return redirect()->route('lms.schedules.index', [
                'year' => $request->scheduled_date ? date('Y', strtotime($request->scheduled_date)) : now()->year,
                'month' => $request->scheduled_date ? date('n', strtotime($request->scheduled_date)) : now()->month
            ])->with('success', 'Training berhasil dijadwalkan');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error:', $e->errors());
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error:', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings()
            ]);
            return back()->withErrors(['error' => 'Database error: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::error('Unexpected error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function show(TrainingSchedule $schedule)
    {
        $schedule->load([
            'course',
            'trainer.jabatan',
            'trainer.divisi',
            'outlet',
            'invitations.user.jabatan',
            'invitations.user.divisi',
            'scheduleTrainers.trainer.jabatan',
            'scheduleTrainers.trainer.divisi',
            'createdBy'
        ]);

        $canEdit = $schedule->canBeEditedBy(auth()->user());
        $canInvite = $schedule->canInviteParticipants(auth()->user());

        // Get available certificate templates for issuing
        $certificateTemplates = \App\Models\CertificateTemplate::active()
            ->orderBy('name')
            ->get(['id', 'name', 'background_image']);

        return Inertia::render('Lms/TrainingSchedule/Show', [
            'schedule' => $schedule,
            'canEdit' => $canEdit,
            'canInvite' => $canInvite,
            'certificateTemplates' => $certificateTemplates
        ]);
    }

    public function edit(TrainingSchedule $schedule)
    {
        if (!$schedule->canBeEditedBy(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengedit training ini']);
        }

        $schedule->load(['course', 'trainer.jabatan', 'trainer.divisi', 'outlet']);
        
        $courses = \App\Models\LmsCourse::whereNotIn('status', ['draft', 'deleted'])
            ->orderBy('title')
            ->get();
        $outlets = Outlet::where('status', 'A')->get();

        return Inertia::render('Lms/TrainingSchedule/Edit', [
            'schedule' => $schedule,
            'courses' => $courses,
            'outlets' => $outlets
        ]);
    }

    public function update(Request $request, TrainingSchedule $schedule)
    {
        if (!$schedule->canBeEditedBy(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengedit training ini']);
        }

        $request->validate([
            'course_id' => 'required|exists:lms_courses,id',
            'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
            'scheduled_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'required|in:draft,published,ongoing,completed,cancelled',
            'notes' => 'nullable|string'
        ]);



        // Get course to update trainer info
        $course = \App\Models\LmsCourse::findOrFail($request->course_id);
        
        $schedule->update([
            'course_id' => $request->course_id,
            'trainer_id' => $course->instructor_id,
            'external_trainer_name' => $course->trainer_type === 'external' ? $course->external_trainer_name : null,
            'outlet_id' => $request->outlet_id,
            'scheduled_date' => $request->scheduled_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => $request->status,
            'notes' => $request->notes
        ]);

        return redirect()->route('lms.schedules.show', $schedule)
            ->with('success', 'Training berhasil diperbarui');
    }

    public function destroy(TrainingSchedule $schedule)
    {
        if (!$schedule->canBeEditedBy(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menghapus training ini']);
        }

        // Check if training has participants
        if ($schedule->participant_count > 0) {
            return back()->withErrors(['error' => 'Tidak dapat menghapus training yang sudah memiliki peserta']);
        }

        $schedule->delete();

        return redirect()->route('lms.schedules.index')
            ->with('success', 'Training berhasil dihapus');
    }

    // Invitation methods
    public function inviteParticipants(Request $request, TrainingSchedule $schedule)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengundang peserta']);
        }

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $invitedCount = 0;
        $alreadyInvitedCount = 0;

        foreach ($request->user_ids as $userId) {
            // Check if user is already invited
            $existingInvitation = TrainingInvitation::where('schedule_id', $schedule->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingInvitation) {
                $alreadyInvitedCount++;
                continue;
            }



            $invitation = TrainingInvitation::create([
                'schedule_id' => $schedule->id,
                'user_id' => $userId,
                'status' => 'invited'
            ]);

            // Generate QR code for this invitation
            $invitation->generateQRCodeForInvitation();
            
            // Debug: Log invitation with QR code
            \Log::info('Invitation created with QR code:', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'qr_code' => $invitation->qr_code,
                'qr_code_url' => $invitation->qr_code_url
            ]);

            // Send notification to invited participant
            $this->sendParticipantInvitationNotification($invitation, $schedule);

            $invitedCount++;
        }

        $message = "Berhasil mengundang {$invitedCount} peserta";
        if ($alreadyInvitedCount > 0) {
            $message .= " ({$alreadyInvitedCount} sudah terdaftar sebelumnya)";
        }

        return back()->with('success', $message);
    }

    // Trainer invitation methods
    public function inviteTrainers(Request $request, TrainingSchedule $schedule)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengundang trainer']);
        }

        $request->validate([
            'trainers' => 'required|array',
            'trainers.*.trainer_type' => 'required|in:internal,external',
            'trainers.*.trainer_id' => 'required_if:trainers.*.trainer_type,internal|exists:users,id',
            'trainers.*.external_trainer_name' => 'required_if:trainers.*.trainer_type,external|string|max:255',
            'trainers.*.external_trainer_email' => 'nullable|email|max:255',
            'trainers.*.external_trainer_phone' => 'nullable|string|max:20',
            'trainers.*.external_trainer_company' => 'nullable|string|max:255',
            'trainers.*.is_primary_trainer' => 'boolean',
            'trainers.*.start_time' => 'nullable|string',
            'trainers.*.end_time' => 'nullable|string',
            'trainers.*.notes' => 'nullable|string|max:1000',
        ]);

        $invitedCount = 0;
        $alreadyInvitedCount = 0;

        foreach ($request->trainers as $trainerData) {
            // Check if trainer is already invited
            $existingTrainer = null;
            
            if ($trainerData['trainer_type'] === 'internal') {
                $existingTrainer = \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                    ->where('trainer_id', $trainerData['trainer_id'])
                    ->first();
            } else {
                // For external trainer, check by name and email
                $existingTrainer = \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                    ->where('trainer_type', 'external')
                    ->where('external_trainer_name', $trainerData['external_trainer_name'])
                    ->where('external_trainer_email', $trainerData['external_trainer_email'])
                    ->first();
            }

            if ($existingTrainer) {
                $alreadyInvitedCount++;
                continue;
            }

            // If setting as primary, remove primary from others
            if ($trainerData['is_primary_trainer'] ?? false) {
                \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                    ->update(['is_primary_trainer' => false]);
            }

            // Format waktu ke format H:i
            $formatTime = function($time) {
                if (!$time || $time === '') return null;
                // Jika sudah dalam format H:i, return as is
                if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                    return $time;
                }
                // Jika dalam format lain, convert ke H:i
                try {
                    $date = \Carbon\Carbon::createFromFormat('H:i:s', $time);
                    return $date->format('H:i');
                } catch (\Exception $e) {
                    try {
                        $date = \Carbon\Carbon::createFromFormat('H:i', $time);
                        return $date->format('H:i');
                    } catch (\Exception $e2) {
                        \Log::warning('Cannot parse time format: ' . $time);
                        return null; // Return null if can't parse
                    }
                }
            };

            $scheduleTrainerData = [
                'schedule_id' => $schedule->id,
                'trainer_type' => $trainerData['trainer_type'],
                'is_primary_trainer' => $trainerData['is_primary_trainer'] ?? false,
                'start_time' => $formatTime($trainerData['start_time'] ?? $schedule->start_time),
                'end_time' => $formatTime($trainerData['end_time'] ?? $schedule->end_time),
                'notes' => $trainerData['notes'] ?? null,
                'status' => 'invited',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];

            // Add trainer-specific data
            if ($trainerData['trainer_type'] === 'internal') {
                $scheduleTrainerData['trainer_id'] = $trainerData['trainer_id'];
                $scheduleTrainerData['trainer_type'] = 'internal';
            } else {
                $scheduleTrainerData['external_trainer_name'] = $trainerData['external_trainer_name'];
                $scheduleTrainerData['external_trainer_email'] = $trainerData['external_trainer_email'] ?? null;
                $scheduleTrainerData['external_trainer_phone'] = $trainerData['external_trainer_phone'] ?? null;
                $scheduleTrainerData['external_trainer_company'] = $trainerData['external_trainer_company'] ?? null;
                $scheduleTrainerData['trainer_type'] = 'external';
            }

            $scheduleTrainer = \App\Models\TrainingScheduleTrainer::create($scheduleTrainerData);

            // Calculate hours if start_time and end_time provided
            if ($scheduleTrainer->start_time && $scheduleTrainer->end_time) {
                $scheduleTrainer->calculateHoursFromTimeRange();
            }

            // Send notification to internal trainer
            if ($trainerData['trainer_type'] === 'internal') {
                $this->sendTrainerInvitationNotification($scheduleTrainer, $schedule);
            }

            $invitedCount++;
        }

        $message = "Berhasil mengundang {$invitedCount} trainer";
        if ($alreadyInvitedCount > 0) {
            $message .= " ({$alreadyInvitedCount} sudah terdaftar sebelumnya)";
        }

        return back()->with('success', $message);
    }

    public function setPrimaryTrainer(Request $request, TrainingSchedule $schedule, $trainerId)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengubah primary trainer']);
        }

        try {
            // Remove primary from all trainers in this schedule
            \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                ->update(['is_primary_trainer' => false]);

            // Set the selected trainer as primary
            $scheduleTrainer = \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                ->where('id', $trainerId)
                ->first();

            if (!$scheduleTrainer) {
                return back()->withErrors(['error' => 'Trainer tidak ditemukan']);
            }

            $scheduleTrainer->update([
                'is_primary_trainer' => true,
                'updated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Primary trainer berhasil diupdate');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal mengupdate primary trainer: ' . $e->getMessage()]);
        }
    }

    public function removeTrainer(TrainingSchedule $schedule, $trainerId)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menghapus trainer']);
        }

        try {
            $scheduleTrainer = \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                ->where('id', $trainerId)
                ->first();

            if (!$scheduleTrainer) {
                return back()->withErrors(['error' => 'Trainer tidak ditemukan']);
            }

            $scheduleTrainer->delete();

            return back()->with('success', 'Trainer berhasil dihapus dari training');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Gagal menghapus trainer: ' . $e->getMessage()]);
        }
    }

    public function removeParticipant(TrainingSchedule $schedule, TrainingInvitation $invitation)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menghapus peserta']);
        }

        $invitation->delete();

        return back()->with('success', 'Peserta berhasil dihapus dari training');
    }

    public function markAttended(TrainingSchedule $schedule, TrainingInvitation $invitation)
    {
        if (!$schedule->canInviteParticipants(auth()->user())) {
            return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengubah status peserta']);
        }

        // Update status to attended and set check-in time
        $invitation->update([
            'status' => 'attended',
            'check_in_time' => now()
        ]);

        return back()->with('success', 'Peserta berhasil ditandai sebagai hadir');
    }

    // QR Code check-in
    public function checkIn(Request $request)
    {
        try {
            \Log::info('Check-in request received', [
                'qr_code' => $request->qr_code,
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            $request->validate([
                'qr_code' => 'required|string'
            ]);

            $invitation = TrainingInvitation::validateQRCode($request->qr_code);

            if (!$invitation) {
                \Log::warning('QR Code validation failed', [
                    'qr_code' => $request->qr_code,
                    'user_id' => auth()->id()
                ]);
                return back()->withErrors(['error' => 'QR Code tidak valid atau Anda tidak terdaftar untuk training ini']);
            }

            // Log invitation details for debugging
            \Log::info('QR Code validation successful', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'schedule_id' => $invitation->schedule_id,
                'status' => $invitation->status,
                'schedule_status' => $invitation->schedule->status ?? 'unknown',
                'is_today' => $invitation->schedule->is_today ?? false
            ]);

            if (!$invitation->canCheckIn()) {
                \Log::warning('Check-in not allowed', [
                    'invitation_id' => $invitation->id,
                    'status' => $invitation->status,
                    'schedule_status' => $invitation->schedule->status ?? 'unknown',
                    'is_today' => $invitation->schedule->is_today ?? false
                ]);
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Tidak dapat check-in saat ini. Pastikan training sedang berlangsung dan Anda terdaftar.'
                    ], 400);
                }
                
                return back()->withErrors(['error' => 'Tidak dapat check-in saat ini. Pastikan training sedang berlangsung dan Anda terdaftar.']);
            }

            if ($invitation->checkIn()) {
                // Get training sessions for the course
                $trainingSessions = $this->getTrainingSessions($invitation->schedule->course_id, $invitation->user_id);
                
                \Log::info('Check-in successful, returning response', [
                    'invitation_id' => $invitation->id,
                    'training_sessions_count' => count($trainingSessions)
                ]);
                
                \Log::info('Sending response with training data', [
                    'training_info' => [
                        'course_title' => $invitation->schedule->course->title,
                        'scheduled_date' => $invitation->schedule->scheduled_date,
                        'start_time' => $invitation->schedule->start_time,
                        'end_time' => $invitation->schedule->end_time,
                        'outlet_name' => $invitation->schedule->outlet->nama_outlet ?? 'N/A'
                    ],
                    'training_sessions_count' => count($trainingSessions)
                ]);

                // Return JSON response for AJAX requests
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Check-in berhasil untuk ' . $invitation->user->nama_lengkap,
                        'participant' => $invitation->user->nama_lengkap,
                        'training' => $invitation->schedule->course->title,
                        'training_info' => [
                            'course_title' => $invitation->schedule->course->title,
                            'scheduled_date' => $invitation->schedule->scheduled_date,
                            'start_time' => $invitation->schedule->start_time,
                            'end_time' => $invitation->schedule->end_time,
                            'outlet_name' => $invitation->schedule->outlet->nama_outlet ?? 'N/A'
                        ],
                        'training_sessions' => $trainingSessions
                    ]);
                }

                return back()->with([
                    'success' => 'Check-in berhasil untuk ' . $invitation->user->nama_lengkap,
                    'participant' => $invitation->user->nama_lengkap,
                    'training' => $invitation->schedule->course->title,
                    'training_info' => [
                        'course_title' => $invitation->schedule->course->title,
                        'scheduled_date' => $invitation->schedule->scheduled_date,
                        'start_time' => $invitation->schedule->start_time,
                        'end_time' => $invitation->schedule->end_time,
                        'outlet_name' => $invitation->schedule->outlet->nama_outlet ?? 'N/A'
                    ],
                    'training_sessions' => $trainingSessions
                ]);
            }

            \Log::warning('Check-in failed - invitation->checkIn() returned false');
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Check-in gagal'
                ], 400);
            }
            
            return back()->withErrors(['error' => 'Check-in gagal']);
            
        } catch (\Exception $e) {
            \Log::error('Check-in error: ' . $e->getMessage(), [
                'qr_code' => $request->qr_code,
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Terjadi kesalahan saat memproses QR Code'
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Terjadi kesalahan saat memproses QR Code']);
        }
    }

    public function checkOut(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string'
        ]);

        $invitation = TrainingInvitation::validateQRCode($request->qr_code);

        if (!$invitation) {
            return back()->withErrors(['error' => 'QR Code tidak valid']);
        }

        if (!$invitation->canCheckOut()) {
            return back()->withErrors(['error' => 'Tidak dapat check-out saat ini']);
        }

        if ($invitation->checkOut()) {
            return back()->with([
                'success' => 'Check-out berhasil untuk ' . $invitation->user->nama_lengkap,
                'participant' => $invitation->user->nama_lengkap
            ]);
        }

        return back()->withErrors(['error' => 'Check-out gagal']);
    }

    // Auto complete training
    public function autoComplete()
    {
        $schedules = TrainingSchedule::ongoing()
            ->where('scheduled_date', '<=', now()->format('Y-m-d'))
            ->get();

        $completedCount = 0;

        foreach ($schedules as $schedule) {
            if ($schedule->shouldAutoComplete()) {
                $schedule->markAsCompleted();
                $completedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$completedCount} training berhasil diselesaikan otomatis"
        ]);
    }

    // Export attendance report
    public function exportAttendance(TrainingSchedule $schedule)
    {
        try {
            \Log::info('Export attendance started for schedule: ' . $schedule->id);
            
            // Load schedule with relationships
            $schedule->load([
                'course',
                'trainer.jabatan',
                'trainer.divisi',
                'outlet',
                'invitations.user.jabatan',
                'invitations.user.divisi'
            ]);

            \Log::info('Schedule loaded with relationships. Invitations count: ' . $schedule->invitations->count());

            // Create Excel file using PhpSpreadsheet directly (like delivery order)
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set title
            $sheet->setCellValue('A1', 'LAPORAN ABSENSI TRAINING');
            $sheet->mergeCells('A1:J1');
            
            // Style title
            $titleStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '3B82F6'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            ];
            $sheet->getStyle('A1:J1')->applyFromArray($titleStyle);

            // Training info section
            $currentRow = 3;
            $sheet->setCellValue('A' . $currentRow, 'Informasi Training:');
            $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Judul Training:');
            $sheet->setCellValue('B' . $currentRow, $schedule->course->title ?? 'N/A');
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Tanggal:');
            $sheet->setCellValue('B' . $currentRow, $schedule->scheduled_date ?? 'N/A');
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Waktu:');
            $sheet->setCellValue('B' . $currentRow, ($schedule->start_time ?? 'N/A') . ' - ' . ($schedule->end_time ?? 'N/A'));
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Lokasi:');
            $sheet->setCellValue('B' . $currentRow, $schedule->outlet->nama_outlet ?? 'N/A');
            $currentRow++;

            $sheet->setCellValue('A' . $currentRow, 'Trainer:');
            $sheet->setCellValue('B' . $currentRow, $schedule->trainer_name ?? 'N/A');
            $currentRow++;

            // Empty row
            $currentRow++;

            // Participant list header
            $sheet->setCellValue('A' . $currentRow, 'Daftar Peserta:');
            $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
            $currentRow++;

            // Table headers
            $headers = [
                'No',
                'Nama Lengkap',
                'Email',
                'Jabatan',
                'Divisi',
                'Status',
                'Check-in Time',
                'Check-out Time',
                'Durasi Hadir',
                'Keterangan'
            ];

            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, $currentRow, $header);
            }

            // Style table headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '10B981'],
                ],
            ];
            $sheet->getStyle('A' . $currentRow . ':J' . $currentRow)->applyFromArray($headerStyle);
            $currentRow++;

            // Add participant data
            foreach ($schedule->invitations as $index => $invitation) {
                $checkInTime = $invitation->check_in_time ? \Carbon\Carbon::parse($invitation->check_in_time)->format('H:i:s') : '-';
                $checkOutTime = $invitation->check_out_time ? \Carbon\Carbon::parse($invitation->check_out_time)->format('H:i:s') : '-';
                
                $duration = '-';
                if ($invitation->check_in_time && $invitation->check_out_time) {
                    $checkIn = \Carbon\Carbon::parse($invitation->check_in_time);
                    $checkOut = \Carbon\Carbon::parse($invitation->check_out_time);
                    $duration = $checkIn->diffInMinutes($checkOut) . ' menit';
                }

                $statusText = [
                    'invited' => 'Terdaftar',
                    'confirmed' => 'Konfirmasi',
                    'attended' => 'Hadir',
                    'absent' => 'Tidak Hadir'
                ];

                $sheet->setCellValueByColumnAndRow(1, $currentRow, $index + 1);
                $sheet->setCellValueByColumnAndRow(2, $currentRow, $invitation->user->nama_lengkap ?? 'N/A');
                $sheet->setCellValueByColumnAndRow(3, $currentRow, $invitation->user->email ?? 'N/A');
                $sheet->setCellValueByColumnAndRow(4, $currentRow, $invitation->user->jabatan->nama_jabatan ?? '-');
                $sheet->setCellValueByColumnAndRow(5, $currentRow, $invitation->user->divisi->nama_divisi ?? '-');
                $sheet->setCellValueByColumnAndRow(6, $currentRow, $statusText[$invitation->status] ?? $invitation->status);
                $sheet->setCellValueByColumnAndRow(7, $currentRow, $checkInTime);
                $sheet->setCellValueByColumnAndRow(8, $currentRow, $checkOutTime);
                $sheet->setCellValueByColumnAndRow(9, $currentRow, $duration);
                $sheet->setCellValueByColumnAndRow(10, $currentRow, $invitation->notes ?? '-');
                
                $currentRow++;
            }

            // Auto size columns
            foreach (range('A', 'J') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create writer
            $writer = new Xlsx($spreadsheet);
            
            // Set headers for download
            $filename = 'Laporan_Absensi_' . str_replace([' ', '/', '\\'], '_', $schedule->course->title) . '_' . $schedule->scheduled_date . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            \Log::info('Excel export created successfully with filename: ' . $filename);

            // Output file
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            \Log::error('Export attendance error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->withErrors(['error' => 'Gagal mengunduh laporan absensi: ' . $e->getMessage()]);
        }
    }

    public function issueCertificates(Request $request, TrainingSchedule $schedule)
    {
        try {
            // Only creator/admin can issue
            if (!$schedule->canBeEditedBy(auth()->user())) {
                return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk menerbitkan sertifikat']);
            }

            $schedule->load(['course.certificateTemplate', 'trainer.jabatan', 'trainer.divisi', 'invitations.user.jabatan', 'invitations.user.divisi']);

            // Determine template to use
            $templateId = $request->template_id;
            
            // If no template provided via request, use course default template
            if (!$templateId && $schedule->course->certificate_template_id) {
                $templateId = $schedule->course->certificate_template_id;
            }

            // If still no template, require user selection
            if (!$templateId) {
                $request->validate([
                    'template_id' => 'required|exists:certificate_templates,id'
                ]);
                $templateId = $request->template_id;
            }

            // Validate template exists
            if (!$templateId || !\App\Models\CertificateTemplate::find($templateId)) {
                return back()->withErrors(['error' => 'Template sertifikat tidak valid']);
            }

            $issued = 0;
            foreach ($schedule->invitations as $invitation) {
                if ($invitation->status !== 'attended' || !$invitation->user) {
                    continue;
                }

                // Avoid duplicate certificates for same course + user + date + template
                $existing = \App\Models\LmsCertificate::where('course_id', $schedule->course_id)
                    ->where('user_id', $invitation->user_id)
                    ->whereDate('issued_at', $schedule->scheduled_date)
                    ->where('template_id', $templateId)
                    ->first();

                if ($existing) {
                    continue;
                }

                \App\Models\LmsCertificate::create([
                    'course_id' => $schedule->course_id,
                    'enrollment_id' => null,
                    'user_id' => $invitation->user_id,
                    'certificate_number' => null, // auto by model
                    'issued_at' => $schedule->scheduled_date,
                    'expires_at' => null,
                    'template_id' => $templateId,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $issued++;
            }

            return back()->with('success', $issued . ' sertifikat berhasil diterbitkan');
        } catch (\Throwable $e) {
            \Log::error('Issue certificates error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal menerbitkan sertifikat: ' . $e->getMessage()]);
        }
    }

    // Helper methods
    private function canManageTraining(\App\Models\LmsCourse $course): bool
    {
        $user = auth()->user();
        
        // Admin can manage all trainings
        if ($user->is_admin) {
            return true;
        }
        
        // Course creator can manage their own course
        if ($course->created_by == $user->id) {
            return true;
        }
        
        // Check if user has permission to manage training schedules
        if ($user->hasPermission('lms-schedules-create')) {
            return true;
        }
        
        // For now, allow all authenticated users to create training schedules
        // You can add more specific logic here based on your requirements
        return true;
    }

    // New methods for flexible trainer management
    public function assignTrainer(Request $request, TrainingSchedule $schedule)
    {
        $request->validate([
            'trainer_id' => 'required|exists:users,id',
            'is_primary_trainer' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // If setting as primary, remove primary from others
            if ($request->is_primary_trainer) {
                \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                    ->update(['is_primary_trainer' => false]);
            }

            $scheduleTrainer = \App\Models\TrainingScheduleTrainer::create([
                'schedule_id' => $schedule->id,
                'trainer_id' => $request->trainer_id,
                'trainer_type' => 'internal',
                'is_primary_trainer' => $request->is_primary_trainer ?? false,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Calculate hours if start_time and end_time provided
            if ($request->start_time && $request->end_time) {
                $scheduleTrainer->calculateHoursFromTimeRange();
            }

            return response()->json([
                'success' => true,
                'message' => 'Trainer berhasil ditambahkan ke schedule',
                'trainer' => $scheduleTrainer->load(['trainer.jabatan', 'trainer.divisi'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan trainer: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateTrainerHours(Request $request, TrainingSchedule $schedule, $trainerId)
    {
        $request->validate([
            'hours_taught' => 'required|numeric|min:0',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $scheduleTrainer = \App\Models\TrainingScheduleTrainer::where('schedule_id', $schedule->id)
                ->where('trainer_id', $trainerId)
                ->first();

            if (!$scheduleTrainer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trainer tidak ditemukan di schedule ini'
                ], 404);
            }

            $scheduleTrainer->update([
                'hours_taught' => $request->hours_taught,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'notes' => $request->notes,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jam mengajar trainer berhasil diperbarui',
                'trainer' => $scheduleTrainer->load(['trainer.jabatan', 'trainer.divisi'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jam mengajar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getScheduleTrainers(TrainingSchedule $schedule)
    {
        $trainers = \App\Models\TrainingScheduleTrainer::with(['trainer.jabatan', 'trainer.divisi'])
            ->where('schedule_id', $schedule->id)
            ->orderBy('is_primary_trainer', 'desc')
            ->orderBy('trainer.nama_lengkap')
            ->get();

        return response()->json([
            'success' => true,
            'trainers' => $trainers
        ]);
    }

    public function getRelevantParticipants(TrainingSchedule $schedule)
    {
        // Get training course details with target relationships
        $course = $schedule->course()->with(['targetDivisions', 'targetJabatans', 'targetOutlets'])->first();
        
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found'
            ], 404);
        }

        // Debug: Log course target information
        \Log::info('Course Target Debug', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'target_type' => $course->target_type,
            'target_divisions_count' => $course->targetDivisions ? $course->targetDivisions->count() : 0,
            'target_jabatans_count' => $course->targetJabatans ? $course->targetJabatans->count() : 0,
            'target_outlets_count' => $course->targetOutlets ? $course->targetOutlets->count() : 0,
            'target_divisions' => $course->targetDivisions ? $course->targetDivisions->pluck('nama_divisi')->toArray() : [],
            'target_jabatans' => $course->targetJabatans ? $course->targetJabatans->pluck('nama_jabatan')->toArray() : [],
            'target_outlets' => $course->targetOutlets ? $course->targetOutlets->pluck('nama_outlet')->toArray() : []
        ]);

        // Get all active users
        $allParticipants = User::where('status', 'A')
            ->with(['jabatan.level', 'divisi', 'outlet'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'nik', 'id_jabatan', 'division_id', 'id_outlet']);

        // Filter participants based on course target requirements
        $relevantParticipants = $allParticipants->filter(function($participant) use ($course) {
            // If course targets all users (target_type = 'all')
            if ($course->target_type === 'all') {
                return true;
            }
            
            // If no specific targets are set, include all participants
            if (!$course->targetDivisions && !$course->targetJabatans && !$course->targetOutlets) {
                return true;
            }
            
            // Check each target type and collect results
            $matchesDivision = false;
            $matchesJabatan = false;
            $matchesOutlet = false;
            
            // Check if participant matches target divisions
            if ($course->targetDivisions && $course->targetDivisions->count() > 0) {
                $targetDivisionIds = $course->targetDivisions->pluck('id')->toArray();
                $matchesDivision = in_array($participant->division_id, $targetDivisionIds);
            } else {
                $matchesDivision = true; // No division filter means all divisions match
            }
            
            // Check if participant matches target jabatans
            if ($course->targetJabatans && $course->targetJabatans->count() > 0) {
                $targetJabatanIds = $course->targetJabatans->pluck('id_jabatan')->toArray();
                $matchesJabatan = $participant->jabatan && in_array($participant->jabatan->id_jabatan, $targetJabatanIds);
            } else {
                $matchesJabatan = true; // No jabatan filter means all jabatans match
            }
            
            // Check if participant matches target outlets
            if ($course->targetOutlets && $course->targetOutlets->count() > 0) {
                $targetOutletIds = $course->targetOutlets->pluck('id_outlet')->toArray();
                $matchesOutlet = $participant->id_outlet && in_array($participant->id_outlet, $targetOutletIds);
            } else {
                $matchesOutlet = true; // No outlet filter means all outlets match
            }
            
            // Participant is relevant if they match ALL specified targets (AND condition)
            return $matchesDivision && $matchesJabatan && $matchesOutlet;
        });
        
        // Generate filter description for debugging
        $filterDescription = [];
        if ($course->target_type === 'all') {
            $filterDescription[] = 'All users (target_type = all)';
        } elseif (!$course->targetDivisions && !$course->targetJabatans && !$course->targetOutlets) {
            $filterDescription[] = 'All users (no specific targets)';
        } else {
            if ($course->targetDivisions && $course->targetDivisions->count() > 0) {
                $divisionNames = $course->targetDivisions->pluck('nama_divisi')->toArray();
                $filterDescription[] = 'Divisi: ' . implode(', ', $divisionNames);
            }
            if ($course->targetJabatans && $course->targetJabatans->count() > 0) {
                $jabatanNames = $course->targetJabatans->pluck('nama_jabatan')->toArray();
                $filterDescription[] = 'Jabatan: ' . implode(', ', $jabatanNames);
            }
            if ($course->targetOutlets && $course->targetOutlets->count() > 0) {
                $outletNames = $course->targetOutlets->pluck('nama_outlet')->toArray();
                $filterDescription[] = 'Outlet: ' . implode(', ', $outletNames);
            }
        }
        
        return response()->json([
            'success' => true,
            'participants' => $relevantParticipants->values(), // Reset array keys
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'target_divisions' => $course->targetDivisions,
                'target_jabatans' => $course->targetJabatans,
                'target_outlets' => $course->targetOutlets,
                'target_type' => $course->target_type
            ],
            'total' => $relevantParticipants->count(),
            'total_all_participants' => $allParticipants->count(),
            'filter_applied' => implode(' AND ', $filterDescription),
            'filter_logic' => 'AND condition - participant must match ALL specified targets'
        ]);
    }

    /**
     * Send notification to invited participant
     */
    private function sendParticipantInvitationNotification($invitation, $schedule)
    {
        try {
            \Log::info('Starting participant notification process', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'schedule_id' => $schedule->id
            ]);

            // Get participant details
            $participant = User::find($invitation->user_id);
            if (!$participant) {
                \Log::warning('Participant not found for notification', ['user_id' => $invitation->user_id]);
                return;
            }

            \Log::info('Participant found', ['participant_name' => $participant->nama_lengkap]);

            // Get course and outlet details - reload with relationships
            $scheduleWithRelations = TrainingSchedule::with(['course', 'outlet'])->find($schedule->id);
            $course = $scheduleWithRelations->course;
            $outlet = $scheduleWithRelations->outlet;
            $inviter = auth()->user();

            \Log::info('Schedule relationships loaded', [
                'course_title' => $course ? $course->title : 'NULL',
                'outlet_name' => $outlet ? $outlet->nama_outlet : 'NULL',
                'inviter_name' => $inviter ? $inviter->nama_lengkap : 'NULL'
            ]);

            // Format training date and time
            $trainingDate = \Carbon\Carbon::parse($schedule->scheduled_date)->format('d F Y');
            $trainingTime = $schedule->start_time . ' - ' . $schedule->end_time;

            // Create notification message
            $message = "Anda diundang untuk mengikuti training:\n\n";
            $message .= "📚 Course: {$course->title}\n";
            $message .= "📅 Tanggal: {$trainingDate}\n";
            $message .= "⏰ Waktu: {$trainingTime}\n";
            $message .= "🏢 Outlet: " . ($outlet ? $outlet->nama_outlet : 'Head Office') . "\n";
            $message .= "👤 Diundang oleh: {$inviter->nama_lengkap}\n\n";
            $message .= "Silakan konfirmasi kehadiran Anda melalui sistem LMS.";

            \Log::info('Notification message created', ['message_length' => strlen($message)]);

            // Insert notification
            $notificationId = DB::table('notifications')->insertGetId([
                'user_id' => $participant->id,
                'type' => 'training_invitation',
                'message' => $message,
                'url' => config('app.url') . '/lms/schedules/' . $schedule->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            \Log::info('Participant invitation notification sent successfully', [
                'notification_id' => $notificationId,
                'participant_id' => $participant->id,
                'participant_name' => $participant->nama_lengkap,
                'schedule_id' => $schedule->id,
                'course_title' => $course->title
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending participant invitation notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'invitation_id' => $invitation->id,
                'schedule_id' => $schedule->id
            ]);
        }
    }

    /**
     * Send notification to invited internal trainer
     */
    private function sendTrainerInvitationNotification($scheduleTrainer, $schedule)
    {
        try {
            \Log::info('Starting trainer notification process', [
                'schedule_trainer_id' => $scheduleTrainer->id,
                'trainer_id' => $scheduleTrainer->trainer_id,
                'schedule_id' => $schedule->id
            ]);

            // Get trainer details
            $trainer = User::find($scheduleTrainer->trainer_id);
            if (!$trainer) {
                \Log::warning('Trainer not found for notification', ['trainer_id' => $scheduleTrainer->trainer_id]);
                return;
            }

            \Log::info('Trainer found', ['trainer_name' => $trainer->nama_lengkap]);

            // Get course and outlet details - reload with relationships
            $scheduleWithRelations = TrainingSchedule::with(['course', 'outlet'])->find($schedule->id);
            $course = $scheduleWithRelations->course;
            $outlet = $scheduleWithRelations->outlet;
            $inviter = auth()->user();

            \Log::info('Schedule relationships loaded', [
                'course_title' => $course ? $course->title : 'NULL',
                'outlet_name' => $outlet ? $outlet->nama_outlet : 'NULL',
                'inviter_name' => $inviter ? $inviter->nama_lengkap : 'NULL'
            ]);

            // Format training date and time
            $trainingDate = \Carbon\Carbon::parse($schedule->scheduled_date)->format('d F Y');
            $trainingTime = $schedule->start_time . ' - ' . $schedule->end_time;

            // Check if this trainer is primary
            $isPrimary = $scheduleTrainer->is_primary_trainer ? ' (Primary Trainer)' : '';

            // Create notification message
            $message = "Anda diundang sebagai trainer untuk training:\n\n";
            $message .= "📚 Course: {$course->title}\n";
            $message .= "📅 Tanggal: {$trainingDate}\n";
            $message .= "⏰ Waktu: {$trainingTime}\n";
            $message .= "🏢 Outlet: " . ($outlet ? $outlet->nama_outlet : 'Head Office') . "\n";
            $message .= "🎯 Role: Trainer{$isPrimary}\n";
            $message .= "👤 Diundang oleh: {$inviter->nama_lengkap}\n\n";
            $message .= "Silakan persiapkan materi training dan konfirmasi kehadiran Anda.";

            \Log::info('Notification message created', ['message_length' => strlen($message)]);

            // Insert notification
            $notificationId = DB::table('notifications')->insertGetId([
                'user_id' => $trainer->id,
                'type' => 'trainer_invitation',
                'message' => $message,
                'url' => config('app.url') . '/lms/schedules/' . $schedule->id,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            \Log::info('Trainer invitation notification sent successfully', [
                'notification_id' => $notificationId,
                'trainer_id' => $trainer->id,
                'trainer_name' => $trainer->nama_lengkap,
                'schedule_id' => $schedule->id,
                'course_title' => $course->title,
                'is_primary' => $scheduleTrainer->is_primary_trainer
            ]);

        } catch (\Exception $e) {
            \Log::error('Error sending trainer invitation notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'schedule_trainer_id' => $scheduleTrainer->id,
                'schedule_id' => $schedule->id
            ]);
        }
    }

    /**
     * Get training notifications for current user (as participant or trainer)
     */
    public function getTrainingNotifications()
    {
        try {
            $userId = auth()->id();
            
            // Get training invitations where user is invited as participant
            $participantInvitations = DB::table('training_invitations')
                ->join('training_schedules', 'training_invitations.schedule_id', '=', 'training_schedules.id')
                ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('training_invitations.user_id', $userId)
                ->where('training_invitations.status', 'invited')
                ->where('training_schedules.scheduled_date', '>=', now()->toDateString())
                ->select(
                    'training_invitations.id as invitation_id',
                    'training_schedules.id as schedule_id',
                    'training_schedules.scheduled_date',
                    'training_schedules.start_time',
                    'training_schedules.end_time',
                    'lms_courses.title as course_title',
                    'lms_courses.id as course_id',
                    'tbl_data_outlet.nama_outlet',
                    'training_invitations.status',
                    'training_invitations.created_at as invited_at'
                )
                ->orderBy('training_schedules.scheduled_date')
                ->get();

            // Get training schedules where user is assigned as trainer
            $trainerAssignments = DB::table('training_schedule_trainers')
                ->join('training_schedules', 'training_schedule_trainers.schedule_id', '=', 'training_schedules.id')
                ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->where('training_schedule_trainers.trainer_id', $userId)
                ->where('training_schedule_trainers.trainer_type', 'internal')
                ->where('training_schedules.scheduled_date', '>=', now()->toDateString())
                ->select(
                    'training_schedule_trainers.id as trainer_assignment_id',
                    'training_schedules.id as schedule_id',
                    'training_schedules.scheduled_date',
                    'training_schedules.start_time',
                    'training_schedules.end_time',
                    'lms_courses.title as course_title',
                    'lms_courses.id as course_id',
                    'tbl_data_outlet.nama_outlet',
                    'training_schedule_trainers.is_primary_trainer',
                    'training_schedule_trainers.created_at as assigned_at'
                )
                ->orderBy('training_schedules.scheduled_date')
                ->get();

            // Format the data
            $notifications = collect();

            // Add participant invitations
            foreach ($participantInvitations as $invitation) {
                // Get course sessions with items
                $sessions = DB::table('lms_sessions')
                    ->where('course_id', $invitation->course_id)
                    ->where('status', 'active')
                    ->orderBy('order_number')
                    ->select('id', 'session_title', 'session_description', 'estimated_duration_minutes', 'is_required', 'order_number')
                    ->get();

                // Get session items for each session with prerequisite logic
                foreach ($sessions as $sessionIndex => $session) {
                    $sessionItems = DB::table('lms_session_items')
                        ->where('session_id', $session->id)
                        ->where('status', 'active')
                        ->orderBy('order_number')
                        ->select('id', 'item_type', 'item_id', 'title', 'description', 'estimated_duration_minutes', 'is_required', 'order_number', 'passing_score', 'max_attempts')
                        ->get();
                    
                    // Check if session can be accessed (prerequisite logic)
                    $session->can_access = $this->canAccessSession($userId, $invitation->schedule_id, $sessionIndex, $sessions);
                    $session->progress = null; // Progress tracking not implemented yet
                    
                    // Get access control for each item and fetch quiz data if needed
                    foreach ($sessionItems as $itemIndex => $item) {
                        // Check if item can be accessed (prerequisite logic)
                        $item->can_access = $this->canAccessItem($userId, $invitation->schedule_id, $session->id, $item->id, $sessionItems, $itemIndex, $session->can_access);
                        $item->progress = null; // Progress tracking not implemented yet
                        
                        // Fetch quiz data if item is a quiz
                        if ($item->item_type === 'quiz' && $item->item_id) {
                            $item->quiz = $this->getQuizData($item->item_id, $userId);
                        }
                        
                        // Fetch questionnaire data if item is a questionnaire
                        if ($item->item_type === 'questionnaire' && $item->item_id) {
                            $item->questionnaire = $this->getQuestionnaireData($item->item_id, $userId);
                        }
                    }
                    
                    $session->items = $sessionItems;
                }

                // Check if all sessions and items are completed
                $allCompleted = $this->isAllSessionsCompleted($userId, $invitation->schedule_id, $sessions);
                
                // Get trainer information
                $trainers = $this->getTrainingTrainers($invitation->schedule_id);
                
                $notifications->push([
                    'id' => 'participant_' . $invitation->invitation_id,
                    'type' => 'participant_invitation',
                    'schedule_id' => $invitation->schedule_id,
                    'course_title' => $invitation->course_title,
                    'course_id' => $invitation->course_id,
                    'scheduled_date' => $invitation->scheduled_date,
                    'start_time' => $invitation->start_time,
                    'end_time' => $invitation->end_time,
                    'outlet_name' => $invitation->nama_outlet ?? 'Head Office',
                    'status' => $invitation->status,
                    'created_at' => $invitation->invited_at,
                    'role' => 'Peserta',
                    'sessions' => $sessions,
                    'all_completed' => $allCompleted,
                    'trainers' => $trainers,
                    'can_give_feedback' => $allCompleted && $this->canGiveFeedback($userId, $invitation->schedule_id)
                ]);
            }

            // Add trainer assignments
            foreach ($trainerAssignments as $assignment) {
                // Get course sessions with items
                $sessions = DB::table('lms_sessions')
                    ->where('course_id', $assignment->course_id)
                    ->where('status', 'active')
                    ->orderBy('order_number')
                    ->select('id', 'session_title', 'session_description', 'estimated_duration_minutes', 'is_required', 'order_number')
                    ->get();

                // Get session items for each session with prerequisite logic
                foreach ($sessions as $sessionIndex => $session) {
                    $sessionItems = DB::table('lms_session_items')
                        ->where('session_id', $session->id)
                        ->where('status', 'active')
                        ->orderBy('order_number')
                        ->select('id', 'item_type', 'item_id', 'title', 'description', 'estimated_duration_minutes', 'is_required', 'order_number', 'passing_score', 'max_attempts')
                        ->get();
                    
                    // Check if session can be accessed (prerequisite logic)
                    $session->can_access = $this->canAccessSession($userId, $assignment->schedule_id, $sessionIndex, $sessions);
                    $session->progress = null; // Progress tracking not implemented yet
                    
                    // Get access control for each item and fetch quiz data if needed
                    foreach ($sessionItems as $itemIndex => $item) {
                        // Check if item can be accessed (prerequisite logic)
                        $item->can_access = $this->canAccessItem($userId, $assignment->schedule_id, $session->id, $item->id, $sessionItems, $itemIndex, $session->can_access);
                        $item->progress = null; // Progress tracking not implemented yet
                        
                        // Fetch quiz data if item is a quiz
                        if ($item->item_type === 'quiz' && $item->item_id) {
                            $item->quiz = $this->getQuizData($item->item_id, $userId);
                        }
                        
                        // Fetch questionnaire data if item is a questionnaire
                        if ($item->item_type === 'questionnaire' && $item->item_id) {
                            $item->questionnaire = $this->getQuestionnaireData($item->item_id, $userId);
                        }
                    }
                    
                    $session->items = $sessionItems;
                }

                $notifications->push([
                    'id' => 'trainer_' . $assignment->trainer_assignment_id,
                    'type' => 'trainer_assignment',
                    'schedule_id' => $assignment->schedule_id,
                    'course_title' => $assignment->course_title,
                    'course_id' => $assignment->course_id,
                    'scheduled_date' => $assignment->scheduled_date,
                    'start_time' => $assignment->start_time,
                    'end_time' => $assignment->end_time,
                    'outlet_name' => $assignment->nama_outlet ?? 'Head Office',
                    'is_primary_trainer' => $assignment->is_primary_trainer,
                    'created_at' => $assignment->assigned_at,
                    'role' => $assignment->is_primary_trainer ? 'Primary Trainer' : 'Trainer',
                    'sessions' => $sessions
                ]);
            }

            // Sort by scheduled date
            $notifications = $notifications->sortBy('scheduled_date')->values();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'total' => $notifications->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting training notifications', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat mengambil notifikasi training: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can access a specific session
     */
    private function canAccessSession($userId, $scheduleId, $sessionIndex, $sessions)
    {
        // Check if user has checked in to this training
        $checkIn = DB::table('training_invitations')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time') // Still checked in
            ->first();

        if (!$checkIn) {
            return false; // Must check in first
        }

        // First session is always accessible after check-in
        if ($sessionIndex === 0) {
            return true;
        }

        // For now, all sessions after check-in are accessible
        // TODO: Implement session-level prerequisite logic when progress tracking is available
        return true;
    }

    /**
     * Check if user can access a specific item within a session
     */
    private function canAccessItem($userId, $scheduleId, $sessionId, $itemId, $sessionItems, $itemIndex, $sessionCanAccess)
    {
        // If session can't be accessed, item can't be accessed
        if (!$sessionCanAccess) {
            return false;
        }

        // Check if previous items are completed (prerequisite logic)
        for ($i = 0; $i < $itemIndex; $i++) {
            $previousItem = $sessionItems[$i];
            
            // Skip if previous item is not required
            if (!$previousItem->is_required) {
                continue;
            }
            
            // Check if previous item is completed
            if (!$this->isItemCompleted($userId, $scheduleId, $previousItem)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a session item is completed by the user
     */
    private function isItemCompleted($userId, $scheduleId, $item)
    {
        switch ($item->item_type) {
            case 'quiz':
                return $this->isQuizCompleted($userId, $item->item_id);
            case 'questionnaire':
                return $this->isQuestionnaireCompleted($userId, $item->item_id);
            case 'material':
                return $this->isMaterialCompleted($userId, $item->item_id);
            default:
                return false;
        }
    }

    /**
     * Check if quiz is completed and passed
     */
    private function isQuizCompleted($userId, $quizId)
    {
        $attempt = DB::table('lms_quiz_attempts')
            ->where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->first();
            
        return $attempt !== null;
    }

    /**
     * Check if questionnaire is completed
     */
    private function isQuestionnaireCompleted($userId, $questionnaireId)
    {
        $response = DB::table('lms_questionnaire_responses')
            ->where('questionnaire_id', $questionnaireId)
            ->where('user_id', $userId)
            ->whereNotNull('submitted_at')
            ->first();
            
        return $response !== null;
    }

    /**
     * Check if material is completed
     */
    private function isMaterialCompleted($userId, $materialId)
    {
        // TODO: Implement material completion check when material progress tracking is ready
        return false;
    }

    /**
     * Get quiz data with questions and options for a specific quiz
     */
    private function getQuizData($quizId, $userId)
    {
        try {
            // Get quiz basic info
            $quiz = DB::table('lms_quizzes')
                ->where('id', $quizId)
                ->where('status', 'published')
                ->select('id', 'title', 'description', 'instructions', 'time_limit_type', 'time_limit_minutes', 'time_per_question_seconds', 'passing_score', 'max_attempts', 'is_randomized', 'show_results')
                ->first();

            if (!$quiz) {
                return null;
            }

            // Get questions with options
            $questions = DB::table('lms_quiz_questions')
                ->where('quiz_id', $quizId)
                ->orderBy('order_number')
                ->select('id', 'question_text', 'question_type', 'points', 'order_number', 'is_required')
                ->get();

            // Get options for each question
            foreach ($questions as $question) {
                $question->options = DB::table('lms_quiz_options')
                    ->where('question_id', $question->id)
                    ->orderBy('order_number')
                    ->select('id', 'option_text', 'is_correct', 'order_number')
                    ->get();
            }

            // Get user's attempts for this quiz
            $attempts = DB::table('lms_quiz_attempts')
                ->where('quiz_id', $quizId)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'started_at', 'completed_at', 'score', 'is_passed', 'status', 'attempt_number')
                ->get();

            // Get user's answers for the latest attempt
            $latestAttempt = $attempts->first();
            $answers = [];
            if ($latestAttempt && $latestAttempt->status === 'in_progress') {
                $answers = DB::table('lms_quiz_answers')
                    ->where('attempt_id', $latestAttempt->id)
                    ->select('question_id', 'selected_option_id', 'essay_answer', 'is_correct', 'points_earned')
                    ->get()
                    ->keyBy('question_id');
            }

            $quiz->questions = $questions;
            $quiz->attempts = $attempts;
            $quiz->latest_attempt = $latestAttempt;
            $quiz->answers = $answers;
            $quiz->can_attempt = $this->canUserAttemptQuiz($quiz, $attempts);

            return $quiz;
        } catch (\Exception $e) {
            \Log::error('Error fetching quiz data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user can attempt the quiz
     */
    private function canUserAttemptQuiz($quiz, $attempts)
    {
        // If no max attempts set, user can always attempt
        if (!$quiz->max_attempts) {
            return true;
        }

        // Check if user has reached max attempts
        $completedAttempts = $attempts->where('status', 'completed')->count();
        return $completedAttempts < $quiz->max_attempts;
    }

    /**
     * Get questionnaire data with questions and options for a specific questionnaire
     */
    private function getQuestionnaireData($questionnaireId, $userId)
    {
        try {
            // Get questionnaire basic info
            $questionnaire = DB::table('lms_questionnaires')
                ->where('id', $questionnaireId)
                ->where('status', 'published')
                ->select('id', 'title', 'description', 'instructions', 'is_anonymous', 'allow_multiple_responses', 'start_date', 'end_date')
                ->first();

            if (!$questionnaire) {
                return null;
            }

            // Check if questionnaire is active (within date range)
            $now = now()->toDateString();
            $isActive = true;
            
            if ($questionnaire->start_date && $questionnaire->start_date > $now) {
                $isActive = false;
            }
            
            if ($questionnaire->end_date && $questionnaire->end_date < $now) {
                $isActive = false;
            }

            // Get questions with options
            $questions = DB::table('lms_questionnaire_questions')
                ->where('questionnaire_id', $questionnaireId)
                ->orderBy('order_number')
                ->select('id', 'question_text', 'question_type', 'is_required', 'order_number')
                ->get();

            // Get options for each question
            foreach ($questions as $question) {
                if (in_array($question->question_type, ['multiple_choice', 'true_false', 'checkbox'])) {
                    $question->options = DB::table('lms_questionnaire_options')
                        ->where('question_id', $question->id)
                        ->orderBy('order_number')
                        ->select('id', 'option_text', 'order_number')
                        ->get();
                }
            }

            // Get user's responses for this questionnaire
            $responses = DB::table('lms_questionnaire_responses')
                ->where('questionnaire_id', $questionnaireId)
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->select('id', 'submitted_at', 'created_at')
                ->get();

            // Get user's answers for the latest response
            $latestResponse = $responses->first();
            $answers = [];
            if ($latestResponse && $latestResponse->submitted_at) {
                $answers = DB::table('lms_questionnaire_answers')
                    ->where('response_id', $latestResponse->id)
                    ->select('question_id', 'answer_text', 'selected_option_id', 'rating_value')
                    ->get()
                    ->keyBy('question_id');
            }

            $questionnaire->questions = $questions;
            $questionnaire->responses = $responses;
            $questionnaire->latest_response = $latestResponse;
            $questionnaire->answers = $answers;
            $questionnaire->is_active = $isActive;
            $questionnaire->can_respond = $this->canUserRespondToQuestionnaire($questionnaire, $responses);

            return $questionnaire;
        } catch (\Exception $e) {
            \Log::error('Error fetching questionnaire data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user can respond to the questionnaire
     */
    private function canUserRespondToQuestionnaire($questionnaire, $responses)
    {
        // If questionnaire is not active, user can't respond
        if (!$questionnaire->is_active) {
            return false;
        }

        // If anonymous, user can always respond
        if ($questionnaire->is_anonymous) {
            return true;
        }

        // If multiple responses allowed, user can always respond
        if ($questionnaire->allow_multiple_responses) {
            return true;
        }

        // If single response only, check if user already responded
        $hasSubmittedResponse = $responses->where('submitted_at', '!=', null)->count() > 0;
        return !$hasSubmittedResponse;
    }

    /**
     * Check if all sessions and items are completed
     */
    private function isAllSessionsCompleted($userId, $scheduleId, $sessions)
    {
        // For trainers, they don't need to complete sessions - just check-in/check-out
        $isTrainer = DB::table('training_schedule_trainers')
            ->where('schedule_id', $scheduleId)
            ->where('trainer_id', $userId)
            ->where('trainer_type', 'internal')
            ->exists();
            
        if ($isTrainer) {
            return true; // Trainers are always considered "completed" for feedback purposes
        }
        
        // For participants, check session and item completion
        foreach ($sessions as $session) {
            // Check if session is required and completed
            if ($session->is_required && !$this->isSessionCompleted($userId, $scheduleId, $session->id)) {
                return false;
            }
            
            // Check if all required items in session are completed
            if (isset($session->items)) {
                foreach ($session->items as $item) {
                    if ($item->is_required && !$this->isItemCompleted($userId, $scheduleId, $item)) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * Check if session is completed
     */
    private function isSessionCompleted($userId, $scheduleId, $sessionId)
    {
        // For now, we'll consider a session completed if all required items are completed
        // This can be enhanced later with proper session progress tracking
        $requiredItems = DB::table('lms_session_items')
            ->where('session_id', $sessionId)
            ->where('is_required', 1)
            ->where('status', 'active')
            ->get();

        foreach ($requiredItems as $item) {
            if (!$this->isItemCompleted($userId, $scheduleId, $item)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get training trainers information
     */
    private function getTrainingTrainers($scheduleId)
    {
        $trainers = DB::table('training_schedule_trainers')
            ->join('users', 'training_schedule_trainers.trainer_id', '=', 'users.id')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
            ->where('training_schedule_trainers.schedule_id', $scheduleId)
            ->where('training_schedule_trainers.trainer_type', 'internal')
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'tbl_data_jabatan.nama_jabatan',
                'tbl_data_divisi.nama_divisi',
                'training_schedule_trainers.is_primary_trainer'
            )
            ->get();

        return $trainers;
    }

    /**
     * Get training sessions for a course with user progress
     */
    private function getTrainingSessions($courseId, $userId)
    {
        // For now, return mock data. In a real implementation, this would come from the database
        return [
            [
                'id' => 1,
                'title' => 'Pengenalan Training',
                'description' => 'Sesi pembukaan dan pengenalan materi training',
                'duration' => 30,
                'is_accessible' => true,
                'progress' => 0,
                'items' => [
                    [
                        'id' => 1,
                        'title' => 'Materi Pengenalan Training',
                        'type' => 'material',
                        'duration' => 15,
                        'is_accessible' => true,
                        'is_completed' => false
                    ],
                    [
                        'id' => 2,
                        'title' => 'Quiz Pengenalan',
                        'type' => 'quiz',
                        'duration' => 10,
                        'is_accessible' => true,
                        'is_completed' => false
                    ]
                ]
            ],
            [
                'id' => 2,
                'title' => 'Materi Utama Training',
                'description' => 'Sesi pembelajaran materi utama training',
                'duration' => 60,
                'is_accessible' => false, // Will be unlocked after completing session 1
                'progress' => 0,
                'items' => [
                    [
                        'id' => 3,
                        'title' => 'Video Pembelajaran Interaktif',
                        'type' => 'material',
                        'duration' => 30,
                        'is_accessible' => false,
                        'is_completed' => false
                    ],
                    [
                        'id' => 4,
                        'title' => 'Latihan Praktik',
                        'type' => 'activity',
                        'duration' => 20,
                        'is_accessible' => false,
                        'is_completed' => false
                    ],
                    [
                        'id' => 5,
                        'title' => 'Quiz Materi Utama',
                        'type' => 'quiz',
                        'duration' => 15,
                        'is_accessible' => false,
                        'is_completed' => false
                    ]
                ]
            ],
            [
                'id' => 3,
                'title' => 'Evaluasi dan Penutup',
                'description' => 'Sesi evaluasi dan penutupan training',
                'duration' => 30,
                'is_accessible' => false, // Will be unlocked after completing session 2
                'progress' => 0,
                'items' => [
                    [
                        'id' => 6,
                        'title' => 'Evaluasi Akhir',
                        'type' => 'quiz',
                        'duration' => 20,
                        'is_accessible' => false,
                        'is_completed' => false
                    ],
                    [
                        'id' => 7,
                        'title' => 'Feedback Training',
                        'type' => 'activity',
                        'duration' => 10,
                        'is_accessible' => false,
                        'is_completed' => false
                    ]
                ]
            ]
        ];
    }

    /**
     * Check if user can give feedback
     */
    private function canGiveFeedback($userId, $scheduleId)
    {
        // Check if user has already given feedback
        $existingFeedback = DB::table('training_feedback')
            ->where('schedule_id', $scheduleId)
            ->where('user_id', $userId)
            ->first();
            
        return !$existingFeedback;
    }

    /**
     * Handle training checkout and save history
     */
    public function checkoutTraining(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:training_schedules,id',
            'user_type' => 'required|in:participant,trainer'
        ]);

        $userId = auth()->id();
        $scheduleId = $validated['schedule_id'];
        $userType = $validated['user_type'];

        DB::beginTransaction();
        try {
            // Update invitation status to checked out for participants
            if ($userType === 'participant') {
                DB::table('training_invitations')
                    ->where('schedule_id', $scheduleId)
                    ->where('user_id', $userId)
                    ->update([
                        'status' => 'checked_out',
                        'checkout_time' => now(),
                        'updated_at' => now()
                    ]);
            }
            
            // For trainers, we don't need to update invitation status
            // They just need to checkout and save history

            // Save training history
            $historyController = new \App\Http\Controllers\TrainingHistoryController();
            $historyRequest = new Request([
                'schedule_id' => $scheduleId,
                'user_type' => $userType,
                'checkout_time' => now()
            ]);
            
            $historyResult = $historyController->saveTrainingHistory($historyRequest);
            
            if ($historyResult->getStatusCode() !== 200) {
                throw new \Exception('Failed to save training history');
            }

            DB::commit();

            return response()->json([
                'message' => 'Training checkout successful',
                'data' => json_decode($historyResult->getContent(), true)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user training history
     */
    public function getUserTrainingHistory(Request $request)
    {
        $historyController = new \App\Http\Controllers\TrainingHistoryController();
        return $historyController->getUserTrainingHistory($request);
    }

    /**
     * Get training history details
     */
    public function getTrainingHistoryDetails(Request $request, $historyId)
    {
        $historyController = new \App\Http\Controllers\TrainingHistoryController();
        return $historyController->getTrainingHistoryDetails($request, $historyId);
    }
}
