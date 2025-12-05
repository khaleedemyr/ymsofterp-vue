# Lunch/Dinner Orders Chart Feature

## Overview
Menambahkan chart "Orders by Lunch or Dinner" ke Sales Outlet Dashboard dengan menggunakan logika yang sama seperti di menu monthly fb revenue performance. Chart ini menampilkan distribusi order berdasarkan periode makan siang dan makan malam.

## Business Logic

### Time Classification:
- **Lunch**: `HOUR(created_at) <= 17` (jam 17:00 ke bawah)
- **Dinner**: `HOUR(created_at) > 17` (jam 17:00 ke atas)

### Data Points:
- **Order Count**: Jumlah order per periode
- **Total Revenue**: Total pendapatan per periode
- **Total Pax**: Total jumlah pelanggan per periode
- **Average Order Value**: Rata-rata nilai order per periode

## Changes Made

### 1. Backend Changes

#### File: `app/Http/Controllers/SalesOutletDashboardController.php`

**Added Method: `getLunchDinnerOrders()`**

```php
private function getLunchDinnerOrders($outletFilter, $dateFrom, $dateTo)
{
    $query = "
        SELECT 
            CASE 
                WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END as period,
            COUNT(*) as order_count,
            SUM(grand_total) as total_revenue,
            SUM(pax) as total_pax,
            AVG(grand_total) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
        GROUP BY 
            CASE 
                WHEN HOUR(created_at) <= 17 THEN 'Lunch'
                ELSE 'Dinner'
            END
        ORDER BY period
    ";

    $results = DB::select($query);
    
    // Initialize with default values
    $data = [
        'lunch' => [
            'order_count' => 0,
            'total_revenue' => 0,
            'total_pax' => 0,
            'avg_order_value' => 0
        ],
        'dinner' => [
            'order_count' => 0,
            'total_revenue' => 0,
            'total_pax' => 0,
            'avg_order_value' => 0
        ]
    ];
    
    // Fill in actual data
    foreach ($results as $result) {
        $period = strtolower($result->period);
        $data[$period] = [
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
    }
    
    return $data;
}
```

**Key Features:**
- ✅ **Time-based categorization** using `HOUR(created_at) <= 17`
- ✅ **Comprehensive metrics** (orders, revenue, pax, avg order value)
- ✅ **Default values** to handle missing data gracefully
- ✅ **Consistent data structure** for frontend consumption

### 2. Frontend Changes

#### File: `resources/js/Pages/SalesOutletDashboard/Index.vue`

**Added Computed Properties:**

