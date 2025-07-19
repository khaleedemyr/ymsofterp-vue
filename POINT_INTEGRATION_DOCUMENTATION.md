# Point Integration Documentation

## Overview
Integrasi tabel `point` ke dashboard CRM untuk menampilkan statistik dan analisis transaksi point member.

## Struktur Tabel Point

### Schema
```sql
CREATE TABLE point (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_bill VARCHAR(50),           -- Bill number untuk top up
    no_bill_2 VARCHAR(50),         -- Bill number untuk redeem
    costumer_id INT,               -- Foreign key ke tabel costumers
    cabang_id INT,                 -- Foreign key ke tabel cabangs
    point INT,                     -- Jumlah point
    jml_trans INT,                 -- Nilai transaksi
    type VARCHAR(1),               -- 1=Top Up, 2=Redeem
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Relationships
- `costumer_id` → `costumers.id` (Customer)
- `cabang_id` → `cabangs.id` (Cabang)

## Model Point

### Features
- **Connection**: `mysql_second`
- **Relationships**: Customer, Cabang
- **Scopes**: TopUp, Redeem, ByDateRange, ByCabang
- **Accessors**: TypeText, BillNumber, PointFormatted, JmlTransFormatted, CreatedAtText, Status, Icon, Color

### Key Methods
```php
// Scopes
Point::topUp()           // Filter transaksi top up
Point::redeem()          // Filter transaksi redeem
Point::byDateRange()     // Filter berdasarkan tanggal
Point::byCabang()        // Filter berdasarkan cabang
Point::excludeResetPoint() // Exclude cabang "Reset Point"

// Accessors
$point->type_text        // "Top Up" atau "Redeem"
$point->bill_number      // Bill number yang sesuai
$point->point_formatted  // Point dengan format angka
$point->jml_trans_formatted // Nilai transaksi dengan format Rupiah
$point->created_at_text  // Tanggal dengan format d/m/Y H:i
$point->status           // "success" atau "warning"
$point->icon             // Icon FontAwesome
$point->color            // Warna CSS class
```

## Model Cabang

### Features
- **Connection**: `mysql_second`
- **Table**: `cabangs`
- **Relationships**: Points (hasMany)
- **Scopes**: Active

### Key Methods
```php
// Relationships
$cabang->points          // Semua transaksi point di cabang ini

// Scopes
Cabang::active()         // Filter cabang aktif
```

## Dashboard Integration

### 1. **Point Statistics (KPI Cards)**
- **Total Transaksi**: Jumlah keseluruhan transaksi point
- **Transaksi Hari Ini**: Transaksi point hari ini
- **Transaksi Bulan Ini**: Transaksi point bulan ini dengan growth rate
- **Total Point Diperoleh**: Total point dari top up
- **Total Point Ditukar**: Total point dari redeem
- **Total Nilai Transaksi**: Total nilai transaksi dalam format Rp ###,###
- **Date Filter**: Filter tanggal untuk semua statistik point

### 2. **Latest Point Transactions**
- 10 transaksi point terbaru
- Informasi member, cabang, point, nilai transaksi
- Badge untuk jenis transaksi (Top Up/Redeem)
- Icon dan warna yang berbeda untuk setiap jenis

### 3. **Member Activity Feed**
- **Combined Activities**: Menggabungkan registrasi, top up, dan redeem
- **Real-time Feed**: 10 aktivitas terbaru berdasarkan timestamp
- **Activity Types**: 
  - Member baru mendaftar (Green)
  - Top up point (Blue)
  - Redeem point (Orange)
- **Detailed Information**: 
  - Nama member dan member ID
  - Tanggal dan jam transaksi
  - Jenis aktivitas dengan badge
  - Untuk top up/redeem: cabang, point, nilai transaksi
- **Activity Summary**: Statistik jumlah per jenis aktivitas

### 3. **Point Distribution by Cabang**
- Distribusi transaksi point per cabang
- Ranking berdasarkan jumlah transaksi
- Total point dan nilai transaksi per cabang
- **Data redeem** untuk setiap cabang (jumlah redeem, point redeem, nilai redeem)
- **Filter tanggal** (from-to date) untuk memilih rentang transaksi
- **Exclude cabang "Reset Point"** dari data yang ditampilkan

## Data Sources

### 1. **Point Statistics**
```php
// Total transactions
$totalTransactions = Point::count();

// Transactions today
$transactionsToday = Point::whereDate('created_at', $today)->count();

// Transactions this month
$transactionsThisMonth = Point::whereBetween('created_at', [$thisMonth, Carbon::now()])->count();

// Total point earned (top up)
$totalPointEarned = Point::topUp()->sum('point');

// Total point redeemed
$totalPointRedeemed = Point::redeem()->sum('point');

