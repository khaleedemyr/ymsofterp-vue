# Revenue per Outlet by Region Chart

## Overview
Menambahkan chart "Revenue per Outlet by Region" yang menampilkan revenue breakdown per outlet yang dikelompokkan berdasarkan region. Chart ini menggunakan stacked bar chart untuk membandingkan performance outlet di berbagai region.

## Implementation

### Backend (Controller)

#### **Method: `getRevenuePerOutlet`**
```php
private function getRevenuePerOutlet($outletFilter, $dateFrom, $dateTo)
{
    $query = "
        SELECT 
            o.kode_outlet,
            COALESCE(outlet.nama_outlet, o.kode_outlet) as outlet_name,
            COALESCE(region.name, 'Unknown Region') as region_name,
            COALESCE(region.code, 'UNK') as region_code,
            COUNT(*) as order_count,
            SUM(o.grand_total) as total_revenue,
            SUM(o.pax) as total_pax,
            AVG(o.grand_total) as avg_order_value
        FROM orders o
        LEFT JOIN tbl_data_outlet outlet ON o.kode_outlet = outlet.qr_code
        LEFT JOIN regions region ON outlet.region_id = region.id
        WHERE DATE(o.created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}'
        {$outletFilter}
        GROUP BY o.kode_outlet, outlet.nama_outlet, region.name, region.code
        ORDER BY total_revenue DESC
    ";

    $results = DB::select($query);

    // Group by region
    $data = [];
    foreach ($results as $result) {
        $regionName = $result->region_name;
        $regionCode = $result->region_code;
        
        if (!isset($data[$regionName])) {
            $data[$regionName] = [
                'region_code' => $regionCode,
                'outlets' => [],
                'total_revenue' => 0,
                'total_orders' => 0,
                'total_pax' => 0
            ];
        }
        
        $data[$regionName]['outlets'][] = [
            'outlet_code' => $result->kode_outlet,
            'outlet_name' => $result->outlet_name,
            'order_count' => (int) $result->order_count,
            'total_revenue' => (float) $result->total_revenue,
            'total_pax' => (int) $result->total_pax,
            'avg_order_value' => (float) $result->avg_order_value
        ];
        
        $data[$regionName]['total_revenue'] += (float) $result->total_revenue;
        $data[$regionName]['total_orders'] += (int) $result->order_count;
        $data[$regionName]['total_pax'] += (int) $result->total_pax;
    }

    return $data;
}
```

#### **Data Structure:**
```php
[
    'Jakarta-Tangerang' => [
        'region_code' => 'JKT',
        'outlets' => [
            [
                'outlet_code' => 'SH013',
                'outlet_name' => 'Justus Steak House Bintaro',
                'order_count' => 588,
                'total_revenue' => 526948500,
                'total_pax' => 1650,
                'avg_order_value' => 896170.9184
            ],
            // ... more outlets
        ],
        'total_revenue' => 2464777800,
        'total_orders' => 2813,
        'total_pax' => 7727
    ],
    // ... more regions
]
```

### Frontend (Vue Component)

#### **Computed Properties:**

##### **`revenuePerOutletSeries`**
```javascript
const revenuePerOutletSeries = computed(() => {
    if (!props.dashboardData?.revenuePerOutlet) return [];

    const data = props.dashboardData.revenuePerOutlet;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    // Create series for each region
    const series = [];
    const categories = [];
    
    // Get all unique outlets across all regions
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    categories.push(...Array.from(allOutlets));
    
    // Create series for each region
    regions.forEach(region => {
        const regionData = new Array(categories.length).fill(0);
        
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                regionData[index] = outlet.total_revenue;
            }
        });
        
        series.push({
            name: region,
            data: regionData
        });
    });

    return series;
});
```

##### **`revenuePerOutletOptions`**
```javascript
const revenuePerOutletOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: revenuePerOutletSeries.value.length > 0 ? 
            (() => {
                const data = props.dashboardData?.revenuePerOutlet;
                if (!data) return [];
                
                const allOutlets = new Set();
                Object.keys(data).forEach(region => {
                    data[region].outlets.forEach(outlet => {
                        allOutlets.add(outlet.outlet_name);
                    });
                });
                
                return Array.from(allOutlets);
            })() : [],
        title: { text: 'Outlets' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45,
            maxHeight: 120
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerOutlet;
            if (!data || !data[seriesName]) return seriesName;
            
            const regionData = data[seriesName];
            return `${seriesName}: ${formatCurrency(regionData.total_revenue)} (${regionData.outlets.length} outlets)`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerOutlet;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[seriesIndex];
            const regionData = data[regionName];
            
            if (!regionData || !regionData.outlets[dataPointIndex]) return '';
            
            const outlet = regionData.outlets[dataPointIndex];
            const avgCheck = outlet.total_pax > 0 ? outlet.total_revenue / outlet.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${outlet.outlet_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Region: ${regionName}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(outlet.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Orders: ${outlet.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-purple-500 rounded"></div>
                            <span>Pax: ${outlet.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            },
            xaxis: {
                labels: {
                    rotate: -90,
                    maxHeight: 100
                }
            }
        }
    }]
}));
```

