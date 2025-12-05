# Public Holiday Balance Usage Examples

## Skenario: Multiple Records dengan Saldo Berbeda

### Data Awal:
- Record 1: ID=43, compensation_type='bonus', compensation_amount=5, used_amount=0, available=5
- Record 2: ID=44, compensation_type='bonus', compensation_amount=3, used_amount=0, available=3
- Record 3: ID=45, compensation_type='extra_off', compensation_amount=1, used_date=null, available=1
- **Total Available: 9 hari**

### Perbedaan Compensation Type:

#### **Bonus Type:**
- Dapat digunakan sebagian (partial usage)
- Menggunakan kolom `used_amount` untuk tracking
- Contoh: saldo 5 hari, bisa digunakan 2 hari, sisa 3 hari

#### **Extra Off Type:**
- Harus digunakan penuh (full usage)
- Menggunakan kolom `used_date` dan `status` untuk tracking
- Contoh: 1 record = 1 hari, jika digunakan maka habis

### Skenario 1: User ingin menggunakan 3 hari

#### Menggunakan Sistem Otomatis (FIFO - First In First Out):
```javascript
// API Call
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 3,
    "use_date": "2025-01-20",
    "strategy": "fifo"
}
```

**Hasil:**
- Record 1 (ID=43, bonus): used_amount = 3, available = 2
- Record 2 (ID=44, bonus): tidak berubah, available = 3
- Record 3 (ID=45, extra_off): tidak berubah, available = 1
- **Total tersisa: 6 hari**

### Skenario 2: User ingin menggunakan 6 hari

#### Menggunakan Sistem Otomatis (FIFO):
```javascript
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 6,
    "use_date": "2025-01-20",
    "strategy": "fifo"
}
```

**Hasil:**
- Record 1 (ID=43, bonus): used_amount = 5, available = 0 (habis)
- Record 2 (ID=44, bonus): used_amount = 1, available = 2
- Record 3 (ID=45, extra_off): status = 'used', available = 0 (habis)
- **Total tersisa: 2 hari**

### Skenario 3: User ingin menggunakan 1 hari (hanya extra_off)

#### Menggunakan Sistem Otomatis (FIFO):
```javascript
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 1,
    "use_date": "2025-01-20",
    "strategy": "fifo"
}
```

**Hasil:**
- Record 1 (ID=43, bonus): tidak berubah, available = 5
- Record 2 (ID=44, bonus): tidak berubah, available = 3
- Record 3 (ID=45, extra_off): status = 'used', available = 0 (habis)
- **Total tersisa: 8 hari**

#### Menggunakan Sistem Otomatis (LIFO - Last In First Out):
```javascript
// API Call
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 3,
    "use_date": "2025-01-20",
    "strategy": "lifo"
}
```

**Hasil:**
- Record 1 (ID=43): tidak berubah, available = 5
- Record 2 (ID=44): used_amount = 3, available = 0
- Total tersisa: 5 hari

### Skenario 2: User ingin menggunakan 6 hari

#### Menggunakan Sistem Otomatis (FIFO):
```javascript
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 6,
    "use_date": "2025-01-20",
    "strategy": "fifo"
}
```

**Hasil:**
- Record 1 (ID=43): used_amount = 5, available = 0 (habis)
- Record 2 (ID=44): used_amount = 1, available = 2
- Total tersisa: 2 hari

### Skenario 3: User ingin menggunakan 10 hari (lebih dari yang tersedia)

```javascript
POST /api/holiday-attendance/use-balance-auto
{
    "use_amount": 10,
    "use_date": "2025-01-20",
    "strategy": "fifo"
}
```

**Hasil:**
- Error: "Insufficient Public Holiday balance. Available: 8 days, Requested: 10 days"

## Algoritma Pemilihan Record

### FIFO (First In First Out):
1. Ambil record berdasarkan `created_at` ASC (terlama dulu)
2. Gunakan saldo dari record tertua terlebih dahulu
3. Jika tidak cukup, lanjut ke record berikutnya

### LIFO (Last In First Out):
1. Ambil record berdasarkan `created_at` DESC (terbaru dulu)
2. Gunakan saldo dari record terbaru terlebih dahulu
3. Jika tidak cukup, lanjut ke record sebelumnya

## Response Format

### Success Response:
```json
{
    "success": true,
    "message": "Public Holiday balance used successfully",
    "data": {
        "success": true,
        "used_records": [
            {
                "compensation_id": 43,
                "used_amount": 3,
                "remaining_amount": 2
            }
        ],
        "total_used": 3
    }
}
```

### Error Response:
```json
{
    "success": false,
    "message": "Insufficient Public Holiday balance. Available: 5 days, Requested: 8 days"
}
```

## Integrasi dengan Frontend

### Di Modal Absence:
```javascript
// Saat user submit absence request untuk Public Holiday
const submitAbsentRequest = async () => {
    if (isPublicHolidayType.value) {
        const daysToUse = selectedDaysCount.value;
        
        try {
            const response = await axios.post('/api/holiday-attendance/use-balance-auto', {
                use_amount: daysToUse,
                use_date: absentForm.value.date_from,
                strategy: 'fifo' // atau 'lifo' sesuai preferensi
            });
            
            if (response.data.success) {
                // Tampilkan informasi record yang digunakan
                console.log('Records used:', response.data.data.used_records);
                // Lanjutkan dengan submit absence request
            }
        } catch (error) {
            // Handle error
            console.error('Error using balance:', error.response.data.message);
        }
    }
}
```

## Keuntungan Sistem Ini:

1. **Otomatis**: User tidak perlu memilih record mana yang akan digunakan
2. **Fleksibel**: Mendukung strategi FIFO dan LIFO
3. **Akurat**: Selalu menghitung saldo yang tersedia dengan benar
4. **Transparan**: Memberikan informasi detail tentang record yang digunakan
5. **Efisien**: Menggunakan record yang paling optimal berdasarkan strategi
