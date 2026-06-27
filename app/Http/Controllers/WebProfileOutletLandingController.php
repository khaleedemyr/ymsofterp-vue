<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsBrand;
use App\Models\Outlet;
use App\Models\WebProfileOutletLanding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WebProfileOutletLandingController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $outlets = Outlet::query()
            ->where('status', 'A')
            ->where('is_outlet', 1)
            ->when($search !== '', fn ($q) => $q->where('nama_outlet', 'like', '%'.$search.'%'))
            ->orderBy('nama_outlet')
            ->get(['id_outlet', 'nama_outlet', 'lokasi']);

        $landings = WebProfileOutletLanding::query()
            ->whereIn('outlet_id', $outlets->pluck('id_outlet'))
            ->get()
            ->keyBy('outlet_id');

        $rows = $outlets->map(function (Outlet $outlet) use ($landings) {
            $landing = $landings->get($outlet->id_outlet);

            return [
                'outlet_id' => $outlet->id_outlet,
                'outlet_name' => $outlet->nama_outlet,
                'outlet_address' => $outlet->lokasi,
                'landing_id' => $landing?->id,
                'slug' => $landing?->slug,
                'is_active' => (bool) ($landing?->is_active ?? false),
                'is_published' => $landing?->isPublished() ?? false,
                'preview_live_url' => ($landing?->slug && ($landing?->isPublished() ?? false))
                    ? $this->buildPreviewUrl((string) $landing->slug, false)
                    : null,
                'preview_draft_url' => $landing?->slug
                    ? $this->buildPreviewUrl((string) $landing->slug, true)
                    : null,
            ];
        })->values();

        return Inertia::render('WebProfile/OutletLandings/Index', [
            'rows' => $rows,
            'filters' => ['search' => $search],
            ...$this->previewProps(),
        ]);
    }

    public function edit(int $outletId)
    {
        $outlet = Outlet::where('id_outlet', $outletId)
            ->where('status', 'A')
            ->where('is_outlet', 1)
            ->firstOrFail();

        $landing = WebProfileOutletLanding::firstOrCreate(
            ['outlet_id' => $outlet->id_outlet],
            [
                'slug' => $this->generateUniqueSlug($outlet->nama_outlet, $outlet->id_outlet),
                'is_active' => false,
                'book_now_label' => 'BOOK NOW',
                'see_map_label' => 'SEE MAP',
                'gallery_images' => [],
            ]
        );

        return Inertia::render('WebProfile/OutletLandings/Edit', [
            'outlet' => [
                'id' => $outlet->id_outlet,
                'name' => $outlet->nama_outlet,
                'address' => $outlet->lokasi,
                'lat' => $outlet->lat,
                'long' => $outlet->long,
                'map_url' => $this->resolveOutletMapUrl($outlet),
            ],
            'landing' => $this->landingForEdit($landing),
            ...$this->previewProps(),
        ]);
    }

    public function update(Request $request, int $outletId)
    {
        $outlet = Outlet::where('id_outlet', $outletId)
            ->where('status', 'A')
            ->where('is_outlet', 1)
            ->firstOrFail();

        $landing = WebProfileOutletLanding::firstOrCreate(
            ['outlet_id' => $outlet->id_outlet],
            [
                'slug' => $this->generateUniqueSlug($outlet->nama_outlet, $outlet->id_outlet),
                'gallery_images' => [],
            ]
        );

        $request->validate([
            'slug' => 'required|string|max:191|unique:web_profile_outlet_landings,slug,'.$landing->id,
            'is_active' => 'nullable|boolean',
            'outlet_subtitle' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:500',
            'intro_paragraph' => 'nullable|string',
            'secondary_paragraph' => 'nullable|string',
            'book_now_label' => 'nullable|string|max:100',
            'see_map_label' => 'nullable|string|max:100',
            'hero_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'logo_override' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'gallery_keep_json' => 'nullable|string',
            'gallery_new' => 'nullable',
            'gallery_new.*' => 'file|image|mimes:jpeg,jpg,png,webp|max:51200',
            'remove_hero' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
        ]);

        $slug = Str::slug((string) $request->input('slug'));
        if ($slug === '') {
            $slug = $this->generateUniqueSlug($outlet->nama_outlet, $outlet->id_outlet);
        }

        $keep = json_decode($request->input('gallery_keep_json', '[]'), true);
        if (! is_array($keep)) {
            $keep = [];
        }
        $allowedGallery = collect($landing->gallery_images ?: [])->filter()->values()->all();
        $gallery = collect($keep)
            ->filter(fn ($p) => is_string($p) && in_array($p, $allowedGallery, true))
            ->values()
            ->all();

        foreach ($request->file('gallery_new', []) ?? [] as $file) {
            if (! $file) {
                continue;
            }
            $gallery[] = $file->storeAs(
                'web-profile/outlet-landings/gallery',
                time().'_'.Str::random(8).'.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $removedGallery = array_diff($allowedGallery, $gallery);
        foreach ($removedGallery as $path) {
            Storage::disk('public')->delete($path);
        }

        $heroPath = $landing->hero_image;
        if ($request->boolean('remove_hero') && $heroPath) {
            Storage::disk('public')->delete($heroPath);
            $heroPath = null;
        }
        if ($request->hasFile('hero_image')) {
            if ($heroPath) {
                Storage::disk('public')->delete($heroPath);
            }
            $file = $request->file('hero_image');
            $heroPath = $file->storeAs(
                'web-profile/outlet-landings/hero',
                time().'_hero.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $logoPath = $landing->logo_override;
        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }
        if ($request->hasFile('logo_override')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $file = $request->file('logo_override');
            $logoPath = $file->storeAs(
                'web-profile/outlet-landings/logo',
                time().'_logo.'.$file->getClientOriginalExtension(),
                'public'
            );
        }

        $landing->update([
            'slug' => $slug,
            'is_active' => $request->boolean('is_active'),
            'outlet_subtitle' => $request->input('outlet_subtitle'),
            'headline' => $request->input('headline'),
            'intro_paragraph' => $request->input('intro_paragraph'),
            'secondary_paragraph' => $request->input('secondary_paragraph'),
            'book_now_label' => $request->input('book_now_label') ?: 'BOOK NOW',
            'see_map_label' => $request->input('see_map_label') ?: 'SEE MAP',
            'hero_image' => $heroPath,
            'logo_override' => $logoPath,
            'gallery_images' => array_values($gallery),
        ]);

        return redirect()
            ->route('web-profile.outlet-landings.edit', $outlet->id_outlet)
            ->with('success', 'Landing page outlet berhasil disimpan.');
    }

    public function apiIndex()
    {
        $rows = WebProfileOutletLanding::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebProfileOutletLanding $row) => $row->isPublished())
            ->map(fn (WebProfileOutletLanding $row) => [
                'outlet_id' => (int) $row->outlet_id,
                'slug' => $row->slug,
            ])
            ->values();

        return response()->json($rows);
    }

    public function apiShow(Request $request, string $slug)
    {
        $previewKey = (string) config('services.justus_kunest.preview_key', '');
        $isPreview = $previewKey !== ''
            && hash_equals($previewKey, (string) $request->query('preview', ''));

        $landing = WebProfileOutletLanding::query()
            ->where('slug', $slug)
            ->first();

        if (! $landing) {
            return response()->json(['message' => 'Landing page not found.'], 404);
        }

        if ($isPreview) {
            return response()->json(array_merge($this->buildPublicPayload($landing), [
                'is_preview' => true,
            ]));
        }

        if (! $landing->is_active || ! $landing->isPublished()) {
            return response()->json(['message' => 'Landing page not found.'], 404);
        }

        return response()->json($this->buildPublicPayload($landing));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicPayload(WebProfileOutletLanding $landing): array
    {
        $outlet = Outlet::find($landing->outlet_id);
        $brand = MemberAppsBrand::where('outlet_id', $landing->outlet_id)->first();

        $logoPath = $landing->logo_override ?: ($brand?->logo);
        $address = $this->resolveOutletAddress($outlet);
        $mapUrl = $this->resolveOutletMapUrl($outlet);

        $intro = trim((string) $landing->intro_paragraph);
        $introParagraphs = $intro !== ''
            ? preg_split("/\n\s*\n/", $intro) ?: []
            : [];

        $gallery = collect($landing->gallery_images ?: [])
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->map(fn ($p) => $this->publicStorageUrl($p))
            ->values()
            ->all();

        return [
            'outlet_id' => (int) $landing->outlet_id,
            'slug' => $landing->slug,
            'outlet_name' => (string) ($outlet?->nama_outlet ?? ''),
            'outlet_subtitle' => $landing->outlet_subtitle,
            'headline' => $landing->headline,
            'intro_paragraphs' => array_values(array_filter(array_map('trim', $introParagraphs))),
            'secondary_paragraph' => $landing->secondary_paragraph,
            'hero_image_url' => $landing->hero_image ? $this->publicStorageUrl($landing->hero_image) : null,
            'logo_url' => $logoPath ? $this->publicStorageUrl($logoPath) : null,
            'gallery_images' => $gallery,
            'address' => $address !== '' ? $address : null,
            'map_url' => $mapUrl !== '' ? $mapUrl : null,
            'book_now_label' => $landing->book_now_label ?: 'BOOK NOW',
            'see_map_label' => $landing->see_map_label ?: 'SEE MAP',
            'book_now_outlet_id' => (int) $landing->outlet_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function landingForEdit(WebProfileOutletLanding $landing): array
    {
        return [
            'id' => $landing->id,
            'slug' => $landing->slug,
            'is_active' => $landing->is_active,
            'is_published' => $landing->isPublished(),
            'outlet_subtitle' => $landing->outlet_subtitle,
            'headline' => $landing->headline,
            'intro_paragraph' => $landing->intro_paragraph,
            'secondary_paragraph' => $landing->secondary_paragraph,
            'book_now_label' => $landing->book_now_label,
            'see_map_label' => $landing->see_map_label,
            'hero_image_path' => $landing->hero_image,
            'hero_image_url' => $landing->hero_image ? $this->publicStorageUrl($landing->hero_image) : null,
            'logo_override_path' => $landing->logo_override,
            'logo_override_url' => $landing->logo_override ? $this->publicStorageUrl($landing->logo_override) : null,
            'gallery_images' => collect($landing->gallery_images ?: [])->map(fn ($p) => [
                'path' => $p,
                'url' => $this->publicStorageUrl($p),
            ])->values()->all(),
        ];
    }

    private function resolveOutletAddress(?Outlet $outlet): ?string
    {
        $address = trim((string) ($outlet?->lokasi ?? ''));

        return $address !== '' ? $address : null;
    }

    private function resolveOutletMapUrl(?Outlet $outlet): ?string
    {
        if (! $outlet) {
            return null;
        }

        $lat = trim((string) ($outlet->lat ?? ''));
        $long = trim((string) ($outlet->long ?? ''));

        if ($lat === '' || $long === '') {
            return null;
        }

        return sprintf('https://www.google.com/maps?q=%s,%s', $lat, $long);
    }

    private function generateUniqueSlug(string $outletName, int $outletId): string
    {
        $base = Str::slug($outletName);
        if ($base === '') {
            $base = 'outlet-'.$outletId;
        }

        $slug = $base;
        $i = 2;
        while (
            WebProfileOutletLanding::query()
                ->where('slug', $slug)
                ->where('outlet_id', '!=', $outletId)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function publicStorageUrl(string $path): string
    {
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $baseUrl = request()?->getSchemeAndHttpHost()
            ?: rtrim(config('app.url', 'http://localhost:8000'), '/');

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $baseUrl.'/storage/'.$encodedPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewProps(): array
    {
        return [
            'justus_kunest_web_url' => (string) config('services.justus_kunest.web_url', ''),
            'preview_key' => (string) config('services.justus_kunest.preview_key', ''),
        ];
    }

    private function buildPreviewUrl(string $slug, bool $draft): ?string
    {
        $base = rtrim((string) config('services.justus_kunest.web_url', ''), '/');
        $slug = trim($slug);
        if ($base === '' || $slug === '') {
            return null;
        }

        $url = $base.'/outlets/'.rawurlencode($slug);
        if ($draft) {
            $key = (string) config('services.justus_kunest.preview_key', '');
            if ($key === '') {
                return null;
            }
            $url .= '?preview='.rawurlencode($key);
        }

        return $url;
    }
}