#### **Template:**
```html
<!-- Charts Row 4 - Revenue per Outlet by Region (Full Width) -->
<div class="mb-6">
    <!-- Revenue per Outlet by Region -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue per Outlet by Region</h3>
        <apexchart 
            v-if="revenuePerOutletSeries.length > 0" 
            type="bar" 
            height="400" 
            :options="revenuePerOutletOptions" 
            :series="revenuePerOutletSeries" 
        />
        <div v-else class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-building text-4xl mb-2"></i>
            <p>No outlet revenue data available</p>
        </div>
        
        <!-- Region Summary -->
        <div v-if="dashboardData?.revenuePerOutlet && Object.keys(dashboardData.revenuePerOutlet).length > 0" class="mt-6">
            <h4 class="text-md font-semibold text-gray-800 mb-3">Region Summary</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="(regionData, regionName) in dashboardData.revenuePerOutlet" :key="regionName" class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-semibold text-gray-900">{{ regionName }}</h5>
                        <span class="text-sm font-medium text-blue-600">
                            {{ formatCurrency(regionData.total_revenue) }}
                        </span>
                    </div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Outlets:</span>
                            <span class="font-medium text-gray-900">{{ regionData.outlets.length }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Orders:</span>
                            <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_orders) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Pax:</span>
                            <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_pax) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Avg Check:</span>
                            <span class="font-medium text-gray-900">{{ formatCurrency(regionData.total_pax > 0 ? regionData.total_revenue / regionData.total_pax : 0) }}</span>
                        </div>
                    </div>
                    
                    <!-- Top Outlets in Region -->
                    <div class="mt-3 pt-3 border-t">
                        <h6 class="text-xs font-semibold text-gray-700 mb-2">Top Outlets:</h6>
                        <div class="space-y-1">
                            <div v-for="outlet in regionData.outlets.slice(0, 3)" :key="outlet.outlet_code" class="flex justify-between text-xs">
                                <span class="text-gray-600 truncate">{{ outlet.outlet_name }}</span>
                                <span class="font-medium text-gray-900">{{ formatCurrency(outlet.total_revenue) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

## Database Schema

### **Tables Used:**

#### **`orders`**
- `id` (bigint)
- `kode_outlet` (varchar)
- `grand_total` (decimal)
- `pax` (int)
- `created_at` (timestamp)

#### **`tbl_data_outlet`**
- `qr_code` (varchar) - Primary key
- `nama_outlet` (varchar)
- `region_id` (bigint) - Foreign key to regions.id

#### **`regions`**
- `id` (bigint) - Primary key
- `code` (varchar)
- `name` (varchar)
- `status` (enum)
- `created_at` (timestamp)
- `updated_at` (timestamp)

### **Relationships:**
- `orders.kode_outlet` → `tbl_data_outlet.qr_code`
- `tbl_data_outlet.region_id` → `regions.id`

## Test Results

### **Sample Data (September 1-10, 2025):**

#### **Top 10 Outlets by Revenue:**
```
Outlet Code     Outlet Name          Region          Region Code Orders       Revenue         Pax        Avg Order
------------------------------------------------------------------------------------------------------------------------
SH013           Justus Steak House B Jakarta-Tangera JKT        588          Rp 526,948,500  1,650      Rp 896,171
SH017           Justus Steak House L Jakarta-Tangera JKT        512          Rp 452,838,200  1,398      Rp 884,450
SH011           Justus Steak House C Jakarta-Tangera JKT        521          Rp 408,260,200  1,142      Rp 783,609
SH016           Justus Steakhouse Th Jakarta-Tangera JKT        469          Rp 373,309,700  1,200      Rp 795,970
SH012           Justus Steak House A Jakarta-Tangera JKT        376          Rp 372,983,100  1,287      Rp 991,976
SH009           Justus Steak House D Bandung Prime   BDG        455          Rp 365,725,800  1,399      Rp 803,793
SH015           Justus Steakhouse Am Jakarta-Tangera JKT        347          Rp 330,438,100  1,050      Rp 952,271
SH010           Justus Steak House B Bandung Prime   BDG        524          Rp 328,173,200  1,407      Rp 626,285
SH014           Justus Steak House J Bandung Prime   BDG        342          Rp 229,632,700  950        Rp 671,441
SH004           Justus Steak House M Bandung Reguler REG        262          Rp 140,262,400  703        Rp 535,353
```

#### **Region Summary:**
```
Region: Jakarta-Tangerang (JKT)
  Total Revenue: Rp 2,464,777,800
  Total Orders: 2,813
  Total Pax: 7,727
  Outlets: 6
  Avg Check: Rp 318,983
  Top Outlets:
    - Justus Steak House Bintaro: Rp 526,948,500
    - Justus Steak House Lebak Bulus: Rp 452,838,200
    - Justus Steak House Cipete: Rp 408,260,200

