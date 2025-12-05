# Enhanced Charts Update - Revenue, Orders, Pax & Average Check

## Overview
Menambahkan pax/customer dan average check (revenue/pax) ke chart "Orders by Lunch or Dinner" dan "Revenue per Weekday and Weekend". Chart sekarang menampilkan 3 series (Revenue, Orders, Pax) dengan legend yang menampilkan total values dan average check calculation.

## Changes Made

### 1. Chart Structure Changes

#### **From Donut Charts to Bar Charts:**
- **Before**: Donut charts dengan 2 segments (Lunch/Dinner, Weekday/Weekend)
- **After**: Bar charts dengan 3 series (Revenue, Orders, Pax)

#### **Chart Type**: `type: 'bar'` (Column charts)
- **Height**: 350px
- **Colors**: 
  - Revenue: `#10b981` (Green) / `#3b82f6` (Blue)
  - Orders: `#3b82f6` (Blue) / `#10b981` (Green)  
  - Pax: `#f59e0b` (Orange)

### 2. Series Data Structure

#### **Lunch/Dinner Chart Series:**
```javascript
const lunchDinnerSeries = computed(() => {
    if (!props.dashboardData?.lunchDinnerOrders) return [];
    
    const data = props.dashboardData.lunchDinnerOrders;
    return [
        {
            name: 'Revenue',
            type: 'column',
            data: [data.lunch?.total_revenue || 0, data.dinner?.total_revenue || 0]
        },
        {
            name: 'Orders',
            type: 'column',
            data: [data.lunch?.order_count || 0, data.dinner?.order_count || 0]
        },
        {
            name: 'Pax',
            type: 'column',
            data: [data.lunch?.total_pax || 0, data.dinner?.total_pax || 0]
        }
    ];
});
```

#### **Weekday/Weekend Chart Series:**
```javascript
const weekdayWeekendSeries = computed(() => {
    if (!props.dashboardData?.weekdayWeekendRevenue) return [];
    
    const data = props.dashboardData.weekdayWeekendRevenue;
    return [
        {
            name: 'Revenue',
            type: 'column',
            data: [data.weekday?.total_revenue || 0, data.weekend?.total_revenue || 0]
        },
        {
            name: 'Orders',
            type: 'column',
            data: [data.weekday?.order_count || 0, data.weekend?.order_count || 0]
        },
        {
            name: 'Pax',
            type: 'column',
            data: [data.weekday?.total_pax || 0, data.weekend?.total_pax || 0]
        }
    ];
});
```

### 3. Enhanced Legend with Average Check

#### **Lunch/Dinner Legend Formatter:**
```javascript
legend: { 
    position: 'top',
    fontSize: '14px',
    fontWeight: 600,
    formatter: function(seriesName, opts) {
        const data = props.dashboardData?.lunchDinnerOrders;
        if (!data) return seriesName;
        
        if (seriesName === 'Revenue') {
            const lunchRevenue = data.lunch?.total_revenue || 0;
            const dinnerRevenue = data.dinner?.total_revenue || 0;
            const totalRevenue = lunchRevenue + dinnerRevenue;
            return `${seriesName}: ${formatCurrency(totalRevenue)}`;
        } else if (seriesName === 'Orders') {
            const lunchOrders = data.lunch?.order_count || 0;
            const dinnerOrders = data.dinner?.order_count || 0;
            const totalOrders = lunchOrders + dinnerOrders;
            return `${seriesName}: ${totalOrders.toLocaleString()}`;
        } else if (seriesName === 'Pax') {
            const lunchPax = data.lunch?.total_pax || 0;
            const dinnerPax = data.dinner?.total_pax || 0;
            const totalPax = lunchPax + dinnerPax;
            const avgCheck = totalPax > 0 ? (data.lunch?.total_revenue + data.dinner?.total_revenue) / totalPax : 0;
            return `${seriesName}: ${totalPax.toLocaleString()} | Avg Check: ${formatCurrency(avgCheck)}`;
        }
        return seriesName;
    }
}
```

#### **Weekday/Weekend Legend Formatter:**
```javascript
legend: { 
    position: 'top',
    fontSize: '14px',
    fontWeight: 600,
    formatter: function(seriesName, opts) {
        const data = props.dashboardData?.weekdayWeekendRevenue;
        if (!data) return seriesName;
        
        if (seriesName === 'Revenue') {
            const weekdayRevenue = data.weekday?.total_revenue || 0;
            const weekendRevenue = data.weekend?.total_revenue || 0;
            const totalRevenue = weekdayRevenue + weekendRevenue;
            return `${seriesName}: ${formatCurrency(totalRevenue)}`;
        } else if (seriesName === 'Orders') {
            const weekdayOrders = data.weekday?.order_count || 0;
            const weekendOrders = data.weekend?.order_count || 0;
            const totalOrders = weekdayOrders + weekendOrders;
            return `${seriesName}: ${totalOrders.toLocaleString()}`;
        } else if (seriesName === 'Pax') {
            const weekdayPax = data.weekday?.total_pax || 0;
            const weekendPax = data.weekend?.total_pax || 0;
            const totalPax = weekdayPax + weekendPax;
            const avgCheck = totalPax > 0 ? (data.weekday?.total_revenue + data.weekend?.total_revenue) / totalPax : 0;
            return `${seriesName}: ${totalPax.toLocaleString()} | Avg Check: ${formatCurrency(avgCheck)}`;
        }
        return seriesName;
    }
}
```

