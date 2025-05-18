<?php

namespace App\Http\Controllers;

use App\Models\FoodFloorOrder;
use App\Models\FoodFloorOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;

class FoodFloorOrderController extends Controller
{
    // Tampilkan form edit draft
    public function edit($id)
    {
        $order = FoodFloorOrder::with('items')->findOrFail($id);
        return Inertia::render('FloorOrder/Form', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Buat draft baru
    public function store(Request $request)
    {
        $user = Auth::user();
        $order = FoodFloorOrder::create([
            'tanggal' => now()->toDateString(),
            'fo_mode' => $request->fo_mode,
            'input_mode' => $request->input_mode,
            'id_outlet' => $user->id_outlet,
            'user_id' => $user->id,
            'status' => 'draft',
            'order_number' => null, // diisi saat submit
            'fo_schedule_id' => $request->fo_schedule_id,
        ]);
        // Simpan items dengan category_id dari master item
        foreach ($request->items as $item) {
            $masterItem = Item::find($item['item_id']);
            $order->items()->create([
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'category_id' => $masterItem ? $masterItem->category_id : null,
            ]);
        }
        // Notifikasi ke Executive Chef jika FO Khusus
        if ($order->fo_mode === 'FO Khusus') {
            $executiveChefs = \DB::table('users')->where('id_jabatan', 163)->where('status', 'A')->pluck('id');
            $requester = $user->name ?? ($user->nama_lengkap ?? '-');
            $outletName = $user->outlet->nama_outlet ?? '-';
            $this->sendNotification(
                $executiveChefs,
                'fo_approval',
                'Approval Floor Order Khusus',
                "Floor Order Khusus dari $requester ($outletName) menunggu approval Anda.",
                route('floor-order.edit', $order->id)
            );
        }
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'create',
            'module' => 'food_floor_order',
            'description' => 'Membuat Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->toArray(),
        ]);
        return response()->json(['id' => $order->id]);
    }

    // Autosave draft (update)
    public function update(Request $request, $id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        $oldData = $order->toArray();
        $order->update($request->only(['tanggal', 'description', 'fo_mode', 'input_mode', 'fo_schedule_id']));
        // Update items (bisa dioptimasi, ini contoh sederhana)
        $order->items()->delete();
        foreach ($request->items as $item) {
            $masterItem = Item::find($item['item_id']);
            $order->items()->create([
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'category_id' => $masterItem ? $masterItem->category_id : null,
            ]);
        }
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'update',
            'module' => 'food_floor_order',
            'description' => 'Update Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $order->fresh()->toArray(),
        ]);
        return response()->json(['success' => true]);
    }

    // Hapus draft
    public function destroy($id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        $oldData = $order->toArray();
        if (!in_array($order->status, ['draft', 'approved', 'submitted'])) {
            return response()->json(['error' => 'Tidak bisa hapus selain draft, approved, atau submitted'], 422);
        }
        $order->items()->delete();
        $order->delete();
        \App\Models\ActivityLog::create([
            'user_id' => Auth::id(),
            'activity_type' => 'delete',
            'module' => 'food_floor_order',
            'description' => 'Menghapus Floor Order: ' . $oldData['id'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_data' => $oldData,
            'new_data' => null,
        ]);
        return response()->json(['success' => true]);
    }

    // Submit draft
    public function submit(Request $request, $id)
    {
        $order = FoodFloorOrder::findOrFail($id);
        // Generate order_number
        $date = now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)); // 4 karakter acak angka+huruf
        $order_number = 'FO-' . $date . '-' . $random;

        $order->update([
            'status' => $order->fo_mode === 'FO Khusus' ? 'submitted' : 'approved',
            'order_number' => $order_number,
        ]);
        return response()->json(['success' => true]);
    }

    // Cek apakah sudah ada FO Utama/Tambahan di hari dan outlet yang sama
    public function checkExists(Request $request)
    {
        $tanggal = $request->tanggal;
        $id_outlet = $request->id_outlet;
        $fo_mode = $request->fo_mode;
        $exclude_id = $request->exclude_id;

        $query = \App\Models\FoodFloorOrder::where('tanggal', $tanggal)
            ->where('id_outlet', $id_outlet)
            ->where('fo_mode', $fo_mode)
            ->whereNotIn('status', ['rejected']);

        if ($exclude_id) {
            $query->where('id', '!=', $exclude_id);
        }

        $exists = $query->exists();
        return response()->json(['exists' => $exists]);
    }

    public function show($id)
    {
        $order = FoodFloorOrder::with(['items.category', 'outlet', 'requester', 'foSchedule', 'approver'])->findOrFail($id);
        return Inertia::render('FloorOrder/Show', [
            'order' => $order,
            'user' => Auth::user()->load('outlet'),
        ]);
    }

    // Tambahkan method sendNotification
    private function sendNotification($userIds, $type, $title, $message, $url) {
        $now = now();
        $data = [];
        foreach ($userIds as $uid) {
            $data[] = [
                'user_id' => $uid,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'url' => $url,
                'is_read' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        \DB::table('notifications')->insert($data);
    }

    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $order = \App\Models\FoodFloorOrder::findOrFail($id);

        // Cek hak akses
        $isSuperadmin = $user->id_role === '5af56935b011a' && $user->status === 'A';
        $isExecutiveChef = $user->id_jabatan == 163 && $user->status === 'A';
        if (!($isSuperadmin || $isExecutiveChef)) {
            abort(403, 'Unauthorized');
        }

        if ($order->fo_mode !== 'FO Khusus' || $order->status !== 'submitted') {
            abort(400, 'Tidak bisa approve order ini');
        }

        $order->update([
            'status' => 'approved',
            'approval_by' => $user->id,
            'approval_at' => now(),
            'approval_notes' => $request->notes,
        ]);
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'approve',
            'module' => 'food_floor_order',
            'description' => 'Approve Floor Order: ' . $order->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_data' => null,
            'new_data' => $order->fresh()->toArray(),
        ]);
        return redirect()->back()->with('success', 'Floor Order berhasil di-approve');
    }
} 