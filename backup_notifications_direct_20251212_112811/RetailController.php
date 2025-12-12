<?php

namespace App\Http\Controllers;

use App\Models\Retail;
use App\Models\RetailItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class RetailController extends Controller
{
    /**
     * Store a newly created retail data in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            \Log::info('Received retail store request', [
                'task_id' => $request->task_id,
                'nama_toko' => $request->nama_toko,
                'alamat_toko' => $request->alamat_toko,
                'items' => $request->items,
                'has_invoice_files' => $request->hasFile('invoice_files'),
                'has_barang_files' => $request->hasFile('barang_files'),
            ]);

            $request->validate([
                'task_id' => 'required|exists:maintenance_tasks,id',
                'nama_toko' => 'required|string',
                'alamat_toko' => 'required|string',
                'items' => 'required|string',
                'invoice_files.*' => 'nullable|file|image|max:2048',
                'barang_files.*.*' => 'nullable|file|image|max:2048',
            ]);

            DB::beginTransaction();

            try {
                // Decode items JSON
                $items = json_decode($request->items, true);
                \Log::info('Decoded items data', ['items' => $items]);

                if (!is_array($items)) {
                    throw new \Exception('Invalid items data');
                }

                // Create retail entry
                $retail = Retail::create([
                    'task_id' => $request->task_id,
                    'created_by' => auth()->id(),
                    'nama_toko' => $request->nama_toko,
                    'alamat_toko' => $request->alamat_toko,
                ]);
                \Log::info('Created retail entry', ['retail_id' => $retail->id]);

                // Get task details for notification
                $task = DB::table('maintenance_tasks')
                    ->join('tbl_data_outlet', 'maintenance_tasks.id_outlet', '=', 'tbl_data_outlet.id_outlet')
                    ->select('maintenance_tasks.task_number', 'tbl_data_outlet.nama_outlet as nama_outlet')
                    ->where('maintenance_tasks.id', $request->task_id)
                    ->first();

                // Get creator name
                $creator = DB::table('users')
                    ->select('nama_lengkap')
                    ->where('id', auth()->id())
                    ->first();

                // Handle invoice files
                if ($request->hasFile('invoice_files')) {
                    foreach ($request->file('invoice_files') as $file) {
                        $path = $file->store('retail/invoice', 'public');
                        // Create invoice image record directly without creating retail item
                        DB::table('retail_invoice_images')->insert([
                            'retail_item_id' => 0, // temporary value, will update after creating actual items
                            'file_path' => $path,
                            'created_at' => now(),
                        ]);
                        \Log::info('Stored invoice file', ['path' => $path]);
                    }
                }

                // Create retail items
                foreach ($items as $index => $itemData) {
                    \Log::info('Processing item', ['index' => $index, 'data' => $itemData]);
                    
                    $item = $retail->items()->create([
                        'nama_barang' => $itemData['nama_barang'],
                        'qty' => $itemData['qty'],
                        'harga_barang' => $itemData['harga_barang'],
                        'subtotal' => $itemData['subtotal']
                    ]);
                    \Log::info('Created retail item', ['item_id' => $item->id]);

                    // Update invoice images with the first item's id
                    if ($index === 0) {
                        DB::table('retail_invoice_images')
                            ->where('retail_item_id', 0)
                            ->update(['retail_item_id' => $item->id]);
                    }

                    // Handle barang files for this item
                    $barangFilesKey = "barang_files.{$index}";
                    if ($request->hasFile($barangFilesKey)) {
                        foreach ($request->file($barangFilesKey) as $file) {
                            $path = $file->store('retail/barang', 'public');
                            DB::table('retail_barang_images')->insert([
                                'retail_item_id' => $item->id,
                                'file_path' => $path,
                                'created_at' => now(),
                            ]);
                            \Log::info('Stored barang file', ['item_id' => $item->id, 'path' => $path]);
                        }
                    }
                }

                // Kirim notifikasi ke semua member task
                $taskMembers = DB::table('maintenance_members')
                    ->where('task_id', $request->task_id)
                    ->pluck('user_id');

                // Kirim notifikasi ke semua user yang berkomentar di task
                $commentUsers = DB::table('maintenance_comments')
                    ->where('task_id', $request->task_id)
                    ->pluck('user_id');

                // Gabungkan dan hapus duplikat
                $notifyUsers = $taskMembers->merge($commentUsers)->unique();

                $notificationMessage = "Retail Baru Dibuat: Retail baru telah dibuat untuk task #{$task->task_number} - {$task->nama_outlet} oleh {$creator->nama_lengkap} (Toko: {$request->nama_toko})";

                foreach ($notifyUsers as $userId) {
                    // Skip if user is the creator
                    if ($userId == auth()->id()) continue;

                    DB::table('notifications')->insert([
                        'user_id' => $userId,
                        'task_id' => $request->task_id,
                        'type' => 'retail_created',
                        'message' => $notificationMessage,
                        'url' => '/maintenance-order/' . $request->task_id,
                        'is_read' => 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Simpan log aktivitas maintenance
                DB::table('maintenance_activity_logs')->insert([
                    'task_id' => $request->task_id,
                    'user_id' => auth()->id(),
                    'activity_type' => 'RETAIL_CREATED',
                    'description' => 'Membuat retail baru',
                    'created_at' => now()
                ]);

                // Simpan log aktivitas umum
                DB::table('activity_logs')->insert([
                    'user_id' => auth()->id(),
                    'activity_type' => 'create',
                    'module' => 'retail',
                    'description' => 'Membuat retail baru untuk task #' . $task->task_number,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_data' => null,
                    'new_data' => json_encode([
                        'task_id' => $request->task_id,
                        'nama_toko' => $request->nama_toko,
                        'alamat_toko' => $request->alamat_toko,
                        'items' => $items
                    ]),
                    'created_at' => now()
                ]);

                DB::commit();
                \Log::info('Successfully saved retail data');

                return response()->json([
                    'success' => true,
                    'message' => 'Retail items saved successfully',
                    'data' => $retail->load('items.invoiceImages', 'items.barangImages'),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Error in retail store transaction', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        } catch (\Exception $e) {
            \Log::error('Error saving retail items', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save retail items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get retail data for a specific task.
     *
     * @param  int  $taskId
     * @return \Illuminate\Http\Response
     */
    public function getByTask($taskId)
    {
        try {
            \Log::info('Getting retail data for task', ['task_id' => $taskId]);
            
            $retailData = DB::table('retail')
                ->join('users', 'retail.created_by', '=', 'users.id')
                ->where('retail.task_id', $taskId)
                ->select(
                    'retail.id',
                    'retail.nama_toko',
                    'retail.alamat_toko',
                    'retail.created_at',
                    'users.nama_lengkap as created_by_name'
                )
                ->orderBy('retail.created_at', 'desc')
                ->get();

            // Get items and images for each retail
            foreach ($retailData as $retail) {
                \Log::info('Processing retail', ['retail_id' => $retail->id]);
                
                // Get retail items
                $retail->items = DB::table('retail_items')
                    ->where('retail_id', $retail->id)
                    ->get();

                \Log::info('Found items for retail', [
                    'retail_id' => $retail->id,
                    'items_count' => count($retail->items)
                ]);

                // Get invoice and barang images for each item
                foreach ($retail->items as $item) {
                    $item->invoice_images = DB::table('retail_invoice_images')
                        ->where('retail_item_id', $item->id)
                        ->get()
                        ->map(function ($image) {
                            $image->url = Storage::url($image->file_path);
                            return $image;
                        });

                    $item->barang_images = DB::table('retail_barang_images')
                        ->where('retail_item_id', $item->id)
                        ->get()
                        ->map(function ($image) {
                            $image->url = Storage::url($image->file_path);
                            return $image;
                        });

                    \Log::info('Images for item', [
                        'item_id' => $item->id,
                        'invoice_images_count' => count($item->invoice_images),
                        'barang_images_count' => count($item->barang_images)
                    ]);
                }
            }

            \Log::info('Successfully retrieved retail data', [
                'retail_count' => count($retailData)
            ]);

            return response()->json([
                'success' => true,
                'data' => $retailData
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting retail data', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data retail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a specific retail image.
     *
     * @param  int  $imageId
     * @param  string  $type
     * @return \Illuminate\Http\Response
     */
    public function deleteImage($imageId, $type)
    {
        try {
            $table = $type === 'invoice' ? 'retail_invoice_images' : 'retail_barang_images';
            $image = DB::table($table)->where('id', $imageId)->first();
            
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image tidak ditemukan'
                ], 404);
            }

            // Hapus file dari storage
            Storage::disk('public')->delete($image->file_path);
            
            // Hapus record dari database
            DB::table($table)->where('id', $imageId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus image: ' . $e->getMessage()
            ], 500);
        }
    }
} 