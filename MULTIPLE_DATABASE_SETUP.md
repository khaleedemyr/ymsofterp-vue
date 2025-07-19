# Multiple Database Connection Setup

## Overview
Proyek ini sekarang mendukung multiple database connections. Connection default tetap menggunakan `mysql`, dan connection kedua menggunakan `mysql_second`.

## Konfigurasi Environment Variables

Tambahkan variabel berikut ke file `.env` Anda:

```env
# Database Utama (SUDAH ADA - JANGAN DIUBAH)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ymsofterp
DB_USERNAME=root
DB_PASSWORD=

# Database Kedua (BARU DITAMBAHKAN)
DB_HOST_SECOND=192.168.1.100
DB_PORT_SECOND=3306
DB_DATABASE_SECOND=nama_database_kedua
DB_USERNAME_SECOND=user_kedua
DB_PASSWORD_SECOND=password_kedua

# Optional: SSL Configuration untuk Database Kedua
MYSQL_ATTR_SSL_CA_SECOND=/path/to/ssl/ca.pem
```

## Penggunaan dalam Kode

### 1. Query Builder
```php
// Menggunakan database default (mysql)
$users = DB::table('users')->get();

// Menggunakan database kedua
$otherData = DB::connection('mysql_second')->table('some_table')->get();
```

### 2. Eloquent Model
```php
// Model yang menggunakan database default
class User extends Model
{
    // Tidak perlu menambahkan $connection property
}

// Model yang menggunakan database kedua
class SecondDatabaseModel extends Model
{
    protected $connection = 'mysql_second';
    protected $table = 'your_table_name';
}
```

### 3. Migration
```php
// Migration untuk database default
php artisan make:migration create_users_table

// Migration untuk database kedua
php artisan make:migration create_second_table --database=mysql_second
```

### 4. Seeder
```php
// Seeder untuk database kedua
class SecondDatabaseSeeder extends Seeder
{
    public function run()
    {
        DB::connection('mysql_second')->table('your_table')->insert([
            'column' => 'value',
        ]);
    }
}
```

## Testing Connection

Anda bisa test connection kedua dengan cara:

```php
// Di tinker atau controller
try {
    $result = DB::connection('mysql_second')->select('SELECT 1 as test');
    echo "Connection kedua berhasil!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Best Practices

1. **Gunakan nama connection yang deskriptif** - `mysql_second` lebih baik daripada `mysql2`
2. **Isolasi data** - Pastikan kedua database memiliki tujuan yang jelas
3. **Error handling** - Selalu handle error ketika menggunakan multiple connections
4. **Documentation** - Dokumentasikan tujuan setiap database

## Contoh Use Cases

- **Database utama**: Data aplikasi utama
- **Database kedua**: 
  - Data reporting/analytics
  - Data historis/arsip
  - Data dari sistem eksternal
  - Data testing/staging

## Troubleshooting

### Error: "Connection refused"
- Periksa host, port, dan credentials database kedua
- Pastikan firewall tidak memblokir koneksi

### Error: "Database does not exist"
- Pastikan database kedua sudah dibuat
- Periksa nama database di environment variables

### Error: "Access denied"
- Periksa username dan password
- Pastikan user memiliki akses ke database kedua 