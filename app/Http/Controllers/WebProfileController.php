<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\WebProfilePage;
use App\Models\WebProfilePageSection;
use App\Models\WebProfileMenuItem;
use App\Models\WebProfileGallery;
use App\Models\WebProfileSetting;
use App\Models\WebProfileContact;
use App\Models\WebProfileBanner;
use App\Models\WebProfilePromoSlide;
use App\Models\WebProfileBrand;
use App\Models\WebProfileHomeBlock;
use App\Models\WebProfileHomeServicePackage;
use App\Models\WebProfileHomeServiceLanding;
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
     * Display payment settings (QRIS).
     */
    public function paymentSettingsIndex()
    {
        $outlets = \App\Models\Outlet::query()
            ->active()
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet']);

        $selectedOutletId = (int) request()->query('outlet_id', 0);
        $key = $this->qrisSettingKey($selectedOutletId ?: null);
        $fallbackKey = 'reservation_qris_image_path';

        $qrisImagePath = WebProfileSetting::where('key', $key)->value('value');
        if (!$qrisImagePath && $selectedOutletId > 0) {
            $qrisImagePath = WebProfileSetting::where('key', $fallbackKey)->value('value');
        }

        return Inertia::render('WebProfile/PaymentSettings/Index', [
            'outlets' => $outlets,
            'selected_outlet_id' => $selectedOutletId,
            'qris_image_path' => $qrisImagePath,
            'qris_image_url' => $qrisImagePath
                ? route('api.web-profile.qris-image', ['outlet_id' => $selectedOutletId ?: null])
                : null,
        ]);
    }

    /**
     * Store payment settings (QRIS) using private storage.
     */
    public function paymentSettingsStore(Request $request)
    {
        $request->validate([
            'outlet_id' => 'nullable|integer|min:1',
            'qris_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_qris' => 'nullable|boolean',
        ]);

        $outletId = $request->filled('outlet_id') ? (int) $request->outlet_id : null;
        $key = $this->qrisSettingKey($outletId);
        $oldPath = WebProfileSetting::where('key', $key)->value('value');

        if ($request->boolean('remove_qris') && $oldPath) {
            Storage::disk('local')->delete($oldPath);
            WebProfileSetting::where('key', $key)->delete();
            $oldPath = null;
        }

        if ($request->hasFile('qris_image')) {
            if ($oldPath) {
                Storage::disk('local')->delete($oldPath);
            }

            $file = $request->file('qris_image');
            $fileName = time().'_qris_'.Str::random(8).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('web-profile/qris', $fileName, 'local');

            WebProfileSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $path, 'type' => 'image']
            );
        }

        $newPath = WebProfileSetting::where('key', $key)->value('value');
        if ($oldPath !== $newPath) {
            $this->logWebProfileSecurityEvent(
                $request,
                'payment_qris_updated',
                'QRIS pembayaran reservasi diperbarui',
                ['qris_setting_key' => $key, 'reservation_qris_image_path' => $oldPath],
                ['qris_setting_key' => $key, 'reservation_qris_image_path' => $newPath]
            );
        }

        return redirect()
            ->route('web-profile.payment-settings.index', ['outlet_id' => $outletId])
            ->with('success', 'Pengaturan QRIS berhasil disimpan.');
    }

    /**
     * Security monitoring for critical Web Profile changes.
     */
    public function securityMonitoringIndex(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $activityType = trim((string) $request->input('activity_type', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $logs = ActivityLog::query()
            ->with(['user:id,name,nama_lengkap,email'])
            ->where('module', 'web_profile_security')
            ->when($activityType !== '', function ($query) use ($activityType) {
                $query->where('activity_type', $activityType);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('description', 'like', "%{$q}%")
                        ->orWhere('ip_address', 'like', "%{$q}%")
                        ->orWhere('activity_type', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $logs->through(function ($row) {
            return [
                'id' => $row->id,
                'activity_type' => $row->activity_type,
                'description' => $row->description,
                'ip_address' => $row->ip_address,
                'created_at' => $row->created_at,
                'user_name' => $row->user?->nama_lengkap ?? $row->user?->name ?? $row->user?->email ?? 'System',
                'old_data' => is_array($row->old_data) ? $row->old_data : null,
                'new_data' => is_array($row->new_data) ? $row->new_data : null,
            ];
        });

        return Inertia::render('WebProfile/SecurityMonitoring/Index', [
            'logs' => $logs,
            'filters' => [
                'q' => $q,
                'activity_type' => $activityType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'activityTypes' => [
                'navbar_menu_created',
                'navbar_menu_updated',
                'navbar_menu_deleted',
                'reservation_web_links_updated',
                'payment_qris_updated',
                'banner_created',
                'banner_updated',
                'banner_deleted',
                'home_service_landing_updated',
                'justus_apps_settings_updated',
                'about_page_settings_updated',
                'careers_page_settings_updated',
            ],
        ]);
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
        $settings = WebProfileSetting::all()->pluck('value', 'key')->toArray();

        if (!empty($settings['reservation_qris_image_path'])) {
            $settings['reservation_qris_image_url'] = route('api.web-profile.qris-image');
        }

        return response()->json($settings);
    }

    /**
     * API: Serve reservation QRIS image from private storage.
     */
    public function apiQrisImage()
    {
        $outletId = request()->filled('outlet_id') ? (int) request()->query('outlet_id') : null;
        $path = WebProfileSetting::where('key', $this->qrisSettingKey($outletId))->value('value');
        if (!$path && $outletId) {
            $path = WebProfileSetting::where('key', 'reservation_qris_image_path')->value('value');
        }

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'QRIS image not found.');
        }

        $absolutePath = Storage::disk('local')->path($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function qrisSettingKey(?int $outletId): string
    {
        return $outletId ? "reservation_qris_image_path_outlet_{$outletId}" : 'reservation_qris_image_path';
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

                $this->logWebProfileSecurityEvent(
                    $request,
                    'banner_created',
                    'Banner dibuat: '.$banner->title,
                    null,
                    [
                        'banner_id' => $banner->id,
                        'title' => $banner->title,
                        'order' => $banner->order,
                        'is_active' => $banner->is_active,
                        'background_media_type' => $banner->background_media_type,
                    ]
                );

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
        $oldSnapshot = [
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'order' => $banner->order,
            'is_active' => $banner->is_active,
            'background_media_type' => $banner->background_media_type,
        ];

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

        $this->logWebProfileSecurityEvent(
            $request,
            'banner_updated',
            'Banner diperbarui: '.$banner->title,
            $oldSnapshot,
            [
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'order' => $banner->order,
                'is_active' => $banner->is_active,
                'background_media_type' => $banner->background_media_type,
            ]
        );

        return redirect()->back()->with('success', 'Banner updated successfully');
    }

    /**
     * Delete banner
     */
    public function bannersDestroy($id)
    {
        $banner = WebProfileBanner::findOrFail($id);
        $oldSnapshot = [
            'banner_id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'order' => $banner->order,
            'is_active' => $banner->is_active,
            'background_media_type' => $banner->background_media_type,
        ];

        // Delete images
        if ($banner->background_image) {
            Storage::disk('public')->delete($banner->background_image);
        }
        if ($banner->content_image) {
            Storage::disk('public')->delete($banner->content_image);
        }

        $banner->delete();

        $this->logWebProfileSecurityEvent(
            request(),
            'banner_deleted',
            'Banner dihapus: '.$oldSnapshot['title'],
            $oldSnapshot,
            null
        );

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

    /**
     * API: Home promo slider slides (public website)
     */
    public function apiPromoSlides()
    {
        $slides = WebProfilePromoSlide::where('is_active', true)
            ->orderBy('order')
            ->limit(20)
            ->get()
            ->map(function ($slide) {
                return [
                    'id' => $slide->id,
                    'title' => $slide->title,
                    'image' => $slide->image_url,
                    'link_url' => $slide->link_url,
                ];
            });

        return response()->json($slides);
    }

    // ========== PROMO SLIDES (HOME) ==========

    public function promoSlidesIndex(Request $request)
    {
        $slides = WebProfilePromoSlide::orderBy('order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->paginate(15);

        $slides->through(function ($slide) {
            return [
                'id' => $slide->id,
                'title' => $slide->title,
                'image' => $slide->image,
                'image_url' => $slide->image_url,
                'link_url' => $slide->link_url,
                'order' => $slide->order,
                'is_active' => $slide->is_active,
                'created_at' => $slide->created_at,
                'updated_at' => $slide->updated_at,
            ];
        });

        return Inertia::render('WebProfile/PromoSlides/Index', [
            'slides' => $slides,
        ]);
    }

    public function promoSlidesCreate()
    {
        return Inertia::render('WebProfile/PromoSlides/Create');
    }

    public function promoSlidesStore(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'link_url' => 'nullable|string|max:2048',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ], [
            'image.required' => 'Gambar promo wajib diupload',
            'image.image' => 'File harus berupa gambar',
            'image.mimes' => 'Format gambar: JPG, PNG, atau WEBP',
            'image.max' => 'Ukuran gambar maksimal 10MB',
        ]);

        if ($request->hasFile('image')) {
            $dimErr = $this->validatePromoSlideImageDimensions($request->file('image'));
            if ($dimErr !== null) {
                return redirect()->back()->withErrors(['image' => $dimErr])->withInput();
            }
        }

        return DB::transaction(function () use ($request, $validated) {
            $file = $request->file('image');
            $label = $validated['title'] ?: 'promo';
            $fileName = time().'_'.Str::slug($label).'_promo.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('web-profile/promo-slides', $fileName, 'public');
            if (! $path) {
                throw new \RuntimeException('Gagal mengupload gambar');
            }
            $validated['image'] = $path;
            WebProfilePromoSlide::create($validated);

            return redirect()->route('web-profile.promo-slides.index')
                ->with('success', 'Promo slide berhasil ditambahkan');
        });
    }

    public function promoSlidesEdit($id)
    {
        $slide = WebProfilePromoSlide::findOrFail($id);

        return Inertia::render('WebProfile/PromoSlides/Edit', [
            'slide' => $slide,
        ]);
    }

    public function promoSlidesUpdate(Request $request, $id)
    {
        $slide = WebProfilePromoSlide::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'link_url' => 'nullable|string|max:2048',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $dimErr = $this->validatePromoSlideImageDimensions($request->file('image'));
            if ($dimErr !== null) {
                return redirect()->back()->withErrors(['image' => $dimErr])->withInput();
            }
        }

        if ($request->hasFile('image')) {
            if ($slide->image) {
                Storage::disk('public')->delete($slide->image);
            }
            $file = $request->file('image');
            $label = trim((string) (($validated['title'] ?? '') !== '' ? $validated['title'] : ($slide->title ?? ''))) ?: 'promo';
            $fileName = time().'_'.Str::slug((string) $label).'_promo.'.$file->getClientOriginalExtension();
            $validated['image'] = $file->storeAs('web-profile/promo-slides', $fileName, 'public');
        } else {
            unset($validated['image']);
        }

        $slide->update($validated);

        return redirect()->back()->with('success', 'Promo slide berhasil diperbarui');
    }

    public function promoSlidesDestroy($id)
    {
        $slide = WebProfilePromoSlide::findOrFail($id);
        if ($slide->image) {
            Storage::disk('public')->delete($slide->image);
        }
        $slide->delete();

        return redirect()->route('web-profile.promo-slides.index')
            ->with('success', 'Promo slide berhasil dihapus');
    }

    /**
     * Ukuran & rasio banner promo (homepage full-width): hindari portrait / terlalu tipis.
     *
     * @return string|null Pesan error, atau null jika lolos
     */
    private function validatePromoSlideImageDimensions(\Illuminate\Http\UploadedFile $file): ?string
    {
        $mime = (string) $file->getMimeType();
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        $info = @getimagesize($file->getPathname());
        if (! is_array($info)) {
            return 'File gambar tidak valid atau rusak.';
        }

        $w = (int) ($info[0] ?? 0);
        $h = (int) ($info[1] ?? 0);
        if ($w < 800 || $h < 240) {
            return 'Gambar terlalu kecil. Minimal disarankan 1200×380 px (minimal teknis lebar 800 px, tinggi 240 px).';
        }

        $ratio = $w / max($h, 1);
        if ($ratio < 1.7) {
            return 'Rasio terlalu “tinggi” (hampir portrait). Gunakan banner landscape lebar, contoh 1920×600 px (rasio ± 3:1).';
        }
        if ($ratio > 5.5) {
            return 'Rasio terlalu lebar (banner terlalu tipis). Disarankan rasio lebar:tinggi sekitar 2.5:1–4:1.';
        }

        return null;
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
            $hero = $this->getBrandHeroData((int) $brand->id);
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
                'hero_title' => $hero['title'],
                'hero_subtitle' => $hero['subtitle'],
                'hero_media_url' => $hero['media_url'],
                'hero_media_type' => $hero['media_type'],
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
                'hero_title' => 'nullable|string|max:255',
                'hero_subtitle' => 'nullable|string|max:2000',
                'hero_media' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm|max:51200',
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
                unset($validated['hero_title'], $validated['hero_subtitle'], $validated['hero_media']);

                // Create brand record
                $brand = WebProfileBrand::create($validated);

                if (!$brand || !$brand->id) {
                    foreach ($uploadedFiles as $filePath) {
                        Storage::disk('public')->delete($filePath);
                    }
                    throw new \Exception('Failed to create brand record');
                }

                $this->saveBrandHeroFromRequest($request, (int) $brand->id, (string) $validated['title']);

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
        $hero = $this->getBrandHeroData((int) $brand->id);

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
                'hero_title' => $hero['title'],
                'hero_subtitle' => $hero['subtitle'],
                'hero_media_url' => $hero['media_url'],
                'hero_media_type' => $hero['media_type'],
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
                'hero_title' => 'nullable|string|max:255',
                'hero_subtitle' => 'nullable|string|max:2000',
                'hero_media' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm|max:51200',
                'remove_hero_media' => 'nullable|boolean',
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
                unset($validated['hero_title'], $validated['hero_subtitle'], $validated['hero_media'], $validated['remove_hero_media']);

                $brand->update($validated);
                $this->saveBrandHeroFromRequest($request, (int) $brand->id, (string) $validated['title']);

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
        $this->deleteBrandHeroMediaAndSettings((int) $brand->id);

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
                $hero = $this->getBrandHeroData((int) $brand->id);
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
                    'hero_title' => $hero['title'],
                    'hero_subtitle' => $hero['subtitle'],
                    'hero_media_url' => $hero['media_url'],
                    'hero_media_type' => $hero['media_type'],
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
        $heroTitle = WebProfileSetting::where('key', 'home_service_hero_title')->value('value');
        $heroSubtitle = WebProfileSetting::where('key', 'home_service_hero_subtitle')->value('value');

        return Inertia::render('WebProfile/HomeServicePackages/Index', [
            'packages' => $packages,
            'hero_image_path' => $heroPath,
            'hero_image_url' => $heroUrl,
            'hero_title' => $heroTitle,
            'hero_subtitle' => $heroSubtitle,
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
            'hero_image' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm|max:51200',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:2000',
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
            $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

            WebProfileSetting::updateOrCreate(
                ['key' => 'home_service_hero_image'],
                ['value' => $path, 'type' => $type]
            );
        }

        WebProfileSetting::updateOrCreate(
            ['key' => 'home_service_hero_title'],
            ['value' => $request->input('hero_title') ?: null, 'type' => 'text']
        );
        WebProfileSetting::updateOrCreate(
            ['key' => 'home_service_hero_subtitle'],
            ['value' => $request->input('hero_subtitle') ?: null, 'type' => 'text']
        );

        return redirect()->route('web-profile.home-service-packages.index')
            ->with('success', 'Pengaturan header Home Service disimpan.');
    }

    public function homeServiceLandingEdit()
    {
        $row = WebProfileHomeServiceLanding::singleton();

        $blocksForEdit = collect($row->content_blocks ?: [])->map(function ($b) {
            if (! is_array($b)) {
                return $b;
            }
            $path = isset($b['video_path']) && is_string($b['video_path']) ? $b['video_path'] : null;
            $b['video_storage_url'] = $path ? $this->publicStorageUrl($path) : null;

            return $b;
        })->all();

        return Inertia::render('WebProfile/HomeServiceLanding/Edit', [
            'landing' => [
                'hero_title' => $row->hero_title,
                'hero_subtitle' => $row->hero_subtitle,
                'content_blocks' => $blocksForEdit,
                'collage_images' => collect($row->collage_images ?: [])->map(fn ($p) => [
                    'path' => $p,
                    'url' => $this->publicStorageUrl($p),
                ])->values()->all(),
                'gallery_card_image_path' => $row->gallery_card_image,
                'gallery_card_image_url' => $row->gallery_card_image ? $this->publicStorageUrl($row->gallery_card_image) : null,
                'gallery_card_label' => $row->gallery_card_label,
                'gallery_card_url' => $row->gallery_card_url,
                'menu_card_image_path' => $row->menu_card_image,
                'menu_card_image_url' => $row->menu_card_image ? $this->publicStorageUrl($row->menu_card_image) : null,
                'menu_card_label' => $row->menu_card_label,
                'menu_card_url' => $row->menu_card_url,
                'cta_label' => $row->cta_label,
                'cta_url' => $row->cta_url,
            ],
        ]);
    }

    public function homeServiceLandingUpdate(Request $request)
    {
        $row = WebProfileHomeServiceLanding::singleton();
        $oldSnapshot = [
            'gallery_card_url' => $row->gallery_card_url,
            'menu_card_url' => $row->menu_card_url,
            'cta_url' => $row->cta_url,
            'gallery_card_label' => $row->gallery_card_label,
            'menu_card_label' => $row->menu_card_label,
            'cta_label' => $row->cta_label,
        ];

        $request->validate([
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',
            'content_blocks_json' => 'nullable|string',
            'collage_keep_json' => 'nullable|string',
            'gallery_card_label' => 'nullable|string|max:255',
            'gallery_card_url' => 'nullable|string|max:2048',
            'menu_card_label' => 'nullable|string|max:255',
            'menu_card_url' => 'nullable|string|max:2048',
            'cta_label' => 'nullable|string|max:255',
            'cta_url' => 'nullable|string|max:2048',
            'gallery_card_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'menu_card_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'collage_new' => 'nullable',
            'collage_new.*' => 'file|image|mimes:jpeg,jpg,png,webp|max:51200',
            'remove_gallery_card' => 'nullable|boolean',
            'remove_menu_card' => 'nullable|boolean',
        ]);

        $blocksRaw = json_decode($request->input('content_blocks_json', '[]'), true);
        if (! is_array($blocksRaw)) {
            $blocksRaw = [];
        }

        $oldBlocks = $row->content_blocks ?: [];
        $allowedOldVideoPaths = collect($oldBlocks)->pluck('video_path')->filter()->unique()->values()->all();

        $contentBlocks = [];
        foreach ($blocksRaw as $i => $b) {
            if (! is_array($b)) {
                continue;
            }

            $clientPath = isset($b['video_path']) && is_string($b['video_path']) ? $b['video_path'] : null;
            $videoPath = ($clientPath && in_array($clientPath, $allowedOldVideoPaths, true)) ? $clientPath : null;

            if ($request->boolean('remove_block_video_'.$i) && $videoPath) {
                Storage::disk('public')->delete($videoPath);
                $videoPath = null;
            }

            if ($request->hasFile('block_video_'.$i)) {
                $request->validate([
                    'block_video_'.$i => ['required', 'file', 'mimes:mp4,webm', 'max:102400'],
                ]);
                if ($videoPath) {
                    Storage::disk('public')->delete($videoPath);
                }
                $file = $request->file('block_video_'.$i);
                $videoPath = $file->storeAs(
                    'web-profile/home-service/block-videos',
                    time().'_'.$i.'_'.Str::random(6).'.'.$file->getClientOriginalExtension(),
                    'public'
                );
            }

            $contentBlocks[] = [
                'title' => isset($b['title']) ? (string) $b['title'] : '',
                'body' => isset($b['body']) ? (string) $b['body'] : '',
                'video_url' => isset($b['video_url']) ? (string) $b['video_url'] : '',
                'caption' => isset($b['caption']) ? (string) $b['caption'] : '',
                'text_on_left' => filter_var($b['text_on_left'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'video_path' => $videoPath,
            ];
        }

        $newVideoPaths = collect($contentBlocks)->pluck('video_path')->filter()->unique()->values()->all();
        foreach ($allowedOldVideoPaths as $op) {
            if (! in_array($op, $newVideoPaths, true)) {
                Storage::disk('public')->delete($op);
            }
        }

        $keepCollage = json_decode($request->input('collage_keep_json', '[]'), true);
        if (! is_array($keepCollage)) {
            $keepCollage = [];
        }
        $oldCollage = $row->collage_images ?: [];
        $keepCollage = array_values(array_intersect($keepCollage, $oldCollage));
        foreach ($oldCollage as $path) {
            if (! in_array($path, $keepCollage, true)) {
                Storage::disk('public')->delete($path);
            }
        }
        $newCollage = [];
        $collageUploads = $request->file('collage_new');
        if (! $collageUploads) {
            $allFiles = $request->allFiles();
            $collageUploads = $allFiles['collage_new[]'] ?? null;
        }
        if (! $collageUploads && $request->hasFile('collage_new.*')) {
            $collageUploads = $request->file('collage_new.*');
        }
        if ($collageUploads instanceof \Illuminate\Http\UploadedFile) {
            $collageUploads = [$collageUploads];
        }
        $collageUploads = is_array($collageUploads) ? $collageUploads : [];
        foreach ($collageUploads as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            $stored = $this->storeOptimizedCollageImage($file);
            if ($stored) {
                $newCollage[] = $stored;
            }
        }
        $mergedCollage = array_values(array_merge($keepCollage, $newCollage));

        if ($request->boolean('remove_gallery_card') && $row->gallery_card_image) {
            Storage::disk('public')->delete($row->gallery_card_image);
            $row->gallery_card_image = null;
        }
        if ($request->hasFile('gallery_card_image')) {
            if ($row->gallery_card_image) {
                Storage::disk('public')->delete($row->gallery_card_image);
            }
            $row->gallery_card_image = $request->file('gallery_card_image')->storeAs(
                'web-profile/home-service/cards',
                time().'_gallery.'.$request->file('gallery_card_image')->getClientOriginalExtension(),
                'public'
            );
        }

        if ($request->boolean('remove_menu_card') && $row->menu_card_image) {
            Storage::disk('public')->delete($row->menu_card_image);
            $row->menu_card_image = null;
        }
        if ($request->hasFile('menu_card_image')) {
            if ($row->menu_card_image) {
                Storage::disk('public')->delete($row->menu_card_image);
            }
            $row->menu_card_image = $request->file('menu_card_image')->storeAs(
                'web-profile/home-service/cards',
                time().'_menu.'.$request->file('menu_card_image')->getClientOriginalExtension(),
                'public'
            );
        }

        $row->hero_title = $request->input('hero_title');
        $row->hero_subtitle = $request->input('hero_subtitle');
        $row->content_blocks = $contentBlocks;
        $row->collage_images = $mergedCollage;
        $row->gallery_card_label = $request->input('gallery_card_label');
        $row->gallery_card_url = $request->input('gallery_card_url');
        $row->menu_card_label = $request->input('menu_card_label');
        $row->menu_card_url = $request->input('menu_card_url');
        $row->cta_label = $request->input('cta_label');
        $row->cta_url = $request->input('cta_url');
        $row->save();

        $newSnapshot = [
            'gallery_card_url' => $row->gallery_card_url,
            'menu_card_url' => $row->menu_card_url,
            'cta_url' => $row->cta_url,
            'gallery_card_label' => $row->gallery_card_label,
            'menu_card_label' => $row->menu_card_label,
            'cta_label' => $row->cta_label,
        ];
        if ($oldSnapshot !== $newSnapshot) {
            $urlsOld = [
                'gallery_card_url' => $oldSnapshot['gallery_card_url'],
                'menu_card_url' => $oldSnapshot['menu_card_url'],
                'cta_url' => $oldSnapshot['cta_url'],
            ];
            $urlsNew = [
                'gallery_card_url' => $newSnapshot['gallery_card_url'],
                'menu_card_url' => $newSnapshot['menu_card_url'],
                'cta_url' => $newSnapshot['cta_url'],
            ];
            $reservationUrlsChanged = $urlsOld !== $urlsNew;

            if ($reservationUrlsChanged) {
                $this->logWebProfileSecurityEvent(
                    $request,
                    'reservation_web_links_updated',
                    'Link reservasi/booking web diubah (Home Service Landing: kartu Gallery, Menu, atau CTA)',
                    $oldSnapshot,
                    $newSnapshot
                );
            } else {
                $this->logWebProfileSecurityEvent(
                    $request,
                    'home_service_landing_updated',
                    'Home Service Landing diperbarui (label/teks kartu, tanpa ubah URL reservasi)',
                    $oldSnapshot,
                    $newSnapshot
                );
            }
        }

        return redirect()->route('web-profile.home-service-landing.edit')
            ->with('success', 'Landing Home Service disimpan.');
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
        $heroTitle = WebProfileSetting::where('key', 'home_service_hero_title')->value('value');
        $heroSubtitle = WebProfileSetting::where('key', 'home_service_hero_subtitle')->value('value');

        return response()->json([
            'packages' => $packages,
            'hero_image_url' => $heroPath ? $this->publicStorageUrl($heroPath) : null,
            'hero_title' => $heroTitle,
            'hero_subtitle' => $heroSubtitle,
            'landing' => $this->buildHomeServiceLandingApiPayload(),
        ]);
    }

    private function buildHomeServiceLandingApiPayload(): array
    {
        $row = WebProfileHomeServiceLanding::query()->first();
        if (! $row) {
            return [
                'hero_title' => null,
                'hero_subtitle' => null,
                'content_blocks' => [],
                'collage_images' => [],
                'gallery_card' => null,
                'menu_card' => null,
                'cta' => null,
            ];
        }

        $blocks = collect($row->content_blocks ?: [])->map(function ($b) {
            if (! is_array($b)) {
                return null;
            }

            $path = isset($b['video_path']) && is_string($b['video_path']) ? $b['video_path'] : null;
            $playback = $path ? $this->publicStorageUrl($path) : (string) ($b['video_url'] ?? '');

            return [
                'title' => (string) ($b['title'] ?? ''),
                'body' => (string) ($b['body'] ?? ''),
                'video_url' => $playback,
                'caption' => (string) ($b['caption'] ?? ''),
                'text_on_left' => filter_var($b['text_on_left'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        })->filter()->values()->all();

        $collage = collect($row->collage_images ?: [])->map(fn ($p) => $this->publicStorageUrl($p))->filter()->values()->all();

        $gallery = null;
        if ($row->gallery_card_image) {
            $gallery = [
                'image_url' => $this->publicStorageUrl($row->gallery_card_image),
                'label' => $row->gallery_card_label,
                'url' => $row->gallery_card_url,
            ];
        }

        $menu = null;
        if ($row->menu_card_image) {
            $menu = [
                'image_url' => $this->publicStorageUrl($row->menu_card_image),
                'label' => $row->menu_card_label,
                'url' => $row->menu_card_url,
            ];
        }

        $cta = null;
        if ($row->cta_label || $row->cta_url) {
            $cta = [
                'label' => $row->cta_label,
                'url' => $row->cta_url,
            ];
        }

        return [
            'hero_title' => $row->hero_title,
            'hero_subtitle' => $row->hero_subtitle,
            'content_blocks' => $blocks,
            'collage_images' => $collage,
            'gallery_card' => $gallery,
            'menu_card' => $menu,
            'cta' => $cta,
        ];
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
        $oldSnapshot = [
            'playstore_url' => WebProfileSetting::where('key', 'justus_apps_playstore_url')->value('value'),
            'appstore_url' => WebProfileSetting::where('key', 'justus_apps_appstore_url')->value('value'),
            'hero_media_path' => WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value'),
            'hero_media_type' => WebProfileSetting::where('key', 'justus_apps_hero_image')->value('type'),
        ];

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

        $newSnapshot = [
            'playstore_url' => WebProfileSetting::where('key', 'justus_apps_playstore_url')->value('value'),
            'appstore_url' => WebProfileSetting::where('key', 'justus_apps_appstore_url')->value('value'),
            'hero_media_path' => WebProfileSetting::where('key', 'justus_apps_hero_image')->value('value'),
            'hero_media_type' => WebProfileSetting::where('key', 'justus_apps_hero_image')->value('type'),
        ];
        if ($oldSnapshot !== $newSnapshot) {
            $this->logWebProfileSecurityEvent(
                $request,
                'justus_apps_settings_updated',
                'Pengaturan Justus Apps diperbarui',
                $oldSnapshot,
                $newSnapshot
            );
        }

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
        $oldSnapshot = [
            'about_title' => WebProfileSetting::where('key', 'about_title')->value('value'),
            'about_subtitle' => WebProfileSetting::where('key', 'about_subtitle')->value('value'),
        ];

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

        $newSnapshot = [
            'about_title' => WebProfileSetting::where('key', 'about_title')->value('value'),
            'about_subtitle' => WebProfileSetting::where('key', 'about_subtitle')->value('value'),
        ];
        if ($oldSnapshot !== $newSnapshot) {
            $this->logWebProfileSecurityEvent(
                $request,
                'about_page_settings_updated',
                'Pengaturan About Page diperbarui',
                $oldSnapshot,
                $newSnapshot
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

    // ========== CAREERS PAGE (Company profile web) ==========

    public function careersPageIndex()
    {
        $keys = [
            'careers_title',
            'careers_subtitle',
            'careers_wording',
            'careers_cta_title',
            'careers_cta_subtitle',
            'careers_cta_image_1',
            'careers_cta_image_2',
            'careers_primary_button_label',
            'careers_primary_button_url',
            'careers_secondary_button_label',
            'careers_secondary_button_url',
            'careers_hero_image',
            'careers_card_1_title',
            'careers_card_1_image',
            'careers_card_2_title',
            'careers_card_2_image',
            'careers_card_3_title',
            'careers_card_3_image',
            'careers_card_4_title',
            'careers_card_4_image',
        ];

        $settings = WebProfileSetting::whereIn('key', $keys)->get()->keyBy('key');

        return Inertia::render('WebProfile/CareersPage/Index', [
            'careers' => [
                'careers_title' => $settings->get('careers_title')->value ?? 'CAREERS',
                'careers_subtitle' => $settings->get('careers_subtitle')->value ?? 'Growth Together with Justus Group',
                'careers_wording' => $settings->get('careers_wording')->value ?? '',
                'careers_cta_title' => $settings->get('careers_cta_title')->value ?? 'BE PART OF A JOURNEY TO CREATE THE FUTURE OF LIFESTYLE EXPERIENCES',
                'careers_cta_subtitle' => $settings->get('careers_cta_subtitle')->value ?? '',
                'careers_cta_image_1_path' => $settings->get('careers_cta_image_1')->value ?? null,
                'careers_cta_image_1_url' => ($settings->get('careers_cta_image_1')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('careers_cta_image_1')->value)
                    : null,
                'careers_cta_image_2_path' => $settings->get('careers_cta_image_2')->value ?? null,
                'careers_cta_image_2_url' => ($settings->get('careers_cta_image_2')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('careers_cta_image_2')->value)
                    : null,
                'careers_primary_button_label' => $settings->get('careers_primary_button_label')->value ?? 'HEAD OFFICE Join Us',
                'careers_primary_button_url' => $settings->get('careers_primary_button_url')->value ?? '',
                'careers_secondary_button_label' => $settings->get('careers_secondary_button_label')->value ?? 'OPERATION Join Us',
                'careers_secondary_button_url' => $settings->get('careers_secondary_button_url')->value ?? '',
                'careers_hero_image_path' => $settings->get('careers_hero_image')->value ?? null,
                'careers_hero_image_type' => $settings->get('careers_hero_image')->type ?? 'image',
                'careers_hero_image_url' => ($settings->get('careers_hero_image')->value ?? null)
                    ? $this->publicStorageUrl($settings->get('careers_hero_image')->value)
                    : null,
                'cards' => collect([1, 2, 3, 4])->map(function ($idx) use ($settings) {
                    $titleKey = "careers_card_{$idx}_title";
                    $imageKey = "careers_card_{$idx}_image";
                    $path = $settings->get($imageKey)->value ?? null;
                    return [
                        'title' => $settings->get($titleKey)->value ?? '',
                        'image_path' => $path,
                        'image_url' => $path ? $this->publicStorageUrl($path) : null,
                    ];
                })->all(),
            ],
        ]);
    }

    public function careersPageStore(Request $request)
    {
        $oldSnapshot = [
            'careers_primary_button_url' => WebProfileSetting::where('key', 'careers_primary_button_url')->value('value'),
            'careers_secondary_button_url' => WebProfileSetting::where('key', 'careers_secondary_button_url')->value('value'),
            'careers_primary_button_label' => WebProfileSetting::where('key', 'careers_primary_button_label')->value('value'),
            'careers_secondary_button_label' => WebProfileSetting::where('key', 'careers_secondary_button_label')->value('value'),
        ];

        $validated = $request->validate([
            'careers_title' => 'nullable|string|max:255',
            'careers_subtitle' => 'nullable|string|max:255',
            'careers_wording' => 'nullable|string',
            'careers_cta_title' => 'nullable|string|max:255',
            'careers_cta_subtitle' => 'nullable|string|max:255',
            'careers_primary_button_label' => 'nullable|string|max:255',
            'careers_primary_button_url' => 'nullable|url|max:255',
            'careers_secondary_button_label' => 'nullable|string|max:255',
            'careers_secondary_button_url' => 'nullable|url|max:255',
            'hero_image' => 'nullable|file|mimes:jpeg,jpg,png,webp,mp4,webm|max:51200',
            'cta_image_1' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'cta_image_2' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'card_1_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'card_2_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'card_3_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'card_4_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_hero' => 'nullable|boolean',
            'remove_cta_image_1' => 'nullable|boolean',
            'remove_cta_image_2' => 'nullable|boolean',
            'remove_card_1' => 'nullable|boolean',
            'remove_card_2' => 'nullable|boolean',
            'remove_card_3' => 'nullable|boolean',
            'remove_card_4' => 'nullable|boolean',
            'careers_card_1_title' => 'nullable|string|max:255',
            'careers_card_2_title' => 'nullable|string|max:255',
            'careers_card_3_title' => 'nullable|string|max:255',
            'careers_card_4_title' => 'nullable|string|max:255',
        ]);

        $imageConfig = [
            'careers_hero_image' => ['input' => 'hero_image', 'remove' => 'remove_hero', 'name' => 'careers_hero'],
            'careers_cta_image_1' => ['input' => 'cta_image_1', 'remove' => 'remove_cta_image_1', 'name' => 'careers_cta_1'],
            'careers_cta_image_2' => ['input' => 'cta_image_2', 'remove' => 'remove_cta_image_2', 'name' => 'careers_cta_2'],
            'careers_card_1_image' => ['input' => 'card_1_image', 'remove' => 'remove_card_1', 'name' => 'careers_card_1'],
            'careers_card_2_image' => ['input' => 'card_2_image', 'remove' => 'remove_card_2', 'name' => 'careers_card_2'],
            'careers_card_3_image' => ['input' => 'card_3_image', 'remove' => 'remove_card_3', 'name' => 'careers_card_3'],
            'careers_card_4_image' => ['input' => 'card_4_image', 'remove' => 'remove_card_4', 'name' => 'careers_card_4'],
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
                $path = $file->storeAs('web-profile/careers-page', $fileName, 'public');
                $fileType = 'image';
                if ($settingKey === 'careers_hero_image') {
                    $fileType = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
                }
                WebProfileSetting::updateOrCreate(
                    ['key' => $settingKey],
                    ['value' => $path, 'type' => $fileType]
                );
            }
        }

        $textKeys = [
            'careers_title',
            'careers_subtitle',
            'careers_wording',
            'careers_cta_title',
            'careers_cta_subtitle',
            'careers_primary_button_label',
            'careers_primary_button_url',
            'careers_secondary_button_label',
            'careers_secondary_button_url',
            'careers_card_1_title',
            'careers_card_2_title',
            'careers_card_3_title',
            'careers_card_4_title',
        ];

        foreach ($textKeys as $key) {
            WebProfileSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $validated[$key] ?? '', 'type' => 'text']
            );
        }

        $newSnapshot = [
            'careers_primary_button_url' => WebProfileSetting::where('key', 'careers_primary_button_url')->value('value'),
            'careers_secondary_button_url' => WebProfileSetting::where('key', 'careers_secondary_button_url')->value('value'),
            'careers_primary_button_label' => WebProfileSetting::where('key', 'careers_primary_button_label')->value('value'),
            'careers_secondary_button_label' => WebProfileSetting::where('key', 'careers_secondary_button_label')->value('value'),
        ];
        if ($oldSnapshot !== $newSnapshot) {
            $this->logWebProfileSecurityEvent(
                $request,
                'careers_page_settings_updated',
                'Pengaturan Careers Page diperbarui',
                $oldSnapshot,
                $newSnapshot
            );
        }

        return redirect()->route('web-profile.careers-page.index')
            ->with('success', 'Pengaturan Careers Page berhasil disimpan.');
    }

    public function apiCareersPage()
    {
        $keys = [
            'careers_title',
            'careers_subtitle',
            'careers_wording',
            'careers_cta_title',
            'careers_cta_subtitle',
            'careers_cta_image_1',
            'careers_cta_image_2',
            'careers_primary_button_label',
            'careers_primary_button_url',
            'careers_secondary_button_label',
            'careers_secondary_button_url',
            'careers_hero_image',
            'careers_card_1_title',
            'careers_card_1_image',
            'careers_card_2_title',
            'careers_card_2_image',
            'careers_card_3_title',
            'careers_card_3_image',
            'careers_card_4_title',
            'careers_card_4_image',
        ];

        $settings = WebProfileSetting::whereIn('key', $keys)->get()->keyBy('key');

        $heroPath = $settings->get('careers_hero_image')->value ?? null;

        $cards = collect([1, 2, 3, 4])->map(function ($idx) use ($settings) {
            $title = $settings->get("careers_card_{$idx}_title")->value ?? '';
            $path = $settings->get("careers_card_{$idx}_image")->value ?? null;
            return [
                'id' => $idx,
                'title' => $title,
                'image_url' => $path ? $this->publicStorageUrl($path) : null,
            ];
        })->values()->all();

        return response()->json([
            'title' => $settings->get('careers_title')->value ?? 'CAREERS',
            'subtitle' => $settings->get('careers_subtitle')->value ?? 'Growth Together with Justus Group',
            'hero_image_url' => $heroPath ? $this->publicStorageUrl($heroPath) : null,
            'hero_image_type' => $settings->get('careers_hero_image')->type ?? 'image',
            'wording' => $settings->get('careers_wording')->value ?? '',
            'cards' => $cards,
            'cta_title' => $settings->get('careers_cta_title')->value ?? 'BE PART OF A JOURNEY TO CREATE THE FUTURE OF LIFESTYLE EXPERIENCES',
            'cta_subtitle' => $settings->get('careers_cta_subtitle')->value ?? '',
            'cta_image_1_url' => ($settings->get('careers_cta_image_1')->value ?? null) ? $this->publicStorageUrl($settings->get('careers_cta_image_1')->value) : null,
            'cta_image_2_url' => ($settings->get('careers_cta_image_2')->value ?? null) ? $this->publicStorageUrl($settings->get('careers_cta_image_2')->value) : null,
            'primary_button_label' => $settings->get('careers_primary_button_label')->value ?? 'HEAD OFFICE Join Us',
            'primary_button_url' => $settings->get('careers_primary_button_url')->value ?? null,
            'secondary_button_label' => $settings->get('careers_secondary_button_label')->value ?? 'OPERATION Join Us',
            'secondary_button_url' => $settings->get('careers_secondary_button_url')->value ?? null,
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

    private function brandHeroSettingKey(int $brandId, string $field): string
    {
        return "brand_hero_{$field}_{$brandId}";
    }

    private function getBrandHeroData(int $brandId): array
    {
        if ($brandId <= 0) {
            return [
                'title' => null,
                'subtitle' => null,
                'media_path' => null,
                'media_url' => null,
                'media_type' => null,
            ];
        }

        $title = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'title'))->value('value');
        $subtitle = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'subtitle'))->value('value');
        $mediaPath = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'media'))->value('value');
        $mediaType = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'media'))->value('type');

        return [
            'title' => $title ?: null,
            'subtitle' => $subtitle ?: null,
            'media_path' => $mediaPath ?: null,
            'media_url' => $mediaPath ? $this->publicStorageUrl($mediaPath) : null,
            'media_type' => $mediaType ?: null,
        ];
    }

    private function saveBrandHeroFromRequest(Request $request, int $brandId, string $brandTitle): void
    {
        $title = trim((string) $request->input('hero_title', ''));
        $subtitle = trim((string) $request->input('hero_subtitle', ''));

        WebProfileSetting::updateOrCreate(
            ['key' => $this->brandHeroSettingKey($brandId, 'title')],
            ['value' => $title, 'type' => 'text']
        );
        WebProfileSetting::updateOrCreate(
            ['key' => $this->brandHeroSettingKey($brandId, 'subtitle')],
            ['value' => $subtitle, 'type' => 'text']
        );

        if ($request->boolean('remove_hero_media')) {
            $old = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'media'))->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
            WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'media'))->delete();
        }

        if ($request->hasFile('hero_media')) {
            $old = WebProfileSetting::where('key', $this->brandHeroSettingKey($brandId, 'media'))->value('value');
            if ($old) {
                Storage::disk('public')->delete($old);
            }

            $file = $request->file('hero_media');
            $safeTitle = Str::slug($brandTitle ?: ('brand-'.$brandId));
            $fileName = time().'_'.$safeTitle.'_hero.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('web-profile/brands/hero', $fileName, 'public');
            $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

            WebProfileSetting::updateOrCreate(
                ['key' => $this->brandHeroSettingKey($brandId, 'media')],
                ['value' => $path, 'type' => $type]
            );
        }
    }

    private function deleteBrandHeroMediaAndSettings(int $brandId): void
    {
        $mediaKey = $this->brandHeroSettingKey($brandId, 'media');
        $titleKey = $this->brandHeroSettingKey($brandId, 'title');
        $subtitleKey = $this->brandHeroSettingKey($brandId, 'subtitle');

        $media = WebProfileSetting::where('key', $mediaKey)->value('value');
        if ($media) {
            Storage::disk('public')->delete($media);
        }

        WebProfileSetting::whereIn('key', [$mediaKey, $titleKey, $subtitleKey])->delete();
    }

    private function logWebProfileSecurityEvent(
        Request $request,
        string $activityType,
        string $description,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'activity_type' => $activityType,
            'module' => 'web_profile_security',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'old_data' => $oldData,
            'new_data' => $newData,
            'created_at' => now(),
        ]);
    }

    /**
     * Auto resize/compress collage image on upload.
     * - Longest edge max 1920px
     * - JPEG quality 82
     * - PNG compression level 7
     * - WEBP quality 80
     */
    private function storeOptimizedCollageImage(\Illuminate\Http\UploadedFile $file): ?string
    {
        $raw = @file_get_contents($file->getPathname());
        if ($raw === false || $raw === '') {
            return $file->storeAs(
                'web-profile/home-service/collage',
                time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            return $file->storeAs(
                'web-profile/home-service/collage',
                time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $maxEdge = 1920;
        $scale = 1.0;
        if ($width > $maxEdge || $height > $maxEdge) {
            $scale = min($maxEdge / max(1, $width), $maxEdge / max(1, $height));
        }
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newW, $newH);
        if (! $canvas) {
            imagedestroy($src);
            return $file->storeAs(
                'web-profile/home-service/collage',
                time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        }

        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);

        ob_start();
        $saved = false;
        $ext = 'jpg';
        if ($mime === 'image/png') {
            $ext = 'png';
            $saved = imagepng($canvas, null, 7);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ext = 'webp';
            $saved = imagewebp($canvas, null, 80);
        } else {
            $ext = 'jpg';
            $saved = imagejpeg($canvas, null, 82);
        }
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if (! $saved || $binary === false || $binary === '') {
            return $file->storeAs(
                'web-profile/home-service/collage',
                time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $path = 'web-profile/home-service/collage/'.time().'_'.Str::random(8).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}

