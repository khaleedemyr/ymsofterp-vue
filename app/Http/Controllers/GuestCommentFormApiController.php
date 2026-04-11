<?php

namespace App\Http\Controllers;

use App\Models\GuestCommentForm;
use App\Models\Outlet;
use App\Services\GuestCommentOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Guest Comment (OCR) — API untuk Approval App (Bearer), selaras GuestCommentFormController web.
 */
class GuestCommentFormApiController extends Controller
{
    private const RATINGS = ['poor', 'average', 'good', 'excellent'];

    private function authorizeGuestCommentFormAccess(Request $request, GuestCommentForm $form): void
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($userOutletId === 1) {
            return;
        }
        if ($userOutletId <= 0) {
            abort(403, 'Akun tidak memiliki outlet.');
        }
        $formOutletId = $form->id_outlet !== null ? (int) $form->id_outlet : null;
        if ($formOutletId === $userOutletId) {
            return;
        }
        if ($formOutletId === null && (int) $form->created_by === (int) $request->user()->id) {
            return;
        }
        abort(403, 'Anda tidak dapat mengakses data guest comment ini.');
    }

    private function serializeForm(GuestCommentForm $form): array
    {
        $form->loadMissing([
            'creator:id,nama_lengkap,avatar',
            'verifier:id,nama_lengkap,avatar',
            'outlet:id_outlet,nama_outlet',
        ]);
        $arr = $form->toArray();
        $arr['image_url'] = $form->image_path
            ? Storage::disk('public')->url($form->image_path)
            : null;

        return $arr;
    }

    public function meta(Request $request): JsonResponse
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'rating_options' => self::RATINGS,
            'can_choose_outlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'locked_outlet' => $lockedOutlet,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $query = GuestCommentForm::query()
            ->with([
                'creator:id,nama_lengkap,avatar',
                'verifier:id,nama_lengkap,avatar',
                'outlet:id_outlet,nama_outlet',
            ])
            ->orderByDesc('created_at');

        if ($canChooseOutlet) {
            if ($request->filled('id_outlet')) {
                $idOutlet = (int) $request->id_outlet;
                if ($idOutlet > 0) {
                    $query->where('guest_comment_forms.id_outlet', $idOutlet);
                }
            }
        } elseif ($userOutletId > 0) {
            $uid = (int) $request->user()->id;
            $query->where(function ($q) use ($userOutletId, $uid) {
                $q->where('guest_comment_forms.id_outlet', $userOutletId)
                    ->orWhere(function ($q2) use ($uid) {
                        $q2->whereNull('guest_comment_forms.id_outlet')
                            ->where('guest_comment_forms.created_by', $uid);
                    });
            });
        } else {
            $query->whereRaw('0 = 1');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('guest_comment_forms.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('guest_comment_forms.created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('guest_name', 'like', $s)
                    ->orWhere('guest_phone', 'like', $s)
                    ->orWhere('comment_text', 'like', $s)
                    ->orWhere('status', 'like', $s);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));
        $forms = $query->paginate($perPage)->withQueryString();
        $forms->getCollection()->transform(fn (GuestCommentForm $f) => $this->serializeForm($f));

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        return response()->json([
            'success' => true,
            'forms' => $forms,
            'can_choose_outlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'locked_outlet' => $lockedOutlet,
        ]);
    }

    public function store(Request $request, GuestCommentOcrService $ocr): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->file('image')->store('guest_comment_forms', 'public');

        $form = GuestCommentForm::create([
            'image_path' => $path,
            'status' => 'pending_verification',
            'created_by' => $request->user()->id,
        ]);

        $absolute = Storage::disk('public')->path($path);
        $result = $ocr->extract($absolute);

        $payload = [
            'raw_text' => $result['raw_text'] ?? '',
            'fields' => $result['fields'] ?? [],
        ];

        $updates = [
            'ocr_raw_text' => $payload['raw_text'],
            'ocr_payload' => $payload,
        ];

        $fieldKeys = [
            'rating_service', 'rating_food', 'rating_beverage', 'rating_cleanliness',
            'rating_staff', 'rating_value', 'comment_text', 'guest_name', 'guest_address',
            'guest_phone', 'guest_dob', 'visit_date', 'praised_staff_name',
        ];
        foreach ($fieldKeys as $key) {
            $v = $payload['fields'][$key] ?? null;
            if ($v !== null && $v !== '') {
                $updates[$key] = $v;
            }
        }

        $form->update($updates);

        $anyField = false;
        foreach ($fieldKeys as $key) {
            if (! empty($updates[$key])) {
                $anyField = true;
                break;
            }
        }
        $hasRaw = trim((string) ($updates['ocr_raw_text'] ?? '')) !== '';
        $msg = 'Foto tersimpan. Silakan verifikasi data.';
        if (! $anyField && ! $hasRaw) {
            $msg .= ' OCR tidak mengisi field otomatis — periksa konfigurasi AI di server.';
        }

        $form->refresh();
        $form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        return response()->json([
            'success' => true,
            'message' => $msg,
            'form' => $this->serializeForm($form),
        ], 201);
    }

    public function show(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        return response()->json([
            'success' => true,
            'form' => $this->serializeForm($guest_comment_form),
        ]);
    }

    public function update(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        if ($guest_comment_form->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah terverifikasi, tidak bisa diubah.',
            ], 422);
        }

        if ($request->input('id_outlet') === '' || $request->input('id_outlet') === null) {
            $request->merge(['id_outlet' => null]);
        }

        $rules = [
            'rating_service' => ['nullable', Rule::in(self::RATINGS)],
            'rating_food' => ['nullable', Rule::in(self::RATINGS)],
            'rating_beverage' => ['nullable', Rule::in(self::RATINGS)],
            'rating_cleanliness' => ['nullable', Rule::in(self::RATINGS)],
            'rating_staff' => ['nullable', Rule::in(self::RATINGS)],
            'rating_value' => ['nullable', Rule::in(self::RATINGS)],
            'comment_text' => 'nullable|string',
            'guest_name' => 'nullable|string|max:255',
            'guest_address' => 'nullable|string|max:500',
            'guest_phone' => 'nullable|string|max:100',
            'guest_dob' => 'nullable|date',
            'visit_date' => 'nullable|string|max:100',
            'praised_staff_name' => 'nullable|string|max:255',
            'id_outlet' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'mark_verified' => 'nullable|boolean',
        ];

        $data = $request->validate($rules);
        $markVerified = (bool) ($data['mark_verified'] ?? false);
        unset($data['mark_verified']);

        foreach (['rating_service', 'rating_food', 'rating_beverage', 'rating_cleanliness', 'rating_staff', 'rating_value'] as $rk) {
            if (array_key_exists($rk, $data) && $data[$rk] === '') {
                $data[$rk] = null;
            }
        }

        $editorOutletId = (int) ($request->user()->id_outlet ?? 0);
        if ($editorOutletId !== 1) {
            $data['id_outlet'] = $editorOutletId > 0 ? $editorOutletId : null;
        }

        $guest_comment_form->fill($data);

        if ($markVerified) {
            $guest_comment_form->status = 'verified';
            $guest_comment_form->verified_by = $request->user()->id;
            $guest_comment_form->verified_at = now();
        }

        $guest_comment_form->save();
        $guest_comment_form->refresh();
        $guest_comment_form->load(['creator:id,nama_lengkap,avatar', 'verifier:id,nama_lengkap,avatar', 'outlet:id_outlet,nama_outlet']);

        return response()->json([
            'success' => true,
            'message' => $markVerified
                ? 'Data tersimpan dan terverifikasi.'
                : 'Perubahan disimpan (belum terverifikasi).',
            'form' => $this->serializeForm($guest_comment_form),
        ]);
    }

    public function destroy(Request $request, GuestCommentForm $guest_comment_form): JsonResponse
    {
        $this->authorizeGuestCommentFormAccess($request, $guest_comment_form);

        $relativePath = $guest_comment_form->image_path;
        $guest_comment_form->delete();

        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data guest comment berhasil dihapus.',
        ]);
    }
}
