<?php

namespace App\Http\Controllers;

use App\Models\OmniTeam;
use App\Models\User;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OmnichannelTeamSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );

        $teams = OmniTeam::query()
            ->with(['users:id,nama_lengkap,email'])
            ->orderBy('name')
            ->get();

        $userOptions = User::query()
            ->active()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->nama_lengkap ?? $u->email,
            ]);

        return Inertia::render('Crm/OmnichannelTeams/Index', [
            'teams' => $teams->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'members' => $t->users->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->nama_lengkap ?? $u->email,
                ])->values()->all(),
            ])->values()->all(),
            'seeAllUsers' => $this->buildSeeAllUsersList(),
            'userOptions' => $userOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $team = OmniTeam::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['user_ids'])) {
            $team->users()->sync($validated['user_ids']);
        }

        return redirect()->back()->with('success', 'Tim dibuat.');
    }

    public function update(Request $request, OmniTeam $team): RedirectResponse
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if (array_key_exists('name', $validated)) {
            $team->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $team->description = $validated['description'];
        }
        $team->save();

        if (array_key_exists('user_ids', $validated)) {
            $team->users()->sync($validated['user_ids'] ?? []);
        }

        return redirect()->back()->with('success', 'Tim diperbarui.');
    }

    public function destroy(Request $request, OmniTeam $team): RedirectResponse
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );

        $team->delete();

        return redirect()->back()->with('success', 'Tim dihapus.');
    }

    /**
     * @return list<array{id: int, name: string, email: ?string, via: string}>
     */
    private function buildSeeAllUsersList(): array
    {
        $permissionId = DB::table('erp_permission')->where('code', 'omnichannel_inbox_see_all')->value('id');

        $query = User::query()
            ->active()
            ->select(['users.id', 'users.nama_lengkap', 'users.email', 'users.is_admin'])
            ->orderBy('nama_lengkap');

        $query->where(function ($q) use ($permissionId) {
            $q->where('users.is_admin', 1);
            if ($permissionId) {
                $q->orWhereExists(function ($sub) use ($permissionId) {
                    $sub->select(DB::raw(1))
                        ->from('erp_user_role as ur')
                        ->join('erp_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
                        ->whereColumn('ur.user_id', 'users.id')
                        ->where('rp.permission_id', $permissionId);
                });
            }
        });

        return $query->get()
            ->unique('id')
            ->map(function (User $u) {
                $via = ((int) ($u->is_admin ?? 0) === 1) ? 'Admin' : 'Role (lihat semua chat)';

                return [
                    'id' => (int) $u->id,
                    'name' => $u->nama_lengkap ?? $u->email ?? '',
                    'email' => $u->email,
                    'via' => $via,
                ];
            })
            ->values()
            ->all();
    }
}
