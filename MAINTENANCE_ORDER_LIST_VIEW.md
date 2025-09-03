# 🗂️ Maintenance Order List View

## **📋 Overview**
Menu **Maintenance Order List View** adalah alternatif dari Kanban View yang menampilkan semua maintenance order dalam format tabel/list. Fitur ini memungkinkan user untuk melihat semua order dalam satu layar tanpa perlu ganti outlet/ruko.

## **🎯 Fitur Utama**

### **1. Global View**
- ✅ **Semua Order**: Menampilkan semua maintenance order dari semua outlet
- ✅ **No Outlet Switching**: Tidak perlu ganti outlet/ruko untuk melihat data lain
- ✅ **Comprehensive Data**: Informasi lengkap dalam satu tabel

### **2. Advanced Filtering**
- **Outlet Filter**: Filter berdasarkan outlet tertentu
- **Status Filter**: Filter berdasarkan status (TASK, PR, PO, IN_PROGRESS, IN_REVIEW, DONE)
- **Search**: Pencarian berdasarkan title, description, atau task number
- **Real-time Filtering**: Filter langsung tanpa reload

### **3. Table Features**
- **Responsive Design**: Tabel responsive untuk berbagai ukuran layar
- **Hover Effects**: Hover effect pada setiap row
- **Status Badges**: Badge berwarna untuk status dan priority
- **Action Buttons**: View dan Edit untuk setiap task

## **🔧 Implementasi Teknis**

### **Frontend Components**
- **File**: `resources/js/Pages/MaintenanceOrder/List.vue`
- **Layout**: `AppLayout.vue`
- **Framework**: Vue.js 3 + Inertia.js
- **Styling**: Tailwind CSS

### **Backend API**
- **Controller**: `MaintenanceOrderController@listAll`
- **Route**: `/api/maintenance-order-list`
- **Method**: `GET`
- **Features**: Search, status filter, outlet filter

### **Database Query**
```php
$query = DB::table('maintenance_tasks')
    ->select(
        'maintenance_tasks.*',
        'users.nama_lengkap as created_by_name',
        'maintenance_labels.name as label_name',
        'maintenance_priorities.priority as priority_name'
    )
    ->leftJoin('users', 'maintenance_tasks.created_by', '=', 'users.id')
    ->leftJoin('maintenance_labels', 'maintenance_tasks.label_id', '=', 'maintenance_labels.id')
    ->leftJoin('maintenance_priorities', 'maintenance_tasks.priority_id', '=', 'maintenance_priorities.id');
```

## **📱 User Interface**

### **Header Section**
- **Title**: "Maintenance Order List"
- **Navigation**: Link ke Kanban View dan Export button
- **Responsive**: Layout responsive untuk mobile dan desktop

### **Filter Section**
- **Outlet Dropdown**: Pilih outlet atau "Semua Outlet"
- **Status Dropdown**: Filter berdasarkan status task
- **Search Input**: Pencarian real-time
- **Action Buttons**: Clear dan Apply filters

### **Table Section**
- **Task Info**: Title, task number, description dengan icon
- **Status Column**: Badge berwarna untuk setiap status
- **Priority Column**: Badge berwarna untuk priority
- **Location Column**: Outlet dan ruko (jika ada)
- **Due Date Column**: Tanggal jatuh tempo dengan overdue indicator
- **Actions Column**: View dan Edit buttons

## **🚀 Cara Penggunaan**

### **1. Akses Menu**
- Buka sidebar Maintenance
- Klik "Maintenance Order List"
- Atau dari Kanban View, klik "List View"

### **2. Filter Data**
- **Outlet**: Pilih outlet tertentu atau biarkan kosong untuk semua
- **Status**: Pilih status tertentu atau biarkan kosong untuk semua
- **Search**: Ketik keyword untuk pencarian

### **3. Navigasi Data**
- **View Task**: Klik icon mata untuk melihat detail task
- **Edit Task**: Klik icon edit untuk mengedit task
- **Export**: Klik Export untuk download data

## **🔗 Integrasi dengan Sistem**

### **Navigation Links**
- **Kanban View**: Link ke `/maintenance-order`
- **List View**: Link ke `/maintenance-order/list`
- **Calendar View**: Link ke `/maintenance-order/schedule-calendar`

### **Data Consistency**
- **Same API**: Menggunakan data yang sama dengan Kanban View
- **Real-time**: Update otomatis ketika ada perubahan
- **Permission**: Mengikuti permission system yang sama

## **📊 Keuntungan List View**

### **1. Overview Lengkap**
- Melihat semua maintenance order dalam satu layar
- Tidak perlu switch outlet/ruko
- Data comparison antar outlet

### **2. Filtering Power**
- Filter berdasarkan multiple criteria
- Search functionality yang powerful
- Real-time filtering

### **3. Bulk Operations**
- Kemampuan untuk bulk actions (future enhancement)
- Export data yang sudah difilter
- Batch processing

### **4. Reporting**
- Data untuk reporting dan analytics
- Export untuk external tools
- Historical data tracking

## **🔮 Future Enhancements**

### **Planned Features**
- **Pagination**: Untuk data yang sangat banyak
- **Bulk Actions**: Update status multiple tasks
- **Advanced Filters**: Date range, priority, category
- **Export Options**: Excel, PDF, CSV
- **Sorting**: Sort by columns
- **Saved Filters**: Save filter preferences

### **Advanced Features**
- **Dashboard Integration**: Summary statistics
- **Notification System**: Real-time updates
- **Mobile App**: Native mobile application
- **API Integration**: Third-party integrations

## **🐛 Troubleshooting**

### **Common Issues**
1. **Data tidak muncul**: Cek network connection dan API endpoint
2. **Filter tidak bekerja**: Pastikan data format sesuai
3. **Performance slow**: Gunakan filter untuk mengurangi data

### **Debug Information**
- Check browser console untuk error messages
- Verify API endpoint `/api/maintenance-order-list`
- Check Laravel logs untuk backend errors

## **📝 Changelog**

### **v1.0.0 (Current)**
- ✅ Basic list view implementation
- ✅ Outlet, status, dan search filtering
- ✅ Responsive table design
- ✅ Navigation integration
- ✅ Export button (placeholder)

### **v1.1.0 (Planned)**
- 🔄 Pagination support
- 🔄 Advanced filtering
- 🔄 Bulk operations
- 🔄 Export functionality

---

**🎉 Menu Maintenance Order List View berhasil dibuat dan siap digunakan!**