// Total transaction value
$totalTransactionValue = Point::sum('jml_trans');
```

### 2. **Latest Transactions**
```php
Point::with(['customer', 'cabang'])
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get()
    ->map(function ($point) {
        return [
            'id' => $point->id,
            'bill_number' => $point->bill_number,
            'customer_name' => $point->customer->name ?? 'Member Tidak Diketahui',
            'customer_id' => $point->customer->costumers_id ?? '-',
            'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
            'point' => $point->point,
            'point_formatted' => $point->point_formatted,
            'jml_trans' => $point->jml_trans,
            'jml_trans_formatted' => $point->jml_trans_formatted,
            'type' => $point->type,
            'type_text' => $point->type_text,
            'created_at' => $point->created_at_text,
            'status' => $point->status,
            'icon' => $point->icon,
            'color' => $point->color,
        ];
    });
```

### 3. **Member Activity Feed**
```php
// Get recent member registrations (30 days, limit 5)
$recentRegistrations = Customer::where('tanggal_register', '>=', Carbon::now()->subDays(30))
    ->orderBy('tanggal_register', 'desc')
    ->limit(5)
    ->get()
    ->map(function ($member) {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'activity' => 'Member baru mendaftar',
            'icon' => 'fa-solid fa-user-plus',
            'color' => 'text-green-600',
            'bg_color' => 'bg-green-100',
            'created_at' => $member->tanggal_register,
            'type' => 'registration',
            'member_id' => $member->costumers_id,
        ];
    });

// Get recent top up transactions (limit 5)
$recentTopUps = Point::with(['customer', 'cabang'])
    ->topUp()
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get()
    ->map(function ($point) {
        return [
            'id' => $point->id,
            'name' => $point->customer->name ?? 'Member Tidak Diketahui',
            'activity' => 'Top up ' . $point->point_formatted . ' point',
            'sub_activity' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
            'icon' => 'fa-solid fa-plus-circle',
            'color' => 'text-blue-600',
            'bg_color' => 'bg-blue-100',
            'created_at' => $point->created_at,
            'type' => 'topup',
            'point' => $point->point,
            'point_formatted' => $point->point_formatted,
            'jml_trans' => $point->jml_trans,
            'jml_trans_formatted' => $point->jml_trans_formatted,
            'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
            'member_id' => $point->customer->costumers_id ?? '-',
        ];
    });

// Get recent redeem transactions (limit 5)
$recentRedeems = Point::with(['customer', 'cabang'])
    ->redeem()
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get()
    ->map(function ($point) {
        return [
            'id' => $point->id,
            'name' => $point->customer->name ?? 'Member Tidak Diketahui',
            'activity' => 'Redeem ' . $point->point_formatted . ' point',
            'sub_activity' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
            'icon' => 'fa-solid fa-minus-circle',
            'color' => 'text-orange-600',
            'bg_color' => 'bg-orange-100',
            'created_at' => $point->created_at,
            'type' => 'redeem',
            'point' => $point->point,
            'point_formatted' => $point->point_formatted,
            'jml_trans' => $point->jml_trans,
            'jml_trans_formatted' => $point->jml_trans_formatted,
            'cabang_name' => $point->cabang->name ?? 'Cabang Tidak Diketahui',
            'member_id' => $point->customer->costumers_id ?? '-',
        ];
    });

// Combine and sort all activities (limit 10)
return $activities->sortByDesc('created_at')
    ->take(10)
    ->map(function ($activity) {
        return [
            'id' => $activity['id'],
            'name' => $activity['name'],
            'activity' => $activity['activity'],
            'sub_activity' => $activity['sub_activity'] ?? null,
            'icon' => $activity['icon'],
            'color' => $activity['color'],
            'bg_color' => $activity['bg_color'],
            'created_at' => $activity['created_at']->format('d/m/Y H:i'),
            'type' => $activity['type'],
            'point' => $activity['point'] ?? null,
            'point_formatted' => $activity['point_formatted'] ?? null,
            'jml_trans' => $activity['jml_trans'] ?? null,
            'jml_trans_formatted' => $activity['jml_trans_formatted'] ?? null,
            'cabang_name' => $activity['cabang_name'] ?? null,
            'member_id' => $activity['member_id'] ?? null,
        ];
    });
```

### 4. **Point by Cabang**
```php
Point::with('cabang')
    ->excludeResetPoint()
    ->selectRaw('cabang_id, COUNT(*) as total_transactions, SUM(point) as total_points, SUM(jml_trans) as total_value')
    ->groupBy('cabang_id');

// Filter by date range if provided
if ($startDate && $endDate) {
    $query->whereBetween('created_at', [$startDate, $endDate]);
}

$results = $query->orderBy('total_transactions', 'desc')
    ->limit(10)
    ->get();

