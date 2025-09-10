# Hourly Sales Chart with Revenue Update

## Overview
Memodifikasi chart "Orders by Hour" untuk menampilkan both orders dan revenue menggunakan dual Y-axis approach. Chart sekarang menampilkan orders sebagai bar chart dan revenue sebagai line chart dengan tooltip yang comprehensive.

## Changes Made

### 1. Frontend Changes

#### File: `resources/js/Pages/SalesOutletDashboard/Index.vue`

**Modified `hourlySalesSeries` Computed Property:**

```javascript
const hourlySalesSeries = computed(() => {
    if (!props.dashboardData?.hourlySales) return [];
    
    return [
        {
            name: 'Orders',
            type: 'column',
            data: props.dashboardData.hourlySales.map(item => item.orders)
        },
        {
            name: 'Revenue',
            type: 'line',
            data: props.dashboardData.hourlySales.map(item => item.revenue)
        }
    ];
});
```

**Modified `hourlySalesOptions` Computed Property:**

```javascript
const hourlySalesOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: props.dashboardData?.hourlySales?.map(item => `${item.hour}:00`) || [],
        title: { text: 'Hour' },
        labels: { style: { fontWeight: 600 } }
    },
    yaxis: [
        {
            title: { text: 'Number of Orders' },
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
    colors: ['#6366F1', '#10b981'],
    stroke: {
        width: [0, 3],
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
    legend: { 
        position: 'top',
        fontSize: '14px',
        fontWeight: 600
    },
    grid: { 
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.hourlySales?.[dataPointIndex];
            if (data) {
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${data.hour}:00</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-indigo-500 rounded"></div>
                                <span>Orders: ${data.orders.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Revenue: ${formatCurrency(data.revenue)}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-400 rounded"></div>
                                <span>Avg Order: ${formatCurrency(data.avg_order_value)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            return '';
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
            }
        }
    }]
}));
```

## Chart Features

### ✅ **Dual Y-Axis Display**
- **Left Y-Axis**: Number of Orders (Blue bars)
- **Right Y-Axis**: Revenue in Rupiah (Green line)
- **Independent Scaling**: Each axis scales independently for optimal visualization

