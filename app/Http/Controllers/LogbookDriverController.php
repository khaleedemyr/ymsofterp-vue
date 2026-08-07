<?php

namespace App\Http\Controllers;

use App\Models\LogbookDriver;
use App\Models\LogbookDriverItem;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LogbookDriverController extends Controller
{
    private const SUPERADMIN_ROLE_ID = '5af56935b011a';

    private const MAX_PHOTO_KB = 5120; // 5MB

    public function index(Request $request): Response
    {
        $query = LogbookDriver::query()
            ->withCount('items')
            ->orderByDesc('log_date')
            ->orderByDesc('id');

        if (! $this->isSuperAdmin()) {
            $query->where('driver_id', Auth::id());
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->outlet_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('log_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', $request->string('date_to')->toString());
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        return Inertia::render('LogbookDriver/Index', [
            'records' => $query->paginate($perPage)->withQueryString(),
            'filters' => [
                'search' => $request->get('search', ''),
                'outlet_id' => $request->get('outlet_id', ''),
                'date_from' => $request->get('date_from', ''),
                'date_to' => $request->get('date_to', ''),
                'per_page' => $perPage,
            ],
            'outlets' => $this->activeOutlets(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        return Inertia::render('LogbookDriver/Form', [
            'record' => null,
            'outlets' => $this->activeOutlets(),
            'driver' => [
                'id' => $user->id,
                'name' => (string) ($user->nama_lengkap ?? $user->name ?? ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $record = null;
        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);
            $user = Auth::user();

            $record = LogbookDriver::create([
                'number' => $this->generateNumber(),
                'log_date' => now()->toDateString(),
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'driver_id' => $user->id,
                'driver_name' => (string) ($user->nama_lengkap ?? $user->name ?? ''),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncItems($record, $validated['items'] ?? [], $request);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($record?->id) {
                Storage::disk('public')->deleteDirectory('logbook_drivers/'.$record->id);
            }
            throw $e;
        }

        return redirect()
            ->route('logbook-drivers.show', $record->id)
            ->with('success', 'Logbook Driver berhasil disimpan.');
    }

    public function show(LogbookDriver $logbookDriver): Response
    {
        $this->authorizeView($logbookDriver);

        $logbookDriver->load(['items', 'creator:id,nama_lengkap']);

        return Inertia::render('LogbookDriver/Show', [
            'record' => $logbookDriver,
            'canEdit' => $this->canManage($logbookDriver),
            'canDelete' => $this->canManage($logbookDriver),
        ]);
    }

    public function edit(LogbookDriver $logbookDriver): Response
    {
        $this->authorizeManage($logbookDriver);

        $logbookDriver->load('items');

        return Inertia::render('LogbookDriver/Form', [
            'record' => $logbookDriver,
            'outlets' => $this->activeOutlets(),
            'driver' => [
                'id' => $logbookDriver->driver_id,
                'name' => $logbookDriver->driver_name,
            ],
        ]);
    }

    public function update(Request $request, LogbookDriver $logbookDriver)
    {
        $this->authorizeManage($logbookDriver);

        $validated = $this->validatePayload($request);

        DB::beginTransaction();
        try {
            $outlet = Outlet::findOrFail($validated['outlet_id']);

            $logbookDriver->update([
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => (string) $outlet->nama_outlet,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($logbookDriver, $validated['items'] ?? [], $request);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('logbook-drivers.show', $logbookDriver->id)
            ->with('success', 'Logbook Driver berhasil diperbarui.');
    }

    public function destroy(LogbookDriver $logbookDriver)
    {
        $this->authorizeManage($logbookDriver);

        $id = $logbookDriver->id;
        $logbookDriver->delete();
        Storage::disk('public')->deleteDirectory('logbook_drivers/'.$id);

        return redirect()
            ->route('logbook-drivers.index')
            ->with('success', 'Logbook Driver berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        if ($request->has('items') && is_array($request->input('items'))) {
            $items = $request->input('items');
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $time = $item['log_time'] ?? null;
                if ($time === '' || $time === null) {
                    $items[$i]['log_time'] = null;
                } elseif (is_string($time) && preg_match('/^\d{2}:\d{2}/', $time)) {
                    $items[$i]['log_time'] = substr($time, 0, 5);
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'outlet_id' => ['required', 'integer', 'exists:tbl_data_outlet,id_outlet'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.log_time' => ['nullable', 'date_format:H:i'],
            'items.*.description' => ['required', 'string', 'max:5000'],
            'items.*.keep_photo' => ['nullable', 'boolean'],
            'items.*.photo' => ['nullable', 'image', 'max:'.self::MAX_PHOTO_KB],
        ], [
            'items.required' => 'Minimal satu baris log wajib diisi.',
            'items.min' => 'Minimal satu baris log wajib diisi.',
            'items.*.description.required' => 'Keterangan baris log wajib diisi.',
            'items.*.photo.image' => 'File foto harus berupa gambar.',
            'items.*.photo.max' => 'Ukuran foto maksimal 5MB.',
        ]);

        $hasDescription = collect($validated['items'] ?? [])
            ->contains(fn ($item) => trim((string) ($item['description'] ?? '')) !== '');

        if (! $hasDescription) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu baris log dengan keterangan wajib diisi.',
            ]);
        }

        return $validated;
    }

    private function syncItems(LogbookDriver $record, array $items, Request $request): void
    {
        $keepIds = [];
        $folder = 'logbook_drivers/'.$record->id;

        foreach (array_values($items) as $index => $itemData) {
            $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;
            $item = null;

            if ($itemId) {
                $item = LogbookDriverItem::where('logbook_driver_id', $record->id)
                    ->where('id', $itemId)
                    ->first();
            }

            if (! $item) {
                $item = new LogbookDriverItem([
                    'logbook_driver_id' => $record->id,
                ]);
            }

            $item->log_time = $itemData['log_time'] ?? null;
            $item->description = trim((string) ($itemData['description'] ?? ''));
            $item->sort_order = $index;

            $photoFile = $request->file("items.{$index}.photo");
            $keepPhoto = filter_var($itemData['keep_photo'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($photoFile) {
                if ($item->photo_path) {
                    Storage::disk('public')->delete($item->photo_path);
                }
                $item->photo_path = $photoFile->store($folder, 'public');
            } elseif (! $keepPhoto && $item->photo_path) {
                Storage::disk('public')->delete($item->photo_path);
                $item->photo_path = null;
            }

            $item->save();
            $keepIds[] = $item->id;
        }

        $toDelete = LogbookDriverItem::where('logbook_driver_id', $record->id)
            ->when(! empty($keepIds), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when(empty($keepIds), fn ($q) => $q)
            ->get();

        foreach ($toDelete as $old) {
            if ($old->photo_path) {
                Storage::disk('public')->delete($old->photo_path);
            }
            $old->delete();
        }
    }

    private function generateNumber(): string
    {
        $prefix = 'LD-'.now()->format('Ymd').'-';
        $last = LogbookDriver::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function activeOutlets()
    {
        return Outlet::query()
            ->where('status', 'A')
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return (string) ($user->id_role ?? '') === self::SUPERADMIN_ROLE_ID;
    }

    private function canManage(LogbookDriver $record): bool
    {
        return $this->isSuperAdmin() || (int) $record->driver_id === (int) Auth::id();
    }

    private function authorizeView(LogbookDriver $record): void
    {
        if (! $this->canManage($record) && ! $this->isSuperAdmin()) {
            abort(403);
        }
    }

    private function authorizeManage(LogbookDriver $record): void
    {
        if (! $this->canManage($record)) {
            abort(403);
        }
    }

    // ─── Approval App API ────────────────────────────────────────────

    public function apiIndex(Request $request)
    {
        $query = LogbookDriver::query()
            ->withCount('items')
            ->orderByDesc('log_date')
            ->orderByDesc('id');

        if (! $this->isSuperAdmin()) {
            $query->where('driver_id', Auth::id());
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', (int) $request->outlet_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('log_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', $request->string('date_to')->toString());
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('outlet_name', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
            'outlets' => $this->activeOutlets(),
            'filters' => $request->only(['search', 'outlet_id', 'date_from', 'date_to']),
        ]);
    }

    public function apiCreateData()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'outlets' => $this->activeOutlets(),
                'driver' => [
                    'id' => $user->id,
                    'name' => (string) ($user->nama_lengkap ?? $user->name ?? ''),
                ],
                'log_date' => now()->toDateString(),
            ],
        ]);
    }

    public function apiShow($id)
    {
        $record = LogbookDriver::with(['items', 'creator:id,nama_lengkap'])->findOrFail($id);
        if (! $this->canManage($record)) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $record,
            'outlets' => $this->activeOutlets(),
            'canEdit' => $this->canManage($record),
            'canDelete' => $this->canManage($record),
        ]);
    }

    public function apiStore(Request $request)
    {
        try {
            $validated = $this->validatePayload($request);
            $record = null;
            DB::beginTransaction();
            try {
                $outlet = Outlet::findOrFail($validated['outlet_id']);
                $user = Auth::user();

                $record = LogbookDriver::create([
                    'number' => $this->generateNumber(),
                    'log_date' => now()->toDateString(),
                    'outlet_id' => $outlet->id_outlet,
                    'outlet_name' => (string) $outlet->nama_outlet,
                    'driver_id' => $user->id,
                    'driver_name' => (string) ($user->nama_lengkap ?? $user->name ?? ''),
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->syncItems($record, $validated['items'] ?? [], $request);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                if ($record?->id) {
                    Storage::disk('public')->deleteDirectory('logbook_drivers/'.$record->id);
                }
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Logbook Driver berhasil disimpan.',
                'data' => $record->fresh(['items']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiUpdate(Request $request, $id)
    {
        $record = LogbookDriver::findOrFail($id);
        if (! $this->canManage($record)) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa mengedit logbook ini.'], 403);
        }

        try {
            $validated = $this->validatePayload($request);
            DB::beginTransaction();
            try {
                $outlet = Outlet::findOrFail($validated['outlet_id']);
                $record->update([
                    'outlet_id' => $outlet->id_outlet,
                    'outlet_name' => (string) $outlet->nama_outlet,
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => Auth::id(),
                ]);
                $this->syncItems($record, $validated['items'] ?? [], $request);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Logbook Driver berhasil diperbarui.',
                'data' => $record->fresh(['items']),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDestroy($id)
    {
        $record = LogbookDriver::findOrFail($id);
        if (! $this->canManage($record)) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus logbook ini.'], 403);
        }

        $id = $record->id;
        $record->delete();
        Storage::disk('public')->deleteDirectory('logbook_drivers/'.$id);

        return response()->json([
            'success' => true,
            'message' => 'Logbook Driver dihapus.',
        ]);
    }
}
