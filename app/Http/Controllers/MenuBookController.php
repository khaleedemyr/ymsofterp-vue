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
}

