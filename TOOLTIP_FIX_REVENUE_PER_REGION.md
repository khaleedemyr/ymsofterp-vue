# Tooltip Fix for Revenue per Region Charts

## Problem
Tooltip untuk chart "Lunch/Dinner Revenue per Region" dan "Weekday/Weekend Revenue per Region" hanya menampilkan data Lunch/Weekday saja, meskipun sudah diklik yang Dinner/Weekend. Tooltip tidak berubah sesuai dengan series yang diklik.

## Root Cause
Tooltip configuration menggunakan:
- `shared: true` - Menampilkan tooltip untuk semua series sekaligus
- `intersect: false` - Tooltip muncul tanpa perlu hover tepat di data point

Dengan konfigurasi ini, ApexCharts selalu menampilkan tooltip untuk series pertama (Lunch/Weekday) saja.

## Solution
Mengubah tooltip configuration menjadi:
- `shared: false` - Menampilkan tooltip untuk series yang diklik saja
- `intersect: true` - Tooltip hanya muncul saat hover tepat di data point

## Implementation

### **Before (Problematic):**
```javascript
tooltip: {
    shared: true,
    intersect: false,
    custom: function({series, seriesIndex, dataPointIndex, w}) {
        // Tooltip logic
    }
}
```

### **After (Fixed):**
```javascript
tooltip: {
    shared: false,
    intersect: true,
    custom: function({series, seriesIndex, dataPointIndex, w}) {
        // Tooltip logic
    }
}
```

## Changes Made

### **1. Lunch/Dinner Revenue per Region Chart**
```javascript
const revenuePerRegionLunchDinnerOptions = computed(() => ({
    // ... other options
    tooltip: {
        shared: false,        // Changed from true
        intersect: true,      // Changed from false
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerRegion?.lunch_dinner;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[dataPointIndex];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            const period = seriesIndex === 0 ? 'lunch' : 'dinner';
            const periodData = regionData[period];
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${regionName} - ${seriesIndex === 0 ? 'Lunch' : 'Dinner'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 ${seriesIndex === 0 ? 'bg-green-500' : 'bg-orange-500'} rounded"></div>
                            <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-purple-500 rounded"></div>
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
    }
}));
```

### **2. Weekday/Weekend Revenue per Region Chart**
```javascript
const revenuePerRegionWeekdayWeekendOptions = computed(() => ({
    // ... other options
    tooltip: {
        shared: false,        // Changed from true
        intersect: true,      // Changed from false
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerRegion?.weekday_weekend;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[dataPointIndex];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            const period = seriesIndex === 0 ? 'weekday' : 'weekend';
            const periodData = regionData[period];
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${regionName} - ${seriesIndex === 0 ? 'Weekday' : 'Weekend'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 ${seriesIndex === 0 ? 'bg-blue-500' : 'bg-purple-500'} rounded"></div>
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
    }
}));
```

## Tooltip Logic

### **Parameters:**
- `seriesIndex`: Index of the series (0 = Lunch/Weekday, 1 = Dinner/Weekend)
- `dataPointIndex`: Index of the region in the categories array
- `series`: Array of series data
- `w`: Chart configuration object

### **Data Mapping:**
```javascript
// For Lunch/Dinner Chart
const period = seriesIndex === 0 ? 'lunch' : 'dinner';
const periodData = regionData[period];

// For Weekday/Weekend Chart  
const period = seriesIndex === 0 ? 'weekday' : 'weekend';
const periodData = regionData[period];
```

### **Color Coding:**
```javascript
// Lunch/Dinner Chart
${seriesIndex === 0 ? 'bg-green-500' : 'bg-orange-500'}

// Weekday/Weekend Chart
${seriesIndex === 0 ? 'bg-blue-500' : 'bg-purple-500'}
```

## Test Results

### **Sample Data (September 1-10, 2025):**

#### **Lunch/Dinner Tooltip Data:**
```
Bandung Prime - Lunch (seriesIndex=0, dataPointIndex=0):
  Revenue: 671,653,100
  Orders: 986
  Pax: 2,709
  Avg Check: 247,934

Bandung Prime - Dinner (seriesIndex=1, dataPointIndex=0):
  Revenue: 373,345,300
  Orders: 555
  Pax: 1,593
  Avg Check: 234,366

Jakarta-Tangerang - Lunch (seriesIndex=0, dataPointIndex=2):
  Revenue: 1,384,680,100
  Orders: 1,597
  Pax: 4,312
  Avg Check: 321,122

Jakarta-Tangerang - Dinner (seriesIndex=1, dataPointIndex=2):
  Revenue: 1,084,916,600
  Orders: 1,225
  Pax: 3,433
  Avg Check: 316,026
```