return $results->map(function ($item) use ($startDate, $endDate) {
    // Get redeem data for this cabang
    $redeemQuery = Point::where('cabang_id', $item->cabang_id)
        ->redeem();

    // Apply same date filter if provided
    if ($startDate && $endDate) {
        $redeemQuery->whereBetween('created_at', [$startDate, $endDate]);
    }

    $redeemData = $redeemQuery->selectRaw('COUNT(*) as total_redeem, SUM(point) as total_redeem_points, SUM(jml_trans) as total_redeem_value')
        ->first();

    return [
        'cabang_name' => $item->cabang->name ?? 'Cabang Tidak Diketahui',
        'total_transactions' => $item->total_transactions,
        'total_points' => $item->total_points,
        'total_points_formatted' => number_format($item->total_points, 0, ',', '.'),
        'total_value' => $item->total_value,
        'total_value_formatted' => 'Rp ' . number_format($item->total_value, 0, ',', '.'),
        'total_redeem' => $redeemData->total_redeem ?? 0,
        'total_redeem_points' => $redeemData->total_redeem_points ?? 0,
        'total_redeem_points_formatted' => number_format($redeemData->total_redeem_points ?? 0, 0, ',', '.'),
        'total_redeem_value' => $redeemData->total_redeem_value ?? 0,
        'total_redeem_value_formatted' => 'Rp ' . number_format($redeemData->total_redeem_value ?? 0, 0, ',', '.'),
    ];
});
```

### 4. **Redeem Data Structure**
```php
// Data yang ditampilkan untuk setiap cabang:
[
    'cabang_name' => 'JUSTUS STEAK HOUSE BINTARO',
    'total_transactions' => 98,
    'total_points' => 4628750,
    'total_points_formatted' => '4,628,750',
    'total_value' => 106497500,
    'total_value_formatted' => 'Rp 106,497,500',
    
    // Data Redeem
    'total_redeem' => 15,                    // Jumlah transaksi redeem
    'total_redeem_points' => 750000,         // Total point yang di-redeem
    'total_redeem_points_formatted' => '750,000',
    'total_redeem_value' => 15000000,        // Nilai transaksi redeem
    'total_redeem_value_formatted' => 'Rp 15,000,000',
]
```

### 5. **Member Activity Feed Structure**
```php
// Activity data structure
[
    'id' => 1,
    'name' => 'John Doe',
    'activity' => 'Top up 1,000 point',
    'sub_activity' => 'JUSTUS STEAK HOUSE BINTARO',
    'icon' => 'fa-solid fa-plus-circle',
    'color' => 'text-blue-600',
    'bg_color' => 'bg-blue-100',
    'created_at' => '15/01/2024 14:30',
    'type' => 'topup',
    'point' => 1000,
    'point_formatted' => '1,000',
    'jml_trans' => 500000,
    'jml_trans_formatted' => 'Rp 500,000',
    'cabang_name' => 'JUSTUS STEAK HOUSE BINTARO',
    'member_id' => 'U12345',
]
```

## UI Components

### 1. **Point Statistics Cards**
- 6 KPI cards dengan warna berbeda
- Icon yang meaningful untuk setiap statistik
- Growth rate indicator untuk transaksi bulanan
- Format angka dan mata uang Indonesia

### 2. **Latest Point Transactions**
- Card layout dengan hover effects
- Icon dan badge untuk jenis transaksi
- Informasi lengkap member dan cabang
- Timestamp transaksi

### 3. **Member Activity Feed**
- **Activity Cards**: Layout card untuk setiap aktivitas
- **Color-coded Types**: Warna berbeda untuk setiap jenis aktivitas
- **Activity Badges**: Badge untuk membedakan jenis aktivitas
- **Member Info**: Nama member dan ID
- **Detailed Transaction Info**: 
  - Cabang untuk top up/redeem
  - Jumlah point dengan icon
  - Nilai transaksi dengan format currency
  - Tanggal dan jam transaksi
- **Activity Summary**: 4 cards untuk statistik aktivitas

### 3. **Point Distribution by Cabang**
- Ranking list dengan nomor urut
- Informasi cabang, transaksi, point, dan nilai
- **Data redeem** dengan warna orange untuk membedakan dari data utama
- Color-coded ranking
- **Date filter** dengan input tanggal from-to
- **Filter dan Reset button** untuk mengatur rentang tanggal
- **Filter status indicator** yang menampilkan rentang tanggal aktif

## API Endpoints

### New Endpoints
- `GET /api/crm/chart-data?type=point` - Data statistik point
- `GET /api/crm/chart-data?type=pointByCabang&start_date=2024-01-01&end_date=2024-01-31` - Data distribusi point per cabang dengan filter tanggal

## Database Queries

### Performance Optimizations
```php
// Use eager loading untuk relationships
Point::with(['customer', 'cabang'])

