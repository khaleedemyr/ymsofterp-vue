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
use App\Models\WebProfileHomeBlock;
use App\Models\WebProfileHomeServicePackage;
use App\Models\WebProfileJustusAppsBlock;
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
                'background_media_type' => $banner->background_media_type,
                'background_is_video' => $banner->background_is_video,
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
                'background_image' => [
                    'required',
                    'file',
                    'mimes:jpeg,jpg,png,webp,mp4,webm',
                    'max:51200',
                    function ($attribute, $value, $fail) {
                        if (!$value || !str_starts_with((string) $value->getMimeType(), 'image/')) {
                            return;
                        }

                        $imageInfo = @getimagesize($value->getPathname());
                        if (!$imageInfo) {
                            $fail('Head banner image tidak valid.');
                            return;
                        }

                        $width = $imageInfo[0] ?? 0;
                        $height = $imageInfo[1] ?? 0;
                        if ($width < 1920 || $height < 1080) {
                            $fail('Head banner image minimal 1920x1080 piksel agar fit ke layar.');
                        }
                    },
                ],
                'content_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=800,min_height=600',
                'order' => 'integer|min:0',
                'is_active' => 'boolean'
            ], [
                'background_image.required' => 'Head banner wajib diupload',
                'background_image.mimes' => 'Head banner harus berformat JPG, PNG, WEBP, MP4, atau WEBM',
                'background_image.max' => 'Head banner maksimal 50MB',
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
            'background_image' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,webp,mp4,webm',
                'max:51200',
                function ($attribute, $value, $fail) {
                    if (!$value || !str_starts_with((string) $value->getMimeType(), 'image/')) {
                        return;
                    }

                    $imageInfo = @getimagesize($value->getPathname());
                    if (!$imageInfo) {
                        $fail('Head banner image tidak valid.');
                        return;
                    }

                    $width = $imageInfo[0] ?? 0;
                    $height = $imageInfo[1] ?? 0;
                    if ($width < 1920 || $height < 1080) {
                        $fail('Head banner image minimal 1920x1080 piksel agar fit ke layar.');
                    }
                },
            ],
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
                    'headMediaType' => $banner->background_media_type,
                    'headIsVideo' => $banner->background_is_video,
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
                'logo_cp' => $brand->logo_cp,
                'logo_cp_url' => $brand->logo_cp_url,
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
                'logo_cp' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
                'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
                'content' => 'nullable|string',
            ], [
                'thumbnail.required' => 'Thumbnail image is required',
                'thumbnail.image' => 'Thumbnail must be an image file',
                'logo_cp.image' => 'Logo CP must be an image file',
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

                // Upload company profile logo
                if ($request->hasFile('logo_cp')) {
                    $file = $request->file('logo_cp');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_logo_cp.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');

                    if (!$path) {
                        throw new \Exception('Failed to upload company profile logo');
                    }

                    $validated['logo_cp'] = $path;
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
                'logo_cp' => $brand->logo_cp,
                'logo_cp_url' => $brand->logo_cp_url,
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
                'logo_cp' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
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

                // Upload new company profile logo if provided
                if ($request->hasFile('logo_cp')) {
                    if ($brand->logo_cp) {
                        Storage::disk('public')->delete($brand->logo_cp);
                    }

                    $file = $request->file('logo_cp');
                    $fileName = time() . '_' . Str::slug($validated['title']) . '_logo_cp.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('web-profile/brands', $fileName, 'public');

                    if (!$path) {
                        throw new \Exception('Failed to upload company profile logo');
                    }

                    $validated['logo_cp'] = $path;
                    $uploadedFiles[] = $path;
                } else {
                    unset($validated['logo_cp']);
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
        if ($brand->logo_cp) {
            Storage::disk('public')->delete($brand->logo_cp);
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
                    'logo_cp_url' => $brand->logo_cp_url,
                    'image_url' => $brand->image_url,
                    'content' => $brand->content,
                ];
            });

        return response()->json($brands);
    }

    // ========== HOME PAGE BLOCKS (Company Profile) ==========

    public function homeBlocksIndex(Request $request)
    {
        $blocks = WebProfileHomeBlock::orderBy('sort_order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(20);

        return Inertia::render('WebProfile/HomeBlocks/Index', [
            'blocks' => $blocks,
        ]);
    }

    public function homeBlocksCreate()
    {
        return Inertia::render('WebProfile/HomeBlocks/Create');
    }

    public function homeBlocksStore(Request $request)
    {
        $validated = $request->validate([
            'block_type' => 'required|in:text,video',
            'sort_order' => 'integer|min:0',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'caption' => 'nullable|string',
            'bg_variant' => 'required|in:dark,light,video_dark',
            'video' => 'nullable|file|mimes:mp4,webm|max:102400',
            'is_active' => 'boolean',
        ]);

        if ($validated['block_type'] === 'video' && ! $request->hasFile('video')) {
            return redirect()->back()
                ->withErrors(['video' => 'Video wajib diupload untuk blok tipe video.'])
                ->withInput();
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $fileName = time().'_home_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $validated['video_path'] = $file->storeAs('web-profile/home-blocks', $fileName, 'public');
        }

        unset($validated['video']);

        WebProfileHomeBlock::create($validated);

        return redirect()->route('web-profile.home-blocks.index')
            ->with('success', 'Blok berhasil ditambahkan.');
    }

    public function homeBlocksEdit($id)
    {
        $block = WebProfileHomeBlock::findOrFail($id);

        return Inertia::render('WebProfile/HomeBlocks/Edit', [
            'block' => [
                'id' => $block->id,
                'block_type' => $block->block_type,
                'sort_order' => $block->sort_order,
                'title' => $block->title,
                'body' => $block->body,
                'caption' => $block->caption,
                'video_path' => $block->video_path,
                'video_url' => $block->video_url,
                'bg_variant' => $block->bg_variant,
                'is_active' => $block->is_active,
            ],
        ]);
    }

    public function homeBlocksUpdate(Request $request, $id)
    {
        $block = WebProfileHomeBlock::findOrFail($id);

        $validated = $request->validate([
            'block_type' => 'required|in:text,video',
            'sort_order' => 'integer|min:0',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'caption' => 'nullable|string',
            'bg_variant' => 'required|in:dark,light,video_dark',
            'video' => 'nullable|file|mimes:mp4,webm|max:102400',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('video')) {
            if ($block->video_path) {
                Storage::disk('public')->delete($block->video_path);
            }
            $file = $request->file('video');
            $fileName = time().'_home_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $validated['video_path'] = $file->storeAs('web-profile/home-blocks', $fileName, 'public');
        }

        unset($validated['video']);

        $block->update($validated);

        return redirect()->back()->with('success', 'Blok berhasil diperbarui.');
    }

    public function homeBlocksDestroy($id)
    {
        $block = WebProfileHomeBlock::findOrFail($id);
        if ($block->video_path) {
            Storage::disk('public')->delete($block->video_path);
        }
        $block->delete();

        return redirect()->route('web-profile.home-blocks.index')
            ->with('success', 'Blok berhasil dihapus.');
    }

    /**
     * API: Home page blocks for company profile frontend
     */
    public function apiHomeBlocks()
    {
        $blocks = WebProfileHomeBlock::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($block) {
                return [
                    'id' => $block->id,
                    'block_type' => $block->block_type,
                    'title' => $block->title,
                    'body' => $block->body,
                    'caption' => $block->caption,
                    'video_url' => $block->video_url,
                    'bg_variant' => $block->bg_variant,
                ];
            });

        return response()->json($blocks);
    }

    // ========== HOME SERVICE PACKAGES (Company profile web) ==========

    public function homeServicePackagesIndex(Request $request)
    {
        $packages = WebProfileHomeServicePackage::with('brand')
            ->orderBy('sort_order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(20);

        $heroPath = WebProfileSetting::where('key', 'home_service_hero_image')->value('value');
        $heroUrl = $heroPath ? $this->publicStorageUrl($heroPath) : null;

        return Inertia::render('WebProfile/HomeServicePackages/Index', [
            'packages' => $packages,
            'hero_image_path' => $heroPath,
            'hero_image_url' => $heroUrl,
        ]);
    }

    public function homeServicePackagesCreate()
    {
        $brands = WebProfileBrand::orderBy('title')->get(['id', 'title']);

        return Inertia::render('WebProfile/HomeServicePackages/Create', [
            'brands' => $brands,
        ]);
    }

    public function homeServicePackagesStore(Request $request)
    {
        $validated = $request->validate([
            'web_profile_brand_id' => 'required|exists:web_profile_brands,id',
            'title' => 'required|string|max:255',
            'price_label' => 'nullable|string|max:255',
            'body_html' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        WebProfileHomeServicePackage::create([
            'web_profile_brand_id' => $validated['web_profile_brand_id'],
            'title' => $validated['title'],
            'price_label' => $validated['price_label'] ?? null,
            'body_html' => $validated['body_html'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('web-profile.home-service-packages.index')
            ->with('success', 'Paket Home Service berhasil ditambahkan.');
    }

    public function homeServicePackagesEdit($id)
    {
        $pkg = WebProfileHomeServicePackage::with('brand')->findOrFail($id);
        $brands = WebProfileBrand::orderBy('title')->get(['id', 'title']);

        return Inertia::render('WebProfile/HomeServicePackages/Edit', [
            'package' => [
                'id' => $pkg->id,
                'web_profile_brand_id' => $pkg->web_profile_brand_id,
                'title' => $pkg->title,
                'price_label' => $pkg->price_label,
                'body_html' => $pkg->body_html,
                'sort_order' => $pkg->sort_order,
                'is_active' => $pkg->is_active,
            ],
            'brands' => $brands,
        ]);
    }

    public function homeServicePackagesUpdate(Request $request, $id)
    {
        $pkg = WebProfileHomeServicePackage::findOrFail($id);

        $validated = $request->validate([
            'web_profile_brand_id' => 'required|exists:web_profile_brands,id',
            'title' => 'required|string|max:255',
            'price_label' => 'nullable|string|max:255',
            'body_html' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $pkg->update([
            'web_profile_brand_id' => $validated['web_profile_brand_id'],
            'title' => $validated['title'],
            'price_label' => $validated['price_label'] ?? null,
            'body_html' => $validated['body_html'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('web-profile.home-service-packages.index')
            ->with('success', 'Paket berhasil diperbarui.');
    }

    public function homeServicePackagesDestroy($id)
    {
        WebProfileHomeServicePackage::findOrFail($id)->delete();

        return redirect()->route('web-profile.home-service-packages.index')
            ->with('success', 'Paket berhasil dihapus.');
    }

    public function homeServiceHeroStore(Request $request)
    {
        $request->validate([
            'hero_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if ($request->boolean('remove_hero')) {
            $old = WebProfileSetting::where('key', 'home_service_hero_image')->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            WebProfileSetting::where('key', 'home_service_hero_image')->delete();
        }

        if ($request->hasFile('hero_image')) {
            $old = WebProfileSetting::where('key', 'home_service_hero_image')->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }

            $file = $request->file('hero_image');
            $fileName = time().'_home_service_hero.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('web-profile/home-service', $fileName, 'public');

            WebProfileSetting::updateOrCreate(
                ['key' => 'home_service_hero_image'],
                ['value' => $path, 'type' => 'image']
            );
        }

        return redirect()->route('web-profile.home-service-packages.index')
            ->with('success', 'Pengaturan header Home Service disimpan.');
    }

    /**
     * API: Home Service packages + hero for Justus Group web frontend
     */
    public function apiHomeServicePackages()
    {
        $packages = WebProfileHomeServicePackage::where('is_active', true)
            ->with('brand')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($p) {
                $b = $p->brand;

                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'price_label' => $p->price_label,
                    'body_html' => $p->body_html,
                    'sort_order' => $p->sort_order,
                    'brand' => $b ? [
                        'id' => $b->id,
                        'title' => $b->title,
                        'slug' => $b->slug,
                        'logo_cp_url' => $b->logo_cp_url,
                        'thumbnail_url' => $b->thumbnail_url,
                        'image_url' => $b->image_url,
                    ] : null,
                ];
            });

        $heroPath = WebProfileSetting::where('key', 'home_service_hero_image')->value('value');

        return response()->json([
            'packages' => $packages,
            'hero_image_url' => $heroPath ? $this->publicStorageUrl($heroPath) : null,
        ]);
    }

    // ========== JUSTUS APPS PAGE (Company profile web) ==========

    public function justusAppsIndex(Request $request)
    {
        $blocks = WebProfileJustusAppsBlock::orderBy('sort_order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(20);

        $heroPath = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value');
        $heroUrl = $heroPath ? $this->publicStorageUrl($heroPath) : null;
        $heroMediaType = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('type');
        $playstoreUrl = WebProfileSetting::where('key', 'justus_apps_playstore_url')->value('value');
        $appstoreUrl = WebProfileSetting::where('key', 'justus_apps_appstore_url')->value('value');

        return Inertia::render('WebProfile/JustusApps/Index', [
            'blocks' => $blocks,
            'hero_image_path' => $heroPath,
            'hero_image_url' => $heroUrl,
            'hero_media_type' => $heroMediaType,
            'playstore_url' => $playstoreUrl,
            'appstore_url' => $appstoreUrl,
        ]);
    }

    public function justusAppsCreate()
    {
        return Inertia::render('WebProfile/JustusApps/Create');
    }

    public function justusAppsStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time().'_justus_apps_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $validated['image_path'] = $file->storeAs('web-profile/justus-apps', $fileName, 'public');
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['image']);

        WebProfileJustusAppsBlock::create($validated);

        return redirect()->route('web-profile.justus-apps.index')
            ->with('success', 'Konten Justus Apps berhasil ditambahkan.');
    }

    public function justusAppsEdit($id)
    {
        $block = WebProfileJustusAppsBlock::findOrFail($id);

        return Inertia::render('WebProfile/JustusApps/Edit', [
            'block' => [
                'id' => $block->id,
                'title' => $block->title,
                'body' => $block->body,
                'sort_order' => $block->sort_order,
                'is_active' => $block->is_active,
                'image_path' => $block->image_path,
                'image_url' => $block->image_url,
            ],
        ]);
    }

    public function justusAppsUpdate(Request $request, $id)
    {
        $block = WebProfileJustusAppsBlock::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'sort_order' => 'integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($block->image_path) {
                Storage::disk('public')->delete($block->image_path);
            }
            $file = $request->file('image');
            $fileName = time().'_justus_apps_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $validated['image_path'] = $file->storeAs('web-profile/justus-apps', $fileName, 'public');
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['image']);
        $block->update($validated);

        return redirect()->route('web-profile.justus-apps.index')
            ->with('success', 'Konten Justus Apps berhasil diperbarui.');
    }

    public function justusAppsDestroy($id)
    {
        $block = WebProfileJustusAppsBlock::findOrFail($id);
        if ($block->image_path) {
            Storage::disk('public')->delete($block->image_path);
        }
        $block->delete();

        return redirect()->route('web-profile.justus-apps.index')
            ->with('success', 'Konten Justus Apps berhasil dihapus.');
    }

    public function justusAppsSettingsStore(Request $request)
    {
        $request->validate([
            'hero_image' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm|max:51200',
            'playstore_url' => 'nullable|url|max:255',
            'appstore_url' => 'nullable|url|max:255',
        ]);

        if ($request->boolean('remove_hero')) {
            $old = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            WebProfileSetting::where('key', 'justus_apps_hero_image')->delete();
        }

        if ($request->hasFile('hero_image')) {
            $old = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            $file = $request->file('hero_image');
            $fileName = time().'_justus_apps_hero.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('web-profile/justus-apps', $fileName, 'public');
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            WebProfileSetting::updateOrCreate(
                ['key' => 'justus_apps_hero_image'],
                ['value' => $path, 'type' => $isVideo ? 'video' : 'image']
            );
        }

        WebProfileSetting::updateOrCreate(
            ['key' => 'justus_apps_playstore_url'],
            ['value' => $request->input('playstore_url', ''), 'type' => 'text']
        );
        WebProfileSetting::updateOrCreate(
            ['key' => 'justus_apps_appstore_url'],
            ['value' => $request->input('appstore_url', ''), 'type' => 'text']
        );

        return redirect()->route('web-profile.justus-apps.index')
            ->with('success', 'Pengaturan Justus Apps berhasil disimpan.');
    }

    /**
     * API: Justus Apps page (hero + content blocks + store links)
     */
    public function apiJustusAppsPage()
    {
        $blocks = WebProfileJustusAppsBlock::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($block) {
                return [
                    'id' => $block->id,
                    'title' => $block->title,
                    'body' => $block->body,
                    'image_url' => $block->image_url,
                    'sort_order' => $block->sort_order,
                ];
            });

        $heroPath = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value');
        $heroMediaType = WebProfileSetting::where('key', 'justus_apps_hero_image')->value('type');
        $playstoreUrl = WebProfileSetting::where('key', 'justus_apps_playstore_url')->value('value');
        $appstoreUrl = WebProfileSetting::where('key', 'justus_apps_appstore_url')->value('value');

        return response()->json([
            'hero_image_url' => $heroPath ? $this->publicStorageUrl($heroPath) : null,
            'hero_media_type' => $heroMediaType,
            'playstore_url' => $playstoreUrl,
            'appstore_url' => $appstoreUrl,
            'blocks' => $blocks,
        ]);
    }

    // ========== ABOUT PAGE (Company profile web) ==========

    public function aboutPageIndex()
    {
        $keys = [
            'about_title',
            'about_subtitle',
            'about_our_story_content',
            'about_brand_philosophy_quote',
            'about_brand_philosophy_content',
            'about_profile_role',
            'about_vision_title',
            'about_vision_content',
            'about_mission_title',
            'about_mission_content',
            'about_hero_image',
            'about_logo_image',
            'about_profile_image',
        ];

        $settings = WebProfileSetting::whereIn('key', $keys)->get()->keyBy('key');

        return Inertia::render('WebProfile/AboutPage/Index', [
            'about' => [
                'about_title' => $settings->get('about_title')->value ?? 'OUR STORY',
                'about_subtitle' => $settings->get('about_subtitle')->value ?? 'Elevating Culinary Experiences Since 2005',
                'about_our_story_content' => $settings->get('about_our_story_content')->value ?? '',
                'about_brand_philosophy_quote' => $settings->get('about_brand_philosophy_quote')->value ?? '',
                'about_brand_philosophy_content' => $settings->get('about_brand_philosophy_content')->value ?? '',
                'about_profile_role' => $settings->get('about_profile_role')->value ?? 'Founder & CEO Justus Group',
                'about_vision_title' => $settings->get('about_vision_title')->value ?? 'VISION',
                'about_vision_content' => $settings->get('about_vision_content')->value ?? '',
                'about_mission_title' => $settings->get('about_mission_title')->value ?? 'MISSION',
                'about_mission_content' => $settings->get('about_mission_content')->value ?? '',
                'about_hero_image_path' => $settings->get('about_hero_image')->value ?? null,
                'about_hero_image_url' => ($settings->get('about_hero_image')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('about_hero_image')->value)
                    : null,
                'about_logo_image_path' => $settings->get('about_logo_image')->value ?? null,
                'about_logo_image_url' => ($settings->get('about_logo_image')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('about_logo_image')->value)
                    : null,
                'about_profile_image_path' => $settings->get('about_profile_image')->value ?? null,
                'about_profile_image_url' => ($settings->get('about_profile_image')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('about_profile_image')->value)
                    : null,
            ],
        ]);
    }

    public function aboutPageStore(Request $request)
    {
        $validated = $request->validate([
            'about_title' => 'nullable|string|max:255',
            'about_subtitle' => 'nullable|string|max:255',
            'about_our_story_content' => 'nullable|string',
            'about_brand_philosophy_quote' => 'nullable|string|max:255',
            'about_brand_philosophy_content' => 'nullable|string',
            'about_profile_role' => 'nullable|string|max:255',
            'about_vision_title' => 'nullable|string|max:255',
            'about_vision_content' => 'nullable|string',
            'about_mission_title' => 'nullable|string|max:255',
            'about_mission_content' => 'nullable|string',
            'hero_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'logo_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_hero' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
            'remove_profile' => 'nullable|boolean',
        ]);

        $imageConfig = [
            'about_hero_image' => [
                'input' => 'hero_image',
                'remove' => 'remove_hero',
                'name' => 'about_hero',
            ],
            'about_logo_image' => [
                'input' => 'logo_image',
                'remove' => 'remove_logo',
                'name' => 'about_logo',
            ],
            'about_profile_image' => [
                'input' => 'profile_image',
                'remove' => 'remove_profile',
                'name' => 'about_profile',
            ],
        ];

        foreach ($imageConfig as $settingKey => $cfg) {
            if ($request->boolean($cfg['remove'])) {
                $old = WebProfileSetting::where('key', $settingKey)->value('value');
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
                WebProfileSetting::where('key', $settingKey)->delete();
            }

            if ($request->hasFile($cfg['input'])) {
                $old = WebProfileSetting::where('key', $settingKey)->value('value');
                if ($old) {
                    Storage::disk('public')->delete($old);
                }
                $file = $request->file($cfg['input']);
                $fileName = time().'_'.$cfg['name'].'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('web-profile/about-page', $fileName, 'public');
                WebProfileSetting::updateOrCreate(
                    ['key' => $settingKey],
                    ['value' => $path, 'type' => 'image']
                );
            }
        }

        $textKeys = [
            'about_title',
            'about_subtitle',
            'about_our_story_content',
            'about_brand_philosophy_quote',
            'about_brand_philosophy_content',
            'about_profile_role',
            'about_vision_title',
            'about_vision_content',
            'about_mission_title',
            'about_mission_content',
        ];

        foreach ($textKeys as $key) {
            WebProfileSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $validated[$key] ?? '', 'type' => 'text']
            );
        }

        return redirect()->route('web-profile.about-page.index')
            ->with('success', 'Pengaturan About Page berhasil disimpan.');
    }

    public function apiAboutPage()
    {
        $keys = [
            'about_title',
            'about_subtitle',
            'about_our_story_content',
            'about_brand_philosophy_quote',
            'about_brand_philosophy_content',
            'about_profile_role',
            'about_vision_title',
            'about_vision_content',
            'about_mission_title',
            'about_mission_content',
            'about_hero_image',
            'about_logo_image',
            'about_profile_image',
        ];

        $settings = WebProfileSetting::whereIn('key', $keys)->get()->keyBy('key');

        $heroPath = $settings->get('about_hero_image')->value ?? null;
        $logoPath = $settings->get('about_logo_image')->value ?? null;
        $profilePath = $settings->get('about_profile_image')->value ?? null;

        return response()->json([
            'title' => $settings->get('about_title')->value ?? 'OUR STORY',
            'subtitle' => $settings->get('about_subtitle')->value ?? 'Elevating Culinary Experiences Since 2005',
            'hero_image_url' => $heroPath ? $this->publicStorageUrl($heroPath) : null,
            'sections' => [
                [
                    'id' => 'our-story',
                    'title' => 'About Justus Group',
                    'content' => $settings->get('about_our_story_content')->value ?? '',
                    'image_url' => null,
                ],
                [
                    'id' => 'brand-philosophy',
                    'title' => 'Brand Philosophy',
                    'content' => trim(
                        ($settings->get('about_brand_philosophy_quote')->value ?? '').
                        (($settings->get('about_brand_philosophy_quote')->value ?? '') ? "\n\n" : '').
                        ($settings->get('about_brand_philosophy_content')->value ?? '')
                    ),
                    'image_url' => $logoPath ? $this->publicStorageUrl($logoPath) : null,
                ],
                [
                    'id' => 'vision-mission',
                    'title' => 'Yudi Boim',
                    'subtitle' => $settings->get('about_profile_role')->value ?? 'Founder & CEO Justus Group',
                    'content' => trim(
                        ($settings->get('about_vision_title')->value ?? 'VISION')."\n".
                        ($settings->get('about_vision_content')->value ?? '')."\n\n".
                        ($settings->get('about_mission_title')->value ?? 'MISSION')."\n".
                        ($settings->get('about_mission_content')->value ?? '')
                    ),
                    'image_url' => $profilePath ? $this->publicStorageUrl($profilePath) : null,
                ],
            ],
        ]);
    }

    private function publicStorageUrl(string $path): string
    {
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }

        if (request() && request()->getSchemeAndHttpHost()) {
            $baseUrl = request()->getSchemeAndHttpHost();
        } else {
            $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        }

        $pathParts = explode('/', $path);
        $encodedParts = array_map(static function ($part) {
            return rawurlencode($part);
        }, $pathParts);
        $encodedPath = implode('/', $encodedParts);

        return $baseUrl.'/storage/'.$encodedPath;
    }
}

