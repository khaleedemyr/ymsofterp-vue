<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Region;
use App\Models\TicketCategory;
use App\Models\TicketTeamSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketTeamSettingController extends Controller
{
    private function ensureTicketManager(): void
    {
        if (! TicketController::userCanManageTickets(auth()->user())) {
            abort(403, 'Anda tidak memiliki akses ke pengaturan team ticketing.');
        }
    }

    public function index(Request $request)
    {
        $this->ensureTicketManager();

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'A');
        $categoryId = $request->input('category_id');

        $query = TicketTeamSetting::query()
            ->with([
                'category:id,name',
                'regions:id,name',
                'outlets:id_outlet,nama_outlet',
                'users:id,nama_lengkap',
            ])
            ->orderByDesc('id');

        if ($status === 'A' || $status === 'N') {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('users', fn ($uq) => $uq->where('nama_lengkap', 'like', "%{$search}%"));
            });
        }

        $settings = $query->paginate(15)->withQueryString();

        return Inertia::render('Tickets/TeamSettings/Index', [
            'settings' => $settings,
            'categories' => TicketCategory::active()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function create()
    {
        $this->ensureTicketManager();

        return Inertia::render('Tickets/TeamSettings/Form', [
            'mode' => 'create',
            'setting' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTicketManager();

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated) {
            $setting = TicketTeamSetting::create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->syncRelations($setting, $validated);
        });

        return redirect()->route('tickets.team-settings.index')
            ->with('success', 'Pengaturan team ticketing berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $this->ensureTicketManager();

        $setting = TicketTeamSetting::with([
            'regions:id',
            'outlets:id_outlet',
            'users:id',
        ])->findOrFail($id);

        return Inertia::render('Tickets/TeamSettings/Form', [
            'mode' => 'edit',
            'setting' => [
                'id' => $setting->id,
                'category_id' => $setting->category_id,
                'name' => $setting->name,
                'status' => $setting->status,
                'region_ids' => $setting->regions->pluck('id')->values(),
                'outlet_ids' => $setting->outlets->pluck('id_outlet')->values(),
                'user_ids' => $setting->users->pluck('id')->values(),
                'primary_user_id' => $setting->users->firstWhere('pivot.is_primary', true)?->id
                    ?? $setting->users->first()?->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->ensureTicketManager();

        $setting = TicketTeamSetting::findOrFail($id);
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($setting, $validated) {
            $setting->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->syncRelations($setting, $validated);
        });

        return redirect()->route('tickets.team-settings.index')
            ->with('success', 'Pengaturan team ticketing berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->ensureTicketManager();

        TicketTeamSetting::where('id', $id)->update([
            'status' => 'N',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Pengaturan team dinonaktifkan.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => TicketCategory::active()->orderBy('name')->get(['id', 'name']),
            'regions' => Region::active()->orderBy('name')->get(['id', 'name', 'code']),
            'outlets' => Outlet::query()
                ->where('status', 'A')
                ->orderBy('nama_outlet')
                ->get(['id_outlet', 'nama_outlet', 'region_id']),
            'users' => User::query()
                ->where('status', 'A')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'division_id']),
        ];
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['A', 'N'])],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => ['integer', 'exists:regions,id'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:tbl_data_outlet,id_outlet'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'primary_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $userIds = collect($validated['user_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $primary = isset($validated['primary_user_id']) ? (int) $validated['primary_user_id'] : null;
        if (! $primary || ! $userIds->contains($primary)) {
            $validated['primary_user_id'] = $userIds->first();
        } else {
            $validated['primary_user_id'] = $primary;
        }
        $validated['user_ids'] = $userIds->all();
        $validated['region_ids'] = collect($validated['region_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $validated['outlet_ids'] = collect($validated['outlet_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $validated;
    }

    private function syncRelations(TicketTeamSetting $setting, array $validated): void
    {
        $setting->regions()->sync($validated['region_ids'] ?? []);
        $setting->outlets()->sync($validated['outlet_ids'] ?? []);

        $userSync = [];
        foreach ($validated['user_ids'] as $userId) {
            $userSync[$userId] = [
                'is_primary' => (int) $userId === (int) $validated['primary_user_id'],
            ];
        }
        $setting->users()->sync($userSync);
    }
}
