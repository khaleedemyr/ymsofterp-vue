# Cara Kill MySQL Connections yang Benar

## ⚠️ Kesalahan Umum

**JANGAN jalankan SQL commands langsung di bash shell!**
- ❌ `root@server:~# KILL 6074615;` → Error: command not found
- ❌ `root@server:~# SELECT ...` → Error: command not found

**SQL commands HARUS dijalankan di MySQL client!**

## ✅ Cara yang Benar

### Opsi 1: Login ke MySQL dulu (Recommended)

```bash
# Login ke MySQL
mysql -u root -p

# Setelah masuk MySQL prompt (mysql>), baru jalankan SQL commands:
mysql> KILL 6074615;
mysql> KILL 6074644;
mysql> KILL 6074991;
mysql> KILL 6267259;
mysql> KILL 711905;

# Atau generate kill commands otomatis:
mysql> SELECT CONCAT('KILL ', id, ';') as kill_command
    -> FROM information_schema.processlist 
    -> WHERE command = 'Sleep' 
    -> AND time > 3600 
    -> AND user != 'root';

# Copy hasil query di atas, lalu execute satu per satu
# Atau gunakan cara di bawah untuk execute langsung
```

### Opsi 2: Execute SQL langsung dari bash (One-liner)

```bash
# Kill connections idle > 1 jam (3600 detik)
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') as kill_command
FROM information_schema.processlist 
WHERE command = 'Sleep' 
AND time > 3600 
AND user != 'root';
" | grep -v kill_command | while read kill_cmd; do
    mysql -u root -p -e "$kill_cmd"
done
```

**Atau lebih sederhana, kill manual satu per satu:**

```bash
# Kill connection ID 6074615
mysql -u root -p -e "KILL 6074615;"

# Kill connection ID 6074644
mysql -u root -p -e "KILL 6074644;"

# Kill connection ID 6074991
mysql -u root -p -e "KILL 6074991;"

# Kill connection ID 6267259
mysql -u root -p -e "KILL 6267259;"

# Kill connection ID 711905
mysql -u root -p -e "KILL 711905;"
```

### Opsi 3: Generate dan Execute Script

```bash
# Generate kill script
mysql -u root -p -e "
SELECT CONCAT('KILL ', id, ';') as kill_command
FROM information_schema.processlist 
WHERE command = 'Sleep' 
AND time > 3600 
AND user != 'root';
" > /tmp/kill_connections.sql

# Edit file untuk remove header
sed -i '1d' /tmp/kill_connections.sql

# Execute script
mysql -u root -p < /tmp/kill_connections.sql
```

## 🎯 Cara Paling Mudah (Step by Step)

### Step 1: Login ke MySQL
```bash
mysql -u root -p
# Masukkan password MySQL
```

### Step 2: Check connections yang idle lama
```sql
SELECT id, user, host, db, time, command
FROM information_schema.processlist 
WHERE command = 'Sleep' 
AND time > 3600
ORDER BY time DESC;
```

### Step 3: Kill connections satu per satu
```sql
KILL 6074615;
KILL 6074644;
KILL 6074991;
KILL 6267259;
KILL 711905;
```

### Step 4: Verify connections sudah ter-kill
```sql
SHOW PROCESSLIST;
```

### Step 5: Exit MySQL
```sql
exit;
```

## 📋 Quick Reference

**Bash Shell Commands:**
- `mysql -u root -p` → Login ke MySQL
- `mysql -u root -p -e "SQL_COMMAND"` → Execute SQL dari bash

**MySQL Commands (setelah login):**
- `SHOW PROCESSLIST;` → Lihat semua connections
- `KILL connection_id;` → Kill connection
- `SHOW VARIABLES LIKE 'wait_timeout';` → Check timeout settings
- `exit;` → Keluar dari MySQL

## ⚠️ Catatan Penting

1. **Jangan kill connection yang sedang active query!**
   - Check `SHOW PROCESSLIST` dulu
   - Hanya kill yang `Command = 'Sleep'` dan `Time > 3600`

2. **Backup dulu jika ragu**
   - Check connections sebelum kill
   - Pastikan tidak ada active queries penting

3. **Set permanent fix**
   - Set `wait_timeout = 300` di MySQL config
   - Restart MySQL setelah perubahan
