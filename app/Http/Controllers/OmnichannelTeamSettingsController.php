<?php

namespace App\Http\Controllers;

use App\Models\OmniMessageTemplate;
use App\Models\OmniTeam;
use App\Models\User;
use App\Support\OmnichannelAuthorization;
use App\Support\OmnichannelUserOption;
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
            ->with([
                'users' => fn ($q) => $q->active()->with(['jabatan', 'outlet'])->orderBy('nama_lengkap'),
            ])
            ->orderBy('name')
            ->get();

        $userOptions = OmnichannelUserOption::assignableOptions();

        $fullAccessIds = DB::table('omni_inbox_full_access_users')->pluck('user_id')->all();
        $fullAccessUsers = $fullAccessIds === []
            ? []
            : OmnichannelUserOption::toOptions(
                OmnichannelUserOption::assignableQuery()
                    ->whereIn('id', $fullAccessIds)
                    ->get()
            );

        $messageTemplates = OmniMessageTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (OmniMessageTemplate $t) => array_merge($t->toInboxPayload(), [
                'is_active' => (bool) $t->is_active,
                'sort_order' => (int) $t->sort_order,
            ]))
            ->values()
            ->all();

        return Inertia::render('Crm/OmnichannelTeams/Index', [
            'messageTemplates' => $messageTemplates,
            'teams' => $teams->map(fn (OmniTeam $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'members' => OmnichannelUserOption::toOptions($t->users),
            ])->values()->all(),
            'fullAccessUsers' => $fullAccessUsers,
            'userOptions' => $userOptions,
        ]);
    }

    public function updateFullAccessUsers(Request $request): RedirectResponse
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );

        $validated = $request->validate([
            'user_ids' => OmnichannelUserOption::assignableUserIdRules(),
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));

        DB::transaction(function () use ($ids) {
            DB::table('omni_inbox_full_access_users')->delete();
            if ($ids === []) {
                return;
            }
            $now = now();
            $rows = array_map(fn (int $uid) => [
                'user_id' => $uid,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids);
            DB::table('omni_inbox_full_access_users')->insert($rows);
        });

        return redirect()->back()->with('success', 'Daftar pengguna (lihat semua inbox) disimpan.');
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
            'user_ids' => OmnichannelUserOption::assignableUserIdRules(),
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
            'user_ids' => OmnichannelUserOption::assignableUserIdRules(),
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
}
