<?php

namespace App\Http\Controllers;

use App\Models\MenuBook;
use App\Models\MenuBookPage;
use App\Models\Item;
use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

class MenuBookController extends Controller
{
    // List semua menu books
    public function index(Request $request)
    {
        $query = MenuBook::withCount('pages');

        // Filter by outlet
        if ($request->filled('outlet')) {
            $query->whereHas('outlets', function($q) use ($request) {
                $q->where('id_outlet', $request->outlet);
            });
        }

        $books = $query->with('outlets')->orderBy('created_at', 'desc')->get();
        $outlets = \DB::table('tbl_data_outlet')->where('status', 'A')->orderBy('nama_outlet')->get();

        return Inertia::render('MenuBook/Index', [
            'books' => $books,
            'outlets' => $outlets,
            'filters' => $request->only(['outlet']),
        ]);
    }

    // Show pages dari satu menu book
    public function show(Request $request, MenuBook $menuBook)
    {
        $query = $menuBook->pages()->with(['items', 'categories'])->orderBy('page_order', 'asc');

        // Filter search by items
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('items', function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('sku', 'like', "%$search%");
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('category_id', $request->category);
            });
        }

        $pages = $query->get();
        
        // Load outlets untuk menu book
        $menuBook->load('outlets');
        
        $categories = Category::all();
        $subCategories = SubCategory::where('status', 'active')->get();
        $items = Item::where('status', 'active')
            ->with(['category', 'subCategory'])
            ->orderBy('name')
            ->get();

        return Inertia::render('MenuBook/Show', [
            'menuBook' => $menuBook,
            'pages' => $pages,
            'categories' => $categories,
            'subCategories' => $subCategories,
            'items' => $items,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    // CRUD Menu Books
    public function storeBook(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        // Parse outlet_ids
        $outletIds = [];
        if ($request->has('outlet_ids')) {
            $outletIdsData = is_string($request->outlet_ids) ? json_decode($request->outlet_ids, true) : $request->outlet_ids;
            $outletIds = is_array($outletIdsData) ? $outletIdsData : [];
        }

        // Validate outlet_ids
        if (!empty($outletIds)) {
            foreach ($outletIds as $outletId) {
                if (!\DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->where('status', 'A')->exists()) {
                    return back()->withErrors(['outlet_ids' => 'Invalid outlet ID: ' . $outletId]);
                }
            }
        }

        DB::beginTransaction();
        try {
            $book = MenuBook::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            // Attach outlets
            if (!empty($outletIds)) {
                $book->outlets()->attach($outletIds);
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'menu_book',
                'description' => 'Membuat menu book baru: ' . $book->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $book->toArray()
            ]);

            DB::commit();

            return redirect()->route('menu-book.index')->with('success', 'Menu book berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal membuat menu book: ' . $e->getMessage()]);
        }
    }

    public function updateBook(Request $request, MenuBook $menuBook)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        // Parse outlet_ids
        $outletIds = [];
        if ($request->has('outlet_ids')) {
            $outletIdsData = is_string($request->outlet_ids) ? json_decode($request->outlet_ids, true) : $request->outlet_ids;
            $outletIds = is_array($outletIdsData) ? $outletIdsData : [];
        }

        // Validate outlet_ids
        if (!empty($outletIds)) {
            foreach ($outletIds as $outletId) {
                if (!\DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->where('status', 'A')->exists()) {
                    return back()->withErrors(['outlet_ids' => 'Invalid outlet ID: ' . $outletId]);
                }
            }
        }

        DB::beginTransaction();
        try {
            $oldData = $menuBook->toArray();
            $menuBook->update($validated);

            // Sync outlets
            if ($request->has('outlet_ids')) {
                $menuBook->outlets()->sync($outletIds);
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'menu_book',
                'description' => 'Mengupdate menu book: ' . $menuBook->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData,
                'new_data' => $menuBook->fresh()->toArray()
            ]);

            DB::commit();

            return redirect()->route('menu-book.index')->with('success', 'Menu book berhasil diupdate!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mengupdate menu book: ' . $e->getMessage()]);
        }
    }

    public function destroyBook(MenuBook $menuBook)
    {
        DB::beginTransaction();
        try {
            $oldData = $menuBook->toArray();
            $menuBook->delete();

            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'menu_book',
                'description' => 'Menghapus menu book: ' . $menuBook->name,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
                'new_data' => null
            ]);

            DB::commit();

            return redirect()->route('menu-book.index')->with('success', 'Menu book berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus menu book: ' . $e->getMessage()]);
        }
    }

    // CRUD Pages
    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_book_id' => 'required|exists:menu_books,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'page_order' => 'nullable|integer|min:1',
        ]);

        // Parse JSON fields if sent as strings
        $itemIds = [];
        if ($request->has('item_ids')) {
            $itemIdsData = is_string($request->item_ids) ? json_decode($request->item_ids, true) : $request->item_ids;
            $itemIds = is_array($itemIdsData) ? $itemIdsData : [];
        }

        $categories = [];
        if ($request->has('categories')) {
            $categoriesData = is_string($request->categories) ? json_decode($request->categories, true) : $request->categories;
            $categories = is_array($categoriesData) ? $categoriesData : [];
        }

        // Validate item_ids
        if (!empty($itemIds)) {
            $request->validate([
                'item_ids' => 'array',
            ]);
            foreach ($itemIds as $itemId) {
                if (!\App\Models\Item::where('id', $itemId)->exists()) {
                    return back()->withErrors(['item_ids' => 'Invalid item ID: ' . $itemId]);
                }
            }
        }

        // Validate categories
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                if (empty($cat['category_id']) || !\App\Models\Category::where('id', $cat['category_id'])->exists()) {
                    return back()->withErrors(['categories' => 'Invalid category']);
                }
                if (!empty($cat['sub_category_id']) && !\App\Models\SubCategory::where('id', $cat['sub_category_id'])->exists()) {
                    return back()->withErrors(['categories' => 'Invalid sub category']);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Get max page_order untuk menu book ini
            $maxOrder = MenuBookPage::where('menu_book_id', $validated['menu_book_id'])->max('page_order') ?? 0;
            $pageOrder = $validated['page_order'] ?? ($maxOrder + 1);

            // Upload image
            $imagePath = $request->file('image')->store('menu-book-pages', 'public');

            // Create page
            $page = MenuBookPage::create([
                'menu_book_id' => $validated['menu_book_id'],
                'image' => $imagePath,
                'page_order' => $pageOrder,
                'status' => 'active',
            ]);

            // Attach items
            if (!empty($itemIds)) {
                $page->items()->attach($itemIds);
            }

            // Attach categories
            if (!empty($categories)) {
                foreach ($categories as $cat) {
                    if (!empty($cat['category_id'])) {
                        $page->categories()->attach($cat['category_id'], [
                            'sub_category_id' => $cat['sub_category_id'] ?? null,
                        ]);
                    }
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'create',
                'module' => 'menu_book',
                'description' => 'Membuat halaman menu book baru',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => null,
                'new_data' => $page->toArray()
            ]);

            DB::commit();

            return redirect()->route('menu-book.show', $validated['menu_book_id'])->with('success', 'Halaman menu berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menambahkan halaman: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, MenuBookPage $menuBookPage)
    {
        $validated = $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'page_order' => 'nullable|integer|min:1',
        ]);

        // Parse JSON fields if sent as strings
        $itemIds = [];
        if ($request->has('item_ids')) {
            $itemIdsData = is_string($request->item_ids) ? json_decode($request->item_ids, true) : $request->item_ids;
            $itemIds = is_array($itemIdsData) ? $itemIdsData : [];
        }

        $categories = [];
        if ($request->has('categories')) {
            $categoriesData = is_string($request->categories) ? json_decode($request->categories, true) : $request->categories;
            $categories = is_array($categoriesData) ? $categoriesData : [];
        }

        // Validate item_ids
        if (!empty($itemIds)) {
            foreach ($itemIds as $itemId) {
                if (!\App\Models\Item::where('id', $itemId)->exists()) {
                    return back()->withErrors(['item_ids' => 'Invalid item ID: ' . $itemId]);
                }
            }
        }

        // Validate categories
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                if (empty($cat['category_id']) || !\App\Models\Category::where('id', $cat['category_id'])->exists()) {
                    return back()->withErrors(['categories' => 'Invalid category']);
                }
                if (!empty($cat['sub_category_id']) && !\App\Models\SubCategory::where('id', $cat['sub_category_id'])->exists()) {
                    return back()->withErrors(['categories' => 'Invalid sub category']);
                }
            }
        }

        DB::beginTransaction();
        try {
            $oldData = $menuBookPage->toArray();

            // Update image if provided
            if ($request->hasFile('image')) {
                // Delete old image
                if ($menuBookPage->image) {
                    Storage::disk('public')->delete($menuBookPage->image);
                }
                $imagePath = $request->file('image')->store('menu-book-pages', 'public');
                $menuBookPage->image = $imagePath;
            }

            // Update page_order
            if (isset($validated['page_order'])) {
                $menuBookPage->page_order = $validated['page_order'];
            }

            $menuBookPage->save();

            // Sync items
            if ($request->has('item_ids')) {
                $menuBookPage->items()->sync($itemIds);
            }

            // Sync categories
            if ($request->has('categories')) {
                $menuBookPage->categories()->detach();
                foreach ($categories as $cat) {
                    if (!empty($cat['category_id'])) {
                        $menuBookPage->categories()->attach($cat['category_id'], [
                            'sub_category_id' => $cat['sub_category_id'] ?? null,
                        ]);
                    }
                }
            }

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'update',
                'module' => 'menu_book',
                'description' => 'Mengupdate halaman menu book',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_data' => $oldData,
                'new_data' => $menuBookPage->fresh()->toArray()
            ]);

            DB::commit();

            return redirect()->route('menu-book.show', $menuBookPage->menu_book_id)->with('success', 'Halaman menu berhasil diupdate!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mengupdate halaman: ' . $e->getMessage()]);
        }
    }

    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'pages' => 'required|array',
            'pages.*.id' => 'required|exists:menu_book_pages,id',
            'pages.*.page_order' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['pages'] as $pageData) {
                MenuBookPage::where('id', $pageData['id'])
                    ->update(['page_order' => $pageData['page_order']]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Urutan halaman berhasil diupdate!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengupdate urutan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(MenuBookPage $menuBookPage)
    {
        DB::beginTransaction();
        try {
            $oldData = $menuBookPage->toArray();

            // Delete image
            if ($menuBookPage->image) {
                Storage::disk('public')->delete($menuBookPage->image);
            }

            // Detach relationships
            $menuBookPage->items()->detach();
            $menuBookPage->categories()->detach();

            // Delete page
            $menuBookPage->delete();

            // Log activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => 'delete',
                'module' => 'menu_book',
                'description' => 'Menghapus halaman menu book',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_data' => $oldData,
                'new_data' => null
            ]);

            DB::commit();

            $menuBookId = $menuBookPage->menu_book_id;
            return redirect()->route('menu-book.show', $menuBookId)->with('success', 'Halaman menu berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menghapus halaman: ' . $e->getMessage()]);
        }
    }

    // ========== CUSTOMER PUBLIC VIEWS ==========
    
    // List outlets yang memiliki menu books
    public function customerIndex()
    {
        // Get outlets that have active menu books with pages
        $outlets = \DB::table('tbl_data_outlet as o')
            ->join('menu_book_outlets as mbo', 'o.id_outlet', '=', 'mbo.outlet_id')
            ->join('menu_books as mb', 'mbo.menu_book_id', '=', 'mb.id')
            ->join('menu_book_pages as mbp', 'mb.id', '=', 'mbp.menu_book_id')
            ->where('o.status', 'A')
            ->where('mb.status', 'active')
            ->where('mbp.status', 'active')
            ->select('o.*')
            ->distinct()
            ->orderBy('o.nama_outlet')
            ->get();

        return Inertia::render('MenuBook/Customer/Index', [
            'outlets' => $outlets,
        ]);
    }

    // List menu books untuk outlet tertentu
    public function customerOutlet($outletId)
    {
        $outlet = \DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->first();

        if (!$outlet) {
            abort(404, 'Outlet not found');
        }

        $menuBooks = MenuBook::where('status', 'active')
            ->whereHas('outlets', function($q) use ($outletId) {
                $q->where('id_outlet', $outletId);
            })
            ->whereHas('pages')
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        return Inertia::render('MenuBook/Customer/Outlet', [
            'outlet' => $outlet,
            'menuBooks' => $menuBooks,
        ]);
    }

    // Show menu book pages untuk customer (public view)
    public function customerShow(Request $request, MenuBook $menuBook)
    {
        // Only show active menu books
        if ($menuBook->status !== 'active') {
            abort(404, 'Menu book not found');
        }

        $query = $menuBook->pages()
            ->where('status', 'active')
            ->with(['items', 'categories'])
            ->orderBy('page_order', 'asc');

        $pages = $query->get();

        if ($pages->isEmpty()) {
            abort(404, 'No pages found');
        }

        // Get outlet info
        $outlet = $menuBook->outlets()->first();

        return Inertia::render('MenuBook/Customer/Show', [
            'menuBook' => $menuBook,
            'pages' => $pages,
            'outlet' => $outlet,
        ]);
    }

    // Self-order page ala marketplace (GoFood style)
    public function customerSelfOrder(Request $request, MenuBook $menuBook)
    {
        if ($menuBook->status !== 'active') {
            abort(404, 'Menu book not found');
        }

        $outlet = $this->getMenuBookOutlet($menuBook->id);
        if (!$outlet) {
            abort(404, 'Outlet not found for this menu book');
        }

        $pages = $menuBook->pages()
            ->where('status', 'active')
            ->with(['items:id,name,category_id,sub_category_id,status', 'categories'])
            ->orderBy('page_order', 'asc')
            ->get();

        if ($pages->isEmpty()) {
            abort(404, 'No pages found');
        }

        $directItemIds = $pages->flatMap(function ($page) {
            return $page->items->pluck('id');
        })->unique()->values();

        $categoryFilters = $pages->flatMap(function ($page) {
            return $page->categories->map(function ($category) {
                return [
                    'category_id' => $category->id,
                    'sub_category_id' => $category->pivot->sub_category_id,
                ];
            });
        })->values();

        $categoryItemIds = collect();
        if ($categoryFilters->isNotEmpty()) {
            $categoryItemIds = DB::table('items')
                ->where('status', 'active')
                ->where(function ($query) use ($categoryFilters) {
                    foreach ($categoryFilters as $filter) {
                        $query->orWhere(function ($subQuery) use ($filter) {
                            $subQuery->where('category_id', $filter['category_id']);

                            if (!empty($filter['sub_category_id'])) {
                                $subQuery->where('sub_category_id', $filter['sub_category_id']);
                            }
                        });
                    }
                })
                ->pluck('id');
        }

        $itemIds = $directItemIds
            ->merge($categoryItemIds)
            ->unique()
            ->values();

        $items = collect();
        if ($itemIds->isNotEmpty()) {
            $itemsQuery = $this->buildAvailableItemQuery($outlet->id_outlet, $outlet->region_id)
                ->whereIn('i.id', $itemIds->all())
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id');

            $this->applyPosVisibilityFilter($itemsQuery, (int) $outlet->id_outlet, $outlet->region_id ? (int) $outlet->region_id : null);

            $items = $itemsQuery->select(
                    'i.id',
                    'i.name',
                    'i.description',
                    'i.category_id',
                    'i.sub_category_id',
                    'i.status',
                    'c.name as category_name',
                    'sc.name as sub_category_name',
                    DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price) as price')
                )
                ->orderBy('i.name')
                ->distinct()
                ->get();

            $imageMap = $this->buildItemImageMap($items->pluck('id')->all());

            $items = $items->map(function ($item) use ($imageMap) {
                $item->image_path = $imageMap[$item->id] ?? null;
                $item->price = (float) ($item->price ?? 0);
                return $item;
            })->values();
        }

        $categories = $items
            ->filter(fn ($item) => !empty($item->category_id) && !empty($item->category_name))
            ->map(fn ($item) => [
                'id' => $item->category_id,
                'name' => $item->category_name,
            ])
            ->unique('id')
            ->values();

        $reservationNumber = strtoupper(trim((string) $request->query('reservation_number', '')));
        if ($reservationNumber === '') {
            $reservationNumber = null;
        }

        return Inertia::render('MenuBook/Customer/SelfOrder', [
            'menuBook' => $menuBook,
            'outlet' => $outlet,
            'items' => $items,
            'categories' => $categories,
            'reservationNumber' => $reservationNumber,
        ]);
    }

    // Submit self-order dari halaman customer
    public function customerSelfOrderCheckout(Request $request, MenuBook $menuBook)
    {
        if (!Schema::hasTable('self_orders') || !Schema::hasTable('self_order_items')) {
            return response()->json([
                'success' => false,
                'message' => 'Self order table belum tersedia. Jalankan SQL create table terlebih dahulu.',
            ], 500);
        }

        if ($menuBook->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Menu book tidak aktif.',
            ], 404);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'reservation_number' => 'nullable|string|max:32',
            'order_type' => 'required|in:dine_in,take_away',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:99',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers_named' => 'nullable|array',
        ]);

        $outlet = $this->getMenuBookOutlet($menuBook->id);
        if (!$outlet) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet tidak ditemukan untuk menu book ini.',
            ], 404);
        }

        $reservationNumber = !empty($validated['reservation_number'])
            ? strtoupper(trim((string) $validated['reservation_number']))
            : null;
        $reservationRow = $this->resolveReservationRow($reservationNumber, (int) $outlet->id_outlet);

        // Jika reservasi mengarah ke outlet lain, pakai outlet reservasi agar order tidak nyasar.
        if ($reservationRow?->outlet_id && (int) $reservationRow->outlet_id !== (int) $outlet->id_outlet) {
            $reservedOutlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', (int) $reservationRow->outlet_id)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet', 'qr_code', 'region_id')
                ->first();

            if ($reservedOutlet) {
                $outlet = $reservedOutlet;
                $reservationRow = $this->resolveReservationRow($reservationNumber, (int) $outlet->id_outlet);
            }
        }

        $requestItemIds = collect($validated['items'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $availableItems = $this->buildAvailableItemQuery($outlet->id_outlet, $outlet->region_id)
            ->whereIn('i.id', $requestItemIds->all())
            ->select(
                'i.id',
                'i.name',
                DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price) as price')
            )
            ->distinct()
            ->get()
            ->keyBy('id');

        $missingItemIds = $requestItemIds
            ->reject(fn ($itemId) => $availableItems->has($itemId))
            ->values();

        if ($missingItemIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Ada item yang tidak tersedia untuk outlet ini.',
                'invalid_item_ids' => $missingItemIds,
            ], 422);
        }

        $normalizedItems = [];
        $subtotal = 0;
        $totalQty = 0;

        $modifierOptionNameMap = [];
        if (Schema::hasTable('modifier_options')) {
            $modifierOptionIds = collect($validated['items'])
                ->flatMap(fn ($item) => $this->extractModifierOptionIds($item['modifiers'] ?? null))
                ->unique()
                ->values()
                ->all();

            if (!empty($modifierOptionIds)) {
                $modifierOptionNameMap = DB::table('modifier_options')
                    ->whereIn('id', $modifierOptionIds)
                    ->pluck('name', 'id')
                    ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                    ->all();
            }
        }

        foreach ($validated['items'] as $itemInput) {
            $itemId = (int) $itemInput['item_id'];
            $qty = (int) $itemInput['qty'];
            $itemMaster = $availableItems->get($itemId);
            $price = (float) ($itemMaster->price ?? 0);
            $lineSubtotal = $price * $qty;
            $modifierNames = null;
            if (!empty($itemInput['modifiers_named']) && is_array($itemInput['modifiers_named'])) {
                $modifierNames = $itemInput['modifiers_named'];
            } else {
                $normalizedModifierNames = $this->normalizeModifiersForStorage($itemInput['modifiers'] ?? null, $modifierOptionNameMap);
                $modifierNames = !empty($normalizedModifierNames) ? $normalizedModifierNames : null;
            }

            $normalizedItems[] = [
                'item_id' => $itemId,
                'item_name' => $itemMaster->name,
                'qty' => $qty,
                'price' => $price,
                'modifiers' => !empty($modifierNames) ? json_encode($modifierNames, JSON_UNESCAPED_UNICODE) : null,
                'subtotal' => $lineSubtotal,
                'notes' => $itemInput['notes'] ?? null,
            ];

            $subtotal += $lineSubtotal;
            $totalQty += $qty;
        }

        $service = (float) round($subtotal * 0.05);
        $dpp = (float) round($subtotal + $service);
        $pb1 = (float) round($dpp * 0.10);
        $grandTotal = (float) round($dpp + $pb1);

        DB::beginTransaction();
        try {
            $now = now();
            $orderNo = $this->generateSelfOrderNumber();
            $reservationId = $reservationRow?->id ? (int) $reservationRow->id : null;

            $selfOrderPayload = [
                'order_no' => $orderNo,
                'menu_book_id' => $menuBook->id,
                'outlet_id' => $outlet->id_outlet,
                'kode_outlet' => $outlet->qr_code,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'order_type' => $validated['order_type'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'total_item' => $totalQty,
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('self_orders', 'reservation_number')) {
                $selfOrderPayload['reservation_number'] = $reservationNumber;
            }
            if (Schema::hasColumn('self_orders', 'reservation_id')) {
                $selfOrderPayload['reservation_id'] = $reservationId;
            }

            $selfOrderId = DB::table('self_orders')->insertGetId($selfOrderPayload);

            $rows = array_map(function ($item) use ($selfOrderId, $now) {
                return [
                    'self_order_id' => $selfOrderId,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'modifiers' => $item['modifiers'],
                    'subtotal' => $item['subtotal'],
                    'notes' => $item['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $normalizedItems);

            DB::table('self_order_items')->insert($rows);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Self order berhasil dibuat.',
                'data' => [
                    'id' => $selfOrderId,
                    'order_no' => $orderNo,
                    'total_item' => $totalQty,
                    'grand_total' => $subtotal,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat self order: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getMenuBookOutlet(int $menuBookId)
    {
        return DB::table('tbl_data_outlet as o')
            ->join('menu_book_outlets as mbo', 'o.id_outlet', '=', 'mbo.outlet_id')
            ->where('mbo.menu_book_id', $menuBookId)
            ->where('o.status', 'A')
            ->select('o.id_outlet', 'o.nama_outlet', 'o.qr_code', 'o.region_id')
            ->orderBy('o.id_outlet')
            ->first();
    }

    private function buildAvailableItemQuery($outletId, $regionId)
    {
        return DB::table('items as i')
            ->leftJoin('item_availabilities as ia_outlet', function ($join) use ($outletId) {
                $join->on('ia_outlet.item_id', '=', 'i.id')
                    ->where('ia_outlet.availability_type', 'outlet')
                    ->where('ia_outlet.outlet_id', $outletId);
            })
            ->leftJoin('item_availabilities as ia_region', function ($join) use ($regionId) {
                $join->on('ia_region.item_id', '=', 'i.id')
                    ->where('ia_region.availability_type', 'region')
                    ->where('ia_region.region_id', $regionId);
            })
            ->leftJoin('item_availabilities as ia_all', function ($join) {
                $join->on('ia_all.item_id', '=', 'i.id')
                    ->where('ia_all.availability_type', 'all');
            })
            ->leftJoin('item_prices as ip_outlet', function ($join) use ($outletId) {
                $join->on('ip_outlet.item_id', '=', 'i.id')
                    ->where('ip_outlet.availability_price_type', 'outlet')
                    ->where('ip_outlet.outlet_id', $outletId);
            })
            ->leftJoin('item_prices as ip_region', function ($join) use ($regionId) {
                $join->on('ip_region.item_id', '=', 'i.id')
                    ->where('ip_region.availability_price_type', 'region')
                    ->where('ip_region.region_id', $regionId);
            })
            ->leftJoin('item_prices as ip_all', function ($join) {
                $join->on('ip_all.item_id', '=', 'i.id')
                    ->where('ip_all.availability_price_type', 'all');
            })
            ->where('i.status', 'active')
            ->where(function ($query) {
                $query->whereNotNull('ia_outlet.id')
                    ->orWhereNotNull('ia_region.id')
                    ->orWhereNotNull('ia_all.id');
            });
    }

    private function generateSelfOrderNumber(): string
    {
        $datePrefix = now()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'SO-' . $datePrefix . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = DB::table('self_orders')->where('order_no', $candidate)->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return 'SO-' . $datePrefix . '-' . strtoupper(substr((string) uniqid('', true), -6));
    }

    // API untuk frontend company profile (Next.js): ambil menu self-order by outlet
    public function apiSelfOrderMenuByOutlet(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer',
        ]);

        $outletId = (int) $validated['outlet_id'];
        $outlet = DB::table('tbl_data_outlet')
            ->where('id_outlet', $outletId)
            ->where('status', 'A')
            ->select('id_outlet', 'nama_outlet', 'qr_code', 'region_id')
            ->first();
        if (!$outlet) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet tidak ditemukan.',
            ], 404);
        }

        // Referensi item by outlet/region mengikuti pola PosSyncController::syncItems.
        // Menu book hanya dipakai untuk konteks checkout (menu_book_id), bukan filter daftar item.
        $itemsQuery = $this->buildAvailableItemQuery($outlet->id_outlet, $outlet->region_id)
            ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
            ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id');
        $this->applyPosVisibilityFilter($itemsQuery, (int) $outlet->id_outlet, $outlet->region_id ? (int) $outlet->region_id : null);

        $items = $itemsQuery->select(
                'i.id',
                'i.name',
                'i.description',
                'i.category_id',
                'i.sub_category_id',
                'i.status',
                'c.name as category_name',
                'sc.name as sub_category_name',
                DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price) as price')
            )
            ->orderBy('i.name')
            ->distinct()
            ->get()
            ->values();

        // Fallback: jika availability belum rapi, tetap tampilkan item active yang punya harga.
        if ($items->isEmpty()) {
            $fallbackQuery = $this->buildFallbackItemPriceQuery($outlet->id_outlet, $outlet->region_id)
                ->leftJoin('categories as c', 'i.category_id', '=', 'c.id')
                ->leftJoin('sub_categories as sc', 'i.sub_category_id', '=', 'sc.id');
            $this->applyPosVisibilityFilter($fallbackQuery, (int) $outlet->id_outlet, $outlet->region_id ? (int) $outlet->region_id : null);

            $items = $fallbackQuery->select(
                    'i.id',
                    'i.name',
                    'i.description',
                    'i.category_id',
                    'i.sub_category_id',
                    'i.status',
                    'c.name as category_name',
                    'sc.name as sub_category_name',
                    DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price, 0) as price')
                )
                ->where(function ($query) {
                    $query->whereNotNull('ip_outlet.id')
                        ->orWhereNotNull('ip_region.id')
                        ->orWhereNotNull('ip_all.id');
                })
                ->orderBy('i.name')
                ->distinct()
                ->get()
                ->values();
        }

        $imageMap = collect();
        $modifierMap = collect();
        if ($items->isNotEmpty()) {
            $imageMap = $this->buildItemImageMap($items->pluck('id')->all());
            $modifierMap = $this->buildItemModifierMap($items->pluck('id')->all());
        }

        $items = $items
            ->map(function ($item) {
                $item->price = (float) ($item->price ?? 0);
                return $item;
            })
            ->map(function ($item) use ($imageMap) {
                $item->image_path = $imageMap[$item->id] ?? null;
                return $item;
            })
            ->map(function ($item) use ($modifierMap) {
                $item->modifiers = $modifierMap[$item->id] ?? [];
                return $item;
            })
            ->values();

        $menuBook = MenuBook::where('status', 'active')
            ->whereHas('outlets', function ($q) use ($outletId) {
                $q->where('id_outlet', $outletId);
            })
            ->orderBy('id')
            ->first();

        $categories = $items
            ->filter(fn ($item) => !empty($item->category_id) && !empty($item->category_name))
            ->map(fn ($item) => [
                'id' => $item->category_id,
                'name' => $item->category_name,
            ])
            ->unique('id')
            ->values();

        $menuBookId = $menuBook ? $menuBook->id : null;
        $menuBookName = $menuBook ? $menuBook->name : 'Self Order';

        return response()->json([
            'success' => true,
            'data' => [
                'menu_book' => [
                    'id' => $menuBookId,
                    'name' => $menuBookName,
                ],
                'outlet' => [
                    'id_outlet' => $outlet->id_outlet,
                    'nama_outlet' => $outlet->nama_outlet,
                    'qr_code' => $outlet->qr_code,
                ],
                'categories' => $categories,
                'items' => $items,
            ],
        ]);
    }

    // API checkout self-order by menu_book_id (tanpa route model binding)
    public function apiSelfOrderCheckout(Request $request)
    {
        $webOrderTable = 'web_self_orders';
        $webItemTable = 'web_self_order_items';

        if (!Schema::hasTable($webOrderTable) || !Schema::hasTable($webItemTable)) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel web self order belum tersedia. Jalankan SQL create table web_self_orders dan web_self_order_items terlebih dahulu.',
            ], 500);
        }

        $validated = $request->validate([
            'menu_book_id' => 'nullable|integer|exists:menu_books,id',
            'outlet_id' => 'nullable|integer|exists:tbl_data_outlet,id_outlet',
            'customer_name' => 'required|string|max:100',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email|max:190',
            'reservation_number' => 'nullable|string|max:32',
            'order_type' => 'required|in:dine_in,take_away',
            'pax' => 'nullable|integer|min:1|max:100',
            'table_ids' => 'nullable|array',
            'table_ids.*' => 'integer|min:1',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:99',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.modifiers' => 'nullable|array',
        ]);

        $menuBook = null;
        $outlet = null;

        if (!empty($validated['menu_book_id'])) {
            $menuBook = MenuBook::find($validated['menu_book_id']);
            if (!$menuBook || $menuBook->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu book tidak aktif.',
                ], 404);
            }

            $outlet = $this->getMenuBookOutlet($menuBook->id);
        }

        if (!$outlet && !empty($validated['outlet_id'])) {
            $outlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', (int) $validated['outlet_id'])
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet', 'qr_code', 'region_id')
                ->first();
        }

        if (!$outlet) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet tidak ditemukan untuk menu book ini.',
            ], 404);
        }

        if (!$menuBook) {
            $menuBook = MenuBook::where('status', 'active')
                ->whereHas('outlets', function ($q) use ($outlet) {
                    $q->where('id_outlet', (int) $outlet->id_outlet);
                })
                ->orderBy('id')
                ->first();
        }

        $reservationNumber = !empty($validated['reservation_number'])
            ? strtoupper(trim((string) $validated['reservation_number']))
            : null;
        $reservationRow = $this->resolveReservationRow($reservationNumber, (int) $outlet->id_outlet);

        // Jika reservasi mengarah ke outlet lain, pakai outlet reservasi agar order tidak nyasar.
        if ($reservationRow?->outlet_id && (int) $reservationRow->outlet_id !== (int) $outlet->id_outlet) {
            $reservedOutlet = DB::table('tbl_data_outlet')
                ->where('id_outlet', (int) $reservationRow->outlet_id)
                ->where('status', 'A')
                ->select('id_outlet', 'nama_outlet', 'qr_code', 'region_id')
                ->first();

            if ($reservedOutlet) {
                $outlet = $reservedOutlet;
                $reservationRow = $this->resolveReservationRow($reservationNumber, (int) $outlet->id_outlet);
            }
        }

        $requestItemIds = collect($validated['items'])
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $availableItems = $this->buildAvailableItemQuery($outlet->id_outlet, $outlet->region_id)
            ->whereIn('i.id', $requestItemIds->all())
            ->select(
                'i.id',
                'i.name',
                DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price) as price')
            )
            ->distinct()
            ->get()
            ->keyBy('id');

        $missingItemIds = $requestItemIds
            ->reject(fn ($itemId) => $availableItems->has($itemId))
            ->values();

        if ($missingItemIds->isNotEmpty()) {
            $fallbackItems = $this->buildFallbackItemPriceQuery($outlet->id_outlet, $outlet->region_id)
                ->whereIn('i.id', $missingItemIds->all())
                ->select(
                    'i.id',
                    'i.name',
                    DB::raw('COALESCE(ip_outlet.price, ip_region.price, ip_all.price, 0) as price')
                )
                ->distinct()
                ->get()
                ->keyBy('id');

            if ($fallbackItems->isNotEmpty()) {
                $availableItems = $availableItems->merge($fallbackItems);
                $missingItemIds = $requestItemIds
                    ->reject(fn ($itemId) => $availableItems->has($itemId))
                    ->values();
            }
        }

        if ($missingItemIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Ada item yang tidak tersedia untuk outlet ini.',
                'invalid_item_ids' => $missingItemIds,
            ], 422);
        }

        $normalizedItems = [];
        $subtotal = 0;
        $totalQty = 0;

        $modifierOptionNameMap = [];
        if (Schema::hasTable('modifier_options')) {
            $modifierOptionIds = collect($validated['items'])
                ->flatMap(fn ($item) => $this->extractModifierOptionIds($item['modifiers'] ?? null))
                ->unique()
                ->values()
                ->all();

            if (!empty($modifierOptionIds)) {
                $modifierOptionNameMap = DB::table('modifier_options')
                    ->whereIn('id', $modifierOptionIds)
                    ->pluck('name', 'id')
                    ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                    ->all();
            }
        }

        foreach ($validated['items'] as $itemInput) {
            $itemId = (int) $itemInput['item_id'];
            $qty = (int) $itemInput['qty'];
            $itemMaster = $availableItems->get($itemId);
            $price = (float) ($itemMaster->price ?? 0);
            $lineSubtotal = $price * $qty;
            $modifierNames = $this->normalizeModifiersForStorage($itemInput['modifiers'] ?? null, $modifierOptionNameMap);

            $normalizedItems[] = [
                'item_id' => $itemId,
                'item_name' => $itemMaster->name,
                'qty' => $qty,
                'price' => $price,
                'modifiers' => !empty($modifierNames) ? json_encode($modifierNames, JSON_UNESCAPED_UNICODE) : null,
                'subtotal' => $lineSubtotal,
                'notes' => $itemInput['notes'] ?? null,
            ];

            $subtotal += $lineSubtotal;
            $totalQty += $qty;
        }

        $service = (float) round($subtotal * 0.05);
        $dpp = (float) round($subtotal + $service);
        $pb1 = (float) round($dpp * 0.10);
        $grandTotal = (float) round($dpp + $pb1);

        DB::beginTransaction();
        try {
            $now = now();
            $orderNo = $this->generateSelfOrderNumber();
            $webSelfOrderId = (string) Str::uuid();
            $reservationId = $reservationRow?->id ? (int) $reservationRow->id : null;

            $customerEmail = !empty($validated['customer_email'])
                ? trim((string) $validated['customer_email'])
                : null;
            if (!$customerEmail && !empty($reservationRow?->email)) {
                $customerEmail = trim((string) $reservationRow->email);
            }

            $pax = isset($validated['pax']) ? (int) $validated['pax'] : null;
            if (!$pax && !empty($reservationRow?->number_of_guests)) {
                $pax = (int) $reservationRow->number_of_guests;
            }

            $tableIds = collect($validated['table_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($tableIds) && !empty($reservationRow?->selected_table_ids)) {
                $rawSelected = $reservationRow->selected_table_ids;
                $decodedSelected = is_array($rawSelected) ? $rawSelected : json_decode((string) $rawSelected, true);
                if (is_array($decodedSelected)) {
                    $tableIds = collect($decodedSelected)
                        ->map(fn ($id) => (int) $id)
                        ->filter(fn ($id) => $id > 0)
                        ->unique()
                        ->values()
                        ->all();
                }
            }

            $tableIdsJson = !empty($tableIds) ? json_encode($tableIds, JSON_UNESCAPED_UNICODE) : null;

            $selfOrderPayload = ['id' => $webSelfOrderId];
            $setOrder = function (string $column, $value) use (&$selfOrderPayload, $webOrderTable) {
                if (Schema::hasColumn($webOrderTable, $column)) {
                    $selfOrderPayload[$column] = $value;
                }
            };

            $setOrder('reservation_id', $reservationId ? (int) $reservationId : null);
            $setOrder('reservation_number', $reservationNumber);
            $setOrder('order_no', $orderNo);
            $setOrder('outlet_id', (int) $outlet->id_outlet);
            $setOrder('outlet_code', $outlet->qr_code);
            $setOrder('customer_name', $validated['customer_name']);
            $setOrder('customer_phone', $validated['customer_phone'] ?? null);
            $setOrder('customer_email', $customerEmail);
            $setOrder('order_channel', 'self_order_web');
            $setOrder('order_type', $validated['order_type']);
            $setOrder('pax', $pax);
            $setOrder('table_ids_json', $tableIdsJson);
            $setOrder('notes', $validated['notes'] ?? null);
            $setOrder('subtotal', (int) round($subtotal));
            $setOrder('discount', 0);
            $setOrder('cashback', 0);
            $setOrder('dpp', (int) round($dpp));
            $setOrder('pb1', (int) round($pb1));
            $setOrder('service', (int) round($service));
            $setOrder('grand_total', (int) round($grandTotal));
            $setOrder('commfee', 0);
            $setOrder('rounding', 0);
            $setOrder('status', 'pending');
            $setOrder('paid_status', 'unpaid');
            $setOrder('sync_attempt_count', 0);
            $setOrder('last_sync_at', null);
            $setOrder('sync_error', null);
            $setOrder('created_at', $now);
            $setOrder('updated_at', $now);

            DB::table($webOrderTable)->insert($selfOrderPayload);

            $rows = array_map(function ($item) use ($webSelfOrderId, $now, $webItemTable) {
                $row = ['id' => (string) Str::uuid()];
                $setItem = function (string $column, $value) use (&$row, $webItemTable) {
                    if (Schema::hasColumn($webItemTable, $column)) {
                        $row[$column] = $value;
                    }
                };

                $setItem('web_self_order_id', $webSelfOrderId);
                $setItem('item_id', (string) $item['item_id']);
                $setItem('item_name', $item['item_name']);
                $setItem('qty', (int) $item['qty']);
                $setItem('price', (int) round($item['price']));
                $setItem('subtotal', (int) round($item['subtotal']));
                $setItem('tally', null);
                $setItem('modifiers', $item['modifiers']);
                $setItem('notes', $item['notes']);
                $setItem('created_at', $now);
                $setItem('updated_at', $now);
                return $row;
            }, $normalizedItems);

            DB::table($webItemTable)->insert($rows);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Self order berhasil dibuat.',
                'data' => [
                    'id' => $webSelfOrderId,
                    'order_no' => $orderNo,
                    'total_item' => $totalQty,
                    'subtotal' => $subtotal,
                    'service' => $service,
                    'dpp' => $dpp,
                    'pb1' => $pb1,
                    'grand_total' => $grandTotal,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat self order: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function buildFallbackItemPriceQuery($outletId, $regionId)
    {
        return DB::table('items as i')
            ->leftJoin('item_prices as ip_outlet', function ($join) use ($outletId) {
                $join->on('ip_outlet.item_id', '=', 'i.id')
                    ->where('ip_outlet.availability_price_type', 'outlet')
                    ->where('ip_outlet.outlet_id', $outletId);
            })
            ->leftJoin('item_prices as ip_region', function ($join) use ($regionId) {
                $join->on('ip_region.item_id', '=', 'i.id')
                    ->where('ip_region.availability_price_type', 'region')
                    ->where('ip_region.region_id', $regionId);
            })
            ->leftJoin('item_prices as ip_all', function ($join) {
                $join->on('ip_all.item_id', '=', 'i.id')
                    ->where('ip_all.availability_price_type', 'all');
            })
            ->where('i.status', 'active');
    }

    private function resolveReservationRow(?string $reservationNumber, ?int $outletId = null)
    {
        if (!$reservationNumber || !Schema::hasTable('reservations')) {
            return null;
        }

        $normalized = strtoupper(trim((string) $reservationNumber));
        if ($normalized === '') {
            return null;
        }

        $baseQuery = DB::table('reservations')
            ->whereRaw('UPPER(TRIM(reservation_number)) = ?', [$normalized]);

        if ($outletId) {
            $byOutlet = (clone $baseQuery)
                ->where('outlet_id', $outletId)
                ->orderByDesc('id')
                ->first(['id', 'outlet_id', 'email', 'number_of_guests', 'selected_table_ids']);

            if ($byOutlet) {
                return $byOutlet;
            }
        }

        return $baseQuery
            ->orderByDesc('id')
            ->first(['id', 'outlet_id', 'email', 'number_of_guests', 'selected_table_ids']);
    }

    private function applyPosVisibilityFilter($query, int $outletId, ?int $regionId): void
    {
        if (Schema::hasColumn('categories', 'show_pos')) {
            $query->where(function ($q) use ($outletId) {
                $q->whereNull('i.category_id')
                    ->orWhere(function ($q1) use ($outletId) {
                        // Self-order: category harus show_pos=1 dan terdaftar di outlet ini.
                        $q1->whereIn('c.show_pos', [1, '1']);

                        if (Schema::hasTable('category_outlet')) {
                            $q1->whereExists(function ($qe) use ($outletId) {
                                $qe->select(DB::raw(1))
                                    ->from('category_outlet')
                                    ->whereColumn('category_outlet.category_id', 'c.id')
                                    ->where('category_outlet.outlet_id', $outletId);
                            });
                        }
                    });
            });
        }

        if (Schema::hasColumn('sub_categories', 'show_pos')) {
            $query->where(function ($q) use ($outletId, $regionId) {
                $q->whereNull('i.sub_category_id');

                if (Schema::hasTable('sub_category_availabilities')) {
                    // Self-order: sub-category harus show_pos=1 dan match byRegion/byOutlet.
                    $q->orWhere(function ($q1) use ($outletId, $regionId) {
                        $q1->whereIn('sc.show_pos', [1, '1'])
                            ->whereExists(function ($qe) use ($outletId, $regionId) {
                                $qe->select(DB::raw(1))
                                    ->from('sub_category_availabilities')
                                    ->whereColumn('sub_category_availabilities.sub_category_id', 'sc.id')
                                    ->where(function ($w) use ($regionId, $outletId) {
                                        if (!is_null($regionId)) {
                                            $w->where(function ($w2) use ($regionId) {
                                                $w2->where('sub_category_availabilities.availability_type', 'byRegion')
                                                    ->where('sub_category_availabilities.region_id', $regionId);
                                            });
                                        }

                                        $w->orWhere(function ($w2) use ($outletId) {
                                            $w2->where('sub_category_availabilities.availability_type', 'byOutlet')
                                                ->where('sub_category_availabilities.outlet_id', $outletId);
                                        });
                                    });
                            });
                    });
                }
            });
        }
    }

    private function buildItemImageMap(array $itemIds)
    {
        if (empty($itemIds) || !Schema::hasTable('item_images')) {
            return collect();
        }

        $hasImagePath = Schema::hasColumn('item_images', 'image_path');
        $hasPath = Schema::hasColumn('item_images', 'path');

        if (!$hasImagePath && !$hasPath) {
            return collect();
        }

        $query = DB::table('item_images')->whereIn('item_id', $itemIds);

        if ($hasImagePath && $hasPath) {
            return $query
                ->select('item_id', DB::raw('MIN(COALESCE(image_path, path)) as image_path'))
                ->groupBy('item_id')
                ->pluck('image_path', 'item_id');
        }

        $column = $hasImagePath ? 'image_path' : 'path';

        return $query
            ->select('item_id', DB::raw("MIN($column) as image_path"))
            ->groupBy('item_id')
            ->pluck('image_path', 'item_id');
    }

    private function buildItemModifierMap(array $itemIds)
    {
        if (empty($itemIds)) {
            return collect();
        }

        if (
            !Schema::hasTable('modifiers') ||
            !Schema::hasTable('modifier_options')
        ) {
            return collect();
        }

        $rows = collect();

        // Variasi skema produksi bisa beda: item_modifiers atau item_modifier_options
        if (
            Schema::hasTable('item_modifiers') &&
            Schema::hasColumn('item_modifiers', 'item_id') &&
            Schema::hasColumn('item_modifiers', 'modifier_option_id')
        ) {
            $rows = DB::table('item_modifiers as im')
                ->join('modifiers as m', 'im.modifier_id', '=', 'm.id')
                ->join('modifier_options as mo', 'im.modifier_option_id', '=', 'mo.id')
                ->whereIn('im.item_id', $itemIds)
                ->select(
                    'im.item_id',
                    'im.modifier_id',
                    'm.name as modifier_name',
                    'im.modifier_option_id',
                    'mo.name as option_name'
                )
                ->orderBy('im.item_id')
                ->orderBy('m.name')
                ->orderBy('mo.name')
                ->get();
        } elseif (
            Schema::hasTable('item_modifier_options') &&
            Schema::hasColumn('item_modifier_options', 'item_id') &&
            Schema::hasColumn('item_modifier_options', 'modifier_option_id')
        ) {
            $rows = DB::table('item_modifier_options as im')
                ->join('modifier_options as mo', 'im.modifier_option_id', '=', 'mo.id')
                ->join('modifiers as m', 'mo.modifier_id', '=', 'm.id')
                ->whereIn('im.item_id', $itemIds)
                ->select(
                    'im.item_id',
                    'm.id as modifier_id',
                    'm.name as modifier_name',
                    'im.modifier_option_id',
                    'mo.name as option_name'
                )
                ->orderBy('im.item_id')
                ->orderBy('m.name')
                ->orderBy('mo.name')
                ->get();
        } else {
            return collect();
        }

        $map = [];
        foreach ($rows as $row) {
            $itemId = (int) $row->item_id;
            $modifierId = (int) $row->modifier_id;
            $optionId = (int) $row->modifier_option_id;

            if (!isset($map[$itemId])) {
                $map[$itemId] = [];
            }

            if (!isset($map[$itemId][$modifierId])) {
                $map[$itemId][$modifierId] = [
                    'modifier_id' => $modifierId,
                    'modifier_name' => (string) $row->modifier_name,
                    'options' => [],
                ];
            }

            $exists = collect($map[$itemId][$modifierId]['options'])->contains(function ($option) use ($optionId) {
                return (int) ($option['id'] ?? 0) === $optionId;
            });

            if (!$exists) {
                $map[$itemId][$modifierId]['options'][] = [
                    'id' => $optionId,
                    'name' => (string) $row->option_name,
                ];
            }
        }

        foreach ($map as $itemId => $modifierGroups) {
            $map[$itemId] = array_values($modifierGroups);
        }

        return collect($map);
    }

    private function decodeModifierPayload($modifiers): array
    {
        if (empty($modifiers)) {
            return [];
        }

        if (is_string($modifiers)) {
            $decoded = json_decode($modifiers, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $trimmed = trim($modifiers);
            return $trimmed !== '' ? [$trimmed] : [];
        }

        if (is_array($modifiers)) {
            return $modifiers;
        }

        return [];
    }

    private function extractModifierOptionIds($modifiers): array
    {
        $payload = $this->decodeModifierPayload($modifiers);
        if (empty($payload)) {
            return [];
        }

        $ids = [];
        $walk = function ($entry) use (&$walk, &$ids) {
            if (is_int($entry) || (is_string($entry) && ctype_digit(trim($entry)))) {
                $ids[] = (int) $entry;
                return;
            }

            if (!is_array($entry)) {
                return;
            }

            $idLikeKeys = ['modifier_option_id', 'modifierOptionId', 'option_id', 'optionId'];
            foreach ($idLikeKeys as $key) {
                if (array_key_exists($key, $entry)) {
                    $value = $entry[$key];
                    if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
                        $ids[] = (int) $value;
                    }
                }
            }

            if (array_key_exists('selected_option_ids', $entry) && is_array($entry['selected_option_ids'])) {
                foreach ($entry['selected_option_ids'] as $optionId) {
                    if (is_int($optionId) || (is_string($optionId) && ctype_digit(trim($optionId)))) {
                        $ids[] = (int) $optionId;
                    }
                }
            }

            if (array_is_list($entry)) {
                foreach ($entry as $value) {
                    $walk($value);
                }
                return;
            }

            foreach ($entry as $key => $value) {
                if ($key === 'modifier_id') {
                    continue;
                }
                $walk($value);
            }
        };

        $walk($payload);

        return collect($ids)
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeModifiersForStorage($modifiers, array $modifierOptionNameMap): array
    {
        $payload = $this->decodeModifierPayload($modifiers);
        if (empty($payload)) {
            return [];
        }

        $names = [];
        $pushName = function ($value) use (&$names) {
            $name = trim((string) $value);
            if ($name !== '') {
                $names[] = $name;
            }
        };

        $walk = function ($entry) use (&$walk, &$names, $modifierOptionNameMap, $pushName) {
            if (is_int($entry) || (is_string($entry) && ctype_digit(trim($entry)))) {
                $optionId = (int) $entry;
                if ($optionId > 0 && !empty($modifierOptionNameMap[$optionId])) {
                    $pushName($modifierOptionNameMap[$optionId]);
                }
                return;
            }

            if (is_string($entry)) {
                $pushName($entry);
                return;
            }

            if (!is_array($entry)) {
                return;
            }

            $nameKeys = ['modifier_option_name', 'option_name', 'name', 'label'];
            foreach ($nameKeys as $key) {
                if (!empty($entry[$key]) && is_string($entry[$key])) {
                    $pushName($entry[$key]);
                }
            }

            $idLikeKeys = ['modifier_option_id', 'modifierOptionId', 'option_id', 'optionId', 'id'];
            foreach ($idLikeKeys as $key) {
                if (!array_key_exists($key, $entry)) {
                    continue;
                }

                $value = $entry[$key];
                if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
                    $optionId = (int) $value;
                    if ($optionId > 0 && !empty($modifierOptionNameMap[$optionId])) {
                        $pushName($modifierOptionNameMap[$optionId]);
                    }
                } elseif (is_string($value)) {
                    $pushName($value);
                }
            }

            if (array_key_exists('selected_option_ids', $entry) && is_array($entry['selected_option_ids'])) {
                foreach ($entry['selected_option_ids'] as $optionId) {
                    if (is_int($optionId) || (is_string($optionId) && ctype_digit(trim($optionId)))) {
                        $resolved = $modifierOptionNameMap[(int) $optionId] ?? null;
                        if ($resolved) {
                            $pushName($resolved);
                        }
                    }
                }
            }

            if (array_is_list($entry)) {
                foreach ($entry as $value) {
                    $walk($value);
                }
                return;
            }

            foreach ($entry as $key => $value) {
                if ($key === 'modifier_id') {
                    continue;
                }
                $walk($value);
            }
        };

        $walk($payload);

        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function getMenuBookOutletByOutletId(int $menuBookId, int $outletId)
    {
        return DB::table('tbl_data_outlet as o')
            ->join('menu_book_outlets as mbo', 'o.id_outlet', '=', 'mbo.outlet_id')
            ->where('mbo.menu_book_id', $menuBookId)
            ->where('o.id_outlet', $outletId)
            ->where('o.status', 'A')
            ->select('o.id_outlet', 'o.nama_outlet', 'o.qr_code', 'o.region_id')
            ->first();
    }
}

