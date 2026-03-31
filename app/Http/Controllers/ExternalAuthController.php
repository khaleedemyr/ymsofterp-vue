<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Report\SalesReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExternalAuthController extends Controller
{
    public function showLogin(): Response
    {
        if (Auth::guard('external')->check()) {
            redirect()->route('external.sales-report')->send();
        }

        return Inertia::render('External/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::guard('external')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $externalUser = Auth::guard('external')->user();
        if (!$externalUser || $externalUser->status !== 'A') {
            Auth::guard('external')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun external tidak aktif.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('external.sales-report');
    }

    public function salesReport(): Response
    {
        $externalUser = Auth::guard('external')->user();

        return Inertia::render('Report/ReportSalesSimple', [
            'externalMode' => true,
            'externalOutlet' => $externalUser?->kode_outlet ?? '',
        ]);
    }

    public function outlets()
    {
        $externalUser = Auth::guard('external')->user();

        $query = DB::table('tbl_data_outlet')
            ->select('id_outlet as id', 'nama_outlet as name', 'qr_code')
            ->where('status', 'A');

        // Batasi outlet sesuai mapping user external
        if (!empty($externalUser?->kode_outlet)) {
            $query->where('qr_code', $externalUser->kode_outlet);
        }

        $outlets = $query->orderBy('nama_outlet')->get();

        return response()->json([
            'outlets' => $outlets,
        ]);
    }

    public function salesSimpleReportApi(Request $request, SalesReportController $salesReportController)
    {
        $externalUser = Auth::guard('external')->user();

        if (!empty($externalUser?->kode_outlet)) {
            $request->merge([
                'outlet' => $externalUser->kode_outlet,
            ]);
        }

        return $salesReportController->reportSalesSimple($request);
    }

    public function logout(Request $request)
    {
        Auth::guard('external')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('external.login');
    }
}
