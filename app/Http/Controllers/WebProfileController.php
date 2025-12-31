<?php

namespace App\Http\Controllers;

use App\Models\WebProfilePage;
use App\Models\WebProfilePageSection;
use App\Models\WebProfileMenuItem;
use App\Models\WebProfileGallery;
use App\Models\WebProfileSetting;
use App\Models\WebProfileContact;
use App\Models\WebProfileBanner;
use App\Models\WebProfileBrand;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebProfileController extends Controller
{
    /**
     * Display admin dashboard
     */
    public function index()
    {
        return Inertia::render('WebProfile/Index');
    }

    /**
     * Display pages list
     */
    public function pagesIndex(Request $request)
    {
        $pages = WebProfilePage::orderBy('order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            })
            ->paginate(15);

        return Inertia::render('WebProfile/Pages/Index', [
            'pages' => $pages
        ]);
    }

    /**
     * Show create page form
     */
    public function create()
    {
        return Inertia::render('WebProfile/Create');
    }

    /**
     * Store new page
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:web_profile_pages,slug',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_published' => 'boolean',
            'order' => 'integer'
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $page = WebProfilePage::create($validated);

        return redirect()->route('web-profile.edit', $page->id)
            ->with('success', 'Page created successfully');
    }

    /**
     * Show edit page form
     */
    public function edit($id)
    {
        $page = WebProfilePage::with('sections')->findOrFail($id);

        return Inertia::render('WebProfile/Edit', [
            'page' => $page
        ]);
    }

    /**
     * Update page
     */
    public function update(Request $request, $id)
    {
        $page = WebProfilePage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:web_profile_pages,slug,' . $id,
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_published' => 'boolean',
            'order' => 'integer'
        ]);

        $page->update($validated);

        return redirect()->back()->with('success', 'Page updated successfully');
    }

    /**
     * Delete page
     */
    public function destroy($id)
    {
        $page = WebProfilePage::findOrFail($id);
        $page->delete();

        return redirect()->route('web-profile.index')
            ->with('success', 'Page deleted successfully');
    }

    // ========== API ENDPOINTS FOR FRONTEND ==========

    /**
     * API: Get all published pages
     */
    public function apiPages()
    {
        $pages = WebProfilePage::where('is_published', true)
            ->orderBy('order')
            ->select('id', 'title', 'slug', 'meta_title', 'meta_description')
            ->get();

        return response()->json($pages);
    }

    /**
     * API: Get single page by slug
     */
    public function apiPage($slug)
    {
        $page = WebProfilePage::where('slug', $slug)
            ->where('is_published', true)
            ->with('activeSections')
            ->firstOrFail();

        return response()->json($page);
    }

    /**
     * API: Get page sections
     */
    public function apiPageSections($id)
    {
        $sections = WebProfilePageSection::where('page_id', $id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json($sections);
    }

    /**
     * API: Get menu items
     */
    public function apiMenu()
    {
        $menuItems = WebProfileMenuItem::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children', 'page'])
            ->orderBy('order')
            ->get();

        return response()->json($menuItems);
    }

    /**
     * API: Get gallery
     */
    public function apiGallery(Request $request)
    {
        $query = WebProfileGallery::where('is_active', true);

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $galleries = $query->orderBy('order')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json($galleries);
    }

    /**
     * API: Get settings
     */
    public function apiSettings()
    {
        $settings = WebProfileSetting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    /**
     * API: Submit contact form
     */
    public function apiContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string'
        ]);

        $contact = WebProfileContact::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your message. We will get back to you soon.'
        ]);
    }

    // ========== BANNER MANAGEMENT ==========

    /**
     * Display banners list
     */
    public function bannersIndex(Request $request)
    {
        $banners = WebProfileBanner::orderBy('order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(15);

        // Transform paginated results to ensure accessors are included
        $banners->through(function ($banner) {
            // Accessors should be automatically included via $appends in model
            // But we ensure they're available
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'description' => $banner->description,
                'background_image' => $banner->background_image,
                'content_image' => $banner->content_image,
                'background_image_url' => $banner->background_image_url,
                'content_image_url' => $banner->content_image_url,
                'order' => $banner->order,
                'is_active' => $banner->is_active,
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at,
            ];
        });

        return Inertia::render('WebProfile/Banners/Index', [
            'banners' => $banners
        ]);
    }

    /**
     * Show create banner form
     */
    public function bannersCreate()
    {
        return Inertia::render('WebProfile/Banners/Create');
    }

    /**
     * Store new banner
     */
    public function bannersStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'background_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=1920,min_height=1080',
                'content_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=800,min_height=600',
                'order' => 'integer|min:0',
                'is_active' => 'boolean'
            ], [
                'background_image.required' => 'Background image is required',
                'background_image.image' => 'Background must be an image file',
                'background_image.mimes' => 'Background image must be JPG, PNG, or WEBP',
                'background_image.max' => 'Background image must not exceed 5MB',
                'background_image.dimensions' => 'Background image must be at least 1920x1080 pixels',
                'content_image.image' => 'Content must be an image file',
                'content_image.mimes' => 'Content image must be JPG, PNG, or WEBP',
                'content_image.max' => 'Content image must not exceed 5MB',
                'content_image.dimensions' => 'Content image must be at least 800x600 pixels',
            ]);

            // Use database transaction to ensure atomicity
            return DB::transaction(function () use ($request, $validated) {
                $uploadedFiles = [];

                // Upload background image
                if ($request->hasFile('background_image')) {
                    $file = $request->file('background_image');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_bg.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/banners', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload background image');
                    }
                    
                    $validated['background_image'] = $path;
                    $uploadedFiles[] = $path;
                }

                // Upload content image
                if ($request->hasFile('content_image')) {
                    $file = $request->file('content_image');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_content.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/banners', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload content image');
                    }
                    
                    $validated['content_image'] = $path;
                    $uploadedFiles[] = $path;
                }

                // Create banner record
                $banner = WebProfileBanner::create($validated);

                if (!$banner || !$banner->id) {
                    // Rollback: Delete uploaded files if database insert failed
                    foreach ($uploadedFiles as $filePath) {
                        Storage::disk('public')->delete($filePath);
                    }
                    throw new \Exception('Failed to create banner record');
                }

                return redirect()->route('web-profile.banners.index')
                    ->with('success', 'Banner created successfully');
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Banner creation failed: ' . $e->getMessage(), [
                'request' => $request->except(['background_image', 'content_image']), // Exclude files from log
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create banner: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show edit banner form
     */
    public function bannersEdit($id)
    {
        $banner = WebProfileBanner::findOrFail($id);

        return Inertia::render('WebProfile/Banners/Edit', [
            'banner' => $banner
        ]);
    }

    /**
     * Update banner
     */
    public function bannersUpdate(Request $request, $id)
    {
        $banner = WebProfileBanner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'background_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=1920,min_height=1080',
            'content_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=800,min_height=600',
            'order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Upload new background image if provided
        if ($request->hasFile('background_image')) {
            // Delete old image
            if ($banner->background_image) {
                Storage::disk('public')->delete($banner->background_image);
            }

            $file = $request->file('background_image');
            $fileName = time() . '_' . Str::slug($validated['title']) . '_bg.' . $file->getClientOriginalExtension();
            $validated['background_image'] = $file->storeAs('web-profile/banners', $fileName, 'public');
        } else {
            // Keep existing image
            unset($validated['background_image']);
        }

        // Upload new content image if provided
        if ($request->hasFile('content_image')) {
            // Delete old image
            if ($banner->content_image) {
                Storage::disk('public')->delete($banner->content_image);
            }

            $file = $request->file('content_image');
            $fileName = time() . '_' . Str::slug($validated['title']) . '_content.' . $file->getClientOriginalExtension();
            $validated['content_image'] = $file->storeAs('web-profile/banners', $fileName, 'public');
        } else {
            // Keep existing image
            unset($validated['content_image']);
        }

        $banner->update($validated);

        return redirect()->back()->with('success', 'Banner updated successfully');
    }

    /**
     * Delete banner
     */
    public function bannersDestroy($id)
    {
        $banner = WebProfileBanner::findOrFail($id);

        // Delete images
        if ($banner->background_image) {
            Storage::disk('public')->delete($banner->background_image);
        }
        if ($banner->content_image) {
            Storage::disk('public')->delete($banner->content_image);
        }

        $banner->delete();

        return redirect()->route('web-profile.banners.index')
            ->with('success', 'Banner deleted successfully');
    }

    /**
     * API: Get active banners (max 5)
     */
    public function apiBanners()
    {
        $banners = WebProfileBanner::where('is_active', true)
            ->orderBy('order')
            ->limit(5)
            ->get()
            ->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'description' => $banner->description,
                    'image' => $banner->background_image_url,
                    'contentImage' => $banner->content_image_url,
                ];
            });

        return response()->json($banners);
    }

    // ========== BRAND MANAGEMENT ==========

    /**
     * Display brands list (Admin)
     */
    public function brandsIndex(Request $request)
    {
        $brands = WebProfileBrand::orderBy('title')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(15);

        // Transform paginated results
        $brands->through(function ($brand) {
            return [
                'id' => $brand->id,
                'title' => $brand->title,
                'slug' => $brand->slug,
                'link_menu' => $brand->link_menu,
                'menu_pdf' => $brand->menu_pdf,
                'menu_pdf_url' => $brand->menu_pdf_url,
                'thumbnail' => $brand->thumbnail,
                'thumbnail_url' => $brand->thumbnail_url,
                'image' => $brand->image,
                'image_url' => $brand->image_url,
                'content' => $brand->content,
                'created_at' => $brand->created_at,
                'updated_at' => $brand->updated_at,
            ];
        });

        return Inertia::render('WebProfile/Brands/Index', [
            'brands' => $brands
        ]);
    }

    /**
     * Show create brand form
     */
    public function brandsCreate()
    {
        return Inertia::render('WebProfile/Brands/Create');
    }

    /**
     * Store new brand
     */
    public function brandsStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:web_profile_brands,slug',
                'link_menu' => 'nullable|url|max:255',
                'menu_pdf' => 'nullable|file|mimes:pdf|max:10240',
                'thumbnail' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
                'content' => 'nullable|string',
            ], [
                'thumbnail.required' => 'Thumbnail image is required',
                'thumbnail.image' => 'Thumbnail must be an image file',
                'menu_pdf.file' => 'Menu PDF must be a file',
                'menu_pdf.mimes' => 'Menu PDF must be a PDF file',
            ]);

            return DB::transaction(function () use ($request, $validated) {
                $uploadedFiles = [];

                // Generate slug if not provided
                if (empty($validated['slug'])) {
                    $validated['slug'] = Str::slug($validated['title']);
                }

                // Upload thumbnail
                if ($request->hasFile('thumbnail')) {
                    $file = $request->file('thumbnail');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_thumb.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload thumbnail');
                    }
                    
                    $validated['thumbnail'] = $path;
                    $uploadedFiles[] = $path;
                }

                // Upload image
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_img.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload image');
                    }
                    
                    $validated['image'] = $path;
                    $uploadedFiles[] = $path;
                }

                // Upload menu PDF
                if ($request->hasFile('menu_pdf')) {
                    $file = $request->file('menu_pdf');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_menu.pdf';
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload menu PDF');
                    }
                    
                    $validated['menu_pdf'] = $path;
                    $uploadedFiles[] = $path;
                }

                // Set created_by
                $validated['created_by'] = auth()->user()->name ?? 'System';

                // Create brand record
                $brand = WebProfileBrand::create($validated);

                if (!$brand || !$brand->id) {
                    foreach ($uploadedFiles as $filePath) {
                        Storage::disk('public')->delete($filePath);
                    }
                    throw new \Exception('Failed to create brand record');
                }

                return redirect()->route('web-profile.brands.index')
                    ->with('success', 'Brand created successfully');
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Brand creation failed: ' . $e->getMessage(), [
                'request' => $request->except(['thumbnail', 'image', 'menu_pdf']),
                'trace' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create brand: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show edit brand form
     */
    public function brandsEdit($id)
    {
        $brand = WebProfileBrand::findOrFail($id);

        return Inertia::render('WebProfile/Brands/Edit', [
            'brand' => [
                'id' => $brand->id,
                'title' => $brand->title,
                'slug' => $brand->slug,
                'link_menu' => $brand->link_menu,
                'menu_pdf' => $brand->menu_pdf,
                'menu_pdf_url' => $brand->menu_pdf_url,
                'thumbnail' => $brand->thumbnail,
                'thumbnail_url' => $brand->thumbnail_url,
                'image' => $brand->image,
                'image_url' => $brand->image_url,
                'content' => $brand->content,
            ]
        ]);
    }

    /**
     * Update brand
     */
    public function brandsUpdate(Request $request, $id)
    {
        $brand = WebProfileBrand::findOrFail($id);

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:web_profile_brands,slug,' . $id,
                'link_menu' => 'nullable|url|max:255',
                'menu_pdf' => 'nullable|file|mimes:pdf|max:10240',
                'thumbnail' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
                'content' => 'nullable|string',
            ]);

            return DB::transaction(function () use ($request, $validated, $brand) {
                $uploadedFiles = [];

                // Upload new thumbnail if provided
                if ($request->hasFile('thumbnail')) {
                    if ($brand->thumbnail) {
                        Storage::disk('public')->delete($brand->thumbnail);
                    }

                    $file = $request->file('thumbnail');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_thumb.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload thumbnail');
                    }
                    
                    $validated['thumbnail'] = $path;
                    $uploadedFiles[] = $path;
                } else {
                    unset($validated['thumbnail']);
                }

                // Upload new image if provided
                if ($request->hasFile('image')) {
                    if ($brand->image) {
                        Storage::disk('public')->delete($brand->image);
                    }

                    $file = $request->file('image');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_img.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload image');
                    }
                    
                    $validated['image'] = $path;
                    $uploadedFiles[] = $path;
                } else {
                    unset($validated['image']);
                }

                // Upload new menu PDF if provided
                if ($request->hasFile('menu_pdf')) {
                    if ($brand->menu_pdf) {
                        Storage::disk('public')->delete($brand->menu_pdf);
                    }

                    $file = $request->file('menu_pdf');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_menu.pdf';
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');
                    
                    if (!$path) {
                        throw new \Exception('Failed to upload menu PDF');
                    }
                    
                    $validated['menu_pdf'] = $path;
                    $uploadedFiles[] = $path;
                } else {
                    unset($validated['menu_pdf']);
                }

                // Set updated_by
                $validated['updated_by'] = auth()->user()->name ?? 'System';

                $brand->update($validated);

                return redirect()->back()->with('success', 'Brand updated successfully');
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Brand update failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update brand: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Delete brand
     */
    public function brandsDestroy($id)
    {
        $brand = WebProfileBrand::findOrFail($id);

        // Delete files
        if ($brand->thumbnail) {
            Storage::disk('public')->delete($brand->thumbnail);
        }
        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }
        if ($brand->menu_pdf) {
            Storage::disk('public')->delete($brand->menu_pdf);
        }

        $brand->delete();

        return redirect()->route('web-profile.brands.index')
            ->with('success', 'Brand deleted successfully');
    }

    /**
     * API: Get all brands
     */
    public function apiBrands()
    {
        $brands = WebProfileBrand::orderBy('title')
            ->get()
            ->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'title' => $brand->title,
                    'slug' => $brand->slug,
                    'link_menu' => $brand->link_menu,
                    'menu_pdf_url' => $brand->menu_pdf_url,
                    'thumbnail_url' => $brand->thumbnail_url,
                    'image_url' => $brand->image_url,
                    'content' => $brand->content,
                ];
            });

        return response()->json($brands);
    }
}

