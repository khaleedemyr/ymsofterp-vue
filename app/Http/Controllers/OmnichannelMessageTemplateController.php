<?php

namespace App\Http\Controllers;

use App\Models\OmniMessageTemplate;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OmnichannelMessageTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->assertCanManage($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'shortcut' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN_-]+$/u'],
            'body' => ['required', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        OmniMessageTemplate::query()->create([
            'title' => $validated['title'],
            'shortcut' => $this->normalizeShortcut($validated['shortcut'] ?? null),
            'body' => $validated['body'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Template balasan ditambahkan.');
    }

    public function update(Request $request, OmniMessageTemplate $messageTemplate): RedirectResponse
    {
        $this->assertCanManage($request);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'shortcut' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN_-]+$/u'],
            'body' => ['sometimes', 'required', 'string', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        if (array_key_exists('shortcut', $validated)) {
            $validated['shortcut'] = $this->normalizeShortcut($validated['shortcut']);
        }

        $messageTemplate->update($validated);

        return back()->with('success', 'Template balasan diperbarui.');
    }

    public function destroy(Request $request, OmniMessageTemplate $messageTemplate): RedirectResponse
    {
        $this->assertCanManage($request);

        $messageTemplate->delete();

        return back()->with('success', 'Template balasan dihapus.');
    }

    private function assertCanManage(Request $request): void
    {
        abort_unless(
            OmnichannelAuthorization::userHasPermission((int) $request->user()->id, 'omnichannel_teams_view'),
            403
        );
    }

    private function normalizeShortcut(?string $shortcut): ?string
    {
        if ($shortcut === null || trim($shortcut) === '') {
            return null;
        }

        $s = strtolower(trim($shortcut));
        $s = ltrim($s, '/');

        return $s !== '' ? $s : null;
    }
}
