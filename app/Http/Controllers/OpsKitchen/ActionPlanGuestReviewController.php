<?php

namespace App\Http\Controllers\OpsKitchen;

use App\Http\Controllers\Controller;
use App\Models\OpsKitchen\ActionPlanGuestReview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Outlet;
use App\Models\User;

class ActionPlanGuestReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = ActionPlanGuestReview::with('images')
            ->leftJoin('tbl_data_outlet', 'action_plan_guest_reviews.outlet', '=', 'tbl_data_outlet.id_outlet')
            ->leftJoin('users', 'action_plan_guest_reviews.pic', '=', 'users.id');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tbl_data_outlet.nama_outlet', 'like', "%$search%")
                  ->orWhere('users.nama_lengkap', 'like', "%$search%")
                  ->orWhere('action_plan_guest_reviews.status', 'like', "%$search%")
                  ->orWhere('action_plan_guest_reviews.problem', 'like', "%$search%")
                  ->orWhere('action_plan_guest_reviews.analisa', 'like', "%$search%")
                  ->orWhere('action_plan_guest_reviews.preventive', 'like', "%$search%")
                  ;
            });
        }
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('action_plan_guest_reviews.tanggal', [$request->date_from, $request->date_to]);
        }
        $plans = $query
            ->orderBy('action_plan_guest_reviews.tanggal', 'desc')
            ->select([
                'action_plan_guest_reviews.*',
                'tbl_data_outlet.nama_outlet as outlet_name',
                'users.nama_lengkap as pic_name',
            ])
            ->paginate(10)
            ->withQueryString();
        foreach ($plans as $plan) {
            $plan->setRelation('images', $plan->images);
        }
        return Inertia::render('ops-kitchen/Index', [
            'actionPlans' => $plans,
            'filters' => [
                'search' => $request->search,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]
        ]);
    }

    public function create()
    {
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        $pics = User::whereIn('id_jabatan', [174, 180])
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
        return Inertia::render('ops-kitchen/ActionPlanGuestReview', [
            'outlets' => $outlets,
            'pics' => $pics,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'outlet' => 'required|string',
            'tanggal' => 'required|date',
            'dept' => 'required|string',
            'pic' => 'required|string',
            'problem' => 'required|string',
            'analisa' => 'required|string',
            'preventive' => 'required|string',
            'status' => 'required|string',
            'documentation' => 'nullable|array',
            'documentation.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Hapus documentation dari $data agar tidak error mass assignment
        unset($data['documentation']);

        $plan = ActionPlanGuestReview::create($data);

        // Simpan file gambar ke tabel images (identik dengan Items)
        if ($request->hasFile('documentation')) {
            \Log::info('ActionPlanGuestReviewController@store - Processing documentation images');
            foreach ($request->file('documentation') as $file) {
                $path = $file->store('action_plan_docs', 'public');
                $plan->images()->create(['path' => $path]);
                \Log::info('Image saved', ['path' => $path]);
            }
        } else {
            \Log::info('ActionPlanGuestReviewController@store - No documentation file uploaded');
        }

        return redirect()->route('ops-kitchen.action-plan-guest-review.index')->with('success', 'Data berhasil disimpan!');
    }

    public function show($id)
    {
        $plan = ActionPlanGuestReview::with('images')->findOrFail($id);
        return Inertia::render('ops-kitchen/ActionPlanGuestReviewDetail', [
            'plan' => $plan
        ]);
    }

    public function edit($id)
    {
        $plan = ActionPlanGuestReview::with('images')->findOrFail($id);
        $outlets = Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']);
        $pics = \App\Models\User::whereIn('id_jabatan', [174, 180])
            ->where('status', 'A')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
        return Inertia::render('ops-kitchen/ActionPlanGuestReviewEdit', [
            'plan' => $plan,
            'outlets' => $outlets,
            'pics' => $pics,
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = ActionPlanGuestReview::findOrFail($id);
        $data = $request->validate([
            'outlet' => 'required|string',
            'tanggal' => 'required|date',
            'dept' => 'required|string',
            'pic' => 'required|string',
            'problem' => 'required|string',
            'analisa' => 'required|string',
            'preventive' => 'required|string',
            'status' => 'required|string',
            'documentation' => 'nullable|array',
            'documentation.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'deleted_images' => 'nullable|array',
        ]);
        unset($data['documentation']);
        unset($data['deleted_images']);
        $plan->update($data);
        // Hapus gambar jika ada
        if ($request->deleted_images) {
            foreach ($request->deleted_images as $imgId) {
                $img = $plan->images()->find($imgId);
                if ($img) {
                    \Storage::disk('public')->delete($img->path);
                    $img->delete();
                }
            }
        }
        // Simpan file gambar baru
        if ($request->hasFile('documentation')) {
            foreach ($request->file('documentation') as $file) {
                $path = $file->store('action_plan_docs', 'public');
                $plan->images()->create(['path' => $path]);
            }
        }
        return redirect()->route('ops-kitchen.action-plan-guest-review.index')->with('success', 'Data berhasil diupdate!');
    }

    public function destroy($id)
    {
        $plan = ActionPlanGuestReview::with('images')->findOrFail($id);
        // Hapus semua gambar terkait
        foreach ($plan->images as $img) {
            \Storage::disk('public')->delete($img->path);
            $img->delete();
        }
        $plan->delete();
        return redirect()->route('ops-kitchen.action-plan-guest-review.index')->with('success', 'Data berhasil dihapus!');
    }
}