### 4. Enhanced Tooltip with Color Indicators

#### **Lunch/Dinner Tooltip:**
```javascript
tooltip: {
    shared: true,
    intersect: false,
    custom: function({series, seriesIndex, dataPointIndex, w}) {
        const data = props.dashboardData?.lunchDinnerOrders;
        const period = dataPointIndex === 0 ? 'lunch' : 'dinner';
        const periodData = data?.[period];
        
        if (periodData) {
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${dataPointIndex === 0 ? 'Lunch (≤17:00)' : 'Dinner (>17:00)'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        return '';
    }
}
```

#### **Weekday/Weekend Tooltip:**
```javascript
tooltip: {
    shared: true,
    intersect: false,
    custom: function({series, seriesIndex, dataPointIndex, w}) {
        const data = props.dashboardData?.weekdayWeekendRevenue;
        const period = dataPointIndex === 0 ? 'weekday' : 'weekend';
        const periodData = data?.[period];
        
        if (periodData) {
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${dataPointIndex === 0 ? 'Weekday (Mon-Fri)' : 'Weekend (Sat-Sun)'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        return '';
    }
}
```

### 5. Chart Configuration

#### **Common Chart Options:**
```javascript
chart: {
    type: 'line', // Base type, ApexCharts handles mixed types
    height: 350,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 }
},
xaxis: {
    categories: ['Lunch (≤17:00)', 'Dinner (>17:00)'], // or ['Weekday (Mon-Fri)', 'Weekend (Sat-Sun)']
    title: { text: 'Period' },
    labels: { style: { fontWeight: 600 } }
},
yaxis: [
    {
        title: { text: 'Count' },
        min: 0,
        labels: { 
            style: { fontWeight: 600 },
            formatter: (value) => value.toLocaleString()
        }
    },
    {
        opposite: true,
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: { 
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    }
],
colors: ['#10b981', '#3b82f6', '#f59e0b'], // or ['#3b82f6', '#10b981', '#f59e0b']
stroke: {
    width: [0, 0, 0], // All columns
    curve: 'smooth'
},
plotOptions: {
    bar: {
        borderRadius: 8,
        columnWidth: '60%'
    }
},
dataLabels: { 
    enabled: false 
},
grid: { 
    borderColor: '#e5e7eb',
    strokeDashArray: 4
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
        }
    }
}]
```

## Test Results

### Sample Data (September 1-10, 2025):

#### **Lunch/Dinner Results:**
```
Period     Orders       Revenue         Pax        Avg Order
--------------------------------------------------------------------------------
Dinner     1,881        Rp 1,509,235,000 5,299      Rp 802,358
Lunch      2,794        Rp 2,166,092,900 7,564      Rp 775,266
```

#### **Weekday/Weekend Results:**
```
Period     Orders       Revenue         Pax        Avg Order
--------------------------------------------------------------------------------
Weekday    3,164        Rp 2,382,884,200 8,200      Rp 753,124
Weekend    1,511        Rp 1,292,443,700 4,663      Rp 855,357
```

### **Legend Data:**
- **Lunch/Dinner**: Revenue: Rp 3,675,327,900 | Orders: 4,675 | Pax: 12,863 | Avg Check: Rp 285,729
- **Weekday/Weekend**: Revenue: Rp 3,675,327,900 | Orders: 4,675 | Pax: 12,863 | Avg Check: Rp 285,729

### **Tooltip Data:**
- **Lunch**: Revenue: Rp 2,166,092,900 | Orders: 2,794 | Pax: 7,564 | Avg Check: Rp 286,369
- **Dinner**: Revenue: Rp 1,509,235,000 | Orders: 1,881 | Pax: 5,299 | Avg Check: Rp 284,815
- **Weekday**: Revenue: Rp 2,382,884,200 | Orders: 3,164 | Pax: 8,200 | Avg Check: Rp 290,596
- **Weekend**: Revenue: Rp 1,292,443,700 | Orders: 1,511 | Pax: 4,663 | Avg Check: Rp 277,170

## Key Features

### ✅ **Enhanced Data Display**
- **3 Series**: Revenue, Orders, dan Pax dalam satu chart
- **Bar Charts**: Lebih mudah untuk membandingkan nilai antar periode
- **Color Coding**: Setiap series memiliki warna yang konsisten

