# Jenis Kelamin Update Documentation

## Overview
Update nilai jenis kelamin di database dan aplikasi untuk menggunakan format yang benar: 1=Laki-laki, 2=Perempuan.

## Perubahan yang Dilakukan

### 1. Model Customer Accessor
- ✅ **Jenis Kelamin Accessor**: Diperbaiki untuk menggunakan '1' dan '2'
- ✅ **Format**: 1=Laki-laki, 2=Perempuan, null/empty='-'

### 2. Form Updates
- ✅ **Create Form**: Opsi jenis kelamin diubah dari L/P ke 1/2
- ✅ **Edit Form**: Opsi jenis kelamin diubah dari L/P ke 1/2

### 3. Database Consistency
- ✅ **Nilai Database**: Menggunakan 1 dan 2 untuk jenis kelamin
- ✅ **Accessor**: Menghasilkan text yang readable

## Detail Implementasi

### A. Model Customer Accessor
```php
/**
 * Accessor untuk jenis kelamin yang readable
 * 
 * @return string
 */
public function getJenisKelaminTextAttribute()
{
    if ($this->jenis_kelamin === '1') {
        return 'Laki-laki';
    } elseif ($this->jenis_kelamin === '2') {
        return 'Perempuan';
    }
    return '-';
}
```

### B. Form Options (Create & Edit)
```vue
<select v-model="form.jenis_kelamin" class="...">
  <option value="">Pilih Jenis Kelamin</option>
  <option value="1">Laki-laki</option>
  <option value="2">Perempuan</option>
</select>
```

### C. Show Page Badge
```vue
<span :class="['px-2 py-1 rounded-full text-xs font-medium', getGenderBadgeClass(member.jenis_kelamin_text)]">
  {{ member.jenis_kelamin_text || '-' }}
</span>
```

## Mapping Values

### Database Values
| Value | Description |
|-------|-------------|
| `1` | Laki-laki |
| `2` | Perempuan |
| `null` / `''` | Tidak diisi |

### Display Values
| Database Value | Display Text | Badge Color |
|----------------|--------------|-------------|
| `1` | "Laki-laki" | Blue |
| `2` | "Perempuan" | Pink |
| `null` / `''` | "-" | Gray |

## Files Updated

### 1. Model
- `app/Models/Customer.php`
  - Updated `getJenisKelaminTextAttribute()` method

### 2. Forms
- `resources/js/Pages/Members/Create.vue`
  - Updated select options from L/P to 1/2
- `resources/js/Pages/Members/Edit.vue`
  - Updated select options from L/P to 1/2

### 3. Display
- `resources/js/Pages/Members/Show.vue`
  - Uses `jenis_kelamin_text` accessor (no changes needed)

## Validation Rules

### Controller Validation
```php
'jenis_kelamin' => 'nullable|in:1,2',
```

### Frontend Validation
```javascript
// Optional field, but if filled must be 1 or 2
if (form.jenis_kelamin && !['1', '2'].includes(form.jenis_kelamin)) {
  errors.jenis_kelamin = 'Jenis kelamin harus Laki-laki atau Perempuan';
}
```

## Migration Considerations

### Existing Data
Jika ada data lama yang menggunakan format L/P, perlu migration:

```sql
-- Update existing data
UPDATE costumers SET jenis_kelamin = '1' WHERE jenis_kelamin = 'L';
UPDATE costumers SET jenis_kelamin = '2' WHERE jenis_kelamin = 'P';
```

### Migration File (if needed)
```php
public function up()
{
    Schema::table('costumers', function (Blueprint $table) {
        // Update existing data
        DB::statement("UPDATE costumers SET jenis_kelamin = '1' WHERE jenis_kelamin = 'L'");
        DB::statement("UPDATE costumers SET jenis_kelamin = '2' WHERE jenis_kelamin = 'P'");
    });
}
```

## Testing Examples

### Accessor Testing
```php
// Test jenis kelamin accessor
$member = Customer::factory()->create(['jenis_kelamin' => '1']);
$this->assertEquals('Laki-laki', $member->jenis_kelamin_text);

$member = Customer::factory()->create(['jenis_kelamin' => '2']);
$this->assertEquals('Perempuan', $member->jenis_kelamin_text);

$member = Customer::factory()->create(['jenis_kelamin' => null]);
$this->assertEquals('-', $member->jenis_kelamin_text);
```

### Form Testing
```javascript
// Test form validation
const form = {
  jenis_kelamin: '1'
};
expect(validateJenisKelamin(form.jenis_kelamin)).toBe(true);

const form = {
  jenis_kelamin: 'invalid'
};
expect(validateJenisKelamin(form.jenis_kelamin)).toBe(false);
```

## Benefits

### 1. Data Consistency
- Format yang konsisten di database
- Nilai numerik yang mudah diproses
- Validasi yang lebih mudah

### 2. Internationalization
- Mudah untuk translate ke bahasa lain
- Format yang standard
- Accessor yang flexible

### 3. Performance
- Query yang lebih efisien
- Index yang lebih baik
- Storage yang optimal

### 4. Maintainability
- Kode yang lebih clean
- Mudah untuk extend
- Dokumentasi yang jelas

## Future Considerations

### Potential Enhancements
1. **Enum**: Gunakan PHP 8.1+ enum untuk jenis kelamin
2. **Localization**: Support multiple languages
3. **Validation**: Add more validation rules
4. **UI**: Add gender icons or avatars

### Monitoring
- Track data consistency
- Monitor validation errors
- Ensure proper migration

---

**Status**: ✅ Updated  
**Last Updated**: January 2024  
**Version**: 2.5.1  
**Migration Required**: Yes (if existing data uses L/P format) 