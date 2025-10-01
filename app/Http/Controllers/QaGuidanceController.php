<?php

namespace App\Http\Controllers;

use App\Models\QaGuidance;
use App\Models\QaGuidanceCategory;
use App\Models\QaGuidanceCategoryParameter;
use App\Models\QaGuidanceParameterDetail;
use App\Models\QaCategory;
use App\Models\QaParameter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QaGuidanceController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $departemen = $request->input('departemen');
        $categoryId = $request->input('category_id');
        $status = $request->input('status', 'A');
        $perPage = $request->input('per_page', 15);

        $query = QaGuidance::with([
            'guidanceCategories.category',
            'guidanceCategories.parameters.details.parameter'
        ]);
        
        // Filter by status
        if ($status === 'A') {
            $query->where('status', 'A');
        } elseif ($status === 'N') {
            $query->where('status', 'N');
        }
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('departemen', 'like', "%$search%")
                  ->orWhereHas('guidanceCategories.category', function($categoryQuery) use ($search) {
                      $categoryQuery->where('categories', 'like', "%$search%");
                  });
            });
        }
        
        if ($departemen) {
            $query->where('departemen', $departemen);
        }
        
        if ($categoryId) {
            $query->whereHas('guidanceCategories', function($categoryQuery) use ($categoryId) {
                $categoryQuery->where('category_id', $categoryId);
            });
        }
        
        $guidances = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
        
        // Debug: Uncomment to see data structure
        // if ($guidances->count() > 0) {
        //     dd($guidances->first()->toArray());
        // }

        // Get statistics
        $total = QaGuidance::count();
        $active = QaGuidance::where('status', 'A')->count();
        $inactive = QaGuidance::where('status', 'N')->count();
        
        $statistics = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];

        // Get filter options
        $categories = QaCategory::where('status', 'A')->select('id', 'categories')->orderBy('categories')->get();
        $departemenOptions = ['Kitchen', 'Bar', 'Service'];

        return Inertia::render('QaGuidances/Index', [
            'guidances' => $guidances,
            'filters' => [
                'search' => $search,
                'departemen' => $departemen,
                'category_id' => $categoryId,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'statistics' => $statistics,
            'categories' => $categories,
            'departemenOptions' => $departemenOptions,
        ]);
    }

    public function create()
    {
        $categories = QaCategory::where('status', 'A')->select('id', 'categories')->orderBy('categories')->get();
        $parameters = QaParameter::where('status', 'A')->select('id', 'parameter')->orderBy('parameter')->get();
        $departemenOptions = ['Kitchen', 'Bar', 'Service'];

        return Inertia::render('QaGuidances/CreateComplex', [
            'guidance' => [
                'title' => '',
                'departemen' => '',
                'category_ids' => [],
                'status' => 'A',
                'categories' => [
                    [
                        'category_id' => '',
                        'parameters' => [
                            [
                                'parameter_pemeriksaan' => '',
                                'details' => [
                                    [
                                        'parameter_id' => '',
                                        'point' => 0
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'categories' => $categories,
            'parameters' => $parameters,
            'departemenOptions' => $departemenOptions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'departemen' => 'required|string|in:Kitchen,Bar,Service',
            'status' => 'required|string|in:A,N',
            'categories' => 'required|array|min:1',
            'categories.*.category_id' => 'required|integer|exists:qa_categories,id',
            'categories.*.parameters' => 'required|array|min:1',
            'categories.*.parameters.*.parameter_pemeriksaan' => 'required|string|max:255',
            'categories.*.parameters.*.details' => 'required|array|min:1',
            'categories.*.parameters.*.details.*.parameter_id' => 'required|integer|exists:qa_parameters,id',
            'categories.*.parameters.*.details.*.point' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create guidance header
            $guidance = QaGuidance::create([
                'title' => $validated['title'],
                'departemen' => $validated['departemen'],
                'status' => $validated['status'],
            ]);

            // Create guidance categories and their parameters
            foreach ($validated['categories'] as $categoryData) {
                // Create guidance category
                $guidanceCategory = QaGuidanceCategory::create([
                    'guidance_id' => $guidance->id,
                    'category_id' => $categoryData['category_id'],
                ]);

                // Create category parameters (parameter pemeriksaan per category)
                foreach ($categoryData['parameters'] as $parameterData) {
                    $categoryParameter = QaGuidanceCategoryParameter::create([
                        'guidance_category_id' => $guidanceCategory->id,
                        'parameter_pemeriksaan' => $parameterData['parameter_pemeriksaan'],
                    ]);

                    // Create parameter details (multiple parameter + point per pemeriksaan)
                    foreach ($parameterData['details'] as $detailData) {
                        QaGuidanceParameterDetail::create([
                            'category_parameter_id' => $categoryParameter->id,
                            'parameter_id' => $detailData['parameter_id'],
                            'point' => $detailData['point'],
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('qa-guidances.index')->with('success', 'QA Guidance berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menyimpan data: ' . $e->getMessage()]);
        }
    }

    public function show(QaGuidance $qaGuidance)
    {
        $qaGuidance->load([
            'guidanceCategories.category',
            'guidanceCategories.parameters.details.parameter'
        ]);
        
        
        // Transform data untuk display - sama seperti di edit method
        $transformedCategories = $qaGuidance->guidanceCategories->map(function($guidanceCategory) {
            return [
                'category_id' => $guidanceCategory->category_id,
                'category_name' => $guidanceCategory->category->categories,
                'parameters' => $guidanceCategory->parameters->map(function($parameter) {
                    return [
                        'parameter_pemeriksaan' => $parameter->parameter_pemeriksaan,
                        'details' => $parameter->details->map(function($detail) {
                            return [
                                'parameter_id' => $detail->parameter_id,
                                'parameter_name' => $detail->parameter->parameter,
                                'point' => $detail->point
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();
        
        // Add transformed categories to guidance object
        $qaGuidance->transformedCategories = $transformedCategories;
        
        return Inertia::render('QaGuidances/Show', [
            'guidance' => $qaGuidance
        ]);
    }

    public function edit(QaGuidance $qaGuidance)
    {
        // Load relationships dengan proper eager loading
        $qaGuidance->load([
            'guidanceCategories.category',
            'guidanceCategories.parameters.details.parameter'
        ]);
        
        $categories = QaCategory::where('status', 'A')->select('id', 'categories')->orderBy('categories')->get();
        $parameters = QaParameter::where('status', 'A')->select('id', 'parameter')->orderBy('parameter')->get();
        $departemenOptions = ['Kitchen', 'Bar', 'Service'];

        // Transform data for form - create categories array with proper structure
        $transformedCategories = $qaGuidance->guidanceCategories->map(function($guidanceCategory) {
            return [
                'category_id' => $guidanceCategory->category_id,
                'parameters' => $guidanceCategory->parameters->map(function($parameter) {
                    return [
                        'parameter_pemeriksaan' => $parameter->parameter_pemeriksaan,
                        'details' => $parameter->details->map(function($detail) {
                            return [
                                'parameter_id' => $detail->parameter_id,
                                'point' => $detail->point
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();

        // If no categories exist, create default structure
        if (empty($transformedCategories)) {
            $transformedCategories = [
                [
                    'category_id' => '',
                    'parameters' => [
                        [
                            'parameter_pemeriksaan' => '',
                            'details' => [
                                [
                                    'parameter_id' => '',
                                    'point' => 0
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        // Add transformed categories to guidance object
        $qaGuidance->categories = $transformedCategories;

        return Inertia::render('QaGuidances/EditComplex', [
            'guidance' => $qaGuidance,
            'categories' => $categories,
            'parameters' => $parameters,
            'departemenOptions' => $departemenOptions,
        ]);
    }

    public function update(Request $request, QaGuidance $qaGuidance)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'departemen' => 'required|string|in:Kitchen,Bar,Service',
            'status' => 'required|string|in:A,N',
            'categories' => 'required|array|min:1',
            'categories.*.category_id' => 'required|integer|exists:qa_categories,id',
            'categories.*.parameters' => 'required|array|min:1',
            'categories.*.parameters.*.parameter_pemeriksaan' => 'required|string|max:255',
            'categories.*.parameters.*.details' => 'required|array|min:1',
            'categories.*.parameters.*.details.*.parameter_id' => 'required|integer|exists:qa_parameters,id',
            'categories.*.parameters.*.details.*.point' => 'required|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Update guidance header
            $qaGuidance->update([
                'title' => $validated['title'],
                'departemen' => $validated['departemen'],
                'status' => $validated['status'],
            ]);

            // Delete existing data
            QaGuidanceCategory::where('guidance_id', $qaGuidance->id)->delete();

            // Create new guidance categories and their parameters
            foreach ($validated['categories'] as $categoryData) {
                // Create guidance category
                $guidanceCategory = QaGuidanceCategory::create([
                    'guidance_id' => $qaGuidance->id,
                    'category_id' => $categoryData['category_id'],
                ]);

                // Create category parameters (parameter pemeriksaan per category)
                foreach ($categoryData['parameters'] as $parameterData) {
                    $categoryParameter = QaGuidanceCategoryParameter::create([
                        'guidance_category_id' => $guidanceCategory->id,
                        'parameter_pemeriksaan' => $parameterData['parameter_pemeriksaan'],
                    ]);

                    // Create parameter details (multiple parameter + point per pemeriksaan)
                    foreach ($parameterData['details'] as $detailData) {
                        QaGuidanceParameterDetail::create([
                            'category_parameter_id' => $categoryParameter->id,
                            'parameter_id' => $detailData['parameter_id'],
                            'point' => $detailData['point'],
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('qa-guidances.show', $qaGuidance->id)->with('success', 'QA Guidance berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal update data: ' . $e->getMessage()]);
        }
    }

    public function destroy(QaGuidance $qaGuidance)
    {
        // Set status to 'N' (Non-aktif) instead of deleting
        $qaGuidance->update(['status' => 'N']);
        
        return redirect()->back()->with('success', 'QA Guidance berhasil dinonaktifkan!');
    }

    public function toggleStatus(QaGuidance $qaGuidance)
    {
        $newStatus = $qaGuidance->status === 'A' ? 'N' : 'A';
        $qaGuidance->update(['status' => $newStatus]);
        
        return response()->json([
            'success' => true,
            'message' => 'Status QA Guidance berhasil diubah!',
            'new_status' => $newStatus
        ]);
    }
}
