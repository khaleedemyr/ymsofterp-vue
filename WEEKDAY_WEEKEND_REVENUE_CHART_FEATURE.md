# Weekday/Weekend Revenue Chart Feature

## Overview
Menambahkan chart "Revenue per Weekday and Weekend" ke Sales Outlet Dashboard dengan menggunakan logika yang sama seperti di menu monthly fb revenue performance. Chart ini menampilkan distribusi revenue berdasarkan hari kerja dan akhir pekan.

## Business Logic

### Day Classification:
- **Weekend**: `DAYOFWEEK(created_at) IN (1, 7)` (1=Minggu, 7=Sabtu)
- **Weekday**: Hari lainnya (Senin-Jumat)

### Data Points:
- **Order Count**: Jumlah order per periode
- **Total Revenue**: Total pendapatan per periode
- **Total Pax**: Total jumlah pelanggan per periode
- **Average Order Value**: Rata-rata nilai order per periode

## Changes Made

### 1. Backend Changes

#### File: `app/Http/Controllers/SalesOutletDashboardController.php`

**Added Method: `getWeekdayWeekendRevenue()`**

```php
private function getWeekdayWeekendRevenue($outletFilter, $dateFrom, $dateTo)
{
    $query = "
        SELECT 
            CASE 
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
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
                WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
                ELSE 'Weekday'
            END
        ORDER BY period
    ";

    $results = DB::select($query);
    
    // Initialize with default values
    $data = [
        'weekday' => [
            'order_count' => 0,
            'total_revenue' => 0,
            'total_pax' => 0,
            'avg_order_value' => 0
        ],
        'weekend' => [
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
- ✅ **Day-based categorization** using `DAYOFWEEK(created_at) IN (1, 7)`
- ✅ **Comprehensive metrics** (orders, revenue, pax, avg order value)
- ✅ **Default values** to handle missing data gracefully
- ✅ **Consistent data structure** for frontend consumption

### 2. Frontend Changes

#### File: `resources/js/Pages/SalesOutletDashboard/Index.vue`

**Added Computed Properties:**

```javascript
const weekdayWeekendSeries = computed(() => {
    if (!props.dashboardData?.weekdayWeekendRevenue) return [];
    
    const data = props.dashboardData.weekdayWeekendRevenue;
    return [
        data.weekday?.order_count || 0,
        data.weekend?.order_count || 0
    ];
});

const weekdayWeekendOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    labels: ['Weekday (Mon-Fri)', 'Weekend (Sat-Sun)'],
    colors: ['#3b82f6', '#f59e0b'],
    legend: {
        position: 'bottom',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.weekdayWeekendRevenue;
            const period = seriesName.includes('Weekday') ? 'weekday' : 'weekend';
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
            const data = props.dashboardData?.weekdayWeekendRevenue;
            const period = seriesIndex === 0 ? 'weekday' : 'weekend';
            const periodData = data?.[period];
            
            if (periodData) {
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${seriesIndex === 0 ? 'Weekday (Mon-Fri)' : 'Weekend (Sat-Sun)'}</div>
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
<!-- Weekday/Weekend Revenue -->
<div class="bg-white rounded-lg shadow-sm border p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue per Weekday and Weekend</h3>
    <apexchart 
        v-if="weekdayWeekendSeries.length > 0 && weekdayWeekendSeries.some(val => val > 0)" 
        type="donut" 
        height="350" 
        :options="weekdayWeekendOptions" 
        :series="weekdayWeekendSeries" 
    />
    <div v-else class="text-center py-8 text-gray-500">
        <i class="fa-solid fa-calendar-week text-4xl mb-2"></i>
        <p>No weekday/weekend data available</p>
    </div>
    
    <!-- Weekday/Weekend Details -->
    <div v-if="dashboardData?.weekdayWeekendRevenue" class="mt-6">
        <h4 class="text-md font-semibold text-gray-800 mb-3">Period Details</h4>
        <div class="space-y-3">
            <!-- Weekday Details -->
            <div class="border rounded-lg p-4 bg-blue-50">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-semibold text-gray-900 flex items-center">
                        <i class="fa-solid fa-briefcase text-blue-600 mr-2"></i>
                        Weekday (Mon-Fri)
                    </h5>
                    <span class="text-sm font-medium text-blue-600">
                        {{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekday?.total_revenue || 0) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Orders:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekday?.order_count || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pax:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekday?.total_pax || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Avg Order:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekday?.avg_order_value || 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- Weekend Details -->
            <div class="border rounded-lg p-4 bg-orange-50">
                <div class="flex items-center justify-between mb-2">
                    <h5 class="font-semibold text-gray-900 flex items-center">
                        <i class="fa-solid fa-calendar-weekend text-orange-600 mr-2"></i>
                        Weekend (Sat-Sun)
                    </h5>
                    <span class="text-sm font-medium text-orange-600">
                        {{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekend?.total_revenue || 0) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Orders:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekend?.order_count || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Pax:</span>
                        <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekend?.total_pax || 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Avg Order:</span>
                        <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekend?.avg_order_value || 0) }}</span>
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
Weekday    3,140        Rp 2,369,747,500 8,151      Rp 754,697
Weekend    1,511        Rp 1,292,443,700 4,663      Rp 855,357
--------------------------------------------------------------------------------
TOTAL      4,651        Rp 3,662,191,200 12,814     Rp 787,399
```

### Day of Week Distribution:
```
Day        Period       Orders     Revenue      Avg Revenue
----------------------------------------------------------------------
Sunday     Weekend    711          Rp 614,131,900  Rp 863,758
Monday     Weekday    646          Rp 448,110,000  Rp 693,669
Tuesday    Weekday    745          Rp 514,320,900  Rp 690,364
Wednesday  Weekday    549          Rp 403,922,900  Rp 735,743
Thursday   Weekday    441          Rp 342,758,100  Rp 777,229
Friday     Weekday    759          Rp 660,635,600  Rp 870,403
Saturday   Weekend    800          Rp 678,311,800  Rp 847,890
```

### Key Insights:
- **Weekday Dominance**: 67.5% of orders occur during weekdays
- **Higher Weekend AOV**: Weekend orders have higher average order value (Rp 855,357 vs Rp 754,697)
- **Revenue Distribution**: Weekdays generate 64.7% of total revenue
- **Peak Days**: Saturday (800 orders) and Friday (759 orders) are the busiest days

## Chart Features

### ✅ **Visual Design**
- **Donut Chart**: Clean, modern donut chart with 70% size
- **Color Scheme**: Blue (#3b82f6) for Weekday, Orange (#f59e0b) for Weekend
- **Icons**: Briefcase icon for Weekday, Calendar-weekend icon for Weekend
- **Responsive**: Adapts to different screen sizes

### ✅ **Interactive Elements**
- **Custom Tooltip**: Shows detailed metrics on hover
- **Legend**: Displays order count and revenue for each period
- **Center Label**: Shows total orders in the center
- **Hover Effects**: Smooth animations and transitions

### ✅ **Data Display**
- **Chart**: Visual representation of order distribution
- **Details Section**: Comprehensive metrics below the chart
- **Color-coded Cards**: Blue for Weekday, Orange for Weekend
- **Formatted Numbers**: Proper currency and number formatting

## Layout Changes

### Charts Row 3:
- **New Row**: Added third row of charts
- **Two Columns**: Weekday/Weekend Revenue + Placeholder for future chart

```html
<!-- Charts Row 3 -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
    <!-- Weekday/Weekend Revenue -->
    <!-- Placeholder for future chart -->
</div>
```

## Business Intelligence

### 📊 **Key Findings:**
1. **Weekday Dominance**: 67.5% of orders occur during weekdays (Mon-Fri)
2. **Higher Weekend AOV**: Weekend orders have 13.3% higher average order value
3. **Revenue Distribution**: Weekdays generate 64.7% of total revenue
4. **Peak Performance**: Saturday (800 orders) and Friday (759 orders) are busiest

### 📈 **Strategic Implications:**
1. **Staffing**: More staff needed during weekdays due to higher volume
2. **Menu Planning**: Optimize weekday vs weekend offerings
3. **Marketing**: Target promotions based on period performance
4. **Operations**: Adjust service focus based on AOV differences

### 🎯 **Performance Analysis:**
- **Order Volume**: Weekdays dominate with 67.5% of total orders
- **Revenue Efficiency**: Weekends generate higher revenue per order
- **Customer Behavior**: Weekend customers spend more per visit
- **Operational Planning**: Different strategies needed for each period

## Technical Implementation

### Database Query:
```sql
SELECT 
    CASE 
        WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
        ELSE 'Weekday'
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
        WHEN DAYOFWEEK(created_at) IN (1, 7) THEN 'Weekend'
        ELSE 'Weekday'
    END
ORDER BY period
```

### Frontend Data Structure:
```javascript
{
    "weekday": {
        "order_count": 3140,
        "total_revenue": 2369747500,
        "total_pax": 8151,
        "avg_order_value": 754696.6561
    },
    "weekend": {
        "order_count": 1511,
        "total_revenue": 1292443700,
        "total_pax": 4663,
        "avg_order_value": 855356.5189
    }
}
```

## Benefits

### 🎯 **Business Intelligence**
- **Period Analysis**: Clear understanding of weekday vs weekend performance
- **Revenue Optimization**: Identify which period generates more revenue per order
- **Operational Planning**: Better staffing and resource allocation
- **Customer Behavior**: Understand spending patterns by day type

### 🎯 **User Experience**
- **Visual Clarity**: Easy-to-understand donut chart
- **Detailed Metrics**: Comprehensive information in details section
- **Interactive Elements**: Hover tooltips and responsive design
- **Consistent Design**: Matches existing dashboard aesthetics

### 🎯 **Data Accuracy**
- **Consistent Logic**: Same day classification as monthly fb revenue performance
- **Real-time Data**: Updates based on selected date range and outlet filter
- **Comprehensive Metrics**: Orders, revenue, pax, and average order value
- **Error Handling**: Graceful fallbacks for missing data

## Future Enhancements

### Potential Improvements:
1. **Day-by-Day Analysis**: Show performance for each day of the week
2. **Seasonal Trends**: Track weekday/weekend patterns over time
3. **Outlet Comparison**: Compare weekday/weekend performance across outlets
4. **Export Functionality**: Include weekday/weekend data in exports

### Advanced Features:
1. **Predictive Analytics**: Forecast weekday/weekend demand
2. **Menu Recommendations**: Suggest items based on period performance
3. **Staff Scheduling**: Automated staffing based on historical patterns
4. **Promotion Targeting**: Day-type based promotional campaigns

## Conclusion

The Weekday/Weekend Revenue Chart successfully enhances the Sales Outlet Dashboard by providing valuable insights into revenue distribution patterns. The implementation follows the same business logic as the existing monthly fb revenue performance feature, ensuring consistency across the system.

**Key Success Factors:**
- ✅ **Consistent Business Logic**: Same day classification as existing features
- ✅ **Comprehensive Metrics**: Orders, revenue, pax, and average order value
- ✅ **Visual Excellence**: Modern donut chart with detailed information
- ✅ **User-Friendly Design**: Interactive elements and responsive layout
- ✅ **Data Accuracy**: Real-time data with proper error handling

The feature is now ready for production use and provides valuable business insights for better decision making and operational planning.
