<?php

namespace App\Http\Controllers;

use App\Models\WebProfilePage;
use App\Models\WebProfilePageSection;
use App\Models\WebProfileMenuItem;
use App\Models\WebProfileGallery;
use App\Models\WebProfileSetting;
use App\Models\WebProfileContact;
use App\Models\WebProfileBanner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class WebProfileController extends Controller
{
    /**
     * Display admin panel - list pages
     */
    public function index(Request $request)
    {
        $pages = WebProfilePage::orderBy('order')
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            })
            ->paginate(15);

        return Inertia::render('WebProfile/Index', [
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'background_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=1920,min_height=1080',
            'content_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=800,min_height=600',
            'order' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Upload background image
        if ($request->hasFile('background_image')) {
            $file = $request->file('background_image');
            $fileName = time() . '_' . Str::slug($validated['title']) . '_bg.' . $file->getClientOriginalExtension();
            $validated['background_image'] = $file->storeAs('web-profile/banners', $fileName, 'public');
        }

        // Upload content image
        if ($request->hasFile('content_image')) {
            $file = $request->file('content_image');
            $fileName = time() . '_' . Str::slug($validated['title']) . '_content.' . $file->getClientOriginalExtension();
            $validated['content_image'] = $file->storeAs('web-profile/banners', $fileName, 'public');
        }

        $banner = WebProfileBanner::create($validated);

        return redirect()->route('web-profile.banners.index')
            ->with('success', 'Banner created successfully');
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
}

