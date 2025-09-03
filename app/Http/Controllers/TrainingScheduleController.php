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
            'trainer',
            'outlet',
            'invitations.user.jabatan',
            'invitations.user.divisi'
        ])
        ->whereYear('scheduled_date', $year)
        ->whereMonth('scheduled_date', $month)
        ->orderBy('scheduled_date')
        ->orderBy('start_time')
        ->get();
        
        // Debug: Log each schedule's date for debugging
        foreach ($schedules as $schedule) {
            \Log::info('Schedule ID: ' . $schedule->id . ', Date: ' . $schedule->scheduled_date . ', Year: ' . date('Y', strtotime($schedule->scheduled_date)) . ', Month: ' . date('n', strtotime($schedule->scheduled_date)));
        }

        // Check permissions for creating schedules
        $canCreateSchedule = auth()->user()->is_admin || 
                           auth()->user()->hasPermission('lms-schedules-create');

        // Get available participants (users who can be invited)
        $availableParticipants = User::where('status', 'A')
            ->with(['jabatan.level', 'divisi'])
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email', 'nik', 'id_jabatan', 'division_id']);

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
            'availableParticipants' => $availableParticipants,
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
            'trainer',
            'outlet',
            'invitations.user',
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

        $schedule->load(['course', 'trainer', 'outlet']);
        
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

            $invitedCount++;
        }

        $message = "Berhasil mengundang {$invitedCount} peserta";
        if ($alreadyInvitedCount > 0) {
            $message .= " ({$alreadyInvitedCount} sudah terdaftar sebelumnya)";
        }

        return back()->with('success', $message);
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
        $request->validate([
            'qr_code' => 'required|string'
        ]);

        $invitation = TrainingInvitation::validateQRCode($request->qr_code);

        if (!$invitation) {
            return back()->withErrors(['error' => 'QR Code tidak valid']);
        }

        if (!$invitation->canCheckIn()) {
            return back()->withErrors(['error' => 'Tidak dapat check-in saat ini']);
        }

        if ($invitation->checkIn()) {
            return back()->with([
                'success' => 'Check-in berhasil untuk ' . $invitation->user->nama_lengkap,
                'participant' => $invitation->user->nama_lengkap,
                'training' => $invitation->schedule->course->title
            ]);
        }

        return back()->withErrors(['error' => 'Check-in gagal']);
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

            $schedule->load(['course.certificateTemplate', 'invitations.user']);

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
}