```javascript
const lunchDinnerSeries = computed(() => {
    if (!props.dashboardData?.lunchDinnerOrders) return [];
    
    const data = props.dashboardData.lunchDinnerOrders;
    return [
        data.lunch?.order_count || 0,
        data.dinner?.order_count || 0
    ];
});

const lunchDinnerOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    labels: ['Lunch (≤17:00)', 'Dinner (>17:00)'],
    colors: ['#10b981', '#f59e0b'],
    legend: {
        position: 'bottom',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.lunchDinnerOrders;
            const period = seriesName.includes('Lunch') ? 'lunch' : 'dinner';
            const count = data?.[period]?.order_count || 0;
            const revenue = data?.[period]?.total_revenue || 0;
            return `${seriesName}: ${count.toLocaleString()} orders (${formatCurrency(revenue)})`;
        }
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Orders',
                        fontSize: '16px',
                        fontWeight: 600,
                        color: '#374151',
                        formatter: function (w) {
                            const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            return total.toLocaleString();
                        }
                    },
                    value: {
                        show: true,
                        fontSize: '20px',
                        fontWeight: 700,
                        color: '#1f2937',
                        formatter: function (val) {
                            return val.toLocaleString();
                        }
                    }
                }
            }
        }
    },
    tooltip: {
        custom: function({series, seriesIndex, w}) {
            const data = props.dashboardData?.lunchDinnerOrders;
            const period = seriesIndex === 0 ? 'lunch' : 'dinner';
            const periodData = data?.[period];
            
            if (periodData) {
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${seriesIndex === 0 ? 'Lunch (≤17:00)' : 'Dinner (>17:00)'}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div>Orders: ${periodData.order_count.toLocaleString()}</div>
                            <div>Revenue: ${formatCurrency(periodData.total_revenue)}</div>
                            <div>Pax: ${periodData.total_pax.toLocaleString()}</div>
                            <div>Avg Order: ${formatCurrency(periodData.avg_order_value)}</div>
                        </div>
                    </div>
                `;
            }
            return '';
        }
    },
    dataLabels: {
        enabled: false
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: {
                width: 200
            },
            legend: {
                position: 'bottom',
                fontSize: '10px'
            }
        }
    }]
}));
```

**Added Chart Template:**

```html
<!-- Lunch/Dinner Orders -->
<div class="bg-white rounded-lg shadow-sm border p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Orders by Lunch or Dinner</h3>
    <apexchart 
        v-if="lunchDinnerSeries.length > 0 && lunchDinnerSeries.some(val => val > 0)" 
        type="donut" 
        height="350" 
        :options="lunchDinnerOptions" 
        :series="lunchDinnerSeries" 
    />
    <div v-else class="text-center py-8 text-gray-500">
        <i class="fa-solid fa-utensils text-4xl mb-2"></i>
        <p>No lunch/dinner data available</p>
    </div>
    
    <!-- Lunch/Dinner Details -->
    <div v-if="dashboardData?.lunchDinnerOrders" class="mt-6">
        <h4 class="text-md font-semibold text-gray-800 mb-3">Period Details</h4>
        <div class="space-y-3">
            <!-- Lunch Details -->
            <div class="border rounded-lg p-4 bg-green-50">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-semibold text-gray-900 flex items-center">
                        <i class="fa-solid fa-sun text-green-600 mr-2"></i>
                        Lunch (≤17:00)
                    </h5>
                    <span class="text-sm font-medium text-green-600">
                        {{ formatCurrency(dashboardData.lunchDinnerOrders.lunch?.total_revenue || 0) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Orders:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.lunch?.order_count || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pax:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.lunch?.total_pax || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Avg Order:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.lunchDinnerOrders.lunch?.avg_order_value || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- Dinner Details -->
            <div class="border rounded-lg p-4 bg-orange-50">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-semibold text-gray-900 flex items-center">
                        <i class="fa-solid fa-moon text-orange-600 mr-2"></i>
                        Dinner (>17:00)
                    </h5>
                    <span class="text-sm font-medium text-orange-600">
                        {{ formatCurrency(dashboardData.lunchDinnerOrders.dinner?.total_revenue || 0) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Orders:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.dinner?.order_count || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pax:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.dinner?.total_pax || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Avg Order:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.lunchDinnerOrders.dinner?.avg_order_value || 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

## Test Results

### Sample Data (September 1-10, 2025):
```
Period     Orders       Revenue         Pax        Avg Order
--------------------------------------------------------------------------------
Dinner     1,881        Rp 1,509,235,000 5,299      Rp 802,358
Lunch      2,762        Rp 2,145,724,000 7,492      Rp 776,873
--------------------------------------------------------------------------------
TOTAL      4,643        Rp 3,654,959,000 12,791     Rp 787,198
```

### Hourly Distribution:
```
Hour   Period     Orders       Revenue
------------------------------------------------------------
9:00   Lunch      4            Rp 9,219,200
10:00  Lunch      32           Rp 38,486,900
11:00  Lunch      367          Rp 289,528,900
12:00  Lunch      604          Rp 486,411,900
13:00  Lunch      483          Rp 386,330,900
14:00  Lunch      348          Rp 254,306,100
15:00  Lunch      268          Rp 176,171,900
16:00  Lunch      259          Rp 197,122,700
17:00  Lunch      397          Rp 308,145,500
18:00  Dinner     615          Rp 480,780,600
19:00  Dinner     750          Rp 625,704,700
20:00  Dinner     404          Rp 324,439,500
21:00  Dinner     107          Rp 67,769,100
22:00  Dinner     4            Rp 2,988,500
23:00  Dinner     1            Rp 7,552,600
```

### Percentage Distribution:
- **Lunch**: 59.5% (2,762 orders)
- **Dinner**: 40.5% (1,881 orders)

## Chart Features

### ✅ **Visual Design**
- **Donut Chart**: Clean, modern donut chart with 70% size
- **Color Scheme**: Green (#10b981) for Lunch, Orange (#f59e0b) for Dinner
- **Icons**: Sun icon for Lunch, Moon icon for Dinner
- **Responsive**: Adapts to different screen sizes

### ✅ **Interactive Elements**
- **Custom Tooltip**: Shows detailed metrics on hover
- **Legend**: Displays order count and revenue for each period
- **Center Label**: Shows total orders in the center
- **Hover Effects**: Smooth animations and transitions

### ✅ **Data Display**
- **Chart**: Visual representation of order distribution
- **Details Section**: Comprehensive metrics below the chart
- **Color-coded Cards**: Green for Lunch, Orange for Dinner
- **Formatted Numbers**: Proper currency and number formatting

## Layout Changes

### Charts Row 2:
- **Before**: Single column (Payment Methods only)
- **After**: Two columns (Payment Methods + Lunch/Dinner)

```html
<!-- Before -->
<div class="grid grid-cols-1 gap-4 mb-6">

<!-- After -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
```

## Business Insights

### 📊 **Key Findings:**
1. **Lunch Dominance**: 59.5% of orders occur during lunch hours (≤17:00)
2. **Higher Dinner AOV**: Dinner orders have higher average order value (Rp 802,358 vs Rp 776,873)
3. **Peak Hours**: 
   - Lunch peak: 12:00-13:00 (1,087 orders)
   - Dinner peak: 18:00-19:00 (1,365 orders)
4. **Revenue Distribution**: Lunch generates 58.7% of total revenue

### 📈 **Strategic Implications:**
1. **Staffing**: More staff needed during lunch hours
2. **Menu Planning**: Optimize lunch vs dinner offerings
3. **Marketing**: Target promotions based on period performance
4. **Operations**: Adjust opening hours and service focus

## Technical Implementation

### Database Query:
```sql
SELECT 
    CASE 
        WHEN HOUR(created_at) <= 17 THEN 'Lunch'
        ELSE 'Dinner'
    END as period,
    COUNT(*) as order_count,
    SUM(grand_total) as total_revenue,
    SUM(pax) as total_pax,
    AVG(grand_total) as avg_order_value
FROM orders 
WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
{$outletFilter}
GROUP BY 
    CASE 
        WHEN HOUR(created_at) <= 17 THEN 'Lunch'
        ELSE 'Dinner'
    END
ORDER BY period
```

### Frontend Data Structure:
```javascript
{
    "lunch": {
        "order_count": 2762,
        "total_revenue": 2145724000,
        "total_pax": 7492,
        "avg_order_value": 776873.2802
    },
    "dinner": {
        "order_count": 1881,
        "total_revenue": 1509235000,
        "total_pax": 5299,
        "avg_order_value": 802357.7884
    }
}
```

## Benefits

### 🎯 **Business Intelligence**
- **Period Analysis**: Clear understanding of lunch vs dinner performance
- **Revenue Optimization**: Identify which period generates more revenue
- **Operational Planning**: Better staffing and resource allocation
- **Menu Strategy**: Optimize offerings based on period preferences

### 🎯 **User Experience**
- **Visual Clarity**: Easy-to-understand donut chart
- **Detailed Metrics**: Comprehensive information in details section
- **Interactive Elements**: Hover tooltips and responsive design
- **Consistent Design**: Matches existing dashboard aesthetics

### 🎯 **Data Accuracy**
- **Consistent Logic**: Same time classification as monthly fb revenue performance
- **Real-time Data**: Updates based on selected date range and outlet filter
- **Comprehensive Metrics**: Orders, revenue, pax, and average order value
- **Error Handling**: Graceful fallbacks for missing data

## Future Enhancements

### Potential Improvements:
1. **Time Range Customization**: Allow users to set custom lunch/dinner hours
2. **Day-of-Week Analysis**: Compare lunch/dinner performance by day
3. **Seasonal Trends**: Track lunch/dinner patterns over time
4. **Outlet Comparison**: Compare lunch/dinner performance across outlets
5. **Export Functionality**: Include lunch/dinner data in exports

### Advanced Features:
1. **Predictive Analytics**: Forecast lunch/dinner demand
2. **Menu Recommendations**: Suggest items based on period performance
3. **Staff Scheduling**: Automated staffing based on historical patterns
4. **Promotion Targeting**: Time-based promotional campaigns

## Conclusion

The Lunch/Dinner Orders Chart successfully enhances the Sales Outlet Dashboard by providing valuable insights into order distribution patterns. The implementation follows the same business logic as the existing monthly fb revenue performance feature, ensuring consistency across the system.

**Key Success Factors:**
- ✅ **Consistent Business Logic**: Same time classification as existing features
- ✅ **Comprehensive Metrics**: Orders, revenue, pax, and average order value
- ✅ **Visual Excellence**: Modern donut chart with detailed information
- ✅ **User-Friendly Design**: Interactive elements and responsive layout
- ✅ **Data Accuracy**: Real-time data with proper error handling

The feature is now ready for production use and provides valuable business insights for better decision making and operational planning.