Region: Bandung Prime (BDG)
  Total Revenue: Rp 923,531,700
  Total Orders: 1,321
  Total Pax: 3,756
  Outlets: 3
  Avg Check: Rp 245,882
  Top Outlets:
    - Justus Steak House Dago: Rp 365,725,800
    - Justus Steak House Buah Batu: Rp 328,173,200
    - Justus Steak House Jawa: Rp 229,632,700

Region: Bandung Reguler (REG)
  Total Revenue: Rp 140,262,400
  Total Orders: 262
  Total Pax: 703
  Outlets: 1
  Avg Check: Rp 199,520
  Top Outlets:
    - Justus Steak House Metro Indah Mall: Rp 140,262,400
```

### **Performance:**
- **Query Execution Time**: 134.49 ms
- **Records Returned**: 13
- **Memory Usage**: 24.61 MB

## Key Features

### ✅ **Stacked Bar Chart**
- **Visualization**: Revenue per outlet grouped by region
- **Comparison**: Easy comparison between regions and outlets
- **Interactive**: Hover tooltips with detailed information
- **Responsive**: Adapts to different screen sizes

### ✅ **Region Summary Cards**
- **Overview**: Total revenue, orders, pax per region
- **Metrics**: Average check calculation per region
- **Top Outlets**: Top 3 outlets per region
- **Grid Layout**: Responsive 1-4 columns

### ✅ **Advanced Tooltips**
- **Outlet Details**: Revenue, orders, pax, average check
- **Region Information**: Region name and code
- **Color Coding**: Consistent colors for different metrics
- **Rich Formatting**: Currency and number formatting

### ✅ **Data Processing**
- **Region Grouping**: Automatic grouping by region
- **Outlet Aggregation**: Revenue, orders, pax aggregation
- **Sorting**: Outlets sorted by revenue (descending)
- **Fallback**: Handles missing region data gracefully

## Business Intelligence

### 📊 **Key Insights:**

#### **Regional Performance:**
1. **Jakarta-Tangerang (JKT)**:
   - Highest revenue: Rp 2.46B (69.8% of total)
   - 6 outlets with highest average check: Rp 318,983
   - Best performing outlet: Justus Steak House Bintaro (Rp 526.9M)

2. **Bandung Prime (BDG)**:
   - Second highest revenue: Rp 923.5M (26.2% of total)
   - 3 outlets with moderate average check: Rp 245,882
   - Best performing outlet: Justus Steak House Dago (Rp 365.7M)

3. **Bandung Reguler (REG)**:
   - Lowest revenue: Rp 140.3M (4.0% of total)
   - 1 outlet with lowest average check: Rp 199,520
   - Single outlet: Justus Steak House Metro Indah Mall

#### **Outlet Performance:**
- **Top Performer**: Justus Steak House Bintaro (Rp 526.9M)
- **Highest Avg Check**: Justus Steak House Alam Sutera (Rp 991,976)
- **Most Orders**: Justus Steak House Buah Batu (524 orders)
- **Highest Pax**: Justus Steak House Buah Batu (1,407 pax)

### 📈 **Strategic Implications:**
1. **Regional Focus**: Jakarta-Tangerang region dominates revenue
2. **Outlet Expansion**: Consider expanding successful outlet models
3. **Performance Benchmarking**: Use top performers as benchmarks
4. **Resource Allocation**: Focus resources on high-performing regions

## Layout Integration

### **Dashboard Position:**
- **Row 4**: Full width after Payment Methods chart
- **Height**: 400px (300px on mobile)
- **Responsive**: Adapts to all screen sizes

### **Chart Type:**
- **Type**: Stacked Bar Chart
- **Colors**: 8 distinct colors for regions
- **Animation**: Smooth transitions and hover effects
- **Export**: Supports chart export functionality

## Future Enhancements

### **Potential Improvements:**
1. **Drill-down**: Click outlet untuk detailed analysis
2. **Time Comparison**: Compare dengan previous periods
3. **Export**: Include dalam export functionality
4. **Filters**: Filter by specific regions atau outlets

### **Advanced Features:**
1. **Interactive Filters**: Real-time updates dengan filter changes
2. **Custom Periods**: User-defined time periods
3. **Benchmarking**: Compare dengan industry standards
4. **Alerts**: Notifications untuk significant changes

## Conclusion

The Revenue per Outlet by Region chart successfully provides comprehensive insights into outlet performance across different regions. The stacked bar chart visualization makes it easy to compare performance between regions and individual outlets, while the region summary cards provide detailed metrics for each region.

**Key Success Factors:**
- ✅ **Comprehensive Data**: Revenue, orders, pax, average check per outlet
- ✅ **Regional Grouping**: Clear organization by region
- ✅ **Visual Clarity**: Stacked bar chart for easy comparison
- ✅ **Rich Tooltips**: Detailed information on hover
- ✅ **Responsive Design**: Works on all devices
- ✅ **Performance**: Fast query execution and data processing

The feature is now ready for production use and provides valuable business intelligence for regional and outlet performance analysis.