// Use specific columns untuk aggregation
Point::selectRaw('cabang_id, COUNT(*) as total_transactions, SUM(point) as total_points, SUM(jml_trans) as total_value')

// Use indexes pada kolom yang sering di-query
// - created_at (untuk filter tanggal)
// - type (untuk filter top up/redeem)
// - cabang_id (untuk filter cabang)
// - costumer_id (untuk relationship)
```

## Error Handling

### 1. **Missing Relationships**
```php
// Handle missing customer
$customerName = $point->customer->name ?? 'Member Tidak Diketahui';

// Handle missing cabang
$cabangName = $point->cabang->name ?? 'Cabang Tidak Diketahui';
```

### 2. **Data Validation**
```php
// Validate point value
if ($point->point < 0) {
    // Handle negative point
}

// Validate transaction value
if ($point->jml_trans <= 0) {
    // Handle invalid transaction value
}
```

## Redeem Details Modal

### 1. **Modal Features**
- **Responsive Design**: Modal yang responsive untuk berbagai ukuran layar
- **Loading State**: Indikator loading saat memuat data
- **Summary Statistics**: Total redeem, point, dan nilai dalam cards
- **Detailed List**: Daftar lengkap transaksi redeem dengan informasi member
- **Date Integration**: Menggunakan filter tanggal yang sama dengan dashboard
- **Scrollable Content**: Area scroll yang terbatas dengan custom scrollbar
- **Data Limit**: Maksimal 50 transaksi terbaru untuk performa optimal
- **Performance Optimized**: Layout flexbox untuk handling data besar

### 2. **Modal Data Structure**
```javascript
// Modal props
{
  isOpen: Boolean,
  cabangId: Number,
  cabangName: String,
  startDate: String,
  endDate: String,
}

// Redeem detail data
{
  id: Number,
  customer_name: String,
  customer_id: String,
  point: Number,
  point_formatted: String,
  jml_trans: Number,
  jml_trans_formatted: String,
  bill_number: String,
  created_at: String,        // Format: d/m/Y H:i
  created_at_full: String,   // Format: d/m/Y H:i:s
}

// Summary data
{
  total_redeem: Number,
  total_points: Number,
  total_value: Number,
}
```

### 3. **Modal UI Components**
- **Header**: Judul modal dengan nama cabang dan tombol close
- **Summary Cards**: 3 cards untuk total redeem, point, dan nilai
- **Redeem List**: Daftar transaksi dengan informasi lengkap dan scroll area
- **Empty State**: Pesan ketika tidak ada data redeem
- **Footer**: Tombol close modal
- **Custom Scrollbar**: Scrollbar dengan warna orange yang konsisten dengan tema
- **Data Limit Notice**: Informasi ketika data mencapai limit 50 transaksi

### 4. **Modal Integration**
```javascript
// Dashboard integration
const redeemModal = ref({
  isOpen: false,
  cabangId: null,
  cabangName: '',
});

function openRedeemModal(cabangId, cabangName) {
  redeemModal.value = {
    isOpen: true,
    cabangId: cabangId,
    cabangName: cabangName,
  };
}

function closeRedeemModal() {
  redeemModal.value.isOpen = false;
}
```

## Future Enhancements

### 1. **Additional Analytics**
- Point earning rate per member
- Average transaction value
- Point redemption rate
- Member lifetime value

### 2. **Advanced Features**
- Point balance per member
- Point expiration tracking
- Point tier system
- Point multiplier events

### 3. **Reporting**
- Point transaction reports
- Member point history
- Cabang performance reports
- Point trend analysis

## Testing

### 1. **Unit Tests**
```php
// Test point statistics
public function test_get_point_stats()
{
    $point = Point::factory()->create(['type' => '1', 'point' => 100]);
    $stats = $this->get('/crm/dashboard')->json();
    $this->assertEquals(1, $stats['pointStats']['totalTransactions']);
    $this->assertEquals(100, $stats['pointStats']['totalPointEarned']);
}
```

### 2. **Feature Tests**
```php
// Test point transactions
public function test_get_latest_point_transactions()
{
    $point = Point::factory()->create();
    $response = $this->get('/api/crm/chart-data?type=point');
    $this->assertStatus(200);
    $this->assertIsArray($response->json());
}
```

## Monitoring

### 1. **Metrics to Track**
- Point transaction volume
- Average point per transaction
- Point redemption rate
- Cabang performance

### 2. **Alerts**
- Unusual point activity
- High-value transactions
- Failed point redemptions
- Cabang performance drops

---

**Status**: ✅ Integrated  
**Version**: 1.0.0  
**Last Updated**: January 2024  
**Dependencies**: Customer Model, Cabang Model 