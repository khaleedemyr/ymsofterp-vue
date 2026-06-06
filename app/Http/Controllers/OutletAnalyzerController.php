<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\OutletAnalyzerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OutletAnalyzerController extends Controller
{
    public function __construct(
        private OutletAnalyzerService $analyzer,
    ) {}

    public function index(Request $request)
    {
        $userOutletId = (int) ($request->user()->id_outlet ?? 0);
        $canChooseOutlet = ($userOutletId === 1);

        $month = trim((string) $request->get('month', now()->format('Y-m')));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $selectedOutletId = 0;
        if ($canChooseOutlet) {
            $selectedOutletId = (int) $request->get('id_outlet', 0);
        } elseif ($userOutletId > 0) {
            $selectedOutletId = $userOutletId;
        }

        $outlets = $canChooseOutlet
            ? Outlet::where('status', 'A')->where('is_outlet', 1)->orderBy('nama_outlet')->get(['id_outlet', 'nama_outlet'])
            : collect();

        $lockedOutlet = null;
        if (! $canChooseOutlet && $userOutletId > 0) {
            $lockedOutlet = Outlet::where('id_outlet', $userOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
        }

        $selectedOutlet = null;
        $analysis = null;

        if ($selectedOutletId > 0) {
            $selectedOutlet = Outlet::where('id_outlet', $selectedOutletId)->where('status', 'A')->first(['id_outlet', 'nama_outlet']);
            if ($selectedOutlet) {
                $analysis = $this->analyzer->analyze($selectedOutletId, $month);
            }
        }

        $period = $this->analyzer->calendarPeriod($month);

        return Inertia::render('OutletAnalyzer/Index', [
            'filters' => [
                'month' => $month,
                'id_outlet' => $canChooseOutlet ? ($selectedOutletId > 0 ? $selectedOutletId : null) : $selectedOutletId,
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
            ],
            'canChooseOutlet' => $canChooseOutlet,
            'outlets' => $outlets,
            'lockedOutlet' => $lockedOutlet,
            'selectedOutlet' => $selectedOutlet,
            'analysis' => $analysis,
        ]);
    }
}
