<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MemberAppsBanner;
use App\Models\MemberAppsReward;
use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsWhatsOn;
use App\Models\MemberAppsBrand;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MemberAppsSettingsController extends Controller
{
    public function index()
    {
        try {
            \Log::info('MemberAppsSettings - Loading index page');
            
            $banners = MemberAppsBanner::orderBy('sort_order')->get();
            \Log::info('MemberAppsSettings - Banners loaded:', ['count' => $banners->count()]);
            
            $rewards = MemberAppsReward::with('item')->get();
            \Log::info('MemberAppsSettings - Rewards loaded:', ['count' => $rewards->count()]);
            
            $challenges = MemberAppsChallenge::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Challenges loaded:', ['count' => $challenges->count()]);
            
            $whatsOn = MemberAppsWhatsOn::orderBy('published_at', 'desc')->get();
            \Log::info('MemberAppsSettings - WhatsOn loaded:', ['count' => $whatsOn->count()]);
            
            $brands = MemberAppsBrand::orderBy('sort_order')->get();
            \Log::info('MemberAppsSettings - Brands loaded:', ['count' => $brands->count()]);
            
            return Inertia::render('MemberAppsSettings/Index', [
                'banners' => $banners,
                'rewards' => $rewards,
                'challenges' => $challenges,
                'whatsOn' => $whatsOn,
                'brands' => $brands
            ]);
        } catch (\Exception $e) {
            \Log::error('MemberAppsSettings - Error loading index:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Failed to load page: ' . $e->getMessage());
        }
    }

    // Banner Methods
    public function storeBanner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $imagePath = $request->file('image')->store('member-apps/banners', 'public');

        MemberAppsBanner::create([
            'title' => $request->title,
            'image' => $imagePath,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => true
        ]);

        return redirect()->back()->with('success', 'Banner berhasil ditambahkan');
    }

    public function updateBanner(Request $request, $id)
    {
        $banner = MemberAppsBanner::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active')
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('member-apps/banners', 'public');
        }

        $banner->update($data);

        return redirect()->back()->with('success', 'Banner berhasil diperbarui');
    }

    public function deleteBanner($id)
    {
        $banner = MemberAppsBanner::findOrFail($id);
        
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        
        $banner->delete();

        return redirect()->back()->with('success', 'Banner berhasil dihapus');
    }

    // Reward Methods
    public function storeReward(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'points_required' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsReward::create([
            'item_id' => $request->item_id,
            'points_required' => $request->points_required,
            'is_active' => true
        ]);

        return redirect()->back()->with('success', 'Reward berhasil ditambahkan');
    }

    public function updateReward(Request $request, $id)
    {
        $reward = MemberAppsReward::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'points_required' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $reward->update([
            'item_id' => $request->item_id,
            'points_required' => $request->points_required,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Reward berhasil diperbarui');
    }

    public function deleteReward($id)
    {
        $reward = MemberAppsReward::findOrFail($id);
        $reward->delete();

        return redirect()->back()->with('success', 'Reward berhasil dihapus');
    }

    public function getItems()
    {
        try {
            \Log::info('GetItems - Loading items for reward dropdown');
            
            $items = Item::join('categories', 'items.category_id', '=', 'categories.id')
                        ->where('items.status', 'active')
                        ->where('categories.show_pos', '1')
                        ->select('items.id', 'items.name')
                        ->get();
            
            \Log::info('GetItems - Items loaded successfully:', ['count' => $items->count()]);
            
            return response()->json($items);
        } catch (\Exception $e) {
            \Log::error('GetItems - Error loading items:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Failed to load items'], 500);
        }
    }

    // Challenge Methods
    public function storeChallenge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'points_reward' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'rules' => $request->rules,
            'points_reward' => $request->points_reward,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('member-apps/challenges', 'public');
        }

        MemberAppsChallenge::create($data);

        return redirect()->back()->with('success', 'Challenge berhasil ditambahkan');
    }

    public function updateChallenge(Request $request, $id)
    {
        $challenge = MemberAppsChallenge::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'points_reward' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'rules' => $request->rules,
            'points_reward' => $request->points_reward,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active')
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($challenge->image) {
                Storage::disk('public')->delete($challenge->image);
            }
            $data['image'] = $request->file('image')->store('member-apps/challenges', 'public');
        }

        $challenge->update($data);

        return redirect()->back()->with('success', 'Challenge berhasil diperbarui');
    }

    public function deleteChallenge($id)
    {
        $challenge = MemberAppsChallenge::findOrFail($id);
        
        if ($challenge->image) {
            Storage::disk('public')->delete($challenge->image);
        }
        
        $challenge->delete();

        return redirect()->back()->with('success', 'Challenge berhasil dihapus');
    }

    // Whats On Methods
    public function storeWhatsOn(Request $request)
    {
        try {
            \Log::info('StoreWhatsOn - Request data:', $request->all());
            
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_featured' => 'nullable|in:true,false,1,0,"true","false"',
                'published_at' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                \Log::error('StoreWhatsOn - Validation failed:', $validator->errors()->toArray());
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = [
                'title' => $request->title,
                'content' => $request->content,
                'is_featured' => $request->boolean('is_featured'),
                'published_at' => $request->published_at ?? now(),
                'is_active' => true
            ];

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('member-apps/whats-on', 'public');
            }

            \Log::info('StoreWhatsOn - Data to create:', $data);
            
            $whatsOn = MemberAppsWhatsOn::create($data);
            
            \Log::info('StoreWhatsOn - Successfully created:', ['id' => $whatsOn->id]);

            return redirect()->back()->with('success', 'Whats On berhasil ditambahkan');
        } catch (\Exception $e) {
            \Log::error('StoreWhatsOn - Error occurred:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Failed to save news: ' . $e->getMessage());
        }
    }

    public function updateWhatsOn(Request $request, $id)
    {
        $whatsOn = MemberAppsWhatsOn::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_featured' => 'nullable|in:true,false,1,0,"true","false"',
            'published_at' => 'nullable|date',
            'is_active' => 'nullable|in:true,false,1,0,"true","false"'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'content' => $request->content,
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $request->published_at,
            'is_active' => $request->boolean('is_active')
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($whatsOn->image) {
                Storage::disk('public')->delete($whatsOn->image);
            }
            $data['image'] = $request->file('image')->store('member-apps/whats-on', 'public');
        }

        $whatsOn->update($data);

        return redirect()->back()->with('success', 'Whats On berhasil diperbarui');
    }

    public function deleteWhatsOn($id)
    {
        $whatsOn = MemberAppsWhatsOn::findOrFail($id);
        
        if ($whatsOn->image) {
            Storage::disk('public')->delete($whatsOn->image);
        }
        
        $whatsOn->delete();

        return redirect()->back()->with('success', 'Whats On berhasil dihapus');
    }

    // Brand Methods
    public function storeBrand(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'website_url' => 'nullable|url',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'website_url' => $request->website_url,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => true
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('member-apps/brands', 'public');
        }

        if ($request->hasFile('pdf_file')) {
            $data['pdf_file'] = $request->file('pdf_file')->store('member-apps/brands', 'public');
        }

        MemberAppsBrand::create($data);

        return redirect()->back()->with('success', 'Brand berhasil ditambahkan');
    }

    public function updateBrand(Request $request, $id)
    {
        $brand = MemberAppsBrand::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            'website_url' => 'nullable|url',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'website_url' => $request->website_url,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active')
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }
            $data['image'] = $request->file('image')->store('member-apps/brands', 'public');
        }

        if ($request->hasFile('pdf_file')) {
            // Delete old PDF
            if ($brand->pdf_file) {
                Storage::disk('public')->delete($brand->pdf_file);
            }
            $data['pdf_file'] = $request->file('pdf_file')->store('member-apps/brands', 'public');
        }

        $brand->update($data);

        return redirect()->back()->with('success', 'Brand berhasil diperbarui');
    }

    public function deleteBrand($id)
    {
        $brand = MemberAppsBrand::findOrFail($id);
        
        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }
        
        if ($brand->pdf_file) {
            Storage::disk('public')->delete($brand->pdf_file);
        }
        
        $brand->delete();

        return redirect()->back()->with('success', 'Brand berhasil dihapus');
    }
}
