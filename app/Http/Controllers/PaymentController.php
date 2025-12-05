<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAttachment;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseOrderOps;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with([
            'purchaseRequisition',
            'purchaseOrder',
            'supplier',
            'creator',
            'approver'
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(20);

        return Inertia::render('Payment/Index', [
            'payments' => $payments
        ]);
    }

    public function create()
    {
        // Get fully approved POs that don't have payments yet
        $availablePOs = PurchaseOrderOps::where('status', 'approved')
            ->whereDoesntHave('payments')
            ->with(['supplier', 'source_pr', 'source_pr.division'])
            ->get();

        return Inertia::render('Payment/Create', [
            'availablePOs' => $availablePOs,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_requisition_id' => 'required|exists:purchase_requisitions,id',
            'purchase_order_id' => 'nullable|exists:purchase_order_ops,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();

            // Create payment
            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'purchase_requisition_id' => $request->purchase_requisition_id,
                'purchase_order_id' => $request->purchase_order_id,
                'supplier_id' => $request->supplier_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'status' => 'paid', // Langsung set sebagai paid tanpa approval
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(), // Set creator sebagai approver
                'approved_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('payments.show', $payment->id)
                ->with('success', 'Payment created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment creation error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create payment']);
        }
    }

    public function show(Payment $payment)
    {
        $payment->load([
            'purchaseRequisition.division',
            'purchaseRequisition.creator',
            'purchaseOrder',
            'supplier',
            'creator',
            'approver',
            'attachments.uploader'
        ]);

        return Inertia::render('Payment/Show', [
            'payment' => $payment
        ]);
    }

    public function edit(Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->withErrors(['error' => 'Cannot edit payment that is not pending']);
        }

        $payment->load([
            'purchaseRequisition',
            'purchaseOrder',
            'supplier'
        ]);

        return Inertia::render('Payment/Edit', [
            'payment' => $payment
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->withErrors(['error' => 'Cannot edit payment that is not pending']);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,check',
            'payment_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:payment_date',
            'description' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
        ]);

        $payment->update($request->only([
            'amount',
            'payment_method',
            'payment_date',
            'due_date',
            'description',
            'reference_number'
        ]));

        return redirect()->route('payments.show', $payment->id)
            ->with('success', 'Payment updated successfully');
    }

    public function destroy(Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->withErrors(['error' => 'Cannot delete payment that is not pending']);
        }

        $payment->delete();

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted successfully');
    }


    // Attachment methods
    public function uploadAttachment(Request $request, Payment $payment)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . $originalName;
            $filePath = $file->storeAs('payments/attachments', $fileName, 'public');
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $attachment = PaymentAttachment::create([
                'payment_id' => $payment->id,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment' => $attachment->load('uploader'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Upload attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAttachment(PaymentAttachment $attachment)
    {
        try {
            // Check if user can delete (creator or admin)
            if ($attachment->uploaded_by !== Auth::id() && !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this attachment',
                ], 403);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Delete database record
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadAttachment(PaymentAttachment $attachment)
    {
        try {
            if (!Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);

        } catch (\Exception $e) {
            \Log::error('Download attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function viewAttachment(PaymentAttachment $attachment)
    {
        try {
            if (!Storage::disk('public')->exists($attachment->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            // Check if it's an image
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
            if (!in_array($extension, $imageExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is not an image',
                ], 400);
            }

            $file = Storage::disk('public')->get($attachment->file_path);
            $mimeType = Storage::disk('public')->mimeType($attachment->file_path);

            return response($file, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $attachment->file_name . '"');

        } catch (\Exception $e) {
            \Log::error('View attachment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view file: ' . $e->getMessage(),
            ], 500);
        }
    }


    // Private methods
    private function generatePaymentNumber()
    {
        $year = date('Y');
        $month = date('m');
        
        $lastPayment = Payment::whereYear('created_at', $year)
                             ->whereMonth('created_at', $month)
                             ->orderBy('id', 'desc')
                             ->first();
        
        $sequence = $lastPayment ? (int) substr($lastPayment->payment_number, -4) + 1 : 1;
        
        return "PAY{$year}{$month}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

}