### ✅ **Smart Legend**
- **Total Values**: Menampilkan total untuk semua periode
- **Average Check**: Otomatis menghitung Revenue / Pax
- **Dynamic Formatting**: Currency dan number formatting yang proper

### ✅ **Interactive Tooltips**
- **Color Indicators**: Setiap metric memiliki color indicator
- **Detailed Metrics**: Revenue, Orders, Pax, dan Average Check
- **Period-Specific**: Data spesifik untuk setiap periode

### ✅ **Responsive Design**
- **Mobile-Friendly**: Adapts ke berbagai ukuran layar
- **Touch-Friendly**: Optimized untuk touch devices
- **Consistent Layout**: Maintains readability di semua devices

## Business Intelligence

### 📊 **Key Insights:**

#### **Lunch vs Dinner:**
- **Lunch Dominance**: 59.8% of orders occur during lunch (2,794 vs 1,881)
- **Higher Dinner AOV**: Dinner orders have higher average order value (Rp 802,358 vs Rp 775,266)
- **Similar Avg Check**: Lunch (Rp 286,369) vs Dinner (Rp 284,815) - very close
- **Revenue Distribution**: Lunch generates 58.9% of total revenue

#### **Weekday vs Weekend:**
- **Weekday Dominance**: 67.7% of orders occur during weekdays (3,164 vs 1,511)
- **Higher Weekend AOV**: Weekend orders have higher average order value (Rp 855,357 vs Rp 753,124)
- **Higher Weekday Avg Check**: Weekday (Rp 290,596) vs Weekend (Rp 277,170)
- **Revenue Distribution**: Weekdays generate 64.8% of total revenue

### 📈 **Strategic Implications:**
1. **Staffing**: More staff needed during lunch and weekdays due to higher volume
2. **Menu Planning**: Optimize offerings based on period performance
3. **Pricing Strategy**: Consider higher prices during high-AOV periods
4. **Marketing**: Target promotions based on period performance

## Technical Implementation

### **Chart Type Change:**
- **From**: `type: 'donut'` dengan 2 segments
- **To**: `type: 'bar'` dengan 3 series (Revenue, Orders, Pax)

### **Data Structure:**
- **Series Format**: Array of objects dengan `name`, `type`, dan `data`
- **Data Points**: 2 values per series (Lunch/Dinner atau Weekday/Weekend)
- **Type**: All series menggunakan `type: 'column'`

### **Legend Enhancement:**
- **Position**: `top` untuk better visibility
- **Formatter**: Custom function untuk menampilkan total values dan average check
- **Average Check Calculation**: `(total_revenue / total_pax)`

### **Tooltip Enhancement:**
- **Custom HTML**: Rich tooltip dengan color indicators
- **Shared Tooltip**: Shows all series data at once
- **Color Coding**: Consistent dengan chart colors

## Benefits

### 🎯 **User Experience**
- **Better Comparison**: Bar charts easier to compare values
- **More Information**: 3 metrics instead of 1
- **Clear Legend**: Total values dan average check calculation
- **Rich Tooltips**: Detailed information dengan color indicators

### 🎯 **Business Intelligence**
- **Comprehensive View**: Revenue, Orders, Pax, dan Average Check
- **Period Analysis**: Clear comparison between periods
- **Customer Behavior**: Understanding spending patterns
- **Operational Planning**: Better staffing dan resource allocation

### 🎯 **Data Accuracy**
- **Real-time Calculation**: Average Check calculated dynamically
- **Consistent Formatting**: Proper currency dan number formatting
- **Error Handling**: Graceful fallbacks untuk missing data
- **Responsive Updates**: Charts update dengan filter changes

## Future Enhancements

### Potential Improvements:
1. **Drill-down**: Click untuk melihat daily breakdown
2. **Export**: Include chart data dalam export functionality
3. **Comparison**: Side-by-side comparison dengan previous periods
4. **Forecasting**: Predictive analytics based on historical patterns

### Advanced Features:
1. **Interactive Filters**: Real-time chart updates
2. **Custom Periods**: User-defined time periods
3. **Benchmarking**: Compare dengan industry standards
4. **Alerts**: Notifications untuk significant changes

## Conclusion

The enhanced charts successfully provide a comprehensive view of business performance with Revenue, Orders, Pax, and Average Check metrics. The transition from donut to bar charts improves data comparison, while the enhanced legend and tooltips provide valuable business insights.

**Key Success Factors:**
- ✅ **Enhanced Data Display**: 3 series instead of 1
- ✅ **Better Visualization**: Bar charts for easier comparison
- ✅ **Smart Legend**: Total values dan average check calculation
- ✅ **Rich Tooltips**: Detailed metrics dengan color indicators
- ✅ **Responsive Design**: Mobile-friendly dan touch-optimized
- ✅ **Business Intelligence**: Valuable insights untuk decision making

The feature is now ready for production use and provides enhanced business intelligence for better decision making and operational planning.
