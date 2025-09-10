# Sales Outlet Dashboard - Dokumentasi Lengkap

## 📊 Overview

Sales Outlet Dashboard adalah dashboard yang canggih dan informatif untuk menganalisis performa sales outlet berdasarkan data dari tabel `orders`, `order_items`, `order_payment`, dan `order_promos`.

## 🎯 Fitur Utama

### 1. **Overview Metrics**
- **Total Orders**: Jumlah pesanan dengan growth indicator
- **Total Revenue**: Total pendapatan dengan growth indicator  
- **Average Order Value**: Rata-rata nilai pesanan
- **Total Customers**: Total pelanggan dengan rata-rata pax per order

### 2. **Sales Trend Analysis**
- Grafik trend penjualan harian/mingguan/bulanan
- Kombinasi revenue dan jumlah orders
- Dual y-axis untuk visualisasi yang optimal

### 3. **Top Selling Items**
- 15 item terlaris berdasarkan revenue
- Data: quantity sold, revenue, order count, average price
- Tabel dengan sorting otomatis

### 4. **Payment Methods Analysis**
- Distribusi metode pembayaran
- Chart donut dengan persentase
- Data: transaction count, total amount, average amount

### 5. **Hourly Sales Analysis**
- Analisis penjualan per jam
- Bar chart untuk visualisasi
- Data: orders, revenue, average order value per jam

### 6. **Order Status Distribution**
- Distribusi status pesanan
- Chart donut dengan persentase
- Data: count, total revenue per status

### 7. **Recent Orders**
- 25 pesanan terbaru
- Informasi lengkap: nomor, table, customer, pax, total, status, waktu
- Status dengan color coding

### 8. **Additional Metrics**
- **Total Discount**: Total diskon yang diberikan
- **Service Charge**: Total biaya layanan
- **Commission Fee**: Total komisi
- **Promo Usage**: Penggunaan promo dengan persentase

### 9. **Peak Hours Analysis**
- 5 jam dengan order terbanyak
- Data: order count, revenue, average order value, total customers

## 🛠️ Teknologi yang Digunakan

### Backend
- **Laravel**: Framework PHP
- **MySQL**: Database
- **Carbon**: Date manipulation
- **Inertia.js**: SPA framework

### Frontend
- **Vue.js 3**: JavaScript framework
- **Chart.js**: Chart library
- **Tailwind CSS**: CSS framework
- **SweetAlert2**: Alert library

## 📁 Struktur File

```
app/
├── Http/Controllers/
│   └── SalesOutletDashboardController.php
├── Services/
│   └── SalesOutletDashboardService.php
resources/js/Pages/SalesOutletDashboard/
└── Index.vue
routes/
└── web.php
```

## 🚀 Cara Menggunakan

### 1. **Akses Dashboard**
```
GET /sales-outlet-dashboard
```

### 2. **Filter Options**
- **Outlet**: Pilih outlet tertentu atau "Semua Outlet"
- **Date Range**: Pilih rentang tanggal
- **Period**: Harian, Mingguan, atau Bulanan

### 3. **Export Data**
- Klik tombol "Export Data"
- Data akan diunduh dalam format CSV
- Berisi detail semua pesanan dalam rentang waktu yang dipilih

## 📊 Metrik yang Dihitung

### Overview Metrics
```sql
SELECT 
    COUNT(*) as total_orders,
    SUM(grand_total) as total_revenue,
    AVG(grand_total) as avg_order_value,
    SUM(pax) as total_customers,
    AVG(pax) as avg_pax_per_order,
    SUM(discount) as total_discount,
    SUM(service) as total_service_charge,
    SUM(commfee) as total_commission_fee,
    SUM(manual_discount_amount) as total_manual_discount
FROM orders 
WHERE DATE(created_at) BETWEEN ? AND ?
```

### Growth Calculation
```php
$growth = (($current - $previous) / $previous) * 100;
```

