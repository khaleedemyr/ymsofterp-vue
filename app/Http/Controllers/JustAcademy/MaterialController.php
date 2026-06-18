<?php

namespace App\Http\Controllers\JustAcademy;

use App\Http\Controllers\Controller;
use App\Models\JustAcademy\JaMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MaterialController extends Controller
{
    private const VIDEO_MAX_KB = 102400; // 100 MB

    private const FILE_MAX_KB = 51200; // 50 MB

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

        return $this->respondSaved($request, 'Materi berhasil ditambahkan.');
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

        return $this->respondSaved($request, 'Materi berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $material = JaMaterial::findOrFail($id);

        if ($material->programItems()->exists()) {
            return back()->with('error', 'Materi masih dipakai di program. Hapus dari curriculum program terlebih dahulu.');
        }

        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return redirect()
            ->route('just-academy.materials.index')
            ->with('success', 'Materi berhasil dihapus.');
    }

    private function validateMaterial(Request $request, ?int $ignoreId = null): array
    {
        $type = $request->input('type', 'pdf');
        $fileRule = $type === 'video'
            ? 'nullable|file|mimes:mp4,webm,avi,mov|max:' . self::VIDEO_MAX_KB
            : 'nullable|file|max:' . self::FILE_MAX_KB;

        return $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:pdf,video,link,doc,other',
            'url' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'file' => $fileRule,
            'is_active' => 'boolean',
        ]);
    }

    private function respondSaved(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('just-academy.materials.index'),
            ]);
        }

        return redirect()
            ->route('just-academy.materials.index')
            ->with('success', $message);
    }
}
