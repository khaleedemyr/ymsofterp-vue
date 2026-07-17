<?php

namespace App\Http\Controllers;

use App\Models\ManualCsComplaint;
use App\Models\Outlet;
use App\Services\ManualCsComplaintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ManualCsComplaintController extends Controller
{
    public function __construct(
        private readonly ManualCsComplaintService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $syncStatus = trim((string) $request->get('sync_status', ''));
        $idOutlet = $request->get('id_outlet');

        $records = ManualCsComplaint::query()
            ->with(['creator:id,nama_lengkap', 'outlet:id_outlet,nama_outlet'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('customer_contact', 'like', "%{$search}%")
                        ->orWhere('complaint_text', 'like', "%{$search}%");
                });
            })
            ->when($syncStatus !== '', fn ($q) => $q->where('sync_status', $syncStatus))
            ->when($idOutlet, fn ($q) => $q->where('id_outlet', $idOutlet))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ManualCsComplaint/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'sync_status' => $syncStatus,
                'id_outlet' => $idOutlet,
            ],
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ManualCsComplaint/Create', [
            'outlets' => Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet']),
            'topicOptions' => $this->topicOptions(),
            'channelOptions' => $this->channelOptions(),
            'severityOptions' => $this->severityOptions(),
            'now' => now()->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_outlet' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'author_name' => 'required|string|max:255',
            'customer_contact' => 'nullable|string|max:120',
            'customer_email' => 'nullable|email|max:255',
            'input_channel' => 'required|string|in:phone,walk_in,email,whatsapp_cs,other',
            'event_at' => 'required|date',
            'severity' => 'required|string|in:critical,major,minor',
            'topics' => 'nullable|array',
            'topics.*' => 'string|max:64',
            'summary' => 'nullable|string|max:500',
            'complaint_text' => 'required|string|max:10000',
            'notes' => 'nullable|string|max:2000',
            'sync_to_cvcc' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $complaint = ManualCsComplaint::create([
                'number' => $this->service->generateNumber(),
                'id_outlet' => $validated['id_outlet'] ?? null,
                'author_name' => $validated['author_name'],
                'customer_contact' => $validated['customer_contact'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'input_channel' => $validated['input_channel'],
                'event_at' => $validated['event_at'],
                'severity' => $validated['severity'],
                'topics' => array_values(array_filter($validated['topics'] ?? [])),
                'summary' => $validated['summary'] ?? null,
                'complaint_text' => $validated['complaint_text'],
                'notes' => $validated['notes'] ?? null,
                'sync_status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            $syncToCvcc = $request->boolean('sync_to_cvcc', true);
            if ($syncToCvcc) {
                $this->service->syncToCvcc($complaint, Auth::user());
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('manual-cs-complaints.index')->with('success', 'Complaint CS berhasil disimpan.');
    }

    public function sync(ManualCsComplaint $manualCsComplaint)
    {
        try {
            $result = $this->service->syncToCvcc($manualCsComplaint, Auth::user());
        } catch (\Throwable $e) {
            $manualCsComplaint->update(['sync_status' => 'failed', 'updated_by' => Auth::id()]);

            return back()->with('error', 'Gagal sync ke CVCC: '.$e->getMessage());
        }

        $message = $result['created']
            ? 'Complaint berhasil disync ke CVCC.'
            : 'Complaint sudah ada di CVCC.';

        return back()->with('success', $message);
    }

    public function destroy(ManualCsComplaint $manualCsComplaint)
    {
        if ((string) (Auth::user()?->id_role ?? '') !== '5af56935b011a') {
            abort(403, 'Hanya superadmin yang dapat menghapus data complaint CS.');
        }

        $manualCsComplaint->delete();

        return redirect()->route('manual-cs-complaints.index')->with('success', 'Complaint CS berhasil dihapus.');
    }

    private function topicOptions(): array
    {
        return [
            ['value' => 'service', 'label' => 'Pelayanan'],
            ['value' => 'food', 'label' => 'Makanan / Minuman'],
            ['value' => 'cleanliness', 'label' => 'Kebersihan'],
            ['value' => 'waiting_time', 'label' => 'Waktu Tunggu'],
            ['value' => 'staff', 'label' => 'Staff'],
            ['value' => 'billing', 'label' => 'Tagihan / Pembayaran'],
            ['value' => 'facility', 'label' => 'Fasilitas'],
        ];
    }

    private function channelOptions(): array
    {
        return [
            ['value' => 'phone', 'label' => 'Telepon'],
            ['value' => 'walk_in', 'label' => 'Walk-in'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'whatsapp_cs', 'label' => 'WhatsApp CS'],
            ['value' => 'other', 'label' => 'Lainnya'],
        ];
    }

    private function severityOptions(): array
    {
        return [
            ['value' => 'critical', 'label' => 'Critical'],
            ['value' => 'major', 'label' => 'Major'],
            ['value' => 'minor', 'label' => 'Minor'],
        ];
    }
}