### ✅ **Mixed Chart Types**
- **Orders**: Column/Bar chart (Blue #6366F1)
- **Revenue**: Line chart (Green #10b981)
- **Smooth Curve**: Revenue line uses smooth curve for better trend visualization

### ✅ **Enhanced Tooltip**
- **Shared Tooltip**: Shows both orders and revenue on hover
- **Custom Formatting**: Professional styling with color indicators
- **Complete Metrics**: Orders, Revenue, and Average Order Value
- **Time Display**: Shows specific hour (e.g., "19:00")

### ✅ **Visual Improvements**
- **Legend**: Top position with clear labels
- **Grid**: Subtle grid lines with dashed style
- **Animations**: Smooth transitions and hover effects
- **Responsive**: Adapts to different screen sizes

## Test Results

### Sample Data (September 1-10, 2025):
```
Hour   Orders   Revenue         Avg Order
--------------------------------------------------------------------------------
9:00   4        Rp 9,219,200    Rp 2,304,800
10:00  32       Rp 38,486,900   Rp 1,202,716
11:00  367      Rp 289,528,900  Rp 788,907
12:00  604      Rp 486,411,900  Rp 805,318
13:00  483      Rp 386,330,900  Rp 799,857
14:00  348      Rp 254,306,100  Rp 730,765
15:00  269      Rp 177,898,100  Rp 661,331
16:00  259      Rp 197,122,700  Rp 761,092
17:00  397      Rp 308,145,500  Rp 776,185
18:00  615      Rp 480,780,600  Rp 781,757
19:00  750      Rp 625,704,700  Rp 834,273
20:00  404      Rp 324,439,500  Rp 803,068
21:00  107      Rp 67,769,100   Rp 633,356
22:00  4        Rp 2,988,500    Rp 747,125
23:00  1        Rp 7,552,600    Rp 7,552,600
--------------------------------------------------------------------------------
TOTAL  4,644    Rp 3,656,685,200 Rp 787,400
```

### Key Insights:
- **Peak Orders Hour**: 19:00 (750 orders)
- **Peak Revenue Hour**: 19:00 (Rp 625,704,700)
- **Lunch vs Dinner**: 59.5% orders during lunch (≤17:00), 40.5% during dinner (>17:00)
- **Revenue Correlation**: Peak revenue coincides with peak orders

## Chart Configuration

### Technical Specifications:
```javascript
{
    chart: {
        type: 'line',           // Mixed chart type
        height: 350,            // Fixed height
        toolbar: { show: true }, // Show toolbar
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    yaxis: [
        {
            title: { text: 'Number of Orders' },
            min: 0,
            labels: { formatter: (value) => value.toLocaleString() }
        },
        {
            opposite: true,     // Right side
            title: { text: 'Revenue (Rp)' },
            min: 0,
            labels: { formatter: (value) => formatNumber(value) }
        }
    ],
    colors: ['#6366F1', '#10b981'], // Blue for orders, Green for revenue
    stroke: {
        width: [0, 3],         // No stroke for bars, 3px for line
        curve: 'smooth'        // Smooth curve for revenue line
    }
}
```

## Business Intelligence

### 📊 **Enhanced Analytics:**
1. **Order Volume Trends**: Clear visualization of order patterns throughout the day
2. **Revenue Patterns**: Revenue trends that may differ from order volume
3. **Peak Performance**: Identify both order and revenue peak hours
4. **Efficiency Analysis**: Compare order volume vs revenue generation

### 📈 **Strategic Insights:**
1. **Staffing Optimization**: Align staff schedules with peak hours
2. **Revenue Maximization**: Focus on high-revenue hours
3. **Menu Strategy**: Optimize offerings for peak performance periods
4. **Operational Planning**: Better resource allocation based on dual metrics

### 🎯 **Key Findings:**
- **Peak Hours**: 19:00 is both peak orders (750) and peak revenue (Rp 625M)
- **Lunch Dominance**: 59.5% of orders occur during lunch hours
- **Revenue Correlation**: Strong correlation between order volume and revenue
- **Evening Performance**: Dinner hours show higher average order values

## User Experience Improvements

### ✅ **Visual Clarity:**
- **Dual Metrics**: Both orders and revenue visible simultaneously
- **Color Coding**: Intuitive blue for orders, green for revenue
- **Clear Legend**: Top-positioned legend with descriptive labels
- **Professional Tooltip**: Comprehensive information on hover

### ✅ **Interactive Features:**
- **Hover Effects**: Smooth animations and transitions
- **Shared Tooltip**: Shows all relevant metrics at once
- **Responsive Design**: Adapts to different screen sizes
- **Zoom/Pan**: Chart toolbar for detailed analysis

### ✅ **Data Presentation:**
- **Formatted Numbers**: Proper currency and number formatting
- **Time Labels**: Clear hour display (e.g., "19:00")
- **Grid Lines**: Subtle grid for easier reading
- **Smooth Curves**: Better trend visualization

## Technical Implementation

### Backend Data Structure:
```php
// Already available in getHourlySales() method
[
    'hour' => 19,
    'orders' => 750,
    'revenue' => 625704700,
    'avg_order_value' => 834272.93
]
```

### Frontend Chart Series:
```javascript
[
    {
        name: 'Orders',
        type: 'column',
        data: [4, 32, 367, 604, 483, 348, 269, 259, 397, 615, 750, 404, 107, 4, 1]
    },
    {
        name: 'Revenue',
        type: 'line',
        data: [9219200, 38486900, 289528900, 486411900, 386330900, 254306100, 177898100, 197122700, 308145500, 480780600, 625704700, 324439500, 67769100, 2988500, 7552600]
    }
]
```

## Benefits

### 🎯 **Enhanced Analytics:**
- **Dual Perspective**: View both order volume and revenue simultaneously
- **Trend Analysis**: Better understanding of business patterns
- **Peak Identification**: Clear identification of peak performance hours
- **Correlation Insights**: Understand relationship between orders and revenue

### 🎯 **Better Decision Making:**
- **Staffing**: Optimize staff allocation based on dual metrics
- **Marketing**: Target promotions during high-revenue hours
- **Operations**: Better resource planning and allocation
- **Strategy**: Data-driven business decisions

### 🎯 **User Experience:**
- **Comprehensive View**: All relevant metrics in one chart
- **Professional Appearance**: Modern, clean design
- **Interactive Elements**: Engaging hover effects and tooltips
- **Responsive Design**: Works on all device sizes

## Future Enhancements

### Potential Improvements:
1. **Additional Metrics**: Add average order value as third series
2. **Time Range Selection**: Allow custom time range analysis
3. **Outlet Comparison**: Compare hourly patterns across outlets
4. **Export Functionality**: Export chart data and images
5. **Drill-down**: Click to see detailed order breakdown

### Advanced Features:
1. **Predictive Analytics**: Forecast hourly demand
2. **Anomaly Detection**: Identify unusual patterns
3. **Seasonal Analysis**: Compare patterns across different periods
4. **Real-time Updates**: Live data updates for current day

## Conclusion

The Hourly Sales Chart with Revenue update successfully enhances the Sales Outlet Dashboard by providing comprehensive hourly analytics. The dual Y-axis approach allows users to view both order volume and revenue simultaneously, providing valuable insights for business decision making.

**Key Success Factors:**
- ✅ **Dual Y-Axis**: Independent scaling for optimal visualization
- ✅ **Mixed Chart Types**: Bar chart for orders, line chart for revenue
- ✅ **Enhanced Tooltip**: Comprehensive information display
- ✅ **Professional Design**: Modern, clean, and responsive
- ✅ **Business Intelligence**: Valuable insights for decision making

The feature is now ready for production use and provides enhanced analytics capabilities for better business understanding and strategic planning.
