<?php

namespace App\Http\Controllers;

use App\Models\OmniMessageTemplate;
use App\Support\OmniFlowDefinition;
use App\Support\OmnichannelAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OmnichannelMessageTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->assertCanManage($request);

        $validated = $this->validateTemplate($request);

        $nextSortOrder = ((int) OmniMessageTemplate::query()->max('sort_order')) + 1;

        OmniMessageTemplate::query()->create([
            'title' => $validated['title'],
            'shortcut' => $this->normalizeShortcut($validated['shortcut'] ?? null),
            'body' => $validated['body'],
            'message_mode' => $validated['message_mode'],
            'config' => $validated['config'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $nextSortOrder,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Template balasan ditambahkan.');
    }

    public function update(Request $request, OmniMessageTemplate $messageTemplate): RedirectResponse
    {
        $this->assertCanManage($request);

        $validated = $this->validateTemplate($request, partial: true);

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

    public function uploadMedia(Request $request): JsonResponse
    {
        $this->assertCanManage($request);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:16384',
                'mimes:jpeg,jpg,png,gif,webp,pdf',
            ],
        ]);

        $file = $validated['file'];
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');
        $storedPath = $file->store('omni-template-media/'.date('Y/m'), 'public');
        $relative = Storage::disk('public')->url($storedPath);
        $mediaUrl = str_starts_with($relative, 'http') ? $relative : url($relative);

        return response()->json([
            'success' => true,
            'media_path' => $storedPath,
            'media_url' => $mediaUrl,
            'media_filename' => $file->getClientOriginalName(),
            'media_mime' => $mime,
            'media_kind' => $isImage ? 'image' : 'document',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'shortcut' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN_-]+$/u'],
            'body' => [$partial ? 'sometimes' : 'required', 'string', 'max:4096'],
            'message_mode' => ['nullable', 'string', 'in:text,quick_reply,cta_url,image,document'],
            'config' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];

        $validated = $request->validate($rules);

        if (! array_key_exists('body', $validated) && ! array_key_exists('message_mode', $validated) && ! array_key_exists('config', $validated)) {
            return $validated;
        }

        $body = (string) ($validated['body'] ?? '');
        $mode = (string) ($validated['message_mode'] ?? 'text');
        $config = is_array($validated['config'] ?? null) ? $validated['config'] : [];

        $normalized = OmniFlowDefinition::normalizeSendMessageConfig(array_merge($config, [
            'body' => $body,
            'message_mode' => $mode,
        ]));

        $validated['message_mode'] = (string) ($normalized['message_mode'] ?? 'text');
        $validated['body'] = $body;
        unset($normalized['body'], $normalized['message_mode']);
        $validated['config'] = $normalized;

        return $validated;
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