#### **Weekday/Weekend Tooltip Data:**
```
Bandung Prime - Weekday (seriesIndex=0, dataPointIndex=0):
  Revenue: 647,746,300
  Orders: 999
  Pax: 2,627
  Avg Check: 246,573

Bandung Prime - Weekend (seriesIndex=1, dataPointIndex=0):
  Revenue: 397,252,100
  Orders: 542
  Pax: 1,675
  Avg Check: 237,165

Jakarta-Tangerang - Weekday (seriesIndex=0, dataPointIndex=2):
  Revenue: 1,639,408,000
  Orders: 1,975
  Pax: 5,094
  Avg Check: 321,831

Jakarta-Tangerang - Weekend (seriesIndex=1, dataPointIndex=2):
  Revenue: 830,188,700
  Orders: 847
  Pax: 2,651
  Avg Check: 313,161
```

## Expected Behavior

### **Before Fix:**
- ❌ Tooltip selalu menampilkan data Lunch/Weekday
- ❌ Klik pada Dinner/Weekend tidak mengubah tooltip
- ❌ `shared: true` menyebabkan konflik

### **After Fix:**
- ✅ Tooltip menampilkan data sesuai series yang diklik
- ✅ Klik pada Lunch menampilkan data Lunch
- ✅ Klik pada Dinner menampilkan data Dinner
- ✅ Klik pada Weekday menampilkan data Weekday
- ✅ Klik pada Weekend menampilkan data Weekend
- ✅ `shared: false` memungkinkan tooltip independen per series

## User Experience

### **Improved Interaction:**
1. **Precise Hover**: Tooltip hanya muncul saat hover tepat di data point
2. **Series-Specific**: Setiap series menampilkan tooltip yang sesuai
3. **Visual Feedback**: Color coding yang konsisten dengan chart colors
4. **Rich Information**: Detail metrics untuk setiap period

### **Tooltip Content:**
- **Title**: Region name dan period (Lunch/Dinner atau Weekday/Weekend)
- **Revenue**: Total revenue dengan currency formatting
- **Orders**: Number of orders dengan thousand separators
- **Pax**: Number of customers dengan thousand separators
- **Avg Check**: Average check per customer dengan currency formatting
- **Color Indicators**: Visual indicators sesuai dengan chart colors

## Technical Details

### **ApexCharts Tooltip Configuration:**
```javascript
tooltip: {
    shared: false,      // Show tooltip for single series only
    intersect: true,    // Show tooltip only when hovering exactly on data point
    custom: function({series, seriesIndex, dataPointIndex, w}) {
        // Custom tooltip logic
    }
}
```

### **Data Structure Access:**
```javascript
// Access region data
const regions = Object.keys(data);
const regionName = regions[dataPointIndex];
const regionData = data[regionName];

// Access period data based on series index
const period = seriesIndex === 0 ? 'lunch' : 'dinner';
const periodData = regionData[period];
```

### **Dynamic Content Generation:**
```javascript
// Dynamic title
${regionName} - ${seriesIndex === 0 ? 'Lunch' : 'Dinner'}

// Dynamic color
${seriesIndex === 0 ? 'bg-green-500' : 'bg-orange-500'}

// Dynamic data
${formatCurrency(periodData.total_revenue)}
${periodData.order_count.toLocaleString()}
${periodData.total_pax.toLocaleString()}
${formatCurrency(avgCheck)}
```

## Conclusion

The tooltip fix successfully resolves the issue where tooltips were only showing Lunch/Weekday data regardless of which series was clicked. The changes ensure that:

- ✅ **Accurate Tooltips**: Each series shows its own tooltip data
- ✅ **Better UX**: Precise hover interaction with `intersect: true`
- ✅ **Independent Series**: `shared: false` allows independent tooltip display
- ✅ **Consistent Behavior**: Both Lunch/Dinner and Weekday/Weekend charts work correctly
- ✅ **Rich Information**: Detailed metrics for each period and region

The fix maintains all existing functionality while providing the correct tooltip behavior that users expect when interacting with stacked bar charts.
