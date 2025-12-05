<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSurvey;
use App\Models\EmployeeSurveyResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class EmployeeSurveyController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeSurvey::with(['surveyor', 'responses']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('jabatan', 'like', "%{$search}%")
                  ->orWhere('divisi', 'like', "%{$search}%")
                  ->orWhere('outlet', 'like', "%{$search}%")
                  ->orWhereHas('surveyor', function ($userQuery) use ($search) {
                      $userQuery->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('survey_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('survey_date', '<=', $request->get('date_to'));
        }

        // Per page
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [5, 10, 15, 25, 50]) ? $perPage : 10;

        $surveys = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('EmployeeSurvey/Index', [
            'surveys' => $surveys,
            'user' => auth()->user(),
            'filters' => $request->only(['search', 'status', 'per_page', 'date_from', 'date_to'])
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        
        // Get user details with joins
        $userDetails = User::select(
                'users.id',
                'users.nama_lengkap',
                'users.id_jabatan',
                'users.division_id',
                'users.id_outlet',
                'jabatan.nama_jabatan',
                'divisi.nama_divisi',
                'outlet.nama_outlet'
            )
            ->leftJoin('tbl_data_jabatan as jabatan', 'users.id_jabatan', '=', 'jabatan.id_jabatan')
            ->leftJoin('tbl_data_divisi as divisi', 'users.division_id', '=', 'divisi.id')
            ->leftJoin('tbl_data_outlet as outlet', 'users.id_outlet', '=', 'outlet.id_outlet')
            ->where('users.id', $user->id)
            ->first();

        // Survey questions structure
        $surveyQuestions = [
            'KEPUASAN & KETERLIBATAN KERJA' => [
                'Secara keseluruhan, saya puas dengan pekerjaan saya saat ini.',
                'Saya merasa termotivasi untuk melakukan yang terbaik setiap hari.',
                'Saya akan merekomendasikan perusahaan ini sebagai tempat kerja yang baik kepada teman atau keluarga.',
                'Saya melihat diri saya masih bekerja di perusahaan ini dalam dua tahun ke depan.',
                'Saya ditempatkan pada posisi yang sesuai dengan keahlian dan kemampuan saya.',
                'Saya mengerti tugas dan tanggungjawab saya, serta apa yang diharapkan dari saya.',
                'Jam kerja yang berlaku saat ini sudah cukup dan sesuai dengan jadwal yang bisa saya ikuti.',
                'Saya memahami hubungan antara pekerjaan yang saya lakukan dengan visi dan misi perusahaan.'
            ],
            'MANAJEMEN & KEPEMIMPINAN' => [
                'Atasan saya memberikan umpan balik yang konstruktif dan tepat waktu.',
                'Atasan saya memperlakukan semua anggota tim dengan adil dan hormat.',
                'Saya merasa nyaman untuk menyampaikan kekhawatiran atau ide kepada atasan saya.',
                'Saya diberikan arahan dalam melakukan pekerjaan saya agar saya berkembang menjadi lebih baik.',
                'Prosedur dan kebijakan perusahaan mendukung saya agar dapat bekerja dengan baik.',
                'Perusahaan memiliki struktur organisasi yang jelas untuk alur pelaporan dan evaluasi.'
            ],
            'LINGKUNGAN & BUDAYA KERJA' => [
                'Budaya perusahaan mendorong kolaborasi dan kerja tim yang positif.',
                'Saya merasa memiliki keseimbangan yang sehat antara pekerjaan dan kehidupan pribadi.',
                'Saya memiliki semua alat dan sumber daya yang diperlukan untuk melakukan pekerjaan saya secara efektif.',
                'Saya merasa dihormati dan diterima terlepas dari latar belakang saya.',
                'Saya diperlakukan secara adil dan objektif, hasil kerja saya selalu dinilai dengan teliti dan benar.',
                'Saya dapat bekerjasama secara baik dengan rekan team kerja saya.',
                'Kondisi & lingkungan kerja memungkinkan saya untuk melakukan pekerjaan saya dengan baik.'
            ],
            'PENGEMBANGAN KARIR & PERTUMBUHAN' => [
                'Perusahaan menyediakan peluang pelatihan yang memadai bagi saya.',
                'Saya memiliki pemahaman yang jelas tentang jalur karir saya di perusahaan ini.',
                'Atasan saya secara aktif mendukung tujuan pengembangan profesional saya.',
                'Ada kesempatan bagi saya untuk mendapatkan promosi jabatan.',
                'Perusahaan memberikan kesempatan untuk memperbaiki kesalahan yang telah diperbuat, dimana menurut saya itu menjadi pelajaran yang berharga.'
            ],
            'KOMPENSASI & MANFAAT' => [
                'Gaji yang saya terima sudah sesuai dengan beban tugas dan tanggungjawab saya.',
                'Saya puas dengan paket tunjangan (misalnya: asuransi, cuti) yang ditawarkan perusahaan.',
                'Kontribusi saya diakui dan dihargai secara teratur.',
                'Kebijakan mengenai lembur yang berlaku sudah sesuai dengan harapan saya.',
                'Saya memahami kebijakan perusahaan mengenai pemotongan service charge untuk penggantian kehilangan & kerusakan perusahaan.'
            ],
            'KOMUNIKASI & INFORMASI' => [
                'Informasi penting tentang perusahaan dikomunikasikan secara terbuka dan tepat waktu.',
                'Saya memahami bagaimana pekerjaan saya berkontribusi pada tujuan keseluruhan perusahaan.',
                'Perusahaan efektif dalam mendengarkan dan menanggapi saran atau kekhawatiran karyawan.',
                'Komunikasi saya dengan rekan kerja/atasan terjalin dengan baik dalam menyelesaikan masalah pekerjaan.'
            ]
        ];

        return Inertia::render('EmployeeSurvey/Create', [
            'user' => $userDetails,
            'surveyQuestions' => $surveyQuestions
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'survey_date' => 'required|date',
            'responses' => 'required|array',
            'responses.*.question_category' => 'required|string',
            'responses.*.question_text' => 'required|string',
            'responses.*.score' => 'required|integer|min:1|max:5',
            'responses.*.comment' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $user = auth()->user();
            
            // Get user details
            $userDetails = User::select(
                    'users.nama_lengkap',
                    'jabatan.nama_jabatan',
                    'divisi.nama_divisi',
                    'outlet.nama_outlet'
                )
                ->leftJoin('tbl_data_jabatan as jabatan', 'users.id_jabatan', '=', 'jabatan.id_jabatan')
                ->leftJoin('tbl_data_divisi as divisi', 'users.division_id', '=', 'divisi.id')
                ->leftJoin('tbl_data_outlet as outlet', 'users.id_outlet', '=', 'outlet.id_outlet')
                ->where('users.id', $user->id)
                ->first();

            // Create survey
            $survey = EmployeeSurvey::create([
                'surveyor_id' => $user->id,
                'surveyor_name' => $userDetails->nama_lengkap,
                'surveyor_position' => $userDetails->nama_jabatan,
                'surveyor_division' => $userDetails->nama_divisi,
                'surveyor_outlet' => $userDetails->nama_outlet,
                'survey_date' => $request->survey_date,
                'status' => 'submitted'
            ]);

            // Create responses
            foreach ($request->responses as $response) {
                EmployeeSurveyResponse::create([
                    'survey_id' => $survey->id,
                    'question_category' => $response['question_category'],
                    'question_text' => $response['question_text'],
                    'score' => $response['score'],
                    'comment' => $response['comment'] ?? null
                ]);
            }

            DB::commit();

            return redirect()->route('employee-survey.index')
                ->with('success', 'Survey berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal menyimpan survey: ' . $e->getMessage());
        }
    }

    public function show(EmployeeSurvey $employeeSurvey)
    {
        $employeeSurvey->load(['surveyor', 'responses']);
        
        return Inertia::render('EmployeeSurvey/Show', [
            'survey' => $employeeSurvey
        ]);
    }

    public function edit(EmployeeSurvey $employeeSurvey)
    {
        // Check if user can edit this survey
        if ($employeeSurvey->surveyor_id !== auth()->id() && auth()->user()->id_role !== '5af56935b011a') {
            abort(403, 'Unauthorized action.');
        }
        
        $employeeSurvey->load(['surveyor', 'responses']);
        
        // Survey questions structure (same as create)
        $surveyQuestions = [
            'KEPUASAN & KETERLIBATAN KERJA' => [
                'Secara keseluruhan, saya puas dengan pekerjaan saya saat ini.',
                'Saya merasa termotivasi untuk melakukan yang terbaik setiap hari.',
                'Saya akan merekomendasikan perusahaan ini sebagai tempat kerja yang baik kepada teman atau keluarga.',
                'Saya melihat diri saya masih bekerja di perusahaan ini dalam dua tahun ke depan.',
                'Saya ditempatkan pada posisi yang sesuai dengan keahlian dan kemampuan saya.',
                'Saya mengerti tugas dan tanggungjawab saya, serta apa yang diharapkan dari saya.',
                'Jam kerja yang berlaku saat ini sudah cukup dan sesuai dengan jadwal yang bisa saya ikuti.',
                'Saya memahami hubungan antara pekerjaan yang saya lakukan dengan visi dan misi perusahaan.'
            ],
            'MANAJEMEN & KEPEMIMPINAN' => [
                'Atasan saya memberikan umpan balik yang konstruktif dan tepat waktu.',
                'Atasan saya memperlakukan semua anggota tim dengan adil dan hormat.',
                'Saya merasa nyaman untuk menyampaikan kekhawatiran atau ide kepada atasan saya.',
                'Saya diberikan arahan dalam melakukan pekerjaan saya agar saya berkembang menjadi lebih baik.',
                'Prosedur dan kebijakan perusahaan mendukung saya agar dapat bekerja dengan baik.',
                'Perusahaan memiliki struktur organisasi yang jelas untuk alur pelaporan dan evaluasi.'
            ],
            'LINGKUNGAN & BUDAYA KERJA' => [
                'Budaya perusahaan mendorong kolaborasi dan kerja tim yang positif.',
                'Saya merasa memiliki keseimbangan yang sehat antara pekerjaan dan kehidupan pribadi.',
                'Saya memiliki semua alat dan sumber daya yang diperlukan untuk melakukan pekerjaan saya secara efektif.',
                'Saya merasa dihormati dan diterima terlepas dari latar belakang saya.',
                'Saya diperlakukan secara adil dan objektif, hasil kerja saya selalu dinilai dengan teliti dan benar.',
                'Saya dapat bekerjasama secara baik dengan rekan team kerja saya.',
                'Kondisi & lingkungan kerja memungkinkan saya untuk melakukan pekerjaan saya dengan baik.'
            ],
            'PENGEMBANGAN KARIR & PERTUMBUHAN' => [
                'Perusahaan menyediakan peluang pelatihan yang memadai bagi saya.',
                'Saya memiliki pemahaman yang jelas tentang jalur karir saya di perusahaan ini.',
                'Atasan saya secara aktif mendukung tujuan pengembangan profesional saya.',
                'Ada kesempatan bagi saya untuk mendapatkan promosi jabatan.',
                'Perusahaan memberikan kesempatan untuk memperbaiki kesalahan yang telah diperbuat, dimana menurut saya itu menjadi pelajaran yang berharga.'
            ],
            'KOMPENSASI & MANFAAT' => [
                'Gaji yang saya terima sudah sesuai dengan beban tugas dan tanggungjawab saya.',
                'Saya puas dengan paket tunjangan (misalnya: asuransi, cuti) yang ditawarkan perusahaan.',
                'Kontribusi saya diakui dan dihargai secara teratur.',
                'Kebijakan mengenai lembur yang berlaku sudah sesuai dengan harapan saya.',
                'Saya memahami kebijakan perusahaan mengenai pemotongan service charge untuk penggantian kehilangan & kerusakan perusahaan.'
            ],
            'KOMUNIKASI & INFORMASI' => [
                'Informasi penting tentang perusahaan dikomunikasikan secara terbuka dan tepat waktu.',
                'Saya memahami bagaimana pekerjaan saya berkontribusi pada tujuan keseluruhan perusahaan.',
                'Perusahaan efektif dalam mendengarkan dan menanggapi saran atau kekhawatiran karyawan.',
                'Komunikasi saya dengan rekan kerja/atasan terjalin dengan baik dalam menyelesaikan masalah pekerjaan.'
            ]
        ];

        return Inertia::render('EmployeeSurvey/Edit', [
            'survey' => $employeeSurvey,
            'surveyQuestions' => $surveyQuestions
        ]);
    }

    public function update(Request $request, EmployeeSurvey $employeeSurvey)
    {
        // Check if user can update this survey
        if ($employeeSurvey->surveyor_id !== auth()->id() && auth()->user()->id_role !== '5af56935b011a') {
            abort(403, 'Unauthorized action.');
        }
        
        $request->validate([
            'survey_date' => 'required|date',
            'responses' => 'required|array',
            'responses.*.question_category' => 'required|string',
            'responses.*.question_text' => 'required|string',
            'responses.*.score' => 'required|integer|min:1|max:5',
            'responses.*.comment' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Update survey
            $employeeSurvey->update([
                'survey_date' => $request->survey_date,
                'status' => 'submitted'
            ]);

            // Delete existing responses
            $employeeSurvey->responses()->delete();

            // Create new responses
            foreach ($request->responses as $response) {
                EmployeeSurveyResponse::create([
                    'survey_id' => $employeeSurvey->id,
                    'question_category' => $response['question_category'],
                    'question_text' => $response['question_text'],
                    'score' => $response['score'],
                    'comment' => $response['comment'] ?? null
                ]);
            }

            DB::commit();

            return redirect()->route('employee-survey.index')
                ->with('success', 'Survey berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal memperbarui survey: ' . $e->getMessage());
        }
    }

    public function destroy(EmployeeSurvey $employeeSurvey)
    {
        // Check if user can delete this survey
        if ($employeeSurvey->surveyor_id !== auth()->id() && auth()->user()->id_role !== '5af56935b011a') {
            abort(403, 'Unauthorized action.');
        }
        
        $employeeSurvey->delete();
        
        return redirect()->route('employee-survey.index')
            ->with('success', 'Survey berhasil dihapus!');
    }

    public function report(Request $request)
    {
        // Get date range filters
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        
        // Base query for surveys
        $query = EmployeeSurvey::with(['responses'])
            ->where('status', 'submitted');
            
        // Apply date filters
        if ($dateFrom) {
            $query->whereDate('survey_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('survey_date', '<=', $dateTo);
        }
        
        $surveys = $query->get();
        
        // Get all unique categories and questions
        $categories = [];
        $questions = [];
        
        foreach ($surveys as $survey) {
            foreach ($survey->responses as $response) {
                $category = $response->question_category;
                $question = $response->question_text;
                
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'name' => $category,
                        'total_surveys' => 0,
                        'total_responses' => 0,
                        'total_score' => 0,
                        'average_score' => 0,
                        'percentage' => 0,
                        'questions' => []
                    ];
                }
                
                if (!isset($questions[$category][$question])) {
                    $questions[$category][$question] = [
                        'text' => $question,
                        'total_responses' => 0,
                        'total_score' => 0,
                        'average_score' => 0,
                        'percentage' => 0,
                        'score_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
                    ];
                }
                
                // Update category stats
                $categories[$category]['total_responses']++;
                $categories[$category]['total_score'] += $response->score;
                
                // Update question stats
                $questions[$category][$question]['total_responses']++;
                $questions[$category][$question]['total_score'] += $response->score;
                $questions[$category][$question]['score_distribution'][$response->score]++;
            }
        }
        
        // Calculate averages and percentages
        foreach ($categories as $categoryName => &$category) {
            $category['total_surveys'] = $surveys->count();
            if ($category['total_responses'] > 0) {
                $category['average_score'] = round($category['total_score'] / $category['total_responses'], 2);
                $category['percentage'] = round(($category['average_score'] / 5) * 100, 1);
            }
            
            // Calculate question stats
            foreach ($questions[$categoryName] as $questionText => &$question) {
                if ($question['total_responses'] > 0) {
                    $question['average_score'] = round($question['total_score'] / $question['total_responses'], 2);
                    $question['percentage'] = round(($question['average_score'] / 5) * 100, 1);
                }
            }
            
            $category['questions'] = array_values($questions[$categoryName]);
        }
        
        // Overall statistics
        $totalSurveys = $surveys->count();
        $totalResponses = collect($categories)->sum('total_responses');
        $overallAverage = $totalResponses > 0 ? round(collect($categories)->sum('total_score') / $totalResponses, 2) : 0;
        $overallPercentage = round(($overallAverage / 5) * 100, 1);
        
        return Inertia::render('EmployeeSurvey/Report', [
            'categories' => array_values($categories),
            'totalSurveys' => $totalSurveys,
            'totalResponses' => $totalResponses,
            'overallAverage' => $overallAverage,
            'overallPercentage' => $overallPercentage,
            'filters' => $request->only(['date_from', 'date_to'])
        ]);
    }
}
