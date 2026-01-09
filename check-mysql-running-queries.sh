#!/bin/bash

echo "=========================================="
echo "🔍 CHECK MYSQL RUNNING QUERIES"
echo "=========================================="
echo ""

# 1. Check all running queries
echo "1️⃣ ALL RUNNING QUERIES:"
echo "----------------------------------------"
mysql -u root -p -e "SHOW PROCESSLIST;" 2>/dev/null | head -20
echo ""

# 2. Check queries that run > 5 seconds
echo "2️⃣ QUERIES RUNNING > 5 SECONDS:"
echo "----------------------------------------"
mysql -u root -p -e "
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    LEFT(info, 100) as query_preview
FROM information_schema.processlist 
WHERE command != 'Sleep' 
AND time > 5
ORDER BY time DESC;
" 2>/dev/null

if [ $? -ne 0 ]; then
    echo "⚠️  Error: Cannot connect to MySQL or need password"
    echo "   Run manually: mysql -u root -p -e 'SHOW PROCESSLIST;'"
fi
echo ""

# 3. Check queries that run > 30 seconds (CRITICAL)
echo "3️⃣ QUERIES RUNNING > 30 SECONDS (CRITICAL):"
echo "----------------------------------------"
mysql -u root -p -e "
SELECT 
    id,
    user,
    host,
    db,
    command,
    time,
    state,
    LEFT(info, 100) as query_preview
FROM information_schema.processlist 
WHERE command != 'Sleep' 
AND time > 30
ORDER BY time DESC;
" 2>/dev/null

if [ $? -eq 0 ]; then
    COUNT=$(mysql -u root -p -e "
    SELECT COUNT(*) as count
    FROM information_schema.processlist 
    WHERE command != 'Sleep' 
    AND time > 30;
    " 2>/dev/null | tail -1)
    
    if [ "$COUNT" -gt 0 ]; then
        echo "⚠️  WARNING: Found $COUNT queries running > 30 seconds!"
        echo ""
        echo "To kill these queries, run:"
        echo "mysql -u root -p -e \""
        echo "SELECT CONCAT('KILL ', id, ';')"
        echo "FROM information_schema.processlist"
        echo "WHERE command != 'Sleep'"
        echo "AND time > 30"
        echo "AND user != 'system user';"
        echo "\" | grep KILL | mysql -u root -p"
    else
        echo "✅ No queries running > 30 seconds"
    fi
fi
echo ""

# 4. Check table locks
echo "4️⃣ TABLE LOCKS:"
echo "----------------------------------------"
mysql -u root -p -e "
SELECT 
    r.trx_id waiting_trx_id,
    r.trx_mysql_thread_id waiting_thread,
    r.trx_query waiting_query,
    b.trx_id blocking_trx_id,
    b.trx_mysql_thread_id blocking_thread,
    b.trx_query blocking_query
FROM information_schema.innodb_lock_waits w
INNER JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
INNER JOIN information_schema.innodb_trx r ON r.trx_id = w.requesting_trx_id;
" 2>/dev/null

if [ $? -ne 0 ] || [ -z "$(mysql -u root -p -e 'SELECT * FROM information_schema.innodb_lock_waits;' 2>/dev/null | tail -n +2)" ]; then
    echo "✅ No table locks detected"
fi
echo ""

# 5. Summary
echo "=========================================="
echo "📋 SUMMARY"
echo "=========================================="
echo ""

TOTAL_QUERIES=$(mysql -u root -p -e "SELECT COUNT(*) FROM information_schema.processlist WHERE command != 'Sleep';" 2>/dev/null | tail -1)
LONG_QUERIES=$(mysql -u root -p -e "SELECT COUNT(*) FROM information_schema.processlist WHERE command != 'Sleep' AND time > 5;" 2>/dev/null | tail -1)

if [ -n "$TOTAL_QUERIES" ] && [ "$TOTAL_QUERIES" != "NULL" ]; then
    echo "Total running queries: $TOTAL_QUERIES"
    echo "Queries running > 5 seconds: $LONG_QUERIES"
    echo ""
    
    if [ "$LONG_QUERIES" -gt 0 ]; then
        echo "⚠️  WARNING: Found $LONG_QUERIES queries running > 5 seconds"
        echo "   → These queries might be causing PHP-FPM processes to hang"
        echo "   → Check and optimize these queries"
    else
        echo "✅ No long-running queries detected"
    fi
else
    echo "⚠️  Cannot get query count (need MySQL password)"
fi

echo ""
echo "=========================================="
