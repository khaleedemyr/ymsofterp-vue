<?php

namespace App\Http\Controllers\AssetManagement;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetBrand;
use App\Models\DataOutlet;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $categoryId = $request->get('category_id', '');
        $outletId = $request->get('outlet_id', '');
        $status = $request->get('status', 'all');
        $perPage = $request->get('per_page', 15);

        $query = Asset::with(['category', 'currentOutlet', 'creator']);

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        // Outlet filter
        if ($outletId !== '') {
            if ($outletId === 'null') {
                $query->whereNull('current_outlet_id');
            } else {
                $query->where('current_outlet_id', $outletId);
            }
        }

        // Status filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('asset_code')->paginate($perPage)->withQueryString();

        // Get filter options
        $categories = AssetCategory::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        return Inertia::render('AssetManagement/Assets/Index', [
            'assets' => $assets,
            'categories' => $categories,
            'outlets' => $outlets,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'outlet_id' => $outletId,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = AssetCategory::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $suppliers = Supplier::whereIn('status', ['A', 'active'])
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = AssetBrand::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Generate next asset code
        $nextCode = $this->generateNextAssetCode();

        return Inertia::render('AssetManagement/Assets/Create', [
            'categories' => $categories,
            'outlets' => $outlets,
            'suppliers' => $suppliers,
            'brands' => $brands,
            'nextCode' => $nextCode,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_code' => 'required|string|max:100|unique:assets,asset_code',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'brand' => 'nullable|string|max:255',
            'brand_id' => 'nullable|exists:asset_brands,id',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier' => 'nullable|string|max:255',
            'current_outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'status' => 'required|in:Active,Maintenance,Disposed,Lost,Transfer',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'useful_life' => 'nullable|integer|min:1',
            'warranty_expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Handle photo uploads
            $photos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('assets/photos', 'public');
                    $photos[] = $path;
                }
            }

            // Get supplier name if supplier_id is provided
            $supplierName = null;
            if ($request->supplier_id) {
                $supplier = Supplier::find($request->supplier_id);
                $supplierName = $supplier ? $supplier->name : $request->supplier;
            } else {
                $supplierName = $request->supplier;
            }

            // Get brand name if brand_id is provided
            $brandName = null;
            if ($request->brand_id) {
                $brand = AssetBrand::find($request->brand_id);
                $brandName = $brand ? $brand->name : $request->brand;
            } else {
                $brandName = $request->brand;
            }

            $asset = Asset::create([
                'asset_code' => $request->asset_code,
                'name' => $request->name,
                'category_id' => $request->category_id,
                'brand' => $brandName,
                'model' => $request->model,
                'serial_number' => $request->serial_number,
                'purchase_date' => $request->purchase_date,
                'purchase_price' => $request->purchase_price,
                'supplier' => $supplierName,
                'current_outlet_id' => $request->current_outlet_id,
                'status' => $request->status,
                'photos' => !empty($photos) ? $photos : null,
                'description' => $request->description,
                'useful_life' => $request->useful_life,
                'warranty_expiry_date' => $request->warranty_expiry_date,
                'created_by' => auth()->id(),
            ]);

            // Generate QR code
            $this->generateQrCode($asset);

            DB::commit();

            return redirect()->route('asset-management.assets.index')
                ->with('success', 'Asset created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to create asset: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $asset = Asset::with([
            'category',
            'currentOutlet',
            'creator',
            'transfers' => function($q) {
                $q->latest()->limit(10);
            },
            'maintenanceSchedules',
            'maintenances' => function($q) {
                $q->latest()->limit(10);
            },
            'disposals',
            'documents',
            'depreciation',
        ])->findOrFail($id);
        
        return Inertia::render('AssetManagement/Assets/Show', [
            'asset' => $asset,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $asset = Asset::findOrFail($id);
        
        $categories = AssetCategory::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        
        $outlets = DataOutlet::select('id_outlet as id', 'nama_outlet as name')
            ->orderBy('nama_outlet')
            ->get();

        $suppliers = Supplier::whereIn('status', ['A', 'active'])
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = AssetBrand::active()
            ->orderBy('name')
            ->get(['id', 'name']);
        
        return Inertia::render('AssetManagement/Assets/Edit', [
            'asset' => $asset,
            'categories' => $categories,
            'outlets' => $outlets,
            'suppliers' => $suppliers,
            'brands' => $brands,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'asset_code' => 'required|string|max:100|unique:assets,asset_code,' . $id,
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'brand' => 'nullable|string|max:255',
            'brand_id' => 'nullable|exists:asset_brands,id',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier' => 'nullable|string|max:255',
            'current_outlet_id' => 'nullable|exists:tbl_data_outlet,id_outlet',
            'status' => 'required|in:Active,Maintenance,Disposed,Lost,Transfer',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'useful_life' => 'nullable|integer|min:1',
            'warranty_expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Handle photo uploads
            $existingPhotos = $asset->photos ?? [];
            $newPhotos = [];
            
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('assets/photos', 'public');
                    $newPhotos[] = $path;
                }
            }

            // Merge existing and new photos if needed
            $photos = $request->has('keep_existing_photos') 
                ? array_merge($existingPhotos, $newPhotos)
                : $newPhotos;

            // Get supplier name if supplier_id is provided
            $supplierName = null;
            if ($request->supplier_id) {
                $supplier = Supplier::find($request->supplier_id);
                $supplierName = $supplier ? $supplier->name : $request->supplier;
            } else {
                $supplierName = $request->supplier;
            }

            // Get brand name if brand_id is provided
            $brandName = null;
            if ($request->brand_id) {
                $brand = AssetBrand::find($request->brand_id);
                $brandName = $brand ? $brand->name : $request->brand;
            } else {
                $brandName = $request->brand;
            }

            $asset->update([
                'asset_code' => $request->asset_code,
                'name' => $request->name,
                'category_id' => $request->category_id,
                'brand' => $brandName,
                'model' => $request->model,
                'serial_number' => $request->serial_number,
                'purchase_date' => $request->purchase_date,
                'purchase_price' => $request->purchase_price,
                'supplier' => $supplierName,
                'current_outlet_id' => $request->current_outlet_id,
                'status' => $request->status,
                'photos' => !empty($photos) ? $photos : null,
                'description' => $request->description,
                'useful_life' => $request->useful_life,
                'warranty_expiry_date' => $request->warranty_expiry_date,
            ]);

            DB::commit();

            return redirect()->route('asset-management.assets.index')
                ->with('success', 'Asset updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'Failed to update asset: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);

        // Check if asset has related records
        if ($asset->transfers()->count() > 0 || 
            $asset->maintenances()->count() > 0 || 
            $asset->disposals()->count() > 0) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete asset with existing transfers, maintenances, or disposals',
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete asset with existing transfers, maintenances, or disposals');
        }

        $asset->delete();

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Asset deleted successfully',
            ], 200);
        }

        return redirect()->route('asset-management.assets.index')
            ->with('success', 'Asset deleted successfully');
    }

    /**
     * Generate next asset code
     */
    private function generateNextAssetCode(): string
    {
        $year = date('Y');
        $prefix = "AST-{$year}-";
        
        $lastAsset = Asset::where('asset_code', 'like', "{$prefix}%")
            ->orderBy('asset_code', 'desc')
            ->first();
        
        if ($lastAsset) {
            $lastNumber = (int) substr($lastAsset->asset_code, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate QR code for asset
     */
    private function generateQrCode(Asset $asset): void
    {
        $qrData = json_encode([
            'asset_id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->name,
        ]);

        $asset->update([
            'qr_code' => $qrData,
            // QR code image will be generated by frontend or separate service
        ]);
    }

    /**
     * Generate QR code image
     */
    public function generateQrCodeImage($id)
    {
        $asset = Asset::findOrFail($id);
        
        // TODO: Implement QR code image generation using a library like SimpleSoftwareIO/simple-qrcode
        // For now, return the QR code data
        
        return response()->json([
            'success' => true,
            'qr_code' => $asset->qr_code,
            'message' => 'QR code generated successfully',
        ]);
    }

    /**
     * Create new brand
     */
    public function createBrand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:asset_brands,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $brand = AssetBrand::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'data' => $brand,
        ], 201);
    }
}

