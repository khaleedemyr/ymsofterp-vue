<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = JaMaterial::orderByDesc('id');
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        return Inertia::render('JustAcademy/Materials/Index', [
            'materials' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function create()
    {
        return Inertia::render('JustAcademy/Materials/Form', ['material' => null]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateMaterial($request);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('just-academy/materials', 'public');
        }

        JaMaterial::create([
            ...$validated,
            'file_path' => $path,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('just-academy.materials.index')
            ->with('success', 'Materi berhasil ditambahkan.');
    }

    public function edit(JaMaterial $material)
    {
        return Inertia::render('JustAcademy/Materials/Form', ['material' => $material]);
    }

    public function update(Request $request, JaMaterial $material)
    {
        $validated = $this->validateMaterial($request, $material->id);

        $data = [...$validated, 'is_active' => $request->boolean('is_active', true)];

        if ($request->hasFile('file')) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $data['file_path'] = $request->file('file')->store('just-academy/materials', 'public');
        }

        $material->update($data);

        return redirect()
            ->route('just-academy.materials.index')
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function destroy(JaMaterial $material)
    {
        if ($material->programItems()->exists()) {
            return back()->withErrors(['material' => 'Materi masih dipakai di program. Hapus dari curriculum program terlebih dahulu.']);
        }

        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return back()->with('success', 'Materi berhasil dihapus.');
    }

    private function validateMaterial(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,video,link,doc,other',
            'url' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
            'is_active' => 'boolean',
        ]);
    }
}
