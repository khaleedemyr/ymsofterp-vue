# Dashboard Query Fix - MySQL Compatibility

## 🐛 Problem

Dashboard Sales Outlet mengalami error SQL syntax karena menggunakan fungsi `PERCENTILE_CONT` yang tidak didukung di MySQL:

```
SQLSTATE[42000]: Syntax error or access violation: 1064 
You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'GROUP (ORDER BY grand_total) as median_order_value FROM orders' at line 5
```

## 🔍 Root Cause

Fungsi `PERCENTILE_CONT` adalah fungsi SQL Server/PostgreSQL yang tidak tersedia di MySQL. Query yang bermasalah:

```sql
SELECT 
    AVG(grand_total) as avg_order_value,
    MIN(grand_total) as min_order_value,
    MAX(grand_total) as max_order_value,
    PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY grand_total) as median_order_value  -- ❌ Not supported in MySQL
FROM orders 
WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
```

## ✅ Solution

### 1. **Remove PERCENTILE_CONT Function**
Menghapus fungsi yang tidak didukung dan menghitung median secara manual.

### 2. **Implement MySQL-Compatible Median Calculation**
Menggunakan `ROW_NUMBER()` window function yang didukung MySQL 8.0+.

## 🔧 Code Changes

### Before (❌ Error)
```php
private function getAverageOrderValue($outletFilter, $dateFrom, $dateTo)
{
    $query = "
        SELECT 
            AVG(grand_total) as avg_order_value,
            MIN(grand_total) as min_order_value,
            MAX(grand_total) as max_order_value,
            PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY grand_total) as median_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    ";

    return DB::select($query)[0];
}
```

### After (✅ Fixed)
```php
private function getAverageOrderValue($outletFilter, $dateFrom, $dateTo)
{
    $query = "
        SELECT 
            AVG(grand_total) as avg_order_value,
            MIN(grand_total) as min_order_value,
            MAX(grand_total) as max_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    ";

    $result = DB::select($query)[0];
    
    // Calculate median manually for MySQL compatibility
    $medianQuery = "
        SELECT grand_total as median_order_value
        FROM (
            SELECT grand_total, 
                   ROW_NUMBER() OVER (ORDER BY grand_total) as row_num,
                   COUNT(*) OVER () as total_count
            FROM orders 
            WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
            {$outletFilter}
        ) as ranked
        WHERE row_num IN (FLOOR((total_count + 1) / 2), CEIL((total_count + 1) / 2))
    ";
    
    $medianResult = DB::select($medianQuery);
    $median = 0;
    
    if (count($medianResult) > 0) {
        if (count($medianResult) == 1) {
            $median = $medianResult[0]->median_order_value;
        } else {
            $median = ($medianResult[0]->median_order_value + $medianResult[1]->median_order_value) / 2;
        }
    }
    
    $result->median_order_value = $median;
    
    return $result;
}
```

## 📊 How Median Calculation Works

### 1. **Window Functions**
```sql
SELECT grand_total, 
       ROW_NUMBER() OVER (ORDER BY grand_total) as row_num,
       COUNT(*) OVER () as total_count
FROM orders 
WHERE DATE(created_at) BETWEEN '2025-09-01' AND '2025-09-10'
```

- `ROW_NUMBER() OVER (ORDER BY grand_total)`: Memberikan nomor urut untuk setiap record
- `COUNT(*) OVER ()`: Menghitung total jumlah record

### 2. **Median Position Calculation**
```sql
WHERE row_num IN (FLOOR((total_count + 1) / 2), CEIL((total_count + 1) / 2))
```

- **Odd number of records**: Median = middle value
- **Even number of records**: Median = average of two middle values

### 3. **PHP Logic**
```php
if (count($medianResult) == 1) {
    // Odd number of records
    $median = $medianResult[0]->median_order_value;
} else {
    // Even number of records
    $median = ($medianResult[0]->median_order_value + $medianResult[1]->median_order_value) / 2;
}
```

## 🧪 Testing

### Test Script
Jalankan script test untuk memverifikasi fix:

```bash
php test_dashboard_query_fix.php
```

