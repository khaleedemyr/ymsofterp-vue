<?php

namespace App\Http\Controllers;

use App\Models\MemberAppsBrand;
use App\Models\Outlet;
use App\Models\WebProfileOutletLanding;
use App\Models\WebProfileSetting;
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
            'brandGalleryImages' => $this->resolveBrandGalleryUrls($outlet->id_outlet),
            'landing' => array_merge($this->landingForEdit($landing), [
                'preview_draft_url' => $this->buildPreviewUrl((string) $landing->slug, true),
                'preview_live_url' => $landing->isPublished()
                    ? $this->buildPreviewUrl((string) $landing->slug, false)
                    : null,
            ]),
            ...$this->previewProps(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'justus_kunest_web_url' => 'required|url|max:500',
            'preview_key' => 'nullable|string|max:120',
        ]);

        WebProfileSetting::setValue(
            'justus_kunest_web_url',
            rtrim((string) $validated['justus_kunest_web_url'], '/')
        );
        WebProfileSetting::setValue(
            'justus_kunest_preview_key',
            trim((string) ($validated['preview_key'] ?? ''))
        );

        return redirect()
            ->route('web-profile.outlet-landings.index')
            ->with('success', 'Pengaturan URL preview Justus Kunest berhasil disimpan.');
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
            ]
        );

        $request->validate([
            'slug' => 'required|string|max:191|unique:web_profile_outlet_landings,slug,'.$landing->id,
            'is_active' => 'nullable|boolean',
            'headline' => 'nullable|string|max:500',
            'intro_paragraph' => 'nullable|string',
            'secondary_paragraph' => 'nullable|string',
            'book_now_label' => 'nullable|string|max:100',
            'see_map_label' => 'nullable|string|max:100',
            'hero_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'logo_override' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_hero' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
        ]);

        $slug = Str::slug((string) $request->input('slug'));
        if ($slug === '') {
            $slug = $this->generateUniqueSlug($outlet->nama_outlet, $outlet->id_outlet);
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
            'headline' => $request->input('headline'),
            'intro_paragraph' => $request->input('intro_paragraph'),
            'secondary_paragraph' => $request->input('secondary_paragraph'),
            'book_now_label' => $request->input('book_now_label') ?: 'BOOK NOW',
            'see_map_label' => $request->input('see_map_label') ?: 'SEE MAP',
            'hero_image' => $heroPath,
            'logo_override' => $logoPath,
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
        $previewKey = $this->resolvePreviewKey();
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
        $brand = MemberAppsBrand::where('outlet_id', $landing->outlet_id)
            ->where('is_active', true)
            ->with(['galleries' => fn ($q) => $q->orderBy('sort_order')])
            ->first();

        $logoPath = $landing->logo_override ?: ($brand?->logo);
        $address = $this->resolveOutletAddress($outlet);
        $mapUrl = $this->resolveOutletMapUrl($outlet);

        $intro = trim((string) $landing->intro_paragraph);
        $introParagraphs = $intro !== ''
            ? preg_split("/\n\s*\n/", $intro) ?: []
            : [];

        $gallery = collect($brand?->galleries ?? [])
            ->filter(fn ($row) => is_string($row->image) && trim($row->image) !== '')
            ->sortBy('sort_order')
            ->map(fn ($row) => $this->publicStorageUrl($row->image))
            ->values()
            ->all();

        return [
            'outlet_id' => (int) $landing->outlet_id,
            'slug' => $landing->slug,
            'outlet_name' => (string) ($outlet?->nama_outlet ?? ''),
            'headline' => $landing->headline,
            'intro_paragraphs' => array_values(array_filter(array_map('trim', $introParagraphs))),
            'secondary_paragraph' => $landing->secondary_paragraph,
            'hero_image_url' => $landing->hero_image ? $this->publicStorageUrl($landing->hero_image) : null,
            'logo_url' => $logoPath ? $this->publicStorageUrl($logoPath) : null,
            'gallery_images' => $gallery,
            'address' => $address !== '' ? $address : null,
            'lat' => $outlet?->lat,
            'long' => $outlet?->long,
            'map_url' => $mapUrl !== '' ? $mapUrl : null,
            'map_embed_url' => $this->resolveOutletMapEmbedUrl($outlet),
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
            'headline' => $landing->headline,
            'intro_paragraph' => $landing->intro_paragraph,
            'secondary_paragraph' => $landing->secondary_paragraph,
            'book_now_label' => $landing->book_now_label,
            'see_map_label' => $landing->see_map_label,
            'hero_image_path' => $landing->hero_image,
            'hero_image_url' => $landing->hero_image ? $this->publicStorageUrl($landing->hero_image) : null,
            'logo_override_path' => $landing->logo_override,
            'logo_override_url' => $landing->logo_override ? $this->publicStorageUrl($landing->logo_override) : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveBrandGalleryUrls(int $outletId): array
    {
        $brand = MemberAppsBrand::where('outlet_id', $outletId)
            ->where('is_active', true)
            ->with(['galleries' => fn ($q) => $q->orderBy('sort_order')])
            ->first();

        if (! $brand) {
            return [];
        }

        return collect($brand->galleries ?? [])
            ->filter(fn ($gallery) => is_string($gallery->image) && trim($gallery->image) !== '')
            ->sortBy('sort_order')
            ->map(fn ($gallery) => $this->publicStorageUrl($gallery->image))
            ->values()
            ->all();
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

    private function resolveOutletMapEmbedUrl(?Outlet $outlet): ?string
    {
        if (! $outlet) {
            return null;
        }

        $lat = trim((string) ($outlet->lat ?? ''));
        $long = trim((string) ($outlet->long ?? ''));

        if ($lat !== '' && $long !== '') {
            return sprintf(
                'https://maps.google.com/maps?q=%s,%s&z=15&output=embed',
                rawurlencode($lat),
                rawurlencode($long)
            );
        }

        $address = trim((string) ($outlet->lokasi ?? ''));
        if ($address === '') {
            return null;
        }

        return 'https://maps.google.com/maps?q='.rawurlencode($address).'&z=15&output=embed';
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
        $webUrl = $this->resolveJustusKunestWebUrl();
        $previewKey = $this->resolvePreviewKey();

        return [
            'justus_kunest_web_url' => $webUrl,
            'preview_key' => $previewKey,
            'preview_web_url_configured' => $webUrl !== '',
        ];
    }

    private function resolveJustusKunestWebUrl(): string
    {
        $fromSetting = trim((string) WebProfileSetting::getValue('justus_kunest_web_url', ''));
        if ($fromSetting !== '') {
            return rtrim($fromSetting, '/');
        }

        return rtrim((string) config('services.justus_kunest.web_url', ''), '/');
    }

    private function resolvePreviewKey(): string
    {
        $fromSetting = trim((string) WebProfileSetting::getValue('justus_kunest_preview_key', ''));
        if ($fromSetting !== '') {
            return $fromSetting;
        }

        return (string) config('services.justus_kunest.preview_key', '');
    }

    private function buildPreviewUrl(string $slug, bool $draft): ?string
    {
        $base = $this->resolveJustusKunestWebUrl();
        $slug = trim($slug);
        if ($base === '' || $slug === '') {
            return null;
        }

        $url = $base.'/outlets/'.rawurlencode($slug);
        if ($draft) {
            $key = $this->resolvePreviewKey();
            if ($key === '') {
                return null;
            }
            $url .= '?preview='.rawurlencode($key);
        }

        return $url;
    }
}
