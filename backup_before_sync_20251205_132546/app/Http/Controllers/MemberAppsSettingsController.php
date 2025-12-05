<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MemberAppsBanner;
use App\Models\MemberAppsReward;
use App\Models\MemberAppsChallenge;
use App\Models\MemberAppsWhatsOn;
use App\Models\MemberAppsWhatsOnCategory;
use App\Models\MemberAppsBrand;
use App\Models\MemberAppsBrandGallery;
use App\Models\MemberAppsFaq;
use App\Models\MemberAppsTermCondition;
use App\Models\MemberAppsAboutUs;
use App\Models\MemberAppsBenefits;
use App\Models\MemberAppsContactUs;
use App\Models\MemberAppsMember;
use App\Models\MemberAppsOccupation;
use App\Models\MemberAppsVoucher;
use App\Models\MemberAppsVoucherDistribution;
use App\Models\MemberAppsMemberVoucher;
use App\Events\VoucherReceived;
use App\Models\MemberAppsPushNotification;
use App\Models\MemberAppsDeviceToken;
use App\Models\MemberAppsPushNotificationRecipient;
use App\Models\MemberAppsFeedback;
use App\Services\FCMService;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MemberAppsSettingsController extends Controller
{
    public function index(Request $request)
    {
        try {
            \Log::info('MemberAppsSettings - Loading index page');
            
            $banners = MemberAppsBanner::orderBy('sort_order')->get();
            \Log::info('MemberAppsSettings - Banners loaded:', ['count' => $banners->count()]);
            
            $rewards = MemberAppsReward::with(['item', 'outlets'])->get();
            \Log::info('MemberAppsSettings - Rewards loaded:', ['count' => $rewards->count()]);
            
            $challenges = MemberAppsChallenge::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Challenges loaded:', ['count' => $challenges->count()]);
            
            $whatsOn = MemberAppsWhatsOn::with('category')->orderBy('published_at', 'desc')->get();
            \Log::info('MemberAppsSettings - WhatsOn loaded:', ['count' => $whatsOn->count()]);
            
            $whatsOnCategories = MemberAppsWhatsOnCategory::where('is_active', true)->orderBy('name')->get();
            \Log::info('MemberAppsSettings - WhatsOnCategories loaded:', ['count' => $whatsOnCategories->count()]);
            
            $brands = MemberAppsBrand::with(['outlet', 'galleries'])->orderBy('sort_order')->get();
            \Log::info('MemberAppsSettings - Brands loaded:', ['count' => $brands->count()]);
            
            // Get brands from brands table (for brands tab)
            $brandsTable = DB::table('brands')
                ->orderBy('brand', 'asc')
                ->get();
            \Log::info('MemberAppsSettings - Brands Table loaded:', ['count' => $brandsTable->count()]);
            
            // Get active outlets for brand selection (exclude franchise outlets)
            $outlets = DB::table('tbl_data_outlet')
                ->where('status', 'A')
                ->where('is_outlet', 1)
                ->where('is_fc', 0)
                ->select('id_outlet as id', 'nama_outlet as name', 'id_brand')
                ->orderBy('nama_outlet')
                ->get();
            \Log::info('MemberAppsSettings - Outlets loaded:', ['count' => $outlets->count()]);
            
            $faqs = MemberAppsFaq::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - FAQs loaded:', ['count' => $faqs->count()]);
            
            $termsConditions = MemberAppsTermCondition::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Terms & Conditions loaded:', ['count' => $termsConditions->count()]);
            
            $aboutUs = MemberAppsAboutUs::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - About Us loaded:', ['count' => $aboutUs->count()]);
            
            $benefits = MemberAppsBenefits::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Benefits loaded:', ['count' => $benefits->count()]);
            
            $contactUs = MemberAppsContactUs::orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Contact Us loaded:', ['count' => $contactUs->count()]);
            
            $members = MemberAppsMember::with('occupation')->orderBy('created_at', 'desc')->paginate(20);
            \Log::info('MemberAppsSettings - Members loaded:', ['count' => $members->total()]);
            
            $occupations = MemberAppsOccupation::where('is_active', true)->orderBy('sort_order')->get();
            \Log::info('MemberAppsSettings - Occupations loaded:', ['count' => $occupations->count()]);
            
            $vouchers = MemberAppsVoucher::with(['distributions', 'memberVouchers'])->orderBy('created_at', 'desc')->get();
            \Log::info('MemberAppsSettings - Vouchers loaded:', ['count' => $vouchers->count()]);
            
            $pushNotifications = MemberAppsPushNotification::with('recipients')->orderBy('created_at', 'desc')->paginate(20);
            \Log::info('MemberAppsSettings - Push Notifications loaded:', ['count' => $pushNotifications->total()]);
            
            // Get feedbacks with pagination, filter, and search
            $status = $request->input('status', '');
            $search = $request->input('search', '');
            $perPage = $request->input('per_page', 10);
            
            $feedbacksQuery = MemberAppsFeedback::with(['member', 'replies.member'])
                ->whereNull('parent_id'); // Only get main feedbacks
            
            // Apply status filter
            if ($status && in_array($status, ['pending', 'read', 'replied', 'resolved'])) {
                $feedbacksQuery->where('status', $status);
            }
            
            // Apply search filter
            if ($search) {
                $feedbacksQuery->where(function($query) use ($search) {
                    $query->where('subject', 'like', "%{$search}%")
                          ->orWhere('message', 'like', "%{$search}%")
                          ->orWhereHas('member', function($q) use ($search) {
                              $q->where('nama_lengkap', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('member_id', 'like', "%{$search}%");
                          });
                });
            }
            
            $feedbacks = $feedbacksQuery->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
            // Load outlet names for each feedback
            $feedbacks->getCollection()->transform(function ($feedback) {
                if ($feedback->outlet_id) {
                    $outlet = DB::table('tbl_data_outlet')
                        ->where('id_outlet', $feedback->outlet_id)
                        ->first();
                    $feedback->outlet_name = $outlet ? $outlet->nama_outlet : null;
                } else {
                    $feedback->outlet_name = null;
                }
                return $feedback;
            });
            
            \Log::info('MemberAppsSettings - Feedbacks loaded:', ['count' => $feedbacks->total()]);
            
            return Inertia::render('MemberAppsSettings/Index', [
                'banners' => $banners,
                'rewards' => $rewards,
                'challenges' => $challenges,
                'whatsOn' => $whatsOn,
                'whatsOnCategories' => $whatsOnCategories,
                'brands' => $brands,
                'brandsTable' => $brandsTable,
                'outlets' => $outlets,
                'faqs' => $faqs,
                'termsConditions' => $termsConditions,
                'aboutUs' => $aboutUs,
                'benefits' => $benefits,
                'contactUs' => $contactUs,
                'members' => $members,
                'occupations' => $occupations,
                'vouchers' => $vouchers,
                'pushNotifications' => $pushNotifications,
                'feedbacks' => $feedbacks
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
            'points_required' => 'required|integer|min:1',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet',
            'all_outlets' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Generate unique serial code
        $serialCode = $this->generateSerialCode();

        $reward = MemberAppsReward::create([
            'item_id' => $request->item_id,
            'points_required' => $request->points_required,
            'serial_code' => $serialCode,
            'is_active' => true
        ]);

        // Attach outlets if provided and not all outlets
        if (!$request->boolean('all_outlets') && $request->has('outlet_ids') && !empty($request->outlet_ids)) {
            $reward->outlets()->attach($request->outlet_ids);
        }

        return redirect()->back()->with('success', 'Reward berhasil ditambahkan');
    }

    private function generateSerialCode()
    {
        do {
            $date = date('Ymd');
            $time = date('His');
            $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            $serialCode = "JTS-{$date}-{$time}-{$random}";
        } while (MemberAppsReward::where('serial_code', $serialCode)->exists());

        return $serialCode;
    }

    public function updateReward(Request $request, $id)
    {
        $reward = MemberAppsReward::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'points_required' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet',
            'all_outlets' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $reward->update([
            'item_id' => $request->item_id,
            'points_required' => $request->points_required,
            'is_active' => $request->has('is_active')
        ]);

        // Update outlets
        if ($request->boolean('all_outlets')) {
            // If all outlets, remove all outlet associations
            $reward->outlets()->detach();
        } else {
            // Sync outlets (attach/detach as needed)
            if ($request->has('outlet_ids')) {
                $reward->outlets()->sync($request->outlet_ids);
            } else {
                // If no outlet_ids provided, remove all
                $reward->outlets()->detach();
            }
        }

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
        try {
            \Log::info('StoreChallenge - Request received', [
                'title' => $request->title,
                'has_rules' => $request->has('rules'),
                'has_points_reward' => $request->has('points_reward'),
                'points_reward' => $request->points_reward,
                'challenge_type_id' => $request->challenge_type_id
            ]);
            
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'points_reward' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'challenge_type_id' => 'nullable|string',
            'validity_period_days' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
                \Log::error('StoreChallenge - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'rules' => $request->rules,
                'points_reward' => $request->points_reward ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => true
        ];
        
        // Always set challenge_type_id if provided (even if empty string)
        if ($request->filled('challenge_type_id')) {
            $data['challenge_type_id'] = $request->challenge_type_id;
        }
        
        if ($request->filled('validity_period_days')) {
            $data['validity_period_days'] = $request->validity_period_days;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('member-apps/challenges', 'public');
        }

            \Log::info('StoreChallenge - Data to create', $data);
            
            $challenge = MemberAppsChallenge::create($data);
            
            // Sync challenge outlets
            if ($request->has('challenge_outlet_ids')) {
                $outletIds = $request->input('challenge_outlet_ids', []);
                // Filter out empty strings and null values
                $outletIds = array_filter($outletIds, function($id) {
                    return !empty($id) && $id !== '';
                });
                
                if (!empty($outletIds)) {
                    $challenge->outlets()->sync($outletIds);
                } else {
                    // Empty array means challenge applies to all outlets
                    $challenge->outlets()->detach();
                }
            } else {
                // If no outlet_ids provided, detach all (challenge applies to all outlets)
                $challenge->outlets()->detach();
            }
            
            \Log::info('StoreChallenge - Successfully created', [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'outlet_ids' => $request->challenge_outlet_ids ?? []
            ]);

        return redirect()->back()->with('success', 'Challenge berhasil ditambahkan');
        } catch (\Exception $e) {
            \Log::error('StoreChallenge - Error occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['general' => 'Gagal menyimpan challenge: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function showChallenge($id)
    {
        $challenge = MemberAppsChallenge::with('outlets')->findOrFail($id);
        
        // Parse rules JSON if it's a string
        $rules = $challenge->rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?? [];
        }
        
        // Load items if reward_type is item and reward_value exists (can be array or single)
        $rewardItems = [];
        if (isset($rules['reward_type']) && $rules['reward_type'] === 'item' && isset($rules['reward_value'])) {
            $itemIds = is_array($rules['reward_value']) ? $rules['reward_value'] : [$rules['reward_value']];
            if (!empty($itemIds)) {
                $items = Item::whereIn('id', $itemIds)->get();
                foreach ($items as $item) {
                    // Use string key for JavaScript compatibility
                    $rewardItems[(string)$item->id] = [
                        'id' => $item->id,
                        'name' => $item->name
                    ];
                }
            }
        }
        
        // Load items for product-based challenge
        $productItems = [];
        if (isset($rules['products']) && is_array($rules['products']) && !empty($rules['products'])) {
            $items = Item::whereIn('id', $rules['products'])->get();
            foreach ($items as $item) {
                // Use string key for JavaScript compatibility
                $productItems[(string)$item->id] = [
                    'id' => $item->id,
                    'name' => $item->name
                ];
            }
        }
        
        // Load vouchers if reward_type is voucher and reward_value exists
        $rewardVouchers = [];
        if (isset($rules['reward_type']) && $rules['reward_type'] === 'voucher' && isset($rules['reward_value'])) {
            $voucherIds = is_array($rules['reward_value']) ? $rules['reward_value'] : [$rules['reward_value']];
            if (!empty($voucherIds)) {
                $vouchers = MemberAppsVoucher::whereIn('id', $voucherIds)->get();
                foreach ($vouchers as $voucher) {
                    // Use string key for JavaScript compatibility
                    $rewardVouchers[(string)$voucher->id] = [
                        'id' => $voucher->id,
                        'name' => $voucher->name
                    ];
                }
            }
        }
        
        $challenge->rules = $rules;
        $challenge->reward_items = $rewardItems;
        $challenge->product_items = $productItems;
        $challenge->reward_vouchers = $rewardVouchers;
        
        return Inertia::render('MemberAppsSettings/ChallengeShow', [
            'challenge' => $challenge
        ]);
    }

    public function updateChallenge(Request $request, $id)
    {
        try {
            \Log::info('UpdateChallenge - Request received', [
                'id' => $id,
                'title' => $request->title,
                'has_rules' => $request->has('rules'),
                'has_points_reward' => $request->has('points_reward'),
                'points_reward' => $request->points_reward,
                'challenge_type_id' => $request->challenge_type_id
            ]);
            
        $challenge = MemberAppsChallenge::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rules' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'points_reward' => 'required|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'nullable|boolean',
            'challenge_type_id' => 'nullable|string',
            'validity_period_days' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
                \Log::error('UpdateChallenge - Validation failed', [
                    'id' => $id,
                    'errors' => $validator->errors()->toArray()
                ]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'rules' => $request->rules,
                'points_reward' => $request->points_reward ?? 0,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active')
        ];
        
        // Always set challenge_type_id if provided (even if empty string)
        if ($request->filled('challenge_type_id')) {
            $data['challenge_type_id'] = $request->challenge_type_id;
        }
        
        if ($request->filled('validity_period_days')) {
            $data['validity_period_days'] = $request->validity_period_days;
        }

        if ($request->hasFile('image')) {
            // Delete old image
            if ($challenge->image) {
                Storage::disk('public')->delete($challenge->image);
            }
            $data['image'] = $request->file('image')->store('member-apps/challenges', 'public');
        }

            \Log::info('UpdateChallenge - Data to update', $data);

        $challenge->update($data);
        
        // Sync challenge outlets
        if ($request->has('challenge_outlet_ids')) {
            $outletIds = $request->input('challenge_outlet_ids', []);
            // Filter out empty strings and null values
            $outletIds = array_filter($outletIds, function($id) {
                return !empty($id) && $id !== '';
            });
            
            if (!empty($outletIds)) {
                $challenge->outlets()->sync($outletIds);
            } else {
                // Empty array means challenge applies to all outlets
                $challenge->outlets()->detach();
            }
        } else {
            // If no outlet_ids provided, detach all (challenge applies to all outlets)
            $challenge->outlets()->detach();
        }
            
            \Log::info('UpdateChallenge - Successfully updated', [
                'id' => $challenge->id,
                'title' => $challenge->title,
                'outlet_ids' => $request->challenge_outlet_ids ?? []
            ]);

        return redirect()->back()->with('success', 'Challenge berhasil diperbarui');
        } catch (\Exception $e) {
            \Log::error('UpdateChallenge - Error occurred', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['general' => 'Gagal memperbarui challenge: ' . $e->getMessage()])
                ->withInput();
        }
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
                'published_at' => 'nullable|date',
                'category_id' => 'nullable|exists:member_apps_whats_on_categories,id'
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
                'is_active' => true,
                'category_id' => $request->category_id ?: null
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
            'is_active' => 'nullable|in:true,false,1,0,"true","false"',
            'category_id' => 'nullable|exists:member_apps_whats_on_categories,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'title' => $request->title,
            'content' => $request->content,
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $request->published_at,
            'is_active' => $request->boolean('is_active'),
            'category_id' => $request->category_id ?: null
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

    // Whats On Category Methods
    public function storeWhatsOnCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:member_apps_whats_on_categories,name',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = MemberAppsWhatsOnCategory::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category berhasil ditambahkan',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            \Log::error('StoreWhatsOnCategory - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateWhatsOnCategory(Request $request, $id)
    {
        try {
            $category = MemberAppsWhatsOnCategory::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:member_apps_whats_on_categories,name,' . $id,
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category berhasil diperbarui',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            \Log::error('UpdateWhatsOnCategory - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteWhatsOnCategory($id)
    {
        try {
            $category = MemberAppsWhatsOnCategory::findOrFail($id);
            
            // Check if category is used
            $usedCount = MemberAppsWhatsOn::where('category_id', $id)->count();
            if ($usedCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Category tidak dapat dihapus karena masih digunakan oleh {$usedCount} whats on item(s)"
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            \Log::error('DeleteWhatsOnCategory - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category: ' . $e->getMessage()
            ], 500);
        }
    }

    // Brand Methods
    public function storeBrand(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'outlet_id' => 'required|exists:tbl_data_outlet,id_outlet',
                'id_brand' => 'required|exists:brands,id',
                'description' => 'nullable|string',
                'whatsapp_number' => 'nullable|string|max:20',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'pdf_menu' => 'nullable|file|mimes:pdf|max:10240',
                'pdf_new_dining_experience' => 'nullable|file|mimes:pdf|max:10240',
                'gallery_images' => 'nullable|array',
                'gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'facility' => 'nullable|array',
                'facility.*' => 'string|in:wifi,smoking_area,mushola,meeting_room,valet_parking',
                'tripadvisor_link' => 'nullable|url|max:500'
            ]);

            if ($validator->fails()) {
                \Log::error('Brand validation failed', ['errors' => $validator->errors()->toArray()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Get outlet name
            $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $request->outlet_id)->first();
            if (!$outlet) {
                \Log::error('Brand store - Outlet not found', ['outlet_id' => $request->outlet_id]);
                return redirect()->back()->withErrors(['outlet_id' => 'Outlet tidak ditemukan'])->withInput();
            }

            // Check if brand already exists for this outlet
            $existingBrand = MemberAppsBrand::where('outlet_id', $request->outlet_id)->first();
            if ($existingBrand) {
                \Log::error('Brand store - Brand already exists', ['outlet_id' => $request->outlet_id]);
                return redirect()->back()->withErrors(['outlet_id' => 'Brand untuk outlet ini sudah ada'])->withInput();
            }

            $data = [
                'outlet_id' => $request->outlet_id,
                'name' => $outlet->nama_outlet,
                'description' => $request->description,
                'whatsapp_number' => $request->whatsapp_number,
                'facility' => ($request->has('facility') && is_array($request->facility) && count($request->facility) > 0) 
                    ? json_encode($request->facility) 
                    : null,
                'tripadvisor_link' => $request->tripadvisor_link ?: null,
                'is_active' => true
            ];

            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('member-apps/brands/logos', 'public');
            }

            if ($request->hasFile('pdf_menu')) {
                $data['pdf_menu'] = $request->file('pdf_menu')->store('member-apps/brands/pdfs', 'public');
            }

            if ($request->hasFile('pdf_new_dining_experience')) {
                $data['pdf_new_dining_experience'] = $request->file('pdf_new_dining_experience')->store('member-apps/brands/pdfs', 'public');
            }

            $brand = MemberAppsBrand::create($data);

            // Update tbl_data_outlet with id_brand
            DB::table('tbl_data_outlet')
                ->where('id_outlet', $request->outlet_id)
                ->update(['id_brand' => $request->id_brand]);

            // Handle gallery images
            if ($request->hasFile('gallery_images')) {
                foreach ($request->file('gallery_images') as $index => $image) {
                    $imagePath = $image->store('member-apps/brands/gallery', 'public');
                    MemberAppsBrandGallery::create([
                        'brand_id' => $brand->id,
                        'image' => $imagePath,
                        'sort_order' => $index
                    ]);
                }
            }

            \Log::info('Brand created successfully', ['brand_id' => $brand->id, 'outlet_id' => $request->outlet_id]);
            return redirect()->back()->with('success', 'Brand berhasil ditambahkan');
        } catch (\Exception $e) {
            \Log::error('Error storing brand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['logo', 'pdf_menu', 'pdf_new_dining_experience', 'gallery_images'])
            ]);
            
            return redirect()->back()
                ->withErrors(['general' => 'Terjadi kesalahan: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function updateBrand(Request $request, $id)
    {
        try {
            $brand = MemberAppsBrand::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'id_brand' => 'nullable|exists:brands,id',
                'description' => 'nullable|string',
                'whatsapp_number' => 'nullable|string|max:20',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'pdf_menu' => 'nullable|file|mimes:pdf|max:10240',
                'pdf_new_dining_experience' => 'nullable|file|mimes:pdf|max:10240',
                'gallery_images' => 'nullable|array',
                'gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'delete_gallery_ids' => 'nullable|array',
                'delete_gallery_ids.*' => 'exists:member_apps_brand_galleries,id',
                'facility' => 'nullable', // Can be array or JSON string
                'facility_clear' => 'nullable|string',
                'tripadvisor_link' => 'nullable|string|max:500' // Changed from url to string to allow empty or partial URLs
            ]);

            if ($validator->fails()) {
                \Log::error('Brand update validation failed', ['errors' => $validator->errors()->toArray(), 'brand_id' => $id]);
                
                // Check if AJAX request
                if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Log all request data for debugging
            $allData = $request->all();
            \Log::info('UpdateBrand - All request data', [
                'all_input' => $allData,
                'has_facility' => $request->has('facility'),
                'facility_input' => $request->input('facility'),
                'facility_from_all' => isset($allData['facility']) ? $allData['facility'] : null,
                'has_facility_clear' => $request->has('facility_clear'),
                'tripadvisor_link_input' => $request->input('tripadvisor_link'),
                'description_input' => $request->input('description'),
                'whatsapp_number_input' => $request->input('whatsapp_number'),
            ]);
            
            // Handle facility - Laravel automatically converts facility[] to array
            $facility = null;
            
            if ($request->has('facility_clear') && $request->input('facility_clear') == '1') {
                // Explicit clear marker
                $facility = null;
                \Log::info('UpdateBrand - Clearing facility (explicit clear marker)');
            } else {
                // Get facility from request - use input() which supports arrays
                $facilityInput = $request->input('facility');
                
                if (is_array($facilityInput) && count($facilityInput) > 0) {
                    // If it's already an array from FormData (facility[]), encode it
                    $facility = json_encode($facilityInput);
                    \Log::info('UpdateBrand - Facility from array', ['facility' => $facility, 'input' => $facilityInput]);
                } elseif (is_string($facilityInput)) {
                    if ($facilityInput === '' || $facilityInput === '[]') {
                        // Empty string or empty array means clear facility
                        $facility = null;
                    } else {
                        // If it's a JSON string, validate and use it
                        $decoded = json_decode($facilityInput, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $facility = count($decoded) > 0 ? $facilityInput : null;
                        } else {
                            \Log::warning('Invalid facility JSON format', ['facility' => $facilityInput]);
                            $facility = null;
                        }
                    }
                } else {
                    \Log::info('UpdateBrand - No facility input or empty', ['facilityInput' => $facilityInput]);
                }
            }
            
            \Log::info('UpdateBrand - Processed facility', ['facility' => $facility]);
            
            // Use input() method to get data from request
            $data = [
                'description' => $request->input('description'),
                'whatsapp_number' => $request->input('whatsapp_number'),
                'facility' => $facility,
                'tripadvisor_link' => $request->input('tripadvisor_link') ?: null,
                'is_active' => true
            ];
            
            \Log::info('UpdateBrand - Data to update', $data);

            if ($request->hasFile('logo')) {
                // Delete old logo
                if ($brand->logo) {
                    Storage::disk('public')->delete($brand->logo);
                }
                $data['logo'] = $request->file('logo')->store('member-apps/brands/logos', 'public');
            }

            if ($request->hasFile('pdf_menu')) {
                // Delete old PDF menu
                if ($brand->pdf_menu) {
                    Storage::disk('public')->delete($brand->pdf_menu);
                }
                $data['pdf_menu'] = $request->file('pdf_menu')->store('member-apps/brands/pdfs', 'public');
            }

            if ($request->hasFile('pdf_new_dining_experience')) {
                // Delete old PDF new dining experience
                if ($brand->pdf_new_dining_experience) {
                    Storage::disk('public')->delete($brand->pdf_new_dining_experience);
                }
                $data['pdf_new_dining_experience'] = $request->file('pdf_new_dining_experience')->store('member-apps/brands/pdfs', 'public');
            }

            \Log::info('UpdateBrand - Updating brand', [
                'brand_id' => $brand->id,
                'data' => $data
            ]);
            
            $brand->update($data);
            
            \Log::info('UpdateBrand - Brand updated successfully', [
                'brand_id' => $brand->id,
                'facility' => $brand->facility,
                'tripadvisor_link' => $brand->tripadvisor_link
            ]);

            // Handle delete gallery images
            if ($request->has('delete_gallery_ids')) {
                foreach ($request->delete_gallery_ids as $galleryId) {
                    $gallery = MemberAppsBrandGallery::find($galleryId);
                    if ($gallery) {
                        Storage::disk('public')->delete($gallery->image);
                        $gallery->delete();
                    }
                }
            }

            // Handle new gallery images
            if ($request->hasFile('gallery_images')) {
                $maxSortOrder = MemberAppsBrandGallery::where('brand_id', $brand->id)->max('sort_order') ?? -1;
                foreach ($request->file('gallery_images') as $index => $image) {
                    $imagePath = $image->store('member-apps/brands/gallery', 'public');
                    MemberAppsBrandGallery::create([
                        'brand_id' => $brand->id,
                        'image' => $imagePath,
                        'sort_order' => $maxSortOrder + $index + 1
                    ]);
                }
            }

            // Update tbl_data_outlet with id_brand if provided
            if ($request->has('id_brand') && $request->id_brand) {
                DB::table('tbl_data_outlet')
                    ->where('id_outlet', $brand->outlet_id)
                    ->update(['id_brand' => $request->id_brand]);
            }

            \Log::info('Brand updated successfully', ['brand_id' => $brand->id]);
            
            // Check if AJAX request
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Brand berhasil diperbarui'
                ], 200);
            }
            
            return redirect()->back()->with('success', 'Brand berhasil diperbarui');
        } catch (\Exception $e) {
            \Log::error('Error updating brand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'brand_id' => $id,
                'request_data' => $request->except(['logo', 'pdf_menu', 'pdf_new_dining_experience', 'gallery_images'])
            ]);
            
            $errorMessage = 'Terjadi kesalahan: ' . $e->getMessage();
            
            // Check if AJAX request
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['general' => $errorMessage]
                ], 500);
            }
            
            return redirect()->back()
                ->withErrors(['general' => $errorMessage])
                ->withInput();
        }
    }

    // Brands Table Methods (for brands table, not member_apps_brands)
    public function storeBrandTable(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'brand' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = [
                'brand' => $request->brand,
            ];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('brands', 'public');
                $data['logo'] = $logoPath;
            }

            DB::table('brands')->insert($data);

            return redirect()->back()->with('success', 'Brand berhasil ditambahkan');
        } catch (\Exception $e) {
            \Log::error('Store Brand Table Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to store brand: ' . $e->getMessage()])->withInput();
        }
    }

    public function updateBrandTable(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'brand' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $brand = DB::table('brands')->where('id', $id)->first();
            if (!$brand) {
                return redirect()->back()->withErrors(['error' => 'Brand tidak ditemukan']);
            }

            $data = [
                'brand' => $request->brand,
            ];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($brand->logo) {
                    Storage::disk('public')->delete($brand->logo);
                }
                $logoPath = $request->file('logo')->store('brands', 'public');
                $data['logo'] = $logoPath;
            }

            DB::table('brands')->where('id', $id)->update($data);

            return redirect()->back()->with('success', 'Brand berhasil diperbarui');
        } catch (\Exception $e) {
            \Log::error('Update Brand Table Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update brand: ' . $e->getMessage()])->withInput();
        }
    }

    public function deleteBrandTable($id)
    {
        try {
            $brand = DB::table('brands')->where('id', $id)->first();
            if (!$brand) {
                return redirect()->back()->withErrors(['error' => 'Brand tidak ditemukan']);
            }

            // Delete logo if exists
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }

            DB::table('brands')->where('id', $id)->delete();

            return redirect()->back()->with('success', 'Brand berhasil dihapus');
        } catch (\Exception $e) {
            \Log::error('Delete Brand Table Error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete brand: ' . $e->getMessage()]);
        }
    }

    public function deleteBrand($id)
    {
        $brand = MemberAppsBrand::findOrFail($id);
        
        // Delete logo
        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }
        
        // Delete PDF files
        if ($brand->pdf_menu) {
            Storage::disk('public')->delete($brand->pdf_menu);
        }
        
        if ($brand->pdf_new_dining_experience) {
            Storage::disk('public')->delete($brand->pdf_new_dining_experience);
        }
        
        // Delete gallery images
        foreach ($brand->galleries as $gallery) {
            Storage::disk('public')->delete($gallery->image);
        }
        
        $brand->delete();

        return redirect()->back()->with('success', 'Brand berhasil dihapus');
    }

    // FAQ Methods
    public function storeFaq(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'answer' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsFaq::create([
            'question' => $request->question,
            'answer' => $request->answer,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'FAQ berhasil ditambahkan');
    }

    public function updateFaq(Request $request, $id)
    {
        $faq = MemberAppsFaq::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'question' => 'required|string',
            'answer' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $faq->update([
            'question' => $request->question,
            'answer' => $request->answer,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'FAQ berhasil diperbarui');
    }

    public function deleteFaq($id)
    {
        $faq = MemberAppsFaq::findOrFail($id);
        $faq->delete();

        return redirect()->back()->with('success', 'FAQ berhasil dihapus');
    }

    // Terms & Conditions Methods
    public function storeTermCondition(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsTermCondition::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Terms & Condition berhasil ditambahkan');
    }

    public function updateTermCondition(Request $request, $id)
    {
        $termCondition = MemberAppsTermCondition::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $termCondition->update([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Terms & Condition berhasil diperbarui');
    }

    public function deleteTermCondition($id)
    {
        $termCondition = MemberAppsTermCondition::findOrFail($id);
        $termCondition->delete();

        return redirect()->back()->with('success', 'Terms & Condition berhasil dihapus');
    }

    // About Us Methods
    public function storeAboutUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsAboutUs::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'About Us berhasil ditambahkan');
    }

    public function updateAboutUs(Request $request, $id)
    {
        $aboutUs = MemberAppsAboutUs::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $aboutUs->update([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'About Us berhasil diperbarui');
    }

    public function deleteAboutUs($id)
    {
        $aboutUs = MemberAppsAboutUs::findOrFail($id);
        $aboutUs->delete();

        return redirect()->back()->with('success', 'About Us berhasil dihapus');
    }

    // Benefits Methods
    public function storeBenefits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsBenefits::create([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Benefits berhasil ditambahkan');
    }

    public function updateBenefits(Request $request, $id)
    {
        $benefits = MemberAppsBenefits::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $benefits->update([
            'title' => $request->title,
            'content' => $request->content,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Benefits berhasil diperbarui');
    }

    public function deleteBenefits($id)
    {
        $benefits = MemberAppsBenefits::findOrFail($id);
        $benefits->delete();

        return redirect()->back()->with('success', 'Benefits berhasil dihapus');
    }

    // Contact Us Methods
    public function storeContactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'whatsapp_number' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MemberAppsContactUs::create([
            'title' => $request->title,
            'content' => $request->content,
            'whatsapp_number' => $request->whatsapp_number,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Contact Us berhasil ditambahkan');
    }

    public function updateContactUs(Request $request, $id)
    {
        $contactUs = MemberAppsContactUs::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'whatsapp_number' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $contactUs->update([
            'title' => $request->title,
            'content' => $request->content,
            'whatsapp_number' => $request->whatsapp_number,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->back()->with('success', 'Contact Us berhasil diperbarui');
    }

    public function deleteContactUs($id)
    {
        $contactUs = MemberAppsContactUs::findOrFail($id);
        $contactUs->delete();

        return redirect()->back()->with('success', 'Contact Us berhasil dihapus');
    }

    // Voucher Methods
    public function storeVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'voucher_type' => 'required|in:discount-percentage,discount-fixed,free-item,cashback',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'free_item_name' => 'nullable|string|max:255',
            'free_item_ids' => 'nullable|array',
            'free_item_ids.*' => 'nullable|integer|exists:items,id',
            'free_item_selection' => 'nullable|in:all,single',
            'cashback_amount' => 'nullable|numeric|min:0',
            'cashback_percentage' => 'nullable|numeric|min:0|max:100',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
            'total_quantity' => 'nullable|integer|min:1',
            'applicable_channels' => 'nullable|array',
            'applicable_days' => 'nullable|array',
            'applicable_time_start' => 'nullable|date_format:H:i',
            'applicable_time_end' => 'nullable|date_format:H:i',
            'exclude_items' => 'nullable|array',
            'exclude_categories' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'nullable|boolean',
            'is_for_sale' => 'nullable|boolean',
            'points_required' => 'nullable|integer|min:1|required_if:is_for_sale,1',
            'one_time_purchase' => 'nullable|boolean',
            'all_outlets' => 'nullable|boolean',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:tbl_data_outlet,id_outlet'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'voucher_type' => $request->voucher_type,
            'valid_from' => $request->valid_from,
            'valid_until' => $request->valid_until,
            'usage_limit' => $request->usage_limit,
            'total_quantity' => $request->total_quantity,
            'applicable_channels' => $request->applicable_channels,
            'applicable_days' => $request->applicable_days,
            'applicable_time_start' => $request->applicable_time_start,
            'applicable_time_end' => $request->applicable_time_end,
            'exclude_items' => $request->exclude_items,
            'exclude_categories' => $request->exclude_categories,
            'is_active' => $request->has('is_active'),
            'is_for_sale' => $request->has('is_for_sale') && $request->is_for_sale,
            'points_required' => $request->has('is_for_sale') && $request->is_for_sale ? $request->points_required : null,
            'one_time_purchase' => $request->has('is_for_sale') && $request->is_for_sale && $request->has('one_time_purchase') && $request->one_time_purchase,
            'is_birthday_voucher' => $request->has('is_birthday_voucher') && $request->is_birthday_voucher,
            'created_by' => auth()->id()
        ];

        // Set fields based on voucher type
        if ($request->voucher_type === 'discount-percentage') {
            $data['discount_percentage'] = $request->discount_percentage;
            $data['max_discount'] = $request->max_discount;
        } elseif ($request->voucher_type === 'discount-fixed') {
            $data['discount_amount'] = $request->discount_amount;
        } elseif ($request->voucher_type === 'free-item') {
            $data['free_item_name'] = $request->free_item_name;
            // Save free_item_ids as JSON if provided
            if ($request->has('free_item_ids') && is_array($request->free_item_ids) && !empty($request->free_item_ids)) {
                // Filter out null values and convert to integers
                $freeItemIds = array_filter(array_map('intval', $request->free_item_ids), function($id) {
                    return $id > 0;
                });
                if (!empty($freeItemIds)) {
                    $data['free_item_ids'] = json_encode(array_values($freeItemIds));
                }
            }
            // Save free_item_selection if provided
            if ($request->has('free_item_selection') && in_array($request->free_item_selection, ['all', 'single'])) {
                $data['free_item_selection'] = $request->free_item_selection;
            }
        } elseif ($request->voucher_type === 'cashback') {
            $data['cashback_amount'] = $request->cashback_amount;
            $data['cashback_percentage'] = $request->cashback_percentage;
        }

        if ($request->min_purchase) {
            $data['min_purchase'] = $request->min_purchase;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('vouchers', 'public');
            $data['image'] = $imagePath;
        }

        DB::beginTransaction();
        try {
            $voucher = MemberAppsVoucher::create($data);

            // Handle outlet selection
            $allOutlets = $request->input('all_outlets', true);
            if (!$allOutlets && $request->has('outlet_ids') && is_array($request->outlet_ids) && !empty($request->outlet_ids)) {
                // Sync outlet_ids to pivot table
                $outletIds = array_map('intval', $request->outlet_ids);
                $voucher->outlets()->sync($outletIds);
            } else {
                // If all_outlets is true or outlet_ids is empty, clear the pivot table
                $voucher->outlets()->detach();
            }

            DB::commit();
            return redirect()->back()->with('success', 'Voucher berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('StoreVoucher - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->back()->with('error', 'Gagal membuat voucher: ' . $e->getMessage())->withInput();
        }
    }

    public function distributeVoucher(Request $request, $id)
    {
        $voucher = MemberAppsVoucher::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'distribution_type' => 'required|in:all,specific,filter',
            'member_ids' => 'required_if:distribution_type,specific|array',
            'member_ids.*' => 'exists:member_apps_members,id',
            'filter_criteria' => 'required_if:distribution_type,filter|array'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Create distribution record
            $distribution = MemberAppsVoucherDistribution::create([
                'voucher_id' => $voucher->id,
                'distribution_type' => $request->distribution_type,
                'member_ids' => $request->member_ids,
                'filter_criteria' => $request->filter_criteria,
                'distributed_at' => now(),
                'created_by' => auth()->id()
            ]);

            // Get members based on distribution type
            $members = $this->getMembersForDistribution($request->distribution_type, $request->member_ids ?? [], $request->filter_criteria ?? []);

            $distributedCount = 0;
            foreach ($members as $member) {
                // Check if member already has this voucher (if usage_limit is set)
                $existingVouchers = MemberAppsMemberVoucher::where('voucher_id', $voucher->id)
                    ->where('member_id', $member->id)
                    ->where('status', 'active')
                    ->count();

                if ($voucher->usage_limit && $existingVouchers >= $voucher->usage_limit) {
                    continue; // Skip if already reached usage limit
                }

                // Generate unique voucher code
                $voucherCode = $this->generateVoucherCode($voucher->id, $member->id);
                
                // Generate unique serial code for this voucher distribution
                $serialCode = $this->generateVoucherSerialCode($voucher->id, $member->id);

                $memberVoucher = MemberAppsMemberVoucher::create([
                    'voucher_id' => $voucher->id,
                    'voucher_distribution_id' => $distribution->id,
                    'member_id' => $member->id,
                    'voucher_code' => $voucherCode,
                    'serial_code' => $serialCode,
                    'expires_at' => $voucher->valid_until,
                    'status' => 'active'
                ]);

                // Refresh member and voucher relationships
                $memberVoucher->load(['member', 'voucher']);
                
                // Dispatch event for push notification
                try {
                    event(new VoucherReceived(
                        $memberVoucher->member,
                        $memberVoucher
                    ));
                } catch (\Exception $e) {
                    \Log::error('Error dispatching VoucherReceived event for distributed voucher', [
                        'member_id' => $member->id,
                        'voucher_id' => $voucher->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $distributedCount++;
            }

            // Update distribution total
            $distribution->update(['total_distributed' => $distributedCount]);

            DB::commit();

            return redirect()->back()->with('success', "Voucher berhasil didistribusikan ke {$distributedCount} member");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DistributeVoucher - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->back()->with('error', 'Gagal mendistribusikan voucher: ' . $e->getMessage());
        }
    }

    private function getMembersForDistribution($type, $memberIds = [], $filterCriteria = [])
    {
        $query = MemberAppsMember::query();

        if ($type === 'all') {
            // Get all active members
            $query->where('is_active', true);
        } elseif ($type === 'specific') {
            // Get specific members
            $query->whereIn('id', $memberIds);
        } elseif ($type === 'filter') {
            // Apply filters
            if (isset($filterCriteria['occupation_id']) && $filterCriteria['occupation_id'] !== '') {
                $query->where('pekerjaan_id', $filterCriteria['occupation_id']);
            }

            if (isset($filterCriteria['member_level']) && $filterCriteria['member_level'] !== '') {
                $query->where('member_level', $filterCriteria['member_level']);
            }

            if (isset($filterCriteria['is_active']) && $filterCriteria['is_active'] !== '') {
                $query->where('is_active', $filterCriteria['is_active']);
            }

            if (isset($filterCriteria['is_exclusive_member']) && $filterCriteria['is_exclusive_member'] !== '') {
                $query->where('is_exclusive_member', $filterCriteria['is_exclusive_member']);
            }

            if (isset($filterCriteria['min_spending']) && $filterCriteria['min_spending'] !== null) {
                $query->where('total_spending', '>=', $filterCriteria['min_spending']);
            }

            if (isset($filterCriteria['max_spending']) && $filterCriteria['max_spending'] !== null) {
                $query->where('total_spending', '<=', $filterCriteria['max_spending']);
            }

            if (isset($filterCriteria['min_points']) && $filterCriteria['min_points'] !== null) {
                $query->where('just_points', '>=', $filterCriteria['min_points']);
            }

            if (isset($filterCriteria['max_points']) && $filterCriteria['max_points'] !== null) {
                $query->where('just_points', '<=', $filterCriteria['max_points']);
            }

            if (isset($filterCriteria['registered_from']) && $filterCriteria['registered_from'] !== '') {
                $query->whereDate('created_at', '>=', $filterCriteria['registered_from']);
            }

            if (isset($filterCriteria['registered_until']) && $filterCriteria['registered_until'] !== '') {
                $query->whereDate('created_at', '<=', $filterCriteria['registered_until']);
            }

            if (isset($filterCriteria['jenis_kelamin']) && $filterCriteria['jenis_kelamin'] !== '') {
                $query->where('jenis_kelamin', $filterCriteria['jenis_kelamin']);
            }
        }

        return $query->get();
    }

    private function generateVoucherCode($voucherId, $memberId)
    {
        // Generate unique voucher code: VOUCHER_ID-MEMBER_ID-RANDOM
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        return "V{$voucherId}-M{$memberId}-{$random}";
    }

    private function generateVoucherSerialCode($voucherId, $memberId)
    {
        // Generate unique 8-character alphanumeric serial code
        // Characters: A-Z, 0-9 (36 possible characters)
        // Total possible combinations: 36^8 = 2,821,109,907,456 (very large, collision unlikely)
        
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 20; // Maximum attempts to generate unique code
        
        $attempts = 0;
        do {
            $serialCode = '';
            for ($i = 0; $i < 8; $i++) {
                $serialCode .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            // Check if serial code already exists
            $exists = MemberAppsMemberVoucher::where('serial_code', $serialCode)->exists();
            $attempts++;
            
            // If doesn't exist, we found a unique code
            if (!$exists) {
                return $serialCode;
            }
            
        } while ($attempts < $maxAttempts);
        
        // If we exhausted all attempts, throw exception
        throw new \Exception("Failed to generate unique serial code for voucher {$voucherId} and member {$memberId} after {$maxAttempts} attempts");
    }

    public function deleteVoucher($id)
    {
        $voucher = MemberAppsVoucher::findOrFail($id);
        $voucher->delete();

        return redirect()->back()->with('success', 'Voucher berhasil dihapus');
    }

    public function getVoucherMembers(Request $request, $id)
    {
        try {
            $voucher = MemberAppsVoucher::findOrFail($id);
            $search = $request->input('search', '');
            
            $query = MemberAppsMemberVoucher::where('voucher_id', $voucher->id)
                ->with(['member'])
                ->orderBy('created_at', 'desc');
            
            if ($search) {
                $query->whereHas('member', function($q) use ($search) {
                    $q->where('member_id', 'like', "%{$search}%")
                      ->orWhere('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('mobile_phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            $memberVouchers = $query->get();
            
            $members = $memberVouchers->map(function($memberVoucher) {
                $member = $memberVoucher->member;
                return [
                    'id' => $member->id ?? null,
                    'member_id' => $member->member_id ?? null,
                    'nama_lengkap' => $member->nama_lengkap ?? 'N/A',
                    'mobile_phone' => $member->mobile_phone ?? 'N/A',
                    'email' => $member->email ?? 'N/A',
                    'voucher_code' => $memberVoucher->voucher_code ?? null,
                    'serial_code' => $memberVoucher->serial_code ?? null,
                    'status' => $memberVoucher->status ?? 'active',
                    'created_at' => $memberVoucher->created_at ? $memberVoucher->created_at->format('Y-m-d H:i:s') : null,
                    'used_at' => $memberVoucher->used_at ? $memberVoucher->used_at->format('Y-m-d H:i:s') : null,
                    'expires_at' => $memberVoucher->expires_at ? $memberVoucher->expires_at->format('Y-m-d H:i:s') : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $members,
                'total' => $members->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('GetVoucherMembers - Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher members: ' . $e->getMessage()
            ], 500);
        }
    }

    // Push Notification Methods
    public function sendPushNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'notification_type' => 'required|in:general,promo,voucher,point,transaction,system',
            'target_type' => 'required|in:all,specific,filter',
            'target_member_ids' => 'required_if:target_type,specific|array',
            'target_member_ids.*' => 'exists:member_apps_members,id',
            'target_filter_criteria' => 'required_if:target_type,filter|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'action_url' => 'nullable|url|max:500',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('push-notifications', 'public');
            }

            // Create notification record
            $notification = MemberAppsPushNotification::create([
                'title' => $request->title,
                'message' => $request->message,
                'notification_type' => $request->notification_type,
                'target_type' => $request->target_type,
                'target_member_ids' => $request->target_member_ids,
                'target_filter_criteria' => $request->target_filter_criteria,
                'image_url' => $imagePath,
                'action_url' => $request->action_url,
                'data' => $request->data ?? [],
                'scheduled_at' => $request->scheduled_at,
                'created_by' => auth()->id()
            ]);

            // If not scheduled, send immediately
            if (!$request->scheduled_at) {
                $this->processPushNotification($notification);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Push notification berhasil dibuat' . ($request->scheduled_at ? ' dan dijadwalkan' : ' dan dikirim'));
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('SendPushNotification - Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return redirect()->back()->with('error', 'Gagal mengirim push notification: ' . $e->getMessage());
        }
    }

    private function processPushNotification($notification)
    {
        $fcmService = new FCMService();
        
        // Get target members
        $members = $this->getMembersForNotification($notification->target_type, $notification->target_member_ids ?? [], $notification->target_filter_criteria ?? []);
        
        $sentCount = 0;
        $deliveredCount = 0;
        
        foreach ($members as $member) {
            // Get active device tokens for this member
            $deviceTokens = MemberAppsDeviceToken::where('member_id', $member->id)
                ->where('is_active', true)
                ->get();

            if ($deviceTokens->isEmpty()) {
                continue;
            }

            foreach ($deviceTokens as $deviceToken) {
                // Prepare notification data
                $data = array_merge($notification->data ?? [], [
                    'notification_id' => $notification->id,
                    'type' => $notification->notification_type,
                    'action_url' => $notification->action_url
                ]);

                $imageUrl = $notification->image_url ? asset('storage/' . $notification->image_url) : null;

                // Send FCM
                $result = $fcmService->sendToDevice(
                    $deviceToken->device_token,
                    $notification->title,
                    $notification->message,
                    $data,
                    $imageUrl
                );

                // Create recipient record
                $recipient = MemberAppsPushNotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'member_id' => $member->id,
                    'device_token_id' => $deviceToken->id,
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'fcm_message_id' => $result['message_id'] ?? null,
                    'error_message' => $result['error'] ?? null
                ]);

                if ($result['success']) {
                    $sentCount++;
                    $deviceToken->update(['last_used_at' => now()]);
                }
            }
        }

        // Update notification stats
        $notification->update([
            'sent_count' => $sentCount,
            'sent_at' => now()
        ]);
    }

    private function getMembersForNotification($type, $memberIds = [], $filterCriteria = [])
    {
        $query = MemberAppsMember::query();

        if ($type === 'all') {
            $query->where('is_active', true);
        } elseif ($type === 'specific') {
            $query->whereIn('id', $memberIds);
        } elseif ($type === 'filter') {
            // Apply same filters as voucher distribution
            if (isset($filterCriteria['occupation_id']) && $filterCriteria['occupation_id'] !== '') {
                $query->where('pekerjaan_id', $filterCriteria['occupation_id']);
            }
            if (isset($filterCriteria['member_level']) && $filterCriteria['member_level'] !== '') {
                $query->where('member_level', $filterCriteria['member_level']);
            }
            if (isset($filterCriteria['is_active']) && $filterCriteria['is_active'] !== '') {
                $query->where('is_active', $filterCriteria['is_active']);
            }
            // Add more filters as needed
        }

        return $query->get();
    }

    /**
     * Reply to feedback
     */
    public function replyFeedback(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $feedback = MemberAppsFeedback::findOrFail($id);
            
            // Validate required fields
            if (!$feedback->member_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid feedback: member_id is missing'
                ], 400);
            }
            
            if (!$feedback->subject) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid feedback: subject is missing'
                ], 400);
            }
            
            $authUser = auth()->user();
            if (!$authUser || !$authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Please login again'
                ], 401);
            }
            
            // Ensure outlet_id is set (can be null)
            $outletId = $feedback->outlet_id ?? null;
            
            // Create reply as a new feedback entry
            $reply = MemberAppsFeedback::create([
                'parent_id' => $id,
                'member_id' => $feedback->member_id, // Keep original member_id
                'outlet_id' => $outletId,
                'subject' => 'Re: ' . $feedback->subject,
                'message' => trim($request->admin_reply),
                'status' => 'replied',
                'replied_by' => $authUser->id,
                'replied_at' => now(),
            ]);

            // Update parent feedback
            $feedback->admin_reply = trim($request->admin_reply);
            $feedback->replied_by = $authUser->id;
            $feedback->replied_at = now();
            $feedback->status = 'replied';
            $feedback->save();

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Reply Feedback Error: ' . $e->getMessage(), [
                'feedback_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update feedback status
     */
    public function updateFeedbackStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,read,replied,resolved',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $feedback = MemberAppsFeedback::findOrFail($id);
            $feedback->status = $request->status;
            $feedback->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Update Feedback Status Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
