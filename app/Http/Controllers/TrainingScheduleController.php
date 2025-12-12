<?php

namespace App\Http\Controllers;

use App\Models\TrainingSchedule;
use App\Models\TrainingInvitation;
use App\Models\Course;
use App\Models\Outlet;
use App\Models\User;
use App\Models\TrainingReview;
use App\Models\LmsCourse;
use App\Services\NotificationService;
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

            // Create calendar reminder for trainer (if internal trainer)
            if ($course->instructor_id) {
                $this->createTrainingReminder($schedule, $course->instructor_id, 'trainer');
            }

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
        
        $oldStatus = $schedule->status;
        $newStatus = $request->status;
        
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

        // Auto-generate certificates when training is completed
        if ($newStatus === 'completed' && $course->certificate_template_id) {
            \Log::info('Auto-generating certificates from update method', [
                'schedule_id' => $schedule->id,
                'course_id' => $course->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'certificate_template_id' => $course->certificate_template_id
            ]);
            $this->autoGenerateCertificates($schedule, $course);
        }

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

            // Create calendar reminder for participant
            $this->createTrainingReminder($schedule, $userId, 'participant');

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
                
                // Create calendar reminder for internal trainer
                $this->createTrainingReminder($schedule, $trainerData['trainer_id'], 'trainer');
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
                return back()->withErrors(['error' => 'Tidak dapat check-in saat ini. Pastikan training sedang berlangsung dan Anda terdaftar.']);
            }

            if ($invitation->checkIn()) {
                // Get training sessions for the course
                $trainingSessions = $this->getTrainingSessions($invitation->schedule->course_id, $invitation->user_id, $invitation->schedule_id);
                
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

                // Determine success message based on check-in status
                $successMessage = $invitation->status === 'attended' && $invitation->check_in_time 
                    ? 'Selamat datang kembali! Anda sudah terdaftar untuk training ini.'
                    : 'Check-in berhasil untuk ' . $invitation->user->nama_lengkap;

                // Return Inertia response for all requests
                return back()->with([
                    'success' => $successMessage,
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

    // Manual trigger for certificate generation
    public function generateCertificatesForCompletedTraining($id)
    {
        try {
            $training = TrainingSchedule::findOrFail($id);
            $course = $training->course;
            
            if ($training->status !== 'completed') {
                return back()->withErrors(['error' => 'Training harus dalam status completed untuk generate certificate']);
            }
            
            if (!$course->certificate_template_id) {
                return back()->withErrors(['error' => 'Course tidak memiliki certificate template']);
            }
            
            $this->autoGenerateCertificates($training, $course);
            
            return back()->with('success', 'Certificate berhasil di-generate untuk training yang completed');
            
        } catch (\Exception $e) {
            \Log::error('Manual certificate generation error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal generate certificate: ' . $e->getMessage()]);
        }
    }

    // Helper methods
    private function autoGenerateCertificates($training, $course)
    {
        try {
            \Log::info('Starting auto-generate certificates', [
                'training_id' => $training->id,
                'course_id' => $course->id,
                'course_title' => $course->title
            ]);

            $templateId = $course->certificate_template_id;
            
            if (!$templateId) {
                \Log::warning('No certificate template found for course', [
                    'course_id' => $course->id,
                    'course_title' => $course->title
                ]);
                return;
            }

            \Log::info('Certificate template found', [
                'template_id' => $templateId,
                'course_id' => $course->id
            ]);

            // Validate template exists
            if (!\App\Models\CertificateTemplate::find($templateId)) {
                \Log::warning('Certificate template not found', [
                    'template_id' => $templateId,
                    'course_id' => $course->id
                ]);
                return;
            }

            $issued = 0;
            $training->load('invitations.user');
            
            \Log::info('Processing invitations', [
                'training_id' => $training->id,
                'total_invitations' => $training->invitations->count()
            ]);
            
            foreach ($training->invitations as $invitation) {
                \Log::info('Processing invitation', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $invitation->user_id,
                    'status' => $invitation->status,
                    'user_exists' => $invitation->user ? 'yes' : 'no'
                ]);

                if ($invitation->status !== 'attended' || !$invitation->user) {
                    \Log::info('Skipping invitation - not attended or no user', [
                        'invitation_id' => $invitation->id,
                        'status' => $invitation->status,
                        'user_exists' => $invitation->user ? 'yes' : 'no'
                    ]);
                    continue;
                }

                // Avoid duplicate certificates for same course + user + date + template
                $existing = \App\Models\LmsCertificate::where('course_id', $training->course_id)
                    ->where('user_id', $invitation->user_id)
                    ->whereDate('issued_at', $training->scheduled_date)
                    ->where('template_id', $templateId)
                    ->first();

                if ($existing) {
                    \Log::info('Certificate already exists, skipping', [
                        'invitation_id' => $invitation->id,
                        'existing_certificate_id' => $existing->id
                    ]);
                    continue;
                }

                \Log::info('Creating certificate', [
                    'course_id' => $training->course_id,
                    'user_id' => $invitation->user_id,
                    'template_id' => $templateId,
                    'scheduled_date' => $training->scheduled_date
                ]);

                       // Generate 16-digit certificate number
                       $certificateNumber = 'CERT' . date('Y') . str_pad($training->course_id, 3, '0', STR_PAD_LEFT) . str_pad($invitation->user_id, 3, '0', STR_PAD_LEFT) . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

                       \Log::info('Generated certificate number', [
                           'certificate_number' => $certificateNumber,
                           'course_id' => $training->course_id,
                           'user_id' => $invitation->user_id
                       ]);

                       \App\Models\LmsCertificate::create([
                           'course_id' => $training->course_id,
                           'enrollment_id' => null,
                           'user_id' => $invitation->user_id,
                           'certificate_number' => $certificateNumber,
                           'issued_at' => $training->scheduled_date,
                           'expires_at' => null,
                           'template_id' => $templateId,
                           'status' => 'active',
                           'created_by' => auth()->id(),
                           'updated_by' => auth()->id(),
                       ]);

                $issued++;
                \Log::info('Certificate created successfully', [
                    'invitation_id' => $invitation->id,
                    'user_id' => $invitation->user_id
                ]);
            }

            \Log::info('Auto-generated certificates completed', [
                'training_id' => $training->id,
                'course_id' => $course->id,
                'template_id' => $templateId,
                'certificates_issued' => $issued
            ]);

        } catch (\Exception $e) {
            \Log::error('Auto-generate certificates error: ' . $e->getMessage(), [
                'training_id' => $training->id,
                'course_id' => $course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

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
        $course = $schedule->course()->with(['targetDivisions', 'targetJabatans', 'targetOutlets', 'targetDivision'])->first();
        
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
            
            // If target_type is 'single' or 'multiple' but no specific targets are set, 
            // this means the course is not properly configured - return no participants
            if (($course->target_type === 'single' || $course->target_type === 'multiple') && 
                !$course->target_division_id && 
                (!$course->targetDivisions || $course->targetDivisions->count() === 0) && 
                (!$course->target_jabatan_ids || count($course->target_jabatan_ids) === 0) && 
                (!$course->target_outlet_ids || count($course->target_outlet_ids) === 0)) {
                return false; // No participants if targets are not properly set
            }
            
            // Check each target type and collect results
            $matchesDivision = false;
            $matchesJabatan = false;
            $matchesOutlet = false;
            
            // Check if participant matches target divisions
            if ($course->target_type === 'single' && $course->target_division_id) {
                // Single division target
                $matchesDivision = $participant->division_id == $course->target_division_id;
            } elseif ($course->target_type === 'multiple' && $course->targetDivisions && $course->targetDivisions->count() > 0) {
                // Multiple divisions target
                $targetDivisionIds = $course->targetDivisions->pluck('id')->toArray();
                $matchesDivision = in_array($participant->division_id, $targetDivisionIds);
            } else {
                $matchesDivision = true; // No division filter means all divisions match
            }
            
            // Check if participant matches target jabatans
            if ($course->target_jabatan_ids && is_array($course->target_jabatan_ids) && count($course->target_jabatan_ids) > 0) {
                $matchesJabatan = $participant->id_jabatan && in_array($participant->id_jabatan, $course->target_jabatan_ids);
            } else {
                $matchesJabatan = true; // No jabatan filter means all jabatans match
            }
            
            // Check if participant matches target outlets
            if ($course->target_outlet_ids && is_array($course->target_outlet_ids) && count($course->target_outlet_ids) > 0) {
                $matchesOutlet = $participant->id_outlet && in_array($participant->id_outlet, $course->target_outlet_ids);
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
        } elseif (($course->target_type === 'single' || $course->target_type === 'multiple') && 
                 !$course->target_division_id && 
                 (!$course->targetDivisions || $course->targetDivisions->count() === 0) && 
                 (!$course->target_jabatan_ids || count($course->target_jabatan_ids) === 0) && 
                 (!$course->target_outlet_ids || count($course->target_outlet_ids) === 0)) {
            $filterDescription[] = 'No participants (targets not properly configured)';
        } else {
            // Single division target
            if ($course->target_type === 'single' && $course->target_division_id && $course->targetDivision) {
                $filterDescription[] = 'Divisi: ' . $course->targetDivision->nama_divisi;
            }
            // Multiple divisions target
            elseif ($course->target_type === 'multiple' && $course->targetDivisions && $course->targetDivisions->count() > 0) {
                $divisionNames = $course->targetDivisions->pluck('nama_divisi')->toArray();
                $filterDescription[] = 'Divisi: ' . implode(', ', $divisionNames);
            }
            
            if ($course->target_jabatan_ids && is_array($course->target_jabatan_ids) && count($course->target_jabatan_ids) > 0) {
                // Get jabatan names from database
                $jabatanNames = \App\Models\Jabatan::whereIn('id_jabatan', $course->target_jabatan_ids)->pluck('nama_jabatan')->toArray();
                $filterDescription[] = 'Jabatan: ' . implode(', ', $jabatanNames);
            }
            
            if ($course->target_outlet_ids && is_array($course->target_outlet_ids) && count($course->target_outlet_ids) > 0) {
                // Get outlet names from database
                $outletNames = \App\Models\DataOutlet::whereIn('id_outlet', $course->target_outlet_ids)->pluck('nama_outlet')->toArray();
                $filterDescription[] = 'Outlet: ' . implode(', ', $outletNames);
            }
        }
        
        return response()->json([
            'success' => true,
            'participants' => $relevantParticipants->values(), // Reset array keys
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'target_type' => $course->target_type,
                'target_division_id' => $course->target_division_id,
                'target_divisions' => $course->targetDivisions,
                'target_jabatan_ids' => $course->target_jabatan_ids,
                'target_outlet_ids' => $course->target_outlet_ids,
                'target_jabatans' => $course->targetJabatans,
                'target_outlets' => $course->targetOutlets
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
            $notificationId = NotificationService::insertGetId([
                'user_id' => $participant->id,
                'type' => 'training_invitation',
                'message' => $message,
                'url' => config('app.url') . '/lms/schedules/' . $schedule->id,
                'is_read' => 0,
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
            $notificationId = NotificationService::insertGetId([
                'user_id' => $trainer->id,
                'type' => 'trainer_invitation',
                'message' => $message,
                'url' => config('app.url') . '/lms/schedules/' . $schedule->id,
                'is_read' => 0,
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
     * Get training history for current user (completed training with reviews)
     */
    public function getTrainingHistory()
    {
        try {
            $userId = auth()->id();
            
            // Get training invitations where user has completed and reviewed
            $completedTrainings = DB::table('training_invitations')
                ->join('training_schedules', 'training_invitations.schedule_id', '=', 'training_schedules.id')
                ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->leftJoin('training_schedule_trainers', 'training_schedules.id', '=', 'training_schedule_trainers.schedule_id')
                ->leftJoin('users', function($join) {
                    $join->on('training_schedule_trainers.trainer_id', '=', 'users.id')
                         ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                })
                ->join('training_reviews', function($join) use ($userId) {
                    $join->on('training_reviews.training_schedule_id', '=', 'training_schedules.id')
                         ->where('training_reviews.user_id', $userId);
                })
                ->where('training_invitations.user_id', $userId)
                ->where('training_invitations.status', 'attended')
                ->whereNotNull('training_invitations.check_out_time')
                ->select(
                    'training_invitations.id as invitation_id',
                    'training_schedules.id as schedule_id',
                    'training_schedules.scheduled_date',
                    'training_schedules.start_time',
                    'training_schedules.end_time',
                    'training_schedules.status as training_status',
                    'lms_courses.title as course_title',
                    'lms_courses.id as course_id',
                    'tbl_data_outlet.nama_outlet',
                    'training_invitations.status',
                    'training_invitations.check_in_time',
                    'training_invitations.check_out_time',
                    'training_invitations.created_at as invited_at',
                    'training_reviews.training_rating',
                    'training_reviews.overall_satisfaction',
                    'training_reviews.created_at as review_date',
                    'training_schedule_trainers.trainer_type',
                    'training_schedule_trainers.external_trainer_name',
                    'users.nama_lengkap as internal_trainer_name'
                )
                ->orderBy('training_schedules.scheduled_date', 'desc')
                ->get();

            // Process trainer data and add sessions/materials for each training
            $completedTrainings->each(function ($training) {
                // Determine trainer name based on type
                if ($training->trainer_type === 'internal' && $training->internal_trainer_name) {
                    $training->trainer_name = $training->internal_trainer_name;
                } elseif ($training->trainer_type === 'external' && $training->external_trainer_name) {
                    $training->trainer_name = $training->external_trainer_name;
                } else {
                    $training->trainer_name = 'Trainer tidak tersedia';
                }

                // Get certificate data for this training
                try {
                    $certificate = \App\Models\LmsCertificate::with(['template', 'user', 'course'])
                        ->where('course_id', $training->course_id)
                        ->where('user_id', auth()->id())
                        ->where('status', 'active')
                        ->first();

                    if ($certificate) {
                        // Get trainer name and training location
                        $instructorName = 'Instruktur Training';
                        $trainingLocation = 'Lokasi Training';
                        
                        $trainingSchedule = \App\Models\TrainingSchedule::where('course_id', $training->course_id)
                            ->whereDate('scheduled_date', $certificate->issued_at ? $certificate->issued_at->format('Y-m-d') : now()->format('Y-m-d'))
                            ->with(['scheduleTrainers.trainer', 'outlet'])
                            ->first();
                            
                        if ($trainingSchedule) {
                            if ($trainingSchedule->scheduleTrainers->isNotEmpty()) {
                                $primaryTrainer = $trainingSchedule->scheduleTrainers->where('is_primary_trainer', true)->first();
                                if ($primaryTrainer && $primaryTrainer->trainer) {
                                    $instructorName = $primaryTrainer->trainer->nama_lengkap;
                                } else {
                                    $firstTrainer = $trainingSchedule->scheduleTrainers->first();
                                    if ($firstTrainer && $firstTrainer->trainer) {
                                        $instructorName = $firstTrainer->trainer->nama_lengkap;
                                    }
                                }
                            }
                            
                            if ($trainingSchedule->outlet) {
                                $trainingLocation = $trainingSchedule->outlet->nama_outlet;
                            }
                        }

                        $training->certificate = [
                            'id' => $certificate->id,
                            'certificate_number' => $certificate->certificate_number,
                            'issued_at' => $certificate->issued_at,
                            'status' => $certificate->status,
                            'template' => $certificate->template,
                            'user' => $certificate->user,
                            'course' => $certificate->course,
                            'instructor_name' => $instructorName,
                            'training_location' => $trainingLocation
                        ];
                    } else {
                        $training->certificate = null;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching certificate data', [
                        'course_id' => $training->course_id,
                        'user_id' => auth()->id(),
                        'error' => $e->getMessage()
                    ]);
                    $training->certificate = null;
                }

                // Get course sessions with materials
                $course = LmsCourse::with([
                    'sessions.items.material.files',
                    'sessions.items.quiz',
                    'sessions.items.questionnaire'
                ])->find($training->course_id);

                if ($course) {
                    $training->sessions = $course->sessions->map(function ($session) {
                        return [
                            'id' => $session->id,
                            'session_number' => $session->session_number,
                            'session_title' => $session->session_title,
                            'session_description' => $session->session_description,
                            'order_number' => $session->order_number,
                            'estimated_duration_minutes' => $session->estimated_duration_minutes,
                            'items' => $session->items->map(function ($item) {
                                $itemData = [
                                    'id' => $item->id,
                                    'item_type' => $item->item_type,
                                    'title' => $item->display_title,
                                    'description' => $item->display_description,
                                    'order_number' => $item->order_number,
                                    'is_required' => $item->is_required,
                                    'estimated_duration_minutes' => $item->estimated_duration_minutes,
                                    'can_access' => true // User can access materials from completed training
                                ];

                                // Add material data if it's a material item
                                if ($item->item_type === 'material' && $item->material) {
                                    $itemData['material'] = [
                                        'id' => $item->material->id,
                                        'title' => $item->material->title,
                                        'description' => $item->material->description,
                                        'files' => $item->material->files->map(function ($file) {
                                            return [
                                                'id' => $file->id,
                                                'file_name' => $file->file_name,
                                                'file_path' => $file->file_path,
                                                'file_url' => $file->file_url,
                                                'viewer_url' => $file->viewer_url,
                                                'file_type' => $file->file_type,
                                                'file_mime_type' => $file->file_mime_type,
                                                'file_size' => $file->file_size,
                                                'file_size_formatted' => $file->file_size_formatted,
                                                'is_primary' => $file->is_primary
                                            ];
                                        }),
                                        'primary_file' => $item->material->files->where('is_primary', true)->first() ? [
                                            'id' => $item->material->files->where('is_primary', true)->first()->id,
                                            'file_name' => $item->material->files->where('is_primary', true)->first()->file_name,
                                            'file_path' => $item->material->files->where('is_primary', true)->first()->file_path,
                                            'file_url' => $item->material->files->where('is_primary', true)->first()->file_url,
                                            'viewer_url' => $item->material->files->where('is_primary', true)->first()->viewer_url,
                                            'file_type' => $item->material->files->where('is_primary', true)->first()->file_type,
                                            'file_mime_type' => $item->material->files->where('is_primary', true)->first()->file_mime_type,
                                            'file_size' => $item->material->files->where('is_primary', true)->first()->file_size,
                                            'file_size_formatted' => $item->material->files->where('is_primary', true)->first()->file_size_formatted,
                                            'is_primary' => true
                                        ] : null
                                    ];
                                }

                                return $itemData;
                            })
                        ];
                    });
                } else {
                    $training->sessions = [];
                }
            });

            return response()->json([
                'success' => true,
                'completed_trainings' => $completedTrainings
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching training history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat riwayat training'
            ], 500);
        }
    }

    public function updateTrainingStatus(Request $request, $id)
    {
        try {
            \Log::info('=== UPDATE TRAINING STATUS METHOD CALLED ===', [
                'training_id' => $id,
                'new_status' => $request->status,
                'user_id' => auth()->id()
            ]);

            $request->validate([
                'status' => 'required|in:scheduled,published,ongoing,completed,cancelled'
            ]);

            $training = TrainingSchedule::findOrFail($id);
            
            // Skip permission check - allow all authenticated users to change status
            $course = $training->course; // Still need this for logging
            // if (!$this->canManageTraining($course)) {
            //     return back()->withErrors(['error' => 'Anda tidak memiliki izin untuk mengubah status training ini']);
            // }

            $oldStatus = $training->status;
            $newStatus = $request->status;

            // Update the training status
            $training->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

            // Auto-generate certificates when training is completed
            if ($newStatus === 'completed' && $course->certificate_template_id) {
                $this->autoGenerateCertificates($training, $course);
            }

            // Log the status change
            \Log::info("Training status updated", [
                'training_id' => $id,
                'course_title' => $course->title,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => auth()->user()->nama_lengkap
            ]);

            $statusText = match($newStatus) {
                'scheduled' => 'Terjadwal',
                'published' => 'Dipublikasi',
                'ongoing' => 'Sedang Berlangsung',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan',
                default => 'Tidak Diketahui'
            };

            return back()->with('success', "Status training berhasil diubah menjadi '{$statusText}'");

        } catch (\Exception $e) {
            \Log::error('Error updating training status: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Gagal mengubah status training: ' . $e->getMessage()]);
        }
    }

    public function getTrainingReviews($id)
    {
        try {
            $training = TrainingSchedule::with(['course', 'outlet'])->findOrFail($id);
            
            // Check if user can manage this training
            $course = $training->course;
            if (!$this->canManageTraining($course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melihat review training ini'
                ], 403);
            }

            // Get all reviews for this training with user details
            $reviews = DB::table('training_reviews')
                ->join('users', 'training_reviews.user_id', '=', 'users.id')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('training_schedule_trainers', function($join) {
                    $join->on('training_reviews.training_schedule_id', '=', 'training_schedule_trainers.schedule_id')
                         ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                })
                ->leftJoin('users as trainers', 'training_schedule_trainers.trainer_id', '=', 'trainers.id')
                ->where('training_reviews.training_schedule_id', $id)
                ->select(
                    'training_reviews.id as review_id',
                    'training_reviews.training_rating',
                    'training_reviews.overall_satisfaction',
                    'training_reviews.training_feedback',
                    'training_reviews.trainer_rating',
                    'training_reviews.trainer_feedback',
                    'training_reviews.improvement_suggestions',
                    'training_reviews.created_at as review_date',
                    'users.id as user_id',
                    'users.nama_lengkap as user_name',
                    'users.email as user_email',
                    'tbl_data_jabatan.nama_jabatan',
                    'tbl_data_divisi.nama_divisi',
                    'trainers.nama_lengkap as trainer_name',
                    'training_schedule_trainers.external_trainer_name'
                )
                ->orderBy('training_reviews.created_at', 'desc')
                ->get();

            // Process trainer data for each review
            $reviews->each(function ($review) {
                if ($review->trainer_name) {
                    $review->trainer_name_final = $review->trainer_name;
                } elseif ($review->external_trainer_name) {
                    $review->trainer_name_final = $review->external_trainer_name;
                } else {
                    $review->trainer_name_final = 'Trainer tidak tersedia';
                }
            });

            // Calculate statistics
            $totalReviews = $reviews->count();
            $averageTrainingRating = $totalReviews > 0 ? round($reviews->avg('training_rating'), 2) : 0;
            $averageTrainerRating = $totalReviews > 0 ? round($reviews->avg('trainer_rating'), 2) : 0;
            $averageSatisfaction = $totalReviews > 0 ? round($reviews->avg('overall_satisfaction'), 2) : 0;

            return response()->json([
                'success' => true,
                'training' => [
                    'id' => $training->id,
                    'course_title' => $training->course->title,
                    'scheduled_date' => $training->scheduled_date,
                    'start_time' => $training->start_time,
                    'end_time' => $training->end_time,
                    'outlet_name' => $training->outlet->nama_outlet ?? 'Venue tidak ditentukan'
                ],
                'reviews' => $reviews,
                'statistics' => [
                    'total_reviews' => $totalReviews,
                    'average_training_rating' => $averageTrainingRating,
                    'average_trainer_rating' => $averageTrainerRating,
                    'average_satisfaction' => $averageSatisfaction
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching training reviews: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat review training'
            ], 500);
        }
    }

    /**
     * Get trainer ratings for a specific training
     */
    public function getTrainerRatings($id)
    {
        try {
            $training = TrainingSchedule::with(['course', 'outlet'])->findOrFail($id);
            
            // Check if user can manage this training
            $course = $training->course;
            if (!$this->canManageTraining($course)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk melihat rating trainer ini'
                ], 403);
            }

            // Get trainer ratings specifically
            $trainerRatings = DB::table('training_reviews')
                ->join('users', 'training_reviews.user_id', '=', 'users.id')
                ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                ->leftJoin('training_schedule_trainers', function($join) {
                    $join->on('training_reviews.training_schedule_id', '=', 'training_schedule_trainers.schedule_id')
                         ->where('training_schedule_trainers.trainer_type', '=', 'internal');
                })
                ->leftJoin('users as trainers', 'training_schedule_trainers.trainer_id', '=', 'trainers.id')
                ->where('training_reviews.training_schedule_id', $id)
                ->whereNotNull('training_reviews.trainer_rating')
                ->select(
                    'training_reviews.id as review_id',
                    'training_reviews.trainer_rating',
                    'training_reviews.trainer_feedback',
                    'training_reviews.created_at as review_date',
                    'users.id as user_id',
                    'users.nama_lengkap as user_name',
                    'users.email as user_email',
                    'tbl_data_jabatan.nama_jabatan',
                    'tbl_data_divisi.nama_divisi',
                    'trainers.nama_lengkap as trainer_name',
                    'training_schedule_trainers.external_trainer_name'
                )
                ->orderBy('training_reviews.created_at', 'desc')
                ->get();

            // Process trainer data for each rating
            $trainerRatings->each(function ($rating) {
                if ($rating->trainer_name) {
                    $rating->trainer_name_final = $rating->trainer_name;
                } elseif ($rating->external_trainer_name) {
                    $rating->trainer_name_final = $rating->external_trainer_name;
                } else {
                    $rating->trainer_name_final = 'Trainer tidak tersedia';
                }
            });

            // Calculate statistics
            $totalRatings = $trainerRatings->count();
            $averageTrainerRating = $totalRatings > 0 ? round($trainerRatings->avg('trainer_rating'), 2) : 0;

            // Calculate rating distribution
            $ratingDistribution = [];
            for ($i = 1; $i <= 5; $i++) {
                $count = $trainerRatings->where('trainer_rating', $i)->count();
                $percentage = $totalRatings > 0 ? round(($count / $totalRatings) * 100, 1) : 0;
                $ratingDistribution[] = [
                    'rating' => $i,
                    'count' => $count,
                    'percentage' => $percentage
                ];
            }

            return response()->json([
                'success' => true,
                'training' => [
                    'id' => $training->id,
                    'course_title' => $training->course->title,
                    'scheduled_date' => $training->scheduled_date,
                    'start_time' => $training->start_time,
                    'end_time' => $training->end_time,
                    'outlet_name' => $training->outlet->nama_outlet ?? 'Venue tidak ditentukan'
                ],
                'trainer_ratings' => $trainerRatings,
                'statistics' => [
                    'total_ratings' => $totalRatings,
                    'average_trainer_rating' => $averageTrainerRating,
                    'rating_distribution' => $ratingDistribution
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching trainer ratings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat rating trainer'
            ], 500);
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
            // Exclude training that has been reviewed (completed training)
            $participantInvitations = DB::table('training_invitations')
                ->join('training_schedules', 'training_invitations.schedule_id', '=', 'training_schedules.id')
                ->join('lms_courses', 'training_schedules.course_id', '=', 'lms_courses.id')
                ->leftJoin('tbl_data_outlet', 'training_schedules.outlet_id', '=', 'tbl_data_outlet.id_outlet')
                ->leftJoin('training_reviews', function($join) use ($userId) {
                    $join->on('training_reviews.training_schedule_id', '=', 'training_schedules.id')
                         ->where('training_reviews.user_id', $userId);
                })
                ->where('training_invitations.user_id', $userId)
                ->whereIn('training_invitations.status', ['invited', 'attended'])
                ->where('training_schedules.scheduled_date', '>=', now()->toDateString())
                ->whereNull('training_invitations.check_out_time') // Only exclude after checkout
                ->select(
                    'training_invitations.id as invitation_id',
                    'training_schedules.id as schedule_id',
                    'training_schedules.scheduled_date',
                    'training_schedules.start_time',
                    'training_schedules.end_time',
                    'training_schedules.status as training_status',
                    'lms_courses.title as course_title',
                    'lms_courses.id as course_id',
                    'tbl_data_outlet.nama_outlet',
                    'training_invitations.status',
                    'training_invitations.check_in_time',
                    'training_invitations.check_out_time',
                    'training_invitations.created_at as invited_at',
                    'training_reviews.id as review_id',
                    'training_reviews.created_at as review_created_at'
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
                    'training_schedules.status as training_status',
                    'lms_courses.title as course_title',
                    'lms_courses.id as course_id',
                    'tbl_data_outlet.nama_outlet',
                    'training_schedule_trainers.is_primary_trainer',
                    'training_schedule_trainers.created_at as assigned_at'
                )
                ->orderBy('training_schedules.scheduled_date')
                ->get();

            \Log::info('Participant invitations query result', [
                'count' => $participantInvitations->count(),
                'invitations' => $participantInvitations->map(function($inv) {
                    return [
                        'id' => $inv->invitation_id,
                        'schedule_id' => $inv->schedule_id,
                        'status' => $inv->status,
                        'check_in_time' => $inv->check_in_time,
                        'check_out_time' => $inv->check_out_time
                    ];
                })
            ]);

            // Format the data
            $notifications = collect();

            // Add participant invitations
            foreach ($participantInvitations as $invitation) {
                \Log::info('Processing participant invitation', [
                    'invitation_id' => $invitation->invitation_id,
                    'schedule_id' => $invitation->schedule_id,
                    'status' => $invitation->status,
                    'check_in_time' => $invitation->check_in_time,
                    'check_out_time' => $invitation->check_out_time,
                    'is_checked_in' => !is_null($invitation->check_in_time) && is_null($invitation->check_out_time)
                ]);
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
                        ->leftJoin('lms_curriculum_materials', 'lms_session_items.item_id', '=', 'lms_curriculum_materials.id')
                        ->where('lms_session_items.session_id', $session->id)
                        ->where('lms_session_items.status', 'active')
                        ->orderBy('lms_session_items.order_number')
                        ->select(
                            'lms_session_items.id', 
                            'lms_session_items.item_type', 
                            'lms_session_items.item_id', 
                            'lms_session_items.title', 
                            'lms_session_items.description', 
                            'lms_session_items.estimated_duration_minutes', 
                            'lms_session_items.is_required', 
                            'lms_session_items.order_number', 
                            'lms_session_items.passing_score', 
                            'lms_session_items.max_attempts',
                            'lms_curriculum_materials.quiz_id'
                        )
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
                        if ($item->item_type === 'quiz' && $item->quiz_id) {
                            $item->quiz = $this->getQuizData($item->quiz_id, $userId);
                            
                            // Add completion status
                            $item->is_completed = $this->isItemCompleted($userId, $invitation->schedule_id, $item);
                            $item->completion_status = $this->getQuizCompletionStatus($userId, $item->quiz_id);
                        }
                        
                        // Fetch material data if item is a material
                        if ($item->item_type === 'material' && $item->item_id) {
                            $item->material = $this->getMaterialData($item->item_id, $userId);
                            
                            // Add completion status for material
                            $item->is_completed = $this->isItemCompleted($userId, $invitation->schedule_id, $item);
                            $item->completion_status = $this->getMaterialCompletionStatus($userId, $item->item_id, $invitation->schedule_id);
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
                    'training_status' => $invitation->training_status,
                    'check_in_time' => $invitation->check_in_time,
                    'check_out_time' => $invitation->check_out_time,
                    'is_checked_in' => !is_null($invitation->check_in_time) && is_null($invitation->check_out_time),
                    'created_at' => $invitation->invited_at,
                    'role' => 'Peserta',
                    'sessions' => $sessions,
                    'all_completed' => $allCompleted,
                    'trainers' => $trainers,
                    'can_give_feedback' => $this->canGiveFeedback($userId, $invitation->schedule_id),
                    'review_id' => $invitation->review_id,
                    'review_created_at' => $invitation->review_created_at
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
                    'training_status' => $assignment->training_status,
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

        \Log::info('canAccessSession check', [
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'session_index' => $sessionIndex,
            'check_in_found' => $checkIn ? true : false,
            'check_in_time' => $checkIn->check_in_time ?? null
        ]);

        if (!$checkIn) {
            \Log::info('Session access denied - no check-in found');
            return false; // Must check in first
        }

        // First session is always accessible after check-in
        if ($sessionIndex === 0) {
            \Log::info('Session access granted - first session after check-in');
            return true;
        }

        // For now, all sessions after check-in are accessible
        // TODO: Implement session-level prerequisite logic when progress tracking is available
        \Log::info('Session access granted - all sessions accessible after check-in');
        return true;
    }

    /**
     * Check if user can access a specific item within a session
     */
    private function canAccessItem($userId, $scheduleId, $sessionId, $itemId, $sessionItems, $itemIndex, $sessionCanAccess)
    {
        // If session can't be accessed, item can't be accessed
        if (!$sessionCanAccess) {
            \Log::info('Item access denied - session not accessible', [
                'user_id' => $userId,
                'schedule_id' => $scheduleId,
                'session_id' => $sessionId,
                'item_id' => $itemId,
                'item_index' => $itemIndex
            ]);
            return false;
        }

        // First item in first session is always accessible after check-in
        if ($itemIndex === 0) {
            \Log::info('Item access granted - first item after check-in', [
                'user_id' => $userId,
                'schedule_id' => $scheduleId,
                'session_id' => $sessionId,
                'item_id' => $itemId,
                'item_index' => $itemIndex
            ]);
            return true;
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
                \Log::info('Item access denied - previous required item not completed', [
                    'user_id' => $userId,
                    'schedule_id' => $scheduleId,
                    'session_id' => $sessionId,
                    'item_id' => $itemId,
                    'item_index' => $itemIndex,
                    'previous_item_id' => $previousItem->id
                ]);
                return false;
            }
        }

        \Log::info('Item access granted - all prerequisites met', [
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'session_id' => $sessionId,
            'item_id' => $itemId,
            'item_index' => $itemIndex
        ]);
        return true;
    }

    /**
     * Check if a session item is completed by the user
     */
    private function isItemCompleted($userId, $scheduleId, $item)
    {
        switch ($item->item_type) {
            case 'quiz':
                // Use quiz_id from the join, fallback to item_id if not available
                $quizId = $item->quiz_id ?? $item->item_id;
                return $this->isQuizCompleted($userId, $quizId);
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
            
        \Log::info('Checking quiz completion', [
            'user_id' => $userId,
            'quiz_id' => $quizId,
            'attempt_found' => $attempt ? 'yes' : 'no',
            'attempt_status' => $attempt ? $attempt->status : 'null',
            'attempt_passed' => $attempt ? $attempt->is_passed : 'null',
            'attempt_score' => $attempt ? $attempt->score : 'null'
        ]);
            
        return $attempt !== null;
    }

    /**
     * Get quiz completion status with details
     */
    private function getQuizCompletionStatus($userId, $quizId)
    {
        $attempt = DB::table('lms_quiz_attempts')
            ->where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();
            
        if (!$attempt) {
            return null;
        }
        
        return [
            'attempt_id' => $attempt->id,
            'score' => $attempt->score,
            'is_passed' => $attempt->is_passed,
            'completed_at' => $attempt->completed_at,
            'attempt_number' => $attempt->attempt_number
        ];
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
        return \App\Models\MaterialCompletion::where('user_id', $userId)
                                            ->where('material_id', $materialId)
                                            ->exists();
    }

    /**
     * Get quiz data with questions and options for a specific quiz
     */
    private function getQuizData($quizId, $userId)
    {
        try {
            \Log::info('getQuizData called', [
                'quiz_id' => $quizId,
                'user_id' => $userId
            ]);

            // Get quiz basic info
            $quiz = DB::table('lms_quizzes')
                ->where('id', $quizId)
                ->where('status', 'published')
                ->select('id', 'title', 'description', 'instructions', 'time_limit_type', 'time_limit_minutes', 'time_per_question_seconds', 'passing_score', 'max_attempts', 'is_randomized', 'show_results')
                ->first();

            \Log::info('Quiz query result', [
                'quiz_id' => $quizId,
                'quiz_found' => $quiz ? 'yes' : 'no',
                'quiz_title' => $quiz ? $quiz->title : null,
                'quiz_status' => $quiz ? 'published' : 'not found or not published'
            ]);

            if (!$quiz) {
                \Log::warning('Quiz not found or not published', [
                    'quiz_id' => $quizId,
                    'user_id' => $userId
                ]);
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
     * Get material data with files for a specific material
     */
    private function getMaterialData($materialId, $userId)
    {
        try {
            \Log::info('getMaterialData called', [
                'material_id' => $materialId,
                'user_id' => $userId
            ]);

            // Get material basic info
            $material = DB::table('lms_curriculum_materials')
                ->where('id', $materialId)
                ->where('status', 'active')
                ->select('id', 'title', 'description', 'estimated_duration_minutes')
                ->first();

            if (!$material) {
                \Log::warning('Material not found or not active', [
                    'material_id' => $materialId,
                    'user_id' => $userId
                ]);
                return null;
            }

            // Get material files
            $files = DB::table('lms_curriculum_material_files')
                ->where('material_id', $materialId)
                ->where('status', 'active')
                ->orderBy('order_number')
                ->select('id', 'file_path', 'file_name', 'file_size', 'file_mime_type', 'file_type', 'order_number', 'is_primary')
                ->get();

            // Add file URLs
            foreach ($files as $file) {
                $file->file_url = $file->file_path ? asset('storage/' . $file->file_path) : null;
                $file->viewer_url = route('lms.material.view', ['materialId' => $materialId, 'fileId' => $file->id]);
                $file->file_size_formatted = $this->formatFileSize($file->file_size);
            }

            $material->files = $files;
            $material->primary_file = $files->where('is_primary', true)->first() ?? $files->first();

            \Log::info('Material data fetched successfully', [
                'material_id' => $materialId,
                'files_count' => $files->count(),
                'primary_file_type' => $material->primary_file ? $material->primary_file->file_type : 'none'
            ]);

            return $material;
        } catch (\Exception $e) {
            \Log::error('Error fetching material data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes)
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
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
            ->where('training_schedule_trainers.schedule_id', $scheduleId)
            ->whereIn('training_schedule_trainers.status', ['invited', 'active', 'confirmed'])
            ->select(
                'training_schedule_trainers.id',
                'training_schedule_trainers.trainer_id',
                'training_schedule_trainers.trainer_type',
                'training_schedule_trainers.external_trainer_name',
                'training_schedule_trainers.external_trainer_email',
                'training_schedule_trainers.external_trainer_phone',
                'training_schedule_trainers.external_trainer_company',
                'training_schedule_trainers.is_primary_trainer',
                'training_schedule_trainers.hours_taught',
                'training_schedule_trainers.start_time',
                'training_schedule_trainers.end_time',
                'training_schedule_trainers.notes'
            )
            ->get();

        // Get internal trainer details
        foreach ($trainers as $trainer) {
            if ($trainer->trainer_type === 'internal' && $trainer->trainer_id) {
                $user = DB::table('users')
            ->leftJoin('tbl_data_jabatan', 'users.id_jabatan', '=', 'tbl_data_jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi', 'users.division_id', '=', 'tbl_data_divisi.id')
                    ->where('users.id', $trainer->trainer_id)
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'tbl_data_jabatan.nama_jabatan',
                        'tbl_data_divisi.nama_divisi'
                    )
                    ->first();

                if ($user) {
                    $trainer->trainer_name = $user->nama_lengkap;
                    $trainer->trainer_email = $user->email;
                    $trainer->trainer_jabatan = $user->nama_jabatan;
                    $trainer->trainer_divisi = $user->nama_divisi;
                }
            } else if ($trainer->trainer_type === 'external') {
                $trainer->trainer_name = $trainer->external_trainer_name;
                $trainer->trainer_email = $trainer->external_trainer_email;
                $trainer->trainer_phone = $trainer->external_trainer_phone;
                $trainer->trainer_company = $trainer->external_trainer_company;
            }
        }

        return $trainers;
    }

    /**
     * Get training sessions for a course with user progress
     */
    private function getTrainingSessions($courseId, $userId, $scheduleId = null)
    {
        // Get course sessions with items
        $sessions = DB::table('lms_sessions')
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->orderBy('order_number')
            ->select('id', 'session_title', 'session_description', 'estimated_duration_minutes', 'is_required', 'order_number')
            ->get();

        // Get session items for each session with prerequisite logic
        foreach ($sessions as $sessionIndex => $session) {
            $sessionItems = DB::table('lms_session_items')
                ->leftJoin('lms_curriculum_materials', 'lms_session_items.item_id', '=', 'lms_curriculum_materials.id')
                ->where('lms_session_items.session_id', $session->id)
                ->where('lms_session_items.status', 'active')
                ->orderBy('lms_session_items.order_number')
                ->select(
                    'lms_session_items.id', 
                    'lms_session_items.item_type', 
                    'lms_session_items.item_id', 
                    'lms_session_items.title', 
                    'lms_session_items.description', 
                    'lms_session_items.estimated_duration_minutes', 
                    'lms_session_items.is_required', 
                    'lms_session_items.order_number', 
                    'lms_session_items.passing_score', 
                    'lms_session_items.max_attempts',
                    'lms_curriculum_materials.quiz_id'
                )
                ->get();
            
            // Debug: Log session items after join
            \Log::info('Session items after join with curriculum materials', [
                'session_id' => $session->id,
                'session_title' => $session->session_title,
                'items_count' => $sessionItems->count(),
                'items' => $sessionItems->toArray()
            ]);
            
            // Check if session can be accessed (prerequisite logic)
            if ($scheduleId) {
                $session->can_access = $this->canAccessSession($userId, $scheduleId, $sessionIndex, $sessions);
            } else {
                $session->can_access = true; // Default to accessible if no schedule ID
            }
            $session->progress = null; // Progress tracking not implemented yet
            
            // Get access control for each item and fetch quiz data if needed
            foreach ($sessionItems as $itemIndex => $item) {
                // Check if item can be accessed (prerequisite logic)
                if ($scheduleId) {
                    $item->can_access = $this->canAccessItem($userId, $scheduleId, $session->id, $item->id, $sessionItems, $itemIndex, $session->can_access);
                } else {
                    $item->can_access = $session->can_access; // Default to session access
                }
                $item->progress = null; // Progress tracking not implemented yet
                
                // Debug: Log item structure before processing
                \Log::info('Processing session item', [
                    'item_id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_title' => $item->title,
                    'curriculum_material_id' => $item->item_id,
                    'quiz_id_from_join' => $item->quiz_id ?? 'null',
                    'has_quiz_id' => isset($item->quiz_id) ? 'yes' : 'no'
                ]);
                
                // Fetch quiz data if item is a quiz
                if ($item->item_type === 'quiz' && $item->quiz_id) {
                    \Log::info('Fetching quiz data for item in getTrainingSessions', [
                        'item_id' => $item->id,
                        'item_type' => $item->item_type,
                        'curriculum_material_id' => $item->item_id,
                        'quiz_id' => $item->quiz_id,
                        'user_id' => $userId,
                        'schedule_id' => $scheduleId
                    ]);
                    $item->quiz = $this->getQuizData($item->quiz_id, $userId);
                    
                    // Add completion status
                    $item->is_completed = $this->isItemCompleted($userId, $scheduleId, $item);
                    $item->completion_status = $this->getQuizCompletionStatus($userId, $item->quiz_id);
                    
                    \Log::info('Quiz data result in getTrainingSessions', [
                        'item_id' => $item->id,
                        'quiz_id' => $item->quiz_id,
                        'quiz_data' => $item->quiz ? 'found' : 'null',
                        'is_completed' => $item->is_completed,
                        'completion_status' => $item->completion_status
                    ]);
                } else if ($item->item_type === 'quiz' && !$item->quiz_id) {
                    \Log::warning('Quiz item found but no quiz_id from join', [
                        'item_id' => $item->id,
                        'item_type' => $item->item_type,
                        'curriculum_material_id' => $item->item_id,
                        'quiz_id' => $item->quiz_id ?? 'null'
                    ]);
                }
                
                // Fetch material data if item is a material
                if ($item->item_type === 'material' && $item->item_id) {
                    $item->material = $this->getMaterialData($item->item_id, $userId);
                    
                    // Add completion status for material
                    $item->is_completed = $this->isItemCompleted($userId, $assignment->schedule_id, $item);
                    $item->completion_status = $this->getMaterialCompletionStatus($userId, $item->item_id, $assignment->schedule_id);
                }
                
                // Fetch questionnaire data if item is a questionnaire
                if ($item->item_type === 'questionnaire' && $item->item_id) {
                    $item->questionnaire = $this->getQuestionnaireData($item->item_id, $userId);
                }
            }
            
            $session->items = $sessionItems;
        }

        return $sessions;
    }

    /**
     * Get material completion status for a user
     */
    private function getMaterialCompletionStatus($userId, $materialId, $scheduleId)
    {
        try {
            $completion = DB::table('material_completions')
                ->where('user_id', $userId)
                ->where('material_id', $materialId)
                ->where('schedule_id', $scheduleId)
                ->first();

            if ($completion) {
                return [
                    'completed_at' => $completion->completed_at,
                    'time_spent_seconds' => $completion->time_spent_seconds,
                    'completion_data' => $completion->completion_data ? json_decode($completion->completion_data, true) : null
                ];
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Error getting material completion status', [
                'user_id' => $userId,
                'material_id' => $materialId,
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if user can give feedback
     */
    private function canGiveFeedback($userId, $scheduleId)
    {
        // Check if user has already given review
        return !TrainingReview::hasReviewed($scheduleId, $userId);
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

    /**
     * View material file with proper headers
     */
    public function viewMaterialFile(Request $request, $materialId, $fileId)
    {
        try {
            // Get file information
            $file = DB::table('lms_curriculum_material_files')
                ->where('id', $fileId)
                ->where('material_id', $materialId)
                ->where('status', 'active')
                ->first();

            if (!$file) {
                abort(404, 'File not found');
            }

            // Check if file exists
            $filePath = storage_path('app/public/' . $file->file_path);
            if (!file_exists($filePath)) {
                abort(404, 'File not found on disk');
            }

            // Set appropriate headers based on file type
            $headers = [];
            
            switch ($file->file_type) {
                case 'pdf':
                    $headers = [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                case 'video':
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'video/mp4',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Accept-Ranges' => 'bytes',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                case 'image':
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'image/jpeg',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                case 'docx':
                case 'doc':
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                case 'xlsx':
                case 'xls':
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                case 'pptx':
                case 'ppt':
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'Content-Disposition' => 'inline; filename="' . $file->file_name . '"',
                        'Cache-Control' => 'public, max-age=3600',
                    ];
                    break;
                default:
                    $headers = [
                        'Content-Type' => $file->file_mime_type ?: 'application/octet-stream',
                        'Content-Disposition' => 'attachment; filename="' . $file->file_name . '"',
                    ];
                    break;
            }

            return response()->file($filePath, $headers);
            
        } catch (\Exception $e) {
            \Log::error('Error viewing material file: ' . $e->getMessage());
            abort(500, 'Error loading file');
        }
    }

    /**
     * Submit training review/feedback
     */
    public function submitReview(Request $request)
    {
        $request->validate([
            'training_schedule_id' => 'required|exists:training_schedules,id',
            'trainer_id' => 'nullable|exists:users,id',
            // Trainer ratings
            'trainer_mastery' => 'required|integer|min:1|max:5',
            'trainer_language' => 'required|integer|min:1|max:5',
            'trainer_intonation' => 'required|integer|min:1|max:5',
            'trainer_presentation' => 'required|integer|min:1|max:5',
            'trainer_qna' => 'required|integer|min:1|max:5',
            // Training material ratings
            'material_benefit' => 'required|integer|min:1|max:5',
            'material_clarity' => 'required|integer|min:1|max:5',
            'material_display' => 'required|integer|min:1|max:5',
            'material_suggestions' => 'nullable|string|max:1000',
            'material_needs' => 'nullable|string|max:1000',
        ]);

        $userId = auth()->id();
        $trainingScheduleId = $request->training_schedule_id;

        // Check if user has already reviewed this training
        if (TrainingReview::hasReviewed($trainingScheduleId, $userId)) {
            return redirect()->back()->withErrors(['error' => 'Anda sudah memberikan review untuk training ini']);
        }

        // Check if user has checked in to this training (attended status)
        $invitation = TrainingInvitation::where('schedule_id', $trainingScheduleId)
                                      ->where('user_id', $userId)
                                      ->where('status', 'attended')
                                      ->whereNotNull('check_in_time')
                                      ->first();

        if (!$invitation) {
            return redirect()->back()->withErrors(['error' => 'Anda harus check-in terlebih dahulu sebelum memberikan review']);
        }

        try {
            // Calculate average ratings for legacy fields
            $trainerAvg = ($request->trainer_mastery + $request->trainer_language + 
                          $request->trainer_intonation + $request->trainer_presentation + 
                          $request->trainer_qna) / 5;
            
            $materialAvg = ($request->material_benefit + $request->material_clarity + 
                           $request->material_display) / 3;
            
            $overallAvg = ($trainerAvg + $materialAvg) / 2;

            $review = TrainingReview::create([
                'training_schedule_id' => $trainingScheduleId,
                'user_id' => $userId,
                'trainer_id' => $request->trainer_id,
                // Legacy fields with calculated values
                'training_rating' => round($overallAvg),
                'trainer_rating' => round($trainerAvg),
                'overall_satisfaction' => round($overallAvg),
                'would_recommend' => $overallAvg >= 4 ? 1 : 0,
                // Trainer ratings
                'trainer_mastery' => $request->trainer_mastery,
                'trainer_language' => $request->trainer_language,
                'trainer_intonation' => $request->trainer_intonation,
                'trainer_presentation' => $request->trainer_presentation,
                'trainer_qna' => $request->trainer_qna,
                // Training material ratings
                'material_benefit' => $request->material_benefit,
                'material_clarity' => $request->material_clarity,
                'material_display' => $request->material_display,
                'material_suggestions' => $request->material_suggestions,
                'material_needs' => $request->material_needs,
            ]);

            \Log::info('Training review submitted', [
                'review_id' => $review->id,
                'training_schedule_id' => $trainingScheduleId,
                'user_id' => $userId,
                'trainer_id' => $request->trainer_id,
                'trainer_mastery' => $request->trainer_mastery,
                'trainer_language' => $request->trainer_language,
                'trainer_intonation' => $request->trainer_intonation,
                'trainer_presentation' => $request->trainer_presentation,
                'trainer_qna' => $request->trainer_qna,
                'material_benefit' => $request->material_benefit,
                'material_clarity' => $request->material_clarity,
                'material_display' => $request->material_display,
            ]);

            return redirect()->back()->with('success', 'Review berhasil disubmit. Terima kasih atas feedback Anda!');

        } catch (\Exception $e) {
            \Log::error('Error submitting training review: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan saat menyimpan review']);
        }
    }

    /**
     * Create calendar reminder for training schedule
     */
    private function createTrainingReminder($schedule, $userId, $role = 'participant')
    {
        try {
            // Load schedule with relationships
            $schedule->load(['course', 'outlet']);
            
            // Get user details
            $user = \App\Models\User::find($userId);
            if (!$user) {
                \Log::warning('User not found for training reminder', ['user_id' => $userId]);
                return;
            }

            // Get outlet name
            $outletName = $schedule->outlet ? $schedule->outlet->nama_outlet : 'Head Office';
            
            // Create reminder title based on role
            $title = $role === 'trainer' 
                ? "Training sebagai Trainer: {$schedule->course->title}"
                : "Training: {$schedule->course->title}";
            
            // Create reminder description
            $description = "📚 Course: {$schedule->course->title}\n";
            $description .= "📅 Tanggal: " . \Carbon\Carbon::parse($schedule->scheduled_date)->format('d F Y') . "\n";
            $description .= "⏰ Waktu: {$schedule->start_time} - {$schedule->end_time}\n";
            $description .= "🏢 Lokasi: {$outletName}\n";
            $description .= "👤 Role: " . ($role === 'trainer' ? 'Trainer' : 'Peserta') . "\n";
            
            if ($schedule->notes) {
                $description .= "📝 Catatan: {$schedule->notes}\n";
            }
            
            $description .= "\nJangan lupa untuk hadir tepat waktu!";

            // Create reminder in database
            DB::table('reminders')->insert([
                'user_id' => $userId,
                'created_by' => auth()->id(),
                'date' => $schedule->scheduled_date,
                'time' => $schedule->start_time,
                'title' => $title,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            \Log::info('Training reminder created successfully', [
                'schedule_id' => $schedule->id,
                'user_id' => $userId,
                'role' => $role,
                'title' => $title
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating training reminder', [
                'error' => $e->getMessage(),
                'schedule_id' => $schedule->id,
                'user_id' => $userId,
                'role' => $role
            ]);
        }
    }

    /**
     * Mark material as completed
     */
    public function markMaterialCompleted(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:lms_curriculum_materials,id',
            'schedule_id' => 'required|exists:training_schedules,id',
            'session_id' => 'required|exists:lms_sessions,id',
            'session_item_id' => 'required|exists:lms_session_items,id',
            'time_spent_seconds' => 'nullable|integer|min:0',
            'completion_data' => 'nullable|array'
        ]);

        $userId = auth()->id();

        try {
            // Check if user has access to this training
            $invitation = DB::table('training_invitations')
                ->where('schedule_id', $validated['schedule_id'])
                ->where('user_id', $userId)
                ->first();

            \Log::info('Material completion access check', [
                'user_id' => $userId,
                'schedule_id' => $validated['schedule_id'],
                'invitation_found' => $invitation ? 'yes' : 'no',
                'invitation_status' => $invitation ? $invitation->status : 'not_found'
            ]);

            if (!$invitation || !in_array($invitation->status, ['invited', 'attended', 'checked_in', 'checked_out'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke training ini. Status: ' . ($invitation ? $invitation->status : 'not_found')
                ], 403);
            }

            // Mark material as completed
            $completion = \App\Models\MaterialCompletion::markCompleted(
                $userId,
                $validated['material_id'],
                $validated['schedule_id'],
                $validated['session_id'],
                $validated['session_item_id'],
                $validated['time_spent_seconds'] ?? null,
                $validated['completion_data'] ?? null
            );

            \Log::info('Material marked as completed', [
                'user_id' => $userId,
                'material_id' => $validated['material_id'],
                'schedule_id' => $validated['schedule_id'],
                'session_id' => $validated['session_id'],
                'session_item_id' => $validated['session_item_id'],
                'completion_id' => $completion->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil ditandai sebagai selesai',
                'data' => [
                    'completion_id' => $completion->id,
                    'completed_at' => $completion->completed_at,
                    'time_spent_seconds' => $completion->time_spent_seconds
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to mark material as completed', [
                'user_id' => $userId,
                'material_id' => $validated['material_id'] ?? null,
                'schedule_id' => $validated['schedule_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai material sebagai selesai: ' . $e->getMessage()
            ], 500);
        }
    }

}
