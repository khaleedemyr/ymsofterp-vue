<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeType;
use App\Models\ChallengeItem;
use App\Models\Outlet;
use App\Models\MemberAppsVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ChallengeController extends Controller
{
    public function index(Request $request)
    {
        $query = Challenge::with(['challengeType', 'outlets', 'creator'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->active()->valid();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by type
        if ($request->has('type')) {
            $query->whereHas('challengeType', function($q) use ($request) {
                $q->where('name', $request->type);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        $challenges = $query->paginate(10)->appends($request->all());

        return Inertia::render('MemberAppsSettings/ChallengeIndex', [
            'challenges' => $challenges,
            'filters' => $request->only(['status', 'type', 'search']),
            'challengeTypes' => ChallengeType::all(),
            'stats' => [
                'total' => Challenge::count(),
                'active' => Challenge::active()->valid()->count(),
                'inactive' => Challenge::where('is_active', false)->count(),
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('MemberAppsSettings/ChallengeForm', [
            'challengeTypes' => ChallengeType::all(),
            'challengeItems' => ChallengeItem::available()->get(),
            'vouchers' => MemberAppsVoucher::where('is_active', true)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get(),
            'challenge' => null
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'challenge_type_id' => 'required|exists:challenge_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|array',
            'validity_period_days' => 'required|integer|min:1|max:365',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);

        DB::beginTransaction();
        try {
            $challenge = Challenge::create([
                'challenge_type_id' => $request->challenge_type_id,
                'name' => $request->name,
                'description' => $request->description,
                'rules' => $request->rules,
                'validity_period_days' => $request->validity_period_days,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? true,
                'created_by' => auth()->id()
            ]);

            // Attach outlets if provided
            if ($request->has('outlet_ids') && !empty($request->outlet_ids)) {
                $challenge->outlets()->attach($request->outlet_ids);
            }

            DB::commit();

            return redirect()->route('challenges.index')
                ->with('success', 'Challenge berhasil dibuat!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menyimpan challenge: ' . $e->getMessage()]);
        }
    }

    public function show(Challenge $challenge)
    {
        $challenge->load(['challengeType', 'outlets', 'creator', 'userProgress.user']);

        return Inertia::render('MemberAppsSettings/ChallengeDetail', [
            'challenge' => $challenge,
            'stats' => [
                'total_participants' => $challenge->userProgress()->count(),
                'completed' => $challenge->userProgress()->completed()->count(),
                'claimed' => $challenge->userProgress()->claimed()->count(),
            ]
        ]);
    }

    public function edit(Challenge $challenge)
    {
        $challenge->load(['outlets']);

        return Inertia::render('MemberAppsSettings/ChallengeForm', [
            'challengeTypes' => ChallengeType::all(),
            'challengeItems' => ChallengeItem::available()->get(),
            'vouchers' => MemberAppsVoucher::where('is_active', true)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'outlets' => Outlet::select('id_outlet as id', 'nama_outlet as name')->get(),
            'challenge' => $challenge
        ]);
    }

    public function update(Request $request, Challenge $challenge)
    {
        $request->validate([
            'challenge_type_id' => 'required|exists:challenge_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|array',
            'validity_period_days' => 'required|integer|min:1|max:365',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);

        DB::beginTransaction();
        try {
            $challenge->update([
                'challenge_type_id' => $request->challenge_type_id,
                'name' => $request->name,
                'description' => $request->description,
                'rules' => $request->rules,
                'validity_period_days' => $request->validity_period_days,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $request->is_active ?? $challenge->is_active,
            ]);

            // Update outlets
            if ($request->has('outlet_ids')) {
                $challenge->outlets()->sync($request->outlet_ids);
            }

            DB::commit();

            return redirect()->route('challenges.index')
                ->with('success', 'Challenge berhasil diperbarui!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Terjadi kesalahan saat memperbarui challenge: ' . $e->getMessage()]);
        }
    }

    public function destroy(Challenge $challenge)
    {
        try {
            // Check if challenge has participants
            if ($challenge->userProgress()->count() > 0) {
                return back()->withErrors(['error' => 'Tidak dapat menghapus challenge yang sudah memiliki peserta!']);
            }

            $challenge->delete();

            return redirect()->route('challenges.index')
                ->with('success', 'Challenge berhasil dihapus!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan saat menghapus challenge: ' . $e->getMessage()]);
        }
    }

    public function toggleStatus(Challenge $challenge)
    {
        $challenge->update(['is_active' => !$challenge->is_active]);

        $status = $challenge->is_active ? 'diaktifkan' : 'dinonaktifkan';
        
        return back()->with('success', "Challenge berhasil $status!");
    }

    public function getChallengeItems(Request $request)
    {
        $category = $request->get('category');
        $query = ChallengeItem::available();

        if ($category) {
            $query->byCategory($category);
        }

        return response()->json($query->get());
    }

    public function getChallengeTypeConfig(Request $request)
    {
        $typeId = $request->get('type_id');
        $challengeType = ChallengeType::find($typeId);

        if (!$challengeType) {
            return response()->json(['error' => 'Challenge type tidak ditemukan'], 404);
        }

        return response()->json([
            'parameters_config' => $challengeType->parameters_config,
            'challenge_items' => ChallengeItem::available()->get()
        ]);
    }
}
