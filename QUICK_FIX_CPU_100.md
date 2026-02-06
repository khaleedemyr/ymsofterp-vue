# Quick Fix - MySQL CPU 100%

## EMERGENCY: Kill Stuck Queries NOW!

### Option 1: Manual Kill (Fastest)
```sql
-- Jalankan di MySQL client atau phpMyAdmin
CALL kill_stuck_queries();  -- Jika sudah run kill_stuck_queries.sql

-- Atau manual:
KILL 30057725;
KILL 30058254;
KILL 30058287;
-- ... (lihat di processlist untuk ID lainnya)
```

### Option 2: Auto Monitor (Recommended)
```powershell
# Windows PowerShell (Run as Administrator)
cd D:\Gawean\web\ymsofterp
.\monitor_mysql_queries.ps1
```

## Permanent Fix (5 Minutes)

### Step 1: Add Indexes (1 min)
```bash
# Di terminal/cmd
cd D:\Gawean\web\ymsofterp
mysql -u root -p db_justus < add_outlet_inventory_indexes.sql
```

### Step 2: Already Fixed Controller (DONE ✓)
- Added pagination (50 items/page)
- Added search filter
- Query now optimized

### Step 3: Restart Application (1 min)
```bash
# Restart Laravel
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Restart web server if needed
# - XAMPP: Restart Apache
# - Nginx: sudo systemctl restart nginx
```

### Step 4: Verify (2 min)
```sql
-- Check processlist (should be clean)
SELECT Id, User, Time, State, LEFT(Info, 50) 
FROM information_schema.PROCESSLIST 
WHERE Command = 'Execute' 
ORDER BY Time DESC;

-- Check CPU in Task Manager/top (should be < 20%)
```

## Quick Diagnosis

### Is CPU still 100%?

**YES** → Still have stuck queries
```sql
-- Kill them:
SELECT CONCAT('KILL ', Id, ';') FROM information_schema.PROCESSLIST 
WHERE State = 'Creating sort index' AND Time > 300;
```

**NO** → Good! Monitor for 10 minutes

### Are new queries getting stuck?

**YES** → Indexes not applied properly
```sql
-- Verify indexes exist:
SHOW INDEX FROM categories WHERE Key_name = 'idx_categories_name';
SHOW INDEX FROM items WHERE Key_name = 'idx_items_category_name';
```

**NO** → Perfect! Issue resolved

## Monitoring Commands

```sql
-- Real-time processlist
SHOW FULL PROCESSLIST;

-- Count queries by state
SELECT State, COUNT(*) as count, AVG(Time) as avg_time
FROM information_schema.PROCESSLIST 
GROUP BY State 
ORDER BY count DESC;

-- Long running queries
SELECT * FROM information_schema.PROCESSLIST 
WHERE Time > 30 
ORDER BY Time DESC;
```

## Prevention

### Daily Check (30 seconds)
```sql
SELECT MAX(Time) as longest_query 
FROM information_schema.PROCESSLIST 
WHERE Command = 'Execute';
-- Should be < 60 seconds
```

### Weekly Maintenance
```sql
ANALYZE TABLE categories, items, outlet_food_inventory_stocks;
OPTIMIZE TABLE categories, items, outlet_food_inventory_stocks;
```

## Contact

If issue persists after all steps:
1. Check [FIX_MYSQL_CPU_100_PERCENT.md](FIX_MYSQL_CPU_100_PERCENT.md) for detailed guide
2. Review slow query log: `/var/log/mysql/slow-query.log`
3. Contact DBA team