### Top Items
```sql
SELECT 
    oi.item_name,
    SUM(oi.qty) as total_qty,
    SUM(oi.subtotal) as total_revenue,
    COUNT(DISTINCT oi.order_id) as order_count,
    AVG(oi.price) as avg_price
FROM order_items oi
INNER JOIN orders o ON oi.order_id = o.id
WHERE DATE(o.created_at) BETWEEN ? AND ?
GROUP BY oi.item_name
ORDER BY total_revenue DESC
LIMIT 15
```

## 🎨 UI/UX Features

### 1. **Responsive Design**
- Mobile-first approach
- Grid layout yang adaptif
- Charts yang responsive

### 2. **Interactive Charts**
- **Line Chart**: Sales trend dengan dual y-axis
- **Bar Chart**: Hourly sales analysis
- **Doughnut Charts**: Payment methods & order status

### 3. **Color Coding**
- **Green**: Positive growth, completed orders
- **Red**: Negative growth, cancelled orders
- **Blue**: Pending orders, primary actions
- **Yellow**: Processing orders, warnings

### 4. **Loading States**
- Skeleton loading untuk charts
- Spinner untuk data loading
- Disabled states untuk buttons

## 📈 Performance Optimizations

### 1. **Database Optimizations**
- Indexed queries pada `created_at`
- Efficient JOIN operations
- COALESCE untuk null handling

### 2. **Frontend Optimizations**
- Computed properties untuk chart data
- Lazy loading untuk heavy components
- Debounced filter changes

### 3. **Caching Strategy**
- Service layer untuk data processing
- Reusable metric calculations
- Efficient data transformations

## 🔧 Customization Options

### 1. **Chart Customization**
```javascript
const chartOptions = {
    responsive: true,
    plugins: {
        legend: { position: 'top' },
        title: { display: true, text: 'Custom Title' }
    }
};
```

### 2. **Color Themes**
```css
:root {
    --primary-color: #3B82F6;
    --success-color: #10B981;
    --warning-color: #F59E0B;
    --danger-color: #EF4444;
}
```

### 3. **Date Formats**
```javascript
function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
```

## 📋 API Endpoints

### 1. **Dashboard Index**
```
GET /sales-outlet-dashboard
Parameters:
- outlet_code (optional)
- date_from (optional)
- date_to (optional)
- period (optional)
```

### 2. **Export Data**
```
GET /sales-outlet-dashboard/export
Parameters:
- outlet_code (optional)
- date_from (optional)
- date_to (optional)
```

## 🎯 Use Cases

### 1. **Management Dashboard**
- Monitor performa outlet secara real-time
- Analisis trend penjualan
- Identifikasi item terlaris

### 2. **Operational Analysis**
- Analisis jam sibuk
- Distribusi metode pembayaran
- Status pesanan

### 3. **Business Intelligence**
- Growth analysis
- Customer behavior
- Revenue optimization

## 🔮 Future Enhancements

### 1. **Real-time Updates**
- WebSocket integration
- Live data refresh
- Push notifications

### 2. **Advanced Analytics**
- Predictive analytics
- Machine learning insights
- Customer segmentation

### 3. **Mobile App**
- React Native app
- Offline capabilities
- Push notifications

## 🐛 Troubleshooting

### 1. **Common Issues**
- **Slow Loading**: Check database indexes
- **Chart Not Rendering**: Verify Chart.js installation
- **Export Fails**: Check file permissions

### 2. **Performance Issues**
- **Large Datasets**: Implement pagination
- **Memory Usage**: Optimize queries
- **Slow Charts**: Reduce data points

## 📚 Dependencies

### Backend
```json
{
    "laravel/framework": "^10.0",
    "inertiajs/inertia-laravel": "^0.6.0"
}
```

### Frontend
```json
{
    "vue": "^3.0.0",
    "chart.js": "^4.0.0",
    "vue-chartjs": "^5.0.0",
    "sweetalert2": "^11.0.0"
}
```

## 🎉 Conclusion

Sales Outlet Dashboard memberikan insight yang komprehensif tentang performa sales outlet dengan visualisasi yang menarik dan data yang akurat. Dashboard ini dapat membantu management dalam mengambil keputusan yang tepat berdasarkan data real-time.

---

**Dibuat dengan ❤️ untuk analisis sales outlet yang lebih baik!**