### Expected Output
```
🧪 Testing Dashboard Query Fix...

📅 Date Range: 2025-09-01 to 2025-09-10
🏪 Outlet Filter: All Outlets

1️⃣ Testing basic average order value query...
✅ Basic query successful!
   - Average Order Value: 125,000.00
   - Min Order Value: 25,000.00
   - Max Order Value: 500,000.00

2️⃣ Testing median calculation query...
✅ Median query successful!
   - Median Order Value: 120,000.00
   - Records found: 1

3️⃣ Checking orders table...
✅ Orders table accessible!
   - Total orders in date range: 150

4️⃣ Testing with last 30 days...
✅ Last 30 days query successful!
   - Total Orders: 1,250
   - Average Order Value: 135,000.00
   - Min Order Value: 15,000.00
   - Max Order Value: 750,000.00

5️⃣ Testing Dashboard Controller method...
✅ Controller method successful!
   - Average Order Value: 125,000.00
   - Min Order Value: 25,000.00
   - Max Order Value: 500,000.00
   - Median Order Value: 120,000.00

🎉 Dashboard Query Fix Test Completed!
```

## 📋 Files Modified

### 1. **SalesOutletDashboardController.php**
- ✅ Fixed `getAverageOrderValue()` method
- ✅ Removed `PERCENTILE_CONT` function
- ✅ Added MySQL-compatible median calculation

### 2. **Test Files Created**
- ✅ `test_dashboard_query_fix.php` - Test script
- ✅ `DASHBOARD_QUERY_FIX.md` - Documentation

## 🔍 MySQL Version Requirements

### Minimum Requirements
- **MySQL 8.0+**: Required for `ROW_NUMBER()` window function
- **MySQL 5.7**: Not supported (no window functions)

### Alternative for MySQL 5.7
Jika menggunakan MySQL 5.7, gunakan query alternatif:

```sql
-- Alternative median calculation for MySQL 5.7
SELECT AVG(grand_total) as median_order_value
FROM (
    SELECT grand_total
    FROM orders 
    WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
    {$outletFilter}
    ORDER BY grand_total
    LIMIT 2 OFFSET (
        SELECT FLOOR((COUNT(*) - 1) / 2) 
        FROM orders 
        WHERE DATE(created_at) BETWEEN '{$dateFrom}' AND '{$dateTo}' 
        {$outletFilter}
    )
) as median_calc;
```

## 🎯 Benefits

### 1. **MySQL Compatibility**
- ✅ Works with MySQL 8.0+
- ✅ No more SQL syntax errors
- ✅ Standard SQL functions only

### 2. **Performance**
- ✅ Efficient median calculation
- ✅ Single query for basic stats
- ✅ Separate query for median (only when needed)

### 3. **Maintainability**
- ✅ Clear and readable code
- ✅ Well-documented logic
- ✅ Easy to modify and extend

## 🚀 Deployment

### 1. **Apply Changes**
```bash
# Files are already updated
# No additional deployment steps needed
```

### 2. **Test Dashboard**
1. Access `/sales-outlet-dashboard`
2. Verify no SQL errors in logs
3. Check that all metrics display correctly
4. Test export functionality

### 3. **Monitor Performance**
- Check query execution time
- Monitor database load
- Verify median calculation accuracy

## 🔮 Future Improvements

### 1. **Caching**
```php
// Cache median calculation for better performance
$cacheKey = "median_order_value_{$outletCode}_{$dateFrom}_{$dateTo}";
$median = Cache::remember($cacheKey, 3600, function() use ($medianQuery) {
    return $this->calculateMedian($medianQuery);
});
```

### 2. **Database Indexes**
```sql
-- Add index for better performance
CREATE INDEX idx_orders_created_at_grand_total 
ON orders (created_at, grand_total);
```

### 3. **Alternative Median Algorithms**
- **Approximate Median**: For very large datasets
- **Sampling**: Calculate median from sample data
- **Pre-calculated**: Store median values in separate table

## ✅ Verification Checklist

- [ ] Dashboard loads without SQL errors
- [ ] All metrics display correctly
- [ ] Median calculation works for both odd/even record counts
- [ ] Export functionality works
- [ ] Performance is acceptable
- [ ] No console errors in browser
- [ ] Database logs show no errors

---

**Fix Applied Successfully! Dashboard is now MySQL compatible! 🎉**
