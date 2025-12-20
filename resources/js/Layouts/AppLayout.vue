<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';
import ESignatureModal from '@/Components/ESignatureModal.vue';
import NavLink from '@/Components/NavLink.vue';
import ProfileUpdateModal from '@/Components/ProfileUpdateModal.vue';
import UserPinModal from '@/Components/UserPinModal.vue';
import LiveSupportWidget from '@/Components/LiveSupportWidget.vue';
import { initializeFirebaseMessaging } from '@/firebase-config';

const sidebarOpen = ref(true);
const showLang = ref(false);
const { locale, t } = useI18n();

const allowedMenus = usePage().props.allowedMenus || [];

const menuGroups = [
    {
        title: () => t('sidebar.main'),
        icon: 'fa-solid fa-bars',
        menus: [
            { name: () => t('sidebar.dashboard'), icon: 'fa-solid fa-home', route: '/home', code: 'dashboard' },
            { name: () => 'Sales Outlet Dashboard', icon: 'fa-solid fa-chart-line', route: '/sales-outlet-dashboard', code: 'sales_outlet_dashboard' },
            { name: () => 'Cashflow Outlet Dashboard', icon: 'fa-solid fa-chart-pie', route: route('cashflow-outlet-dashboard.index'), code: 'cashflow_outlet_dashboard' },
            { name: () => 'My Attendance', icon: 'fa-solid fa-user-clock', route: '/attendance', code: 'my_attendance' },
            { name: () => 'Payment', icon: 'fa-solid fa-shopping-cart', route: '/purchase-requisitions', code: 'purchase_requisition_ops' },
            { name: () => 'Payment Approval Tracker', icon: 'fa-solid fa-chart-line', route: '/purchase-requisitions/payment-tracker', code: 'payment_tracker' },
            { name: () => 'Video Tutorial Gallery', icon: 'fa-solid fa-play-circle', route: '/video-tutorials/gallery' },
            //{ name: () => 'Dashboard Outlet', icon: 'fa-solid fa-map-location-dot', route: '/dashboard-outlet', code: 'dashboard_outlet' },
        ],
    },
    //{
    //    title: () => 'Dokumen Bersama',
    //    icon: 'fa-solid fa-file-alt',
    //    collapsible: true,
    //    open: ref(false),
    //    menus: [
        //        { name: () => 'Daftar Dokumen', icon: 'fa-solid fa-list', route: '/shared-documents', code: 'shared_documents_list' },
        //        { name: () => 'Upload Dokumen', icon: 'fa-solid fa-upload', route: '/shared-documents/create', code: 'shared_documents_create' },
    //    ],
    //  },
    //{
    //    title: () => t('sidebar.maintenance'),
    //    icon: 'fa-solid fa-screwdriver-wrench',
    //    collapsible: true,
    //    open: ref(false),
    //    menus: [
    //        { name: () => 'MT Dashboard', icon: 'fa-solid fa-gauge', route: route('dashboard.maintenance'), code: 'mt_dashboard' },
    //        { name: () => 'Maintenance Order', icon: 'fa-solid fa-clipboard-check', route: '/maintenance-order', code: 'maintenance_order' },
    //        { name: () => 'Maintenance Order List', icon: 'fa-solid fa-list', route: '/maintenance-order/list', code: 'maintenance_order_list' },
    //        { name: () => 'Kalender Jadwal', icon: 'fa-solid fa-calendar-alt', route: '/maintenance-order/schedule-calendar', code: 'maintenance_schedule_calendar' },
    //    ],
    //},
    {
        title: () => 'Master Data',
        icon: 'fa-solid fa-database',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Categories', icon: 'fa-solid fa-tags', route: '/categories', code: 'categories' },
            { name: () => 'Sub Category', icon: 'fa-solid fa-tag', route: '/sub-categories', code: 'sub_categories' },
            { name: () => 'Units', icon: 'fa-solid fa-ruler', route: '/units', code: 'units' },
            { name: () => 'Items', icon: 'fa-solid fa-boxes-stacked', route: '/items', code: 'items' },
            { name: () => 'Repack', icon: 'fa-solid fa-box-open', route: '/repack', code: 'repack' },
            { name: () => 'Menu Type', icon: 'fa-solid fa-list', route: '/menu-types', code: 'menu_types' },
            { name: () => 'Modifiers', icon: 'fa-solid fa-sliders', route: '/modifiers', code: 'modifiers' },
            { name: () => 'Modifier Options', icon: 'fa-solid fa-sliders', route: '/modifier-options', code: 'modifier_options' },
            { name: () => 'Warehouses', icon: 'fa-solid fa-warehouse', route: '/warehouses', code: 'warehouses' },
            { name: () => 'Warehouse Outlet', icon: 'fa-solid fa-store', route: '/warehouse-outlets', code: 'warehouse_outlets' },
            { name: () => 'Warehouse Division', icon: 'fa-solid fa-sitemap', route: '/warehouse-divisions', code: 'warehouse_divisions' },
            { name: () => 'Outlets', icon: 'fa-solid fa-store', route: '/outlets', code: 'outlets' },
            { name: () => 'Customers', icon: 'fa-solid fa-users', route: '/customers', code: 'customers' },
            { name: () => 'Suppliers', icon: 'fa-solid fa-truck', route: '/suppliers', code: 'suppliers' },
            { name: () => 'Regions', icon: 'fa-solid fa-globe-asia', route: '/regions', code: 'regions' },
            { name: () => 'Item Schedule', icon: 'fa-solid fa-calendar-days', route: '/item-schedules', code: 'item_schedules' },
            { name: () => 'RO Schedule', icon: 'fa-solid fa-calendar-days', route: '/fo-schedules', code: 'fo_schedules' },
            { name: () => 'Items Supplier', icon: 'fa-solid fa-link', route: '/item-supplier', code: 'view-item-supplier' },
            { name: () => 'Data Investor Outlet', icon: 'fa-solid fa-user-tie', route: '/investors', code: 'data_investor_outlet' },
            { name: () => 'Officer Check', icon: 'fa-solid fa-user-check', route: '/officer-check', code: 'officer_check' },
            { name: () => 'Jenis Pembayaran', icon: 'fa-solid fa-money-bill', route: '/payment-types', code: 'payment_types' },
            { name: () => 'Video Tutorial', icon: 'fa-solid fa-video', route: '/video-tutorials', code: 'master-data-video-tutorials' },
            { name: () => 'Group Video Tutorial', icon: 'fa-solid fa-folder', route: '/video-tutorial-groups', code: 'master-data-video-tutorial-groups' },
            { name: () => 'Locked Budget Food Categories', icon: 'fa-solid fa-lock', route: '/locked-budget-food-categories', code: 'locked_budget_food_categories' },
            { name: () => 'Budget Management', icon: 'fa-solid fa-chart-pie', route: '/budget-management', code: 'budget_management' },
            { name: () => 'Chart of Account', icon: 'fa-solid fa-chart-line', route: '/chart-of-accounts', code: 'chart_of_account' },
        ],
    },
    {
        title: () => 'Quality Assurance',
        icon: 'fa-solid fa-shield-halved',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'QA Categories', icon: 'fa-solid fa-clipboard-list', route: '/qa-categories', code: 'qa_categories' },
            { name: () => 'QA Parameters', icon: 'fa-solid fa-cogs', route: '/qa-parameters', code: 'qa_parameters' },
            { name: () => 'QA Guidance', icon: 'fa-solid fa-clipboard-check', route: '/qa-guidances', code: 'qa_guidances' },
            { name: () => 'Inspections', icon: 'fa-solid fa-camera', route: '/inspections', code: 'inspections' },
        ],
    },
    {
          title: () => 'Ops Management',
          icon: 'fa-solid fa-cogs',
          collapsible: true,
          open: ref(false),
          menus: [
            { name: () => 'Master Daily Report', icon: 'fa-solid fa-chart-line', route: '/master-report', code: 'master_report' },
            { name: () => 'Daily Report', icon: 'fa-solid fa-clipboard-list', route: '/daily-report', code: 'daily_report' },
            { name: () => 'Ticketing System', icon: 'fa-solid fa-ticket-alt', route: '/tickets', code: 'tickets' },
            { name: () => 'PR Tracking Report', icon: 'fa-solid fa-timeline', route: '/purchase-requisitions/tracking-report', code: 'pr_tracking_report' },
          ],
        },
    {
        title: () => 'Human Resource',
        icon: 'fa-solid fa-users-gear',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Data Level', icon: 'fa-solid fa-layer-group', route: '/data-levels', code: 'data_levels' },
            { name: () => 'Data Jabatan', icon: 'fa-solid fa-user-tie', route: '/jabatans', code: 'data_jabatan' },
            { name: () => 'Data Divisi', icon: 'fa-solid fa-building', route: '/divisis', code: 'data_divisi' },
            { name: () => 'Data Karyawan', icon: 'fa-solid fa-users', route: '/users', code: 'data_karyawan' },
           // { name: () => 'Struktur Organisasi', icon: 'fa-solid fa-sitemap', route: '/organization-chart', code: 'organization_chart' },
            { name: () => 'Regional Management', icon: 'fa-solid fa-globe', route: '/regional', code: 'regional_management' },
            { name: () => 'Report Man Power Outlet', icon: 'fa-solid fa-users-gear', route: '/man-power-outlet', code: 'man_power_outlet_report' },
            { name: () => 'Job Vacancy', icon: 'fa-solid fa-briefcase', route: '/admin/job-vacancy', code: 'job_vacancy' },
            { name: () => 'Master Data Outlet', icon: 'fa-solid fa-store', route: '/outlets', code: 'master-data-outlet' },
            { name: () => 'Master Jam Kerja', icon: 'fa-solid fa-clock', route: '/shifts', code: 'shift_view' },
            { name: () => 'Input Shift Mingguan', icon: 'fa-solid fa-calendar-days', route: '/user-shifts', code: 'user_shift_view' },
            { name: () => 'Kalender Jadwal Shift', icon: 'fa-solid fa-calendar-week', route: '/user-shifts/calendar', code: 'user_shift_calendar_view' },
            { name: () => 'Schedule/Attendance Correction', icon: 'fa-solid fa-edit', route: '/schedule-attendance-correction', code: 'schedule_attendance_correction' },
            { name: () => 'Report Schedule/Attendance Correction', icon: 'fa-solid fa-chart-bar', route: '/schedule-attendance-correction/report', code: 'schedule_attendance_correction_report' },
            { name: () => 'Report Absent', icon: 'fa-solid fa-file-lines', route: '/attendance/report', code: 'absent-report' },
            { name: () => 'Libur Nasional', icon: 'fa-solid fa-calendar-day', route: '/kalender-perusahaan', code: 'libur_nasional' },
            { name: () => 'Report Attendance', icon: 'fa-solid fa-fingerprint', route: '/attendance-report', code: 'attendance_report' },
            { name: () => 'Attendance per Outlet', icon: 'fa-solid fa-fingerprint', route: '/attendance-report/employee-summary', code: 'attendance_outlet_summary' },
            { name: () => 'Holiday Attendance', icon: 'fa-solid fa-calendar-day', route: '/holiday-attendance', code: 'holiday_attendance' },
            { name: () => 'Extra Off & PH Report', icon: 'fa-solid fa-chart-line', route: '/extra-off-report', code: 'extra_off_report' },
            { name: () => 'Master Payroll', icon: 'fa-solid fa-money-check-dollar', route: '/payroll/master', code: 'payroll_master' },
            { name: () => 'Payroll', icon: 'fa-solid fa-file-invoice-dollar', route: '/payroll/report', code: 'payroll_report' },
            { name: () => 'Employee Movement', icon: 'fa-solid fa-people-arrows', route: '/employee-movements', code: 'employee_movement' },
            { name: () => 'Employee Resignation', icon: 'fa-solid fa-user-minus', route: '/employee-resignations', code: 'employee_resignation' },
            { name: () => 'Outlet/HO Inspection', icon: 'fa-solid fa-clipboard-check', route: '/dynamic-inspections', code: 'dynamic_inspection' },
            { name: () => 'Coaching', icon: 'fa-solid fa-user-graduate', route: '/coaching', code: 'coaching' },
            { name: () => 'Employee Survey', icon: 'fa-solid fa-clipboard-list', route: '/employee-survey', code: 'employee_survey' },
            { name: () => 'Employee Survey Report', icon: 'fa-solid fa-chart-bar', route: '/employee-survey-report', code: 'employee_survey_report' },
            { name: () => 'Master Soal', icon: 'fa-solid fa-clipboard-question', route: '/master-soal-new', code: 'master_soal' },
            { name: () => 'Enroll Test', icon: 'fa-solid fa-user-graduate', route: '/enroll-test', code: 'enroll_test' },
            { name: () => 'My Tests', icon: 'fa-solid fa-clipboard-check', route: '/my-tests', code: 'my_tests' },
            { name: () => 'Report Hasil Test', icon: 'fa-solid fa-chart-line', route: '/enroll-test-report', code: 'enroll_test_report' },
            { name: () => 'Manajemen Cuti', icon: 'fa-solid fa-calendar-days', route: '/leave-management', code: 'leave_management' },
            { name: () => 'Report Travel & Kasbon', icon: 'fa-solid fa-plane', route: '/travel-kasbon-report', code: 'travel_kasbon_report' },
        ],
    },
    {
        title: () => 'Outlet Management',
        icon: 'fa-solid fa-store',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: 'Dashboard Sales Outlet', route: '/outlet-dashboard', icon: 'fa-store', group: 'Outlet Management', order: 0 },
            { name: () => 'Request Order (RO)', icon: 'fa-solid fa-calendar-check', route: '/floor-order', code: 'floor_order' },
            { name: () => 'Outlet Good Receive', icon: 'fa-solid fa-truck-loading', route: '/outlet-food-good-receives', code: 'outlet_food_good_receive' },
            { name: () => 'Good Receive Outlet Supplier', icon: 'fa-solid fa-truck-arrow-right', route: '/good-receive-outlet-supplier', code: 'good_receive_outlet_supplier' },
            { name: () => 'Outlet Stock Adjustment', icon: 'fa-solid fa-boxes-stacked', route: '/outlet-food-inventory-adjustment', code: 'outlet_stock_adjustment' },
            { name: () => 'Laporan Stok Akhir Outlet', icon: 'fa-solid fa-clipboard-list', route: '/outlet-inventory/stock-position', code: 'outlet_inventory_stock_position' },
            { name: () => 'Saldo Awal Stok Outlet', icon: 'fa-solid fa-warehouse', route: '/outlet-stock-balances', code: 'outlet_stock_balances' },
            { name: () => 'Kartu Stok Outlet', icon: 'fa-solid fa-file-lines', route: '/outlet-inventory/stock-card', code: 'outlet_stock_card' },
            { name: () => 'Laporan Nilai Persediaan Outlet', icon: 'fa-solid fa-coins', route: '/outlet-inventory/inventory-value-report', code: 'outlet_inventory_value_report' },
            { name: () => 'Laporan Rekap Persediaan per Kategori Outlet', icon: 'fa-solid fa-chart-pie', route: '/outlet-inventory/category-recap-report', code: 'outlet_category_recap_report' },
            { name: () => 'Category Cost Outlet', icon: 'fa-solid fa-trash', route: '/outlet-internal-use-waste', code: 'outlet_internal_use_waste' },
            { name: () => 'Outlet Transfer', icon: 'fa-solid fa-right-left', route: '/outlet-transfer', code: 'outlet_transfer' },
            { name: () => 'Internal Warehouse Transfer', icon: 'fas fa-exchange-alt', route: '/internal-warehouse-transfer', code: 'internal_warehouse_transfer' },
            { name: () => 'Retail Food', icon: 'fa-solid fa-store', route: '/retail-food', code: 'view-retail-food' },
            { name: () => 'Retail Non Food', icon: 'fa-solid fa-shopping-bag', route: '/retail-non-food', code: 'view-retail-non-food' },
            { name: () => 'Outlet Food Return', icon: 'fa-solid fa-undo', route: '/outlet-food-return', code: 'outlet_food_return' },
            { name: () => 'Stock Opname', icon: 'fa-solid fa-clipboard-check', route: '/stock-opnames', code: 'stock_opname' },
            { name: () => 'Report Invoice Outlet', icon: 'fa-solid fa-file-invoice', route: '/report-invoice-outlet', code: 'report_invoice_outlet' },
            { name: () => 'Stock Cut', icon: 'fa-solid fa-scissors', route: '/stock-cut', code: 'stock_cut' },
            { name: () => 'Outlet WIP Production', icon: 'fa-solid fa-industry', route: '/outlet-wip', code: 'outlet_wip_production' },
            { name: () => 'Laporan Outlet WIP', icon: 'fa-solid fa-file-lines', route: '/outlet-wip/report', code: 'outlet_wip_report' },
        ],
    },
    {
        title: () => 'Outlet Report',
        icon: 'fa-solid fa-chart-line',
        collapsible: true,
        open: ref(false),
        menus: [
        { name: () => 'Sales Report', icon: 'fa-solid fa-chart-line', route: '/report-sales-simple', code: 'outlet_sales_report' },
        { name: () => 'Opex Outlet Dashboard', icon: 'fa-solid fa-chart-pie', route: '/opex-outlet-dashboard', code: 'opex_outlet_dashboard' },
            { name: () => 'Daily Outlet Revenue', icon: 'fa-solid fa-chart-bar', route: '/report-daily-outlet-revenue', code: 'daily_outlet_revenue' },
            { name: () => 'Weekly Outlet FB Revenue', icon: 'fa-solid fa-calendar-week', route: '/report-weekly-outlet-fb-revenue', code: 'weekly_outlet_fb_revenue' },
            { name: () => 'Daily Revenue Forecast', icon: 'fa-solid fa-chart-line', route: '/report-daily-revenue-forecast', code: 'daily_revenue_forecast' },
            { name: () => 'Monthly FB Revenue Performance', icon: 'fa-solid fa-chart-bar', route: '/report-monthly-fb-revenue-performance', code: 'monthly_fb_revenue_performance' },
         
            { name: () => 'Receiving Sheet', icon: 'fa-solid fa-receipt', route: '/report-receiving-sheet', code: 'receiving_sheet' },
            { name: () => 'Item Engineering', icon: 'fa-solid fa-cogs', route: '/item-engineering', code: 'item_engineering' },
        ],
    },
    {
        title: () => 'HO Finance',
        icon: 'fa-solid fa-building-columns',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Jurnal', icon: 'fa-solid fa-book', route: '/jurnal', code: 'jurnal' },
            { name: () => 'Contra Bon', icon: 'fa-solid fa-file-circle-xmark', route: '/contra-bons', code: 'contra_bon' },
            { name: () => 'Food Payment', icon: 'fa-solid fa-money-bill-transfer', route: '/food-payments', code: 'food_payment' },
            { name: () => 'Non Food Payment', icon: 'fa-solid fa-credit-card', route: '/non-food-payments', code: 'non_food_payment' },
            { name: () => 'OPEX Report', icon: 'fa-solid fa-chart-line', route: '/opex-report', code: 'opex_report' },
            { name: () => 'OPEX By Category', icon: 'fa-solid fa-chart-pie', route: '/opex-by-category', code: 'opex_by_category' },
            //{ name: () => 'MT PO Payment', icon: 'fa-solid fa-money-bill-wave', route: route('mt-po-payment.index'), code: 'mt_po_payment' },
            { name: () => 'Outlet Payments', icon: 'fa-solid fa-money-bill', route: route('outlet-payments.index'), code: 'outlet_payments' },
            //{ name: () => 'Outlet Payment Supplier', icon: 'fa-solid fa-money-bill', route: route('outlet-payment-suppliers.index'), code: 'outlet_payment_suppliers' },
           
            //{ name: () => 'PR Payment', icon: 'fa-solid fa-credit-card', route: '/payments', code: 'pr_payment' },
            {
                name: () => 'Report Penjualan Pivot per Outlet per Sub Kategori',
                icon: 'fa-solid fa-table-columns',
                route: '/report-sales-pivot-per-outlet-sub-category',
                code: 'report_sales_pivot_per_outlet_sub_category',
            },
            {
                name: () => 'Report Rekap FJ',
                icon: 'fa-solid fa-table-list',
                route: '/report-rekap-fj',
                code: 'report_rekap_fj',
            },
            { name: () => 'Report Hutang', icon: 'fa-solid fa-file-invoice-dollar', route: '/debt-report', code: 'debt_report' },
        ],
    },
    {
        title: () => 'Purchasing',
        icon: 'fa-solid fa-shopping-bag',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Purchase Order Foods', icon: 'fa-solid fa-file-invoice-dollar', route: '/po-foods', code: 'po_foods' },
            { name: () => 'Purchase Order Ops', icon: 'fa-solid fa-file-invoice', route: '/po-ops', code: 'purchase_order_ops' },
            { name: () => 'Report PO GR', icon: 'fa-solid fa-chart-line', route: '/po-report', code: 'po_report' },
        ],
    },
    {
        title: () => 'Warehouse Management',
        icon: 'fa-solid fa-warehouse',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Purchase Requisition Foods', icon: 'fa-solid fa-file-invoice', route: '/pr-foods', code: 'pr_foods' },
            { name: () => 'Good Receive', icon: 'fa-solid fa-truck', route: '/food-good-receive', code: 'food_good_receive' },
            { name: () => 'Food Good Receive Report', icon: 'fa-solid fa-chart-bar', route: '/food-good-receive-report', code: 'food_good_receive_report' },
            { name: () => 'Pindah Gudang', icon: 'fa-solid fa-right-left', route: '/warehouse-transfer', code: 'warehouse_transfer' },
            { name: () => 'Stock Adjustment', icon: 'fa-solid fa-boxes-stacked', route: '/food-inventory-adjustment', code: 'stock_adjustment' },
            { name: () => 'Packing List', icon: 'fa-solid fa-box', route: '/packing-list', code: 'packing_list' },
            { name: () => 'Delivery Order', icon: 'fa-solid fa-truck-arrow-right', route: '/delivery-order', code: 'delivery_order' },
            { name: () => 'Penjualan Warehouse Retail', icon: 'fa-solid fa-store', route: '/retail-warehouse-sale', code: 'retail_warehouse_sale' },
            { name: () => 'Warehouse Retail Food', icon: 'fa-solid fa-warehouse', route: '/retail-warehouse-food', code: 'view-retail-warehouse-food' },
            { name: () => 'Saldo Awal Stok', icon: 'fa-solid fa-money-bill-wave', route: '/food-stock-balances', code: 'food_stock_balances' },
            { name: () => 'Laporan Stok Akhir', icon: 'fa-solid fa-clipboard-list', route: '/inventory/stock-position', code: 'inventory_stock_position' },
            { name: () => 'Stock Opname', icon: 'fa-solid fa-clipboard-check', route: '/warehouse-stock-opnames', code: 'warehouse_stock_opname' },
            { name: () => 'Laporan Kartu Stok', icon: 'fa-solid fa-file-lines', route: '/inventory/stock-card', code: 'inventory_stock_card' },
            { name: () => 'Laporan Penerimaan Barang', icon: 'fa-solid fa-truck-ramp-box', route: '/inventory/goods-received-report', code: 'inventory_goods_received_report' },
            { name: () => 'Laporan Nilai Persediaan', icon: 'fa-solid fa-money-check-dollar', route: '/inventory/inventory-value-report', code: 'inventory_value_report' },
            { name: () => 'Laporan Riwayat Perubahan Harga Pokok', icon: 'fa-solid fa-history', route: '/inventory/cost-history-report', code: 'inventory_cost_history_report' },
            { name: () => 'Laporan Stok Minimum', icon: 'fa-solid fa-arrow-down-short-wide', route: '/inventory/minimum-stock-report', code: 'inventory_minimum_stock_report' },
            { name: () => 'Laporan Rekap Persediaan per Kategori', icon: 'fa-solid fa-layer-group', route: '/inventory/category-recap-report', code: 'inventory_category_recap_report' },
            { name: () => 'Laporan Aging Persediaan', icon: 'fa-solid fa-hourglass-half', route: '/inventory/aging-report', code: 'inventory_aging_report' },
            { name: () => 'Internal Use & Waste', icon: 'fa-solid fa-recycle', route: '/internal-use-waste', code: 'internal_use_waste' },
            { name: () => 'Penjualan Antar Gudang', icon: 'fas fa-exchange-alt', route: '/warehouse-sales', code: 'warehouse_sales' },
            { name: () => 'Outlet Rejection', icon: 'fas fa-undo', route: '/outlet-rejections', code: 'outlet_rejection' },
            { name: () => 'Kelola Return Outlet', icon: 'fa-solid fa-building', route: '/head-office-return', code: 'head_office_return' },
        ],
    },
    {
        title: () => 'Cost Control',
        icon: 'fa-solid fa-coins',
        collapsible: true,
        open: ref(false),
        menus: [
            {
                name: () => 'Laporan Perubahan Harga PO',
                icon: 'fa-solid fa-arrow-trend-up',
                route: '/inventory/po-price-change-report',
                code: 'po_price_change_report_view',
            },
            {
                name: () => 'MAC Report',
                icon: 'fa-solid fa-chart-line',
                route: '/mac-report',
                code: 'mac_report',
            },
            {
                name: () => 'Outlet Stock Report',
                icon: 'fa-solid fa-chart-line',
                route: '/outlet-stock-report',
                code: 'outlet_stock_report',
            },
            {
                name: () => 'Report RnD, BM, WM',
                icon: 'fa-solid fa-chart-line',
                route: '/internal-use-waste-report',
                code: 'internal_use_waste_report',
            },
            {
                name: () => 'Report Penjualan per Category',
                icon: 'fa-solid fa-table-list',
                route: '/report-sales-per-category',
                code: 'report_sales_per_category',
            },
            {
                name: () => 'Report Penjualan per Tanggal',
                icon: 'fa-solid fa-calendar-day',
                route: '/report-sales-per-tanggal',
                code: 'report_sales_per_tanggal',
            },
            {
                name: () => 'Report Penjualan All Item ke All Outlet',
                icon: 'fa-solid fa-list-check',
                route: '/report-sales-all-item-all-outlet',
                code: 'report_sales_all_item_all_outlet',
            },
            {
                name: () => 'Report Good Receive Outlet',
                icon: 'fa-solid fa-table-cells-large',
                route: '/report-good-receive-outlet',
                code: 'report_good_receive_outlet',
            },
            {
                name: () => 'Report Retail Food per Supplier',
                icon: 'fa-solid fa-chart-line',
                route: '/retail-food/report-supplier',
                code: 'retail_food_supplier_report',
            },
            {
                name: () => 'Stock Opname Adjustment Report',
                icon: 'fa-solid fa-chart-bar',
                route: '/stock-opname-adjustment-report',
                code: 'stock_opname_adjustment_report',
            },
        ],
    },
    {
        title: () => 'Production',
        icon: 'fa-solid fa-industry',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Butcher', icon: 'fa-solid fa-cut', route: '/butcher-processes', code: 'butcher' },
            { name: () => 'Butcher Report', icon: 'fa-solid fa-file-lines', route: '/butcher-processes/report', code: 'butcher_report' },
            { name: () => 'Laporan Stok & Cost Butcher', icon: 'fa-solid fa-money-bill-trend-up', route: '/butcher-processes/stock-cost-report', code: 'butcher_stock_cost_report' },
            { name: () => 'Laporan Analisis Butcher', icon: 'fa-solid fa-chart-line', route: '/butcher-processes/analysis-report', code: 'butcher_analysis_report' },
            { name: () => 'Summary Hasil Butcher', icon: 'fa-solid fa-list', route: '/butcher-summary-report', code: 'butcher_summary_report' },
            { name: () => 'MK Production', icon: 'fa-solid fa-industry', route: '/mk-production', code: 'mk_production' },
            { name: () => 'Laporan MK Production', icon: 'fa-solid fa-file-lines', route: '/mk-production/report', code: 'mk_production_report' },
        ],
    },
    {
        title: () => 'OPS-Kitchen',
        icon: 'fa-solid fa-utensils',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Action Plan Guest Review', icon: 'fa-solid fa-clipboard-list', route: '/ops-kitchen/action-plan-guest-review', code: 'ops_kitchen_action_plan_guest_review' },
        ],
    },
    {
        title: () => 'Sales & Marketing',
        icon: 'fa-solid fa-bullhorn',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Scrapper Google Review', icon: 'fa-brands fa-google', route: '/scrapper-google-review', code: 'scrapper_google_review' },
            { name: () => 'Promo', icon: 'fa-solid fa-tag', route: '/promos', code: 'promos' },
            { name: () => 'Marketing Visit Checklist', icon: 'fa-solid fa-clipboard-check', route: '/marketing-visit-checklist', code: 'marketing_visit_checklist_view' },
            { name: () => 'Reservasi', icon: 'fa-solid fa-calendar-check', route: '/reservations', code: 'reservations' },
            { name: () => 'Data Roulette', icon: 'fa-solid fa-dice', route: '/roulette', code: 'data_roulette' },
        ],
    },
    {
        title: () => 'User Management',
        icon: 'fa-solid fa-user-gear',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Role Management', icon: 'fa-solid fa-user-shield', route: '/roles', code: 'role_management' },
            { name: () => 'User Role Setting', icon: 'fa-solid fa-users-cog', route: '/user-roles', code: 'user_role_setting' },
            { name: () => 'Menu Management', icon: 'fa-solid fa-bars-progress', route: '/menus', code: 'menu_management' },
        ],
    },
    {
        title: () => 'Support',
        icon: 'fa-solid fa-headset',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Support Admin Panel', icon: 'fa-solid fa-comments', route: '/support/admin', code: 'support_admin_panel' },
            { name: () => 'Monitoring User Aktif', icon: 'fa-solid fa-users-line', route: '/monitoring/active-users', code: 'monitoring_active_users' },
            { name: () => 'Activity Log Report', icon: 'fa-solid fa-list-alt', route: '/report/activity-log', code: 'activity_log_report' },
        ],
    },
    {
        title: () => 'Announcement',
        icon: 'fa-solid fa-bullhorn',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Announcement', icon: 'fa-solid fa-bullhorn', route: '/announcement', code: 'announcement' },
        ],
    },
    {
        title: () => 'CRM',
        icon: 'fa-solid fa-handshake',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Data Member', icon: 'fa-solid fa-users', route: '/members', code: 'crm_members' },
            { name: () => 'Migrasi Data Member', icon: 'fa-solid fa-database', route: '/member-migration', code: 'member_migration' },
            { name: () => 'Push Notification', icon: 'fa-solid fa-bell', route: '/push-notification', code: 'push_notification' },
            { name: () => 'Dashboard CRM', icon: 'fa-solid fa-chart-line', route: '/crm/dashboard', code: 'crm_dashboard' },
            { name: () => 'Customer Analytics', icon: 'fa-solid fa-chart-pie', route: '/crm/customer-analytics', code: 'crm_analytics' },
            { name: () => 'Member Reports', icon: 'fa-solid fa-file-lines', route: '/crm/member-reports', code: 'crm_reports' },
            { name: () => 'Point Management', icon: 'fa-solid fa-coins', route: '/crm/point-management', code: 'crm_point_management' },
            { name: () => 'Member Apps Settings', icon: 'fa-solid fa-mobile-screen-button', route: '/admin/member-apps-settings', code: 'member_apps_settings' },
        ],
    },
       
    {
        title: () => 'LMS',
        icon: 'fa-solid fa-graduation-cap',
        collapsible: true,
        open: ref(false),
        menus: [
           
            { name: () => 'Kategori Training', icon: 'fa-solid fa-folder', route: '/lms/categories', code: 'lms-categories' },
            { name: () => 'Training', icon: 'fa-solid fa-book', route: '/lms/courses', code: 'lms-courses' },      
            { name: () => 'Quiz', icon: 'fa-solid fa-question-circle', route: '/lms/quizzes', code: 'lms-quizzes' },
            { name: () => 'Kuesioner', icon: 'fa-solid fa-clipboard-list', route: '/lms/questionnaires', code: 'lms-questionnaires' },    
           // { name: () => 'Sertifikat', icon: 'fa-solid fa-certificate', route: '/lms/certificates', code: 'lms-certificates' },
            { name: () => 'Template Sertifikat', icon: 'fa-solid fa-certificate', route: '/lms/certificate-templates', code: 'lms-certificate-templates' },          
            { name: () => 'Jadwal Training', icon: 'fa-solid fa-calendar-alt', route: '/lms/schedules', code: 'lms-schedules' },
            { name: () => 'Trainer Report', icon: 'fa-solid fa-chart-line', route: '/lms/trainer-report-page', code: 'lms-trainer-report' },
            { name: () => 'Laporan Training Karyawan', icon: 'fa-solid fa-users', route: '/lms/employee-training-report-page', code: 'lms-employee-training-report' },
            { name: () => 'Training Report', icon: 'fa-solid fa-chart-bar', route: '/lms/training-report-page', code: 'lms-training-report' },
            { name: () => 'Quiz Report', icon: 'fa-solid fa-question-circle', route: '/lms/quiz-report-page', code: 'lms-quiz-report' },
        ],
    },
];

const filteredMenuGroups = computed(() =>
  menuGroups.map(group => ({
    ...group,
    menus: group.menus.filter(menu => !menu.code || allowedMenus.includes(menu.code))
  })).filter(group => group.menus.length > 0)
);

const languages = [
    { code: 'id', label: 'Indonesia' },
    { code: 'en', label: 'English' },
];
const currentLang = ref('id');

function toggleGroup(group) {
    if (group.collapsible) group.open.value = !group.open.value;
}

function setLang(code) {
    currentLang.value = code;
    locale.value = code;
    localStorage.setItem('currentLang', code);
}

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

const page = usePage();
const user = computed(() => page.props.auth?.user || { nama_lengkap: '', avatar: null });
const avatarUrl = computed(() => user.value.avatar ? `/storage/${user.value.avatar}` : '/images/avatar-default.png');

// Computed properties for user information
const userOutlet = computed(() => user.value.outlet?.nama_outlet || 'N/A');
const userDivisi = computed(() => user.value.divisi?.nama_divisi || 'N/A');
const userLevel = computed(() => user.value.jabatan?.level?.nama_level || 'N/A');
const userJabatan = computed(() => user.value.jabatan?.nama_jabatan || 'N/A');
const showProfileDropdown = ref(false);
const showESignatureModal = ref(false);
const showProfileModal = ref(false);
const showUserPinModal = ref(false);
const showPayrollPinModal = ref(false);
const showPayrollListModal = ref(false);
const payrollPin = ref('');
const payrollList = ref([]);
const loadingPayrollList = ref(false);
const showPayrollSlipModal = ref(false);
const payrollSlipDetail = ref(null);
const loadingPayrollSlip = ref(false);

// Notification state
const notifications = ref([]);
const showNotifDropdown = ref(false);
const unreadCount = ref(0);
const loading = ref(false);
const lastNotificationIds = ref(new Set()); // Track last known notification IDs
const notificationSound = ref(null);

// Function to play notification sound
function playNotificationSound() {
    if (!notificationSound.value) return;
    
    try {
        notificationSound.value.currentTime = 0; // Reset audio to start
        notificationSound.value.play().catch(error => {
            console.log('Audio play failed:', error);
        });
    } catch (error) {
        console.log('Audio play failed:', error);
    }
}

async function fetchNotifications() {
    try {
        loading.value = true;
        const response = await axios.get('/api/notifications');
        
        // Response is an array of notifications
        const newNotifications = Array.isArray(response.data) ? response.data : (response.data?.notifications || []);
        
        // Check for new unread notifications
        newNotifications.forEach(notif => {
            if (!lastNotificationIds.value.has(notif.id) && !notif.is_read) {
                // This is a new unread notification, show toast
                showToast({ 
                    title: notif.title || (notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info'),
                    message: notif.message, 
                    type: notif.type 
                });
                
                // Play notification sound once
                playNotificationSound();
            }
        });
        
        // Update last known notification IDs
        lastNotificationIds.value = new Set(newNotifications.map(n => n.id));
        
        notifications.value = newNotifications;
    } catch (error) {
        console.error('Error fetching notifications:', error);
        // Don't show error to user, just log it
    } finally {
        loading.value = false;
    }
}

async function fetchUnreadCount() {
    try {
        const response = await axios.get('/api/notifications/unread-count');
        unreadCount.value = response.data.count;
    } catch (error) {
        console.error('Error fetching unread count:', error);
    }
}

async function markAsRead(id) {
    try {
        await axios.post(`/api/notifications/${id}/read`);
        await fetchUnreadCount();
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        await axios.post('/api/notifications/mark-all-read');
        await fetchUnreadCount();
        await fetchNotifications();
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

function handleNotifClick(notif) {
    markAsRead(notif.id);
    
    // Redirect ke URL notifikasi jika ada
    if (notif.url) {
        // Jika URL adalah external (full URL), gunakan window.location.href
        if (notif.url.startsWith('http://') || notif.url.startsWith('https://')) {
            window.location.href = notif.url;
        } else {
            // Jika URL relatif, gunakan Inertia router
            window.location.href = notif.url;
        }
    }
    
    showToast({ 
        title: notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info',
        message: notif.message, 
        type: notif.type 
    });
    showNotifDropdown.value = false;
}

// Fetch notifications on mount and every 30 seconds
onMounted(async () => {
    await fetchNotifications();
    await fetchUnreadCount();
    
    setInterval(async () => {
        await fetchNotifications();
        await fetchUnreadCount();
    }, 30000);

    // Load html5-qrcode library
    if (!window.Html5Qrcode) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode';
        document.body.appendChild(script);
    }

    // Initialize Firebase Messaging for web push notifications
    if (typeof window !== 'undefined' && 'Notification' in window) {
        try {
            await initializeFirebaseMessaging();
        } catch (error) {
            console.error('Error initializing Firebase Messaging:', error);
        }
    }
});

// Toast state
const toasts = ref([]);
function showToast({ title, message, type = 'info', duration = 4000 }) {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, title, message, type });
    setTimeout(() => {
        removeToast(id);
    }, duration);
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// Example: show toast on mount (can be removed)
onMounted(() => {
    // showToast({ title: 'Welcome', message: 'You have 2 new notifications!', type: 'success' });
});

// Payroll functions
async function openPayroll() {
    // Ambil user langsung dari usePage untuk memastikan data terbaru
    const page = usePage();
    const currentUser = page.props.auth?.user;
    
    // Cek apakah user punya pin_payroll
    // Periksa dengan lebih teliti: null, undefined, empty string, atau string kosong setelah trim
    const pinPayroll = currentUser?.pin_payroll;
    
    // Cek dengan lebih ketat: pastikan bukan null, undefined, empty string, atau whitespace only
    // Juga cek jika nilainya adalah string 'null' atau 'undefined'
    const pinPayrollStr = pinPayroll ? String(pinPayroll).trim() : '';
    const hasPinPayroll = pinPayroll !== null && 
                         pinPayroll !== undefined && 
                         pinPayroll !== '' && 
                         pinPayrollStr !== '' &&
                         pinPayrollStr !== 'null' &&
                         pinPayrollStr !== 'undefined';
    
    if (!hasPinPayroll) {
        Swal.fire({
            title: 'PIN Payroll Belum Diatur',
            text: 'Silakan isi PIN Payroll terlebih dahulu di Profile Anda',
            icon: 'warning',
            confirmButtonText: 'Buka Profile',
            showCancelButton: true,
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                showProfileModal.value = true;
            }
        });
        return;
    }
    
    // Tampilkan modal input PIN
    showPayrollPinModal.value = true;
    payrollPin.value = '';
}

async function verifyPayrollPin() {
    if (!payrollPin.value || payrollPin.value.trim() === '') {
        Swal.fire('Peringatan', 'Masukkan PIN Payroll', 'warning');
        return;
    }
    
    try {
        const response = await axios.post('/payroll/verify-pin', {
            pin: payrollPin.value
        });
        
        if (response.data.success) {
            showPayrollPinModal.value = false;
            payrollPin.value = '';
            await fetchPayrollList();
            showPayrollListModal.value = true;
        } else {
            Swal.fire('Error', response.data.message || 'PIN salah', 'error');
        }
    } catch (error) {
        console.error('Error verifying PIN:', error);
        Swal.fire('Error', error.response?.data?.message || 'Terjadi kesalahan saat verifikasi PIN', 'error');
    }
}

async function fetchPayrollList() {
    loadingPayrollList.value = true;
    try {
        const response = await axios.get('/payroll/user-list');
        if (response.data.success) {
            payrollList.value = response.data.data || [];
        } else {
            Swal.fire('Error', response.data.message || 'Gagal mengambil data payroll', 'error');
        }
    } catch (error) {
        console.error('Error fetching payroll list:', error);
        Swal.fire('Error', 'Terjadi kesalahan saat mengambil data payroll', 'error');
    } finally {
        loadingPayrollList.value = false;
    }
}

function printPayrollSlip(payrollDetail) {
    const url = `/payroll/report/print?user_id=${payrollDetail.user_id}&outlet_id=${payrollDetail.outlet_id || ''}&month=${payrollDetail.month}&year=${payrollDetail.year}`;
    window.open(url, '_blank');
}

function viewPayrollDetail(payrollDetail) {
    // Buka detail payroll di tab baru
    const url = `/payroll/report/show?user_id=${payrollDetail.user_id}&outlet_id=${payrollDetail.outlet_id || ''}&month=${payrollDetail.month}&year=${payrollDetail.year}`;
    window.open(url, '_blank');
}

async function viewPayrollSlip(payrollItem) {
    loadingPayrollSlip.value = true;
    try {
        const response = await axios.get('/payroll/user-slip-detail', {
            params: {
                payroll_detail_id: payrollItem.payroll_detail_id,
                type: payrollItem.type
            }
        });
        
        if (response.data.success) {
            payrollSlipDetail.value = response.data.data;
            showPayrollSlipModal.value = true;
        } else {
            Swal.fire('Error', response.data.message || 'Gagal mengambil detail slip gaji', 'error');
        }
    } catch (error) {
        console.error('Error fetching payroll slip detail:', error);
        Swal.fire('Error', 'Terjadi kesalahan saat mengambil detail slip gaji', 'error');
    } finally {
        loadingPayrollSlip.value = false;
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
}
</script>

<template>
<div class="min-h-screen flex bg-gray-100">
    <!-- Audio element for notification sound -->
    <audio ref="notificationSound" src="/sounds/aya_gawean_anyar_ringtone.mp3" preload="auto"></audio>
    
    <!-- Sidebar -->
    <aside :class="['transition-all duration-300 bg-white shadow-lg border-r border-gray-200 flex flex-col fixed z-30 h-full', sidebarOpen ? 'w-72' : 'w-20']">
        <div class="flex items-center justify-between h-20 border-b border-gray-200 px-4">
            <img v-if="sidebarOpen" src="/images/logo.png" alt="Logo" class="h-12 w-auto transition-all duration-300" />
            <img v-else src="/images/logo-icon.png" alt="Logo Icon" class="h-10 w-10 transition-all duration-300" />
            <button @click="toggleSidebar" class="text-gray-400 hover:text-blue-500 transition-all ml-2">
                <i :class="sidebarOpen ? 'fas fa-angle-double-left' : 'fas fa-angle-double-right'"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <div v-for="(group, idx) in filteredMenuGroups" :key="group.title" class="mb-4">
                <div
                    class="px-4 py-3 text-xs font-bold uppercase flex items-center gap-2 cursor-pointer group-title mx-2"
                    :class="sidebarOpen ? 'text-gray-500' : 'text-gray-400'"
                    @click="toggleGroup(group)"
                    v-if="group.collapsible"
                    :title="!sidebarOpen ? (typeof group.title === 'function' ? group.title() : group.title) : ''"
                >
                    <span class="text-lg flex-shrink-0"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen" class="truncate flex-1">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                    <svg v-if="sidebarOpen" :class="['w-4 h-4 transition-transform flex-shrink-0', group.open.value ? 'rotate-90' : '']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </div>
                <div
                    v-else
                    class="px-4 py-3 text-xs font-bold uppercase flex items-center gap-2 group-title mx-2"
                    :class="sidebarOpen ? 'text-gray-500' : 'text-gray-400'"
                    :title="!sidebarOpen ? (typeof group.title === 'function' ? group.title() : group.title) : ''"
                >
                    <span class="text-lg flex-shrink-0"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen" class="truncate flex-1">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                </div>
                <div v-show="!group.collapsible || group.open.value">
                    <Link
                        v-for="menu in group.menus"
                        :key="menu.name"
                        :href="menu.route"
                        class="flex items-center gap-3 px-4 py-2.5 my-1 mx-2 rounded-lg text-gray-700 hover:bg-blue-100 transition-all sidebar-menu relative"
                        :class="[
                            sidebarOpen ? 'justify-start' : 'justify-center',
                            $page.url.startsWith(menu.route) ? 'bg-blue-50 font-bold text-blue-700' : ''
                        ]"
                        :title="!sidebarOpen ? (typeof menu.name === 'function' ? menu.name() : menu.name) : ''"
                    >
                        <span class="text-lg w-6 flex justify-center flex-shrink-0"><i :class="menu.icon"></i></span>
                        <span v-if="sidebarOpen" class="text-sm leading-tight truncate">{{ typeof menu.name === 'function' ? menu.name() : menu.name }}</span>
                    </Link>
                </div>
                <hr v-if="idx < filteredMenuGroups.length - 1" class="my-2 border-gray-200" />
            </div>
        </nav>
    </aside>
    <!-- Main Content -->
    <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', sidebarOpen ? 'ml-72' : 'ml-20']">
        <!-- Navbar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center px-6 justify-between shadow-sm">
            <div class="flex items-center gap-4">
                <button @click="toggleSidebar" class="md:hidden text-gray-500 focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="flex items-center gap-4">
                <!-- Language -->
                <div class="relative">
                    <button class="flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100" @click="showLang = !showLang">
                        <img :src="currentLang === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full" />
                        <span class="hidden md:inline">{{ languages.find(l => l.code === currentLang)?.label }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div v-if="showLang" class="absolute right-0 mt-2 w-40 bg-white border rounded shadow z-50">
                        <div v-for="lang in languages" :key="lang.code" @click="setLang(lang.code); showLang = false" class="flex items-center gap-2 px-4 py-2 hover:bg-blue-50 cursor-pointer">
                            <img :src="lang.code === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full" />
                            <span>{{ lang.label }}</span>
                        </div>
                    </div>
                </div>
                <!-- Fullscreen -->
                <button @click="toggleFullscreen" class="p-2 rounded hover:bg-gray-100">
                    <i class="fas fa-expand"></i>
                </button>
                <!-- Notif -->
                <div class="relative">
                    <button class="p-2 rounded hover:bg-gray-100 relative" @click="showNotifDropdown = !showNotifDropdown">
                        <i class="fas fa-bell"></i>
                        <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">{{ unreadCount }}</span>
                    </button>
                    <div v-if="showNotifDropdown" class="absolute right-0 mt-2 w-80 bg-white border rounded shadow-lg z-50 max-h-96 overflow-y-auto">
                        <div class="flex items-center justify-between px-4 py-2 border-b">
                            <span class="font-bold text-gray-700">Notifikasi</span>
                            <button class="text-xs text-blue-500 hover:underline" @click="markAllAsRead">Tandai semua dibaca</button>
                        </div>
                        <div v-if="loading" class="px-4 py-6 text-center">
                            <i class="fas fa-spinner fa-spin text-blue-500"></i>
                        </div>
                        <div v-else-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400">Tidak ada notifikasi</div>
                        <div v-else>
                            <div v-for="notif in notifications" :key="notif.id" @click="handleNotifClick(notif)" class="px-4 py-3 border-b last:border-b-0 cursor-pointer hover:bg-blue-50 flex gap-2" :class="notif.is_read ? 'bg-gray-50' : 'bg-blue-50/50'">
                                <div class="flex-shrink-0 mt-1">
                                    <i :class="['fas', notif.type === 'success' ? 'fa-check-circle text-green-400' : notif.type === 'error' ? 'fa-exclamation-circle text-red-400' : 'fa-info-circle text-blue-400']"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold text-sm text-gray-800">{{ notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info' }}</div>
                                    <div class="text-xs text-gray-600">{{ notif.message }}</div>
                                    <div class="text-xs text-gray-400 mt-1">{{ notif.time }}</div>
                                    <div v-if="notif.url" class="text-xs text-blue-500 mt-1">
                                        <i class="fas fa-link mr-1"></i>{{ notif.url }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Avatar -->
                <div class="relative">
                    <button @click="showProfileDropdown = !showProfileDropdown" class="flex items-center gap-2 focus:outline-none">
                        <img :src="avatarUrl" alt="Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-blue-200" />
                        <span class="hidden md:inline font-semibold text-gray-700">{{ user.nama_lengkap }}</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div v-if="showProfileDropdown" class="absolute right-0 mt-2 w-64 bg-white border rounded shadow z-50">
                        <div class="px-4 py-3 border-b">
                            <div class="font-bold text-gray-800">{{ user.nama_lengkap }}</div>
                            <div class="mt-2 space-y-1">
                                <div class="text-xs text-gray-500" v-if="userJabatan !== 'N/A'">
                                    <span class="font-medium">Jabatan:</span> {{ userJabatan }}
                                </div>
                                <div class="text-xs text-gray-500" v-if="userLevel !== 'N/A'">
                                    <span class="font-medium">Level:</span> {{ userLevel }}
                                </div>
                                <div class="text-xs text-gray-500" v-if="userDivisi !== 'N/A'">
                                    <span class="font-medium">Divisi:</span> {{ userDivisi }}
                                </div>
                                <div class="text-xs text-gray-500" v-if="userOutlet !== 'N/A'">
                                    <span class="font-medium">Outlet:</span> {{ userOutlet }}
                                </div>
                            </div>
                        </div>
                        <button @click="showProfileModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-user"></i> {{ t('profile.profile') }}
                        </button>
                        <button @click="showESignatureModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-pen-nib"></i> {{ t('profile.esign') }}
                        </button>
                        <button @click="showUserPinModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-key"></i> Kelola PIN Outlet
                        </button>
                        <button @click="openPayroll(); showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-file-invoice-dollar"></i> Payroll
                        </button>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50"
                        >
                            <i class="fa-solid fa-right-from-bracket"></i> {{ t('profile.logout') }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>
        <!-- Slot konten -->
        <main class="flex-1 bg-gray-50">
            <slot />
        </main>
        
        <!-- Toast Notification Container -->
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 items-end">
            <transition-group name="toast-slide" tag="div">
                <div v-for="toast in toasts" :key="toast.id" 
                    class="min-w-[300px] max-w-sm bg-white border-l-4 shadow-lg px-4 py-3 rounded-lg mb-2 flex flex-col gap-1 transform transition-all duration-300"
                    :class="[
                        toast.type === 'success' ? 'border-green-500' : 
                        toast.type === 'error' ? 'border-red-500' : 
                        'border-blue-500'
                    ]">
                    <div class="flex items-start gap-2">
                        <i :class="[
                            'fas mt-1',
                            toast.type === 'success' ? 'fa-check-circle text-green-500' : 
                            toast.type === 'error' ? 'fa-exclamation-circle text-red-500' : 
                            'fa-info-circle text-blue-500'
                        ]"></i>
                        <div class="flex-1">
                            <div class="font-bold text-gray-800">{{ toast.title }}</div>
                            <div class="text-sm text-gray-600">{{ toast.message }}</div>
                        </div>
                        <button @click="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </transition-group>
        </div>
    </div>

    <ESignatureModal 
        :show="showESignatureModal"
        :user="user"
        @close="showESignatureModal = false"
        @saved="showProfileDropdown = false"
    />

    <ProfileUpdateModal
        :show="showProfileModal"
        @close="showProfileModal = false"
    />

    <UserPinModal
        :show="showUserPinModal"
        @close="showUserPinModal = false"
    />

    <!-- Payroll PIN Modal -->
    <div v-if="showPayrollPinModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showPayrollPinModal = false">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Masukkan PIN Payroll</h3>
                <button @click="showPayrollPinModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">PIN Payroll</label>
                <input
                    v-model="payrollPin"
                    type="password"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Masukkan PIN Payroll"
                    @keyup.enter="verifyPayrollPin"
                    autofocus
                />
            </div>
            <div class="flex gap-2 justify-end">
                <button @click="showPayrollPinModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Batal
                </button>
                <button @click="verifyPayrollPin" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Verifikasi
                </button>
            </div>
        </div>
    </div>

    <!-- Payroll List Modal -->
    <div v-if="showPayrollListModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showPayrollListModal = false">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Daftar Payroll Saya</h3>
                <button @click="showPayrollListModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div v-if="loadingPayrollList" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                <p class="mt-2 text-gray-600">Memuat data payroll...</p>
            </div>
            
            <div v-else-if="payrollList.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-file-invoice-dollar text-4xl mb-2"></i>
                <p>Tidak ada data payroll</p>
            </div>
            
            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase border">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase border">Jenis Slip Gaji</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase border">Tanggal Gajian</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase border">Outlet</th>
                            <th class="px-4 py-3 text-center text-xs font-bold uppercase border">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in payrollList" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 border">
                                {{ item.month }}/{{ item.year }}
                            </td>
                            <td class="px-4 py-3 border">
                                <span :class="[
                                    'px-2 py-1 rounded text-xs font-semibold',
                                    item.type === 'gajian1' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
                                ]">
                                    {{ item.type_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 border">
                                {{ item.gajian_date_formatted }}
                            </td>
                            <td class="px-4 py-3 border">
                                {{ item.outlet_name || '-' }}
                            </td>
                            <td class="px-4 py-3 border text-center">
                                <div class="flex gap-2 justify-center">
                                    <button 
                                        @click="viewPayrollSlip(item)" 
                                        class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
                                        title="Lihat Slip Gaji"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payroll Slip Detail Modal -->
    <div v-if="showPayrollSlipModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showPayrollSlipModal = false">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Slip Gaji - {{ payrollSlipDetail?.type_label || '' }}</h3>
                <button @click="showPayrollSlipModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div v-if="loadingPayrollSlip" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                <p class="mt-2 text-gray-600">Memuat detail slip gaji...</p>
            </div>
            
            <div v-else-if="payrollSlipDetail" class="space-y-4">
                <!-- Header Info -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Nama</p>
                            <p class="font-semibold">{{ payrollSlipDetail.nama_lengkap }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">NIK</p>
                            <p class="font-semibold">{{ payrollSlipDetail.nik || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Jabatan</p>
                            <p class="font-semibold">{{ payrollSlipDetail.jabatan || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Divisi</p>
                            <p class="font-semibold">{{ payrollSlipDetail.divisi || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Outlet</p>
                            <p class="font-semibold">{{ payrollSlipDetail.outlet_name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Periode</p>
                            <p class="font-semibold">{{ payrollSlipDetail.month }}/{{ payrollSlipDetail.year }}</p>
                        </div>
                    </div>
                </div>

                <!-- Gajian 1 Content -->
                <div v-if="payrollSlipDetail.type === 'gajian1' && payrollSlipDetail.gajian1" class="space-y-4">
                    <h4 class="text-md font-bold text-gray-800 border-b pb-2">Gajian 1 (Akhir Bulan)</h4>
                    
                    <!-- 1. Gaji Pokok -->
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="font-semibold">1. Gaji Pokok</span>
                        <span class="font-bold text-blue-700">{{ formatCurrency(payrollSlipDetail.gajian1.gaji_pokok) }}</span>
                    </div>
                    
                    <!-- 2. Tunjangan -->
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="font-semibold">2. Tunjangan</span>
                        <span class="font-bold text-blue-700">{{ formatCurrency(payrollSlipDetail.gajian1.tunjangan) }}</span>
                    </div>
                    
                    <!-- 3. Custom Deduction -->
                    <div v-if="payrollSlipDetail.gajian1.custom_deduction_items && payrollSlipDetail.gajian1.custom_deduction_items.length > 0" class="space-y-2">
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="font-semibold">3. Custom Deduction</span>
                            <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian1.custom_deductions) }}</span>
                        </div>
                        <div class="ml-4 space-y-1">
                            <div v-for="(item, index) in payrollSlipDetail.gajian1.custom_deduction_items" :key="index" class="flex justify-between text-sm">
                                <span>{{ item.name }}</span>
                                <span class="text-red-600">- {{ formatCurrency(item.amount) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="font-semibold">3. Custom Deduction</span>
                        <span class="font-bold text-gray-600">- {{ formatCurrency(0) }}</span>
                    </div>
                    
                    <!-- 4. Custom Earning -->
                    <div v-if="payrollSlipDetail.gajian1.custom_earning_items && payrollSlipDetail.gajian1.custom_earning_items.length > 0" class="space-y-2">
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                            <span class="font-semibold">4. Custom Earning</span>
                            <span class="font-bold text-green-700">{{ formatCurrency(payrollSlipDetail.gajian1.custom_earnings) }}</span>
                        </div>
                        <div class="ml-4 space-y-1">
                            <div v-for="(item, index) in payrollSlipDetail.gajian1.custom_earning_items" :key="index" class="flex justify-between text-sm">
                                <span>{{ item.name }}</span>
                                <span class="text-green-600">{{ formatCurrency(item.amount) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span class="font-semibold">4. Custom Earning</span>
                        <span class="font-bold text-gray-600">{{ formatCurrency(0) }}</span>
                    </div>
                    
                    <!-- 5. Telat -->
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="font-semibold">5. Potongan Telat</span>
                        <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian1.potongan_telat) }}</span>
                    </div>
                    
                    <!-- 6. Alpha & Unpaid Leave -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="font-semibold">6. Alpha & Unpaid Leave</span>
                            <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian1.potongan_alpha + payrollSlipDetail.gajian1.potongan_unpaid_leave) }}</span>
                        </div>
                        <div class="ml-4 space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Total Alpha: {{ payrollSlipDetail.gajian1.total_alpha }} hari</span>
                                <span class="text-red-600">- {{ formatCurrency(payrollSlipDetail.gajian1.potongan_alpha) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Potongan Unpaid Leave</span>
                                <span class="text-red-600">- {{ formatCurrency(payrollSlipDetail.gajian1.potongan_unpaid_leave) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 7. Leave Type Breakdown -->
                    <div v-if="payrollSlipDetail.gajian1.leave_data && Object.keys(payrollSlipDetail.gajian1.leave_data).length > 0" class="space-y-2">
                        <div class="p-3 bg-blue-50 rounded">
                            <span class="font-semibold">7. Leave Type Breakdown</span>
                        </div>
                        <div class="ml-4 space-y-1">
                            <div v-for="(days, key) in payrollSlipDetail.gajian1.leave_data" :key="key" class="flex justify-between text-sm">
                                <span>{{ key.replace('_days', '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</span>
                                <span class="text-blue-600">{{ days }} hari</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-3 bg-gray-50 rounded">
                        <span class="font-semibold">7. Leave Type Breakdown</span>
                        <p class="text-sm text-gray-500 mt-1">Tidak ada data cuti</p>
                    </div>
                    
                    <!-- Total Gajian 1 -->
                    <div class="flex justify-between items-center p-4 bg-blue-100 rounded-lg border-2 border-blue-300">
                        <span class="text-lg font-bold">Total Gajian 1</span>
                        <span class="text-xl font-bold text-blue-700">{{ formatCurrency(payrollSlipDetail.gajian1.total_gaji_gajian1) }}</span>
                    </div>
                </div>

                <!-- Gajian 2 Content -->
                <div v-if="payrollSlipDetail.type === 'gajian2' && payrollSlipDetail.gajian2" class="space-y-4">
                    <h4 class="text-md font-bold text-gray-800 border-b pb-2">Gajian 2 (Tanggal 8)</h4>
                    
                    <!-- 1. Service Charge Point -->
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="font-semibold">1. Service Charge (By Point)</span>
                        <span class="font-bold text-green-700">{{ formatCurrency(payrollSlipDetail.gajian2.service_charge_by_point) }}</span>
                    </div>
                    
                    <!-- 2. Service Charge Prorate -->
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="font-semibold">2. Service Charge (Pro Rate)</span>
                        <span class="font-bold text-green-700">{{ formatCurrency(payrollSlipDetail.gajian2.service_charge_pro_rate) }}</span>
                    </div>
                    
                    <!-- Total Service Charge -->
                    <div class="flex justify-between items-center p-3 bg-green-100 rounded border border-green-300">
                        <span class="font-semibold">Total Service Charge</span>
                        <span class="font-bold text-green-700">{{ formatCurrency(payrollSlipDetail.gajian2.service_charge) }}</span>
                    </div>
                    
                    <!-- 3. Uang Makan -->
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="font-semibold">3. Uang Makan</span>
                        <span class="font-bold text-blue-700">{{ formatCurrency(payrollSlipDetail.gajian2.uang_makan) }}</span>
                    </div>
                    
                    <!-- 4. Lembur -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                            <span class="font-semibold">4. Lembur</span>
                            <span class="font-bold text-blue-700">{{ formatCurrency(payrollSlipDetail.gajian2.gaji_lembur) }}</span>
                        </div>
                        <div class="ml-4 space-y-1 text-sm text-gray-600">
                            <div>Total Lembur: {{ payrollSlipDetail.gajian2.total_lembur }} jam</div>
                            <div>Nominal per Jam: {{ formatCurrency(payrollSlipDetail.gajian2.nominal_lembur_per_jam) }}</div>
                        </div>
                    </div>
                    
                    <!-- 5. L & B -->
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="font-semibold">5. L & B</span>
                        <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian2.lb_total) }}</span>
                    </div>
                    
                    <!-- 6. Deviasi -->
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="font-semibold">6. Deviasi</span>
                        <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian2.deviasi_total) }}</span>
                    </div>
                    
                    <!-- 7. City Ledger -->
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="font-semibold">7. City Ledger</span>
                        <span class="font-bold text-red-700">- {{ formatCurrency(payrollSlipDetail.gajian2.city_ledger_total) }}</span>
                    </div>
                    
                    <!-- 8. PH Bonus -->
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="font-semibold">8. PH Bonus</span>
                        <span class="font-bold text-green-700">+ {{ formatCurrency(payrollSlipDetail.gajian2.ph_bonus || 0) }}</span>
                    </div>
                    
                    <!-- Total Gajian 2 -->
                    <div class="flex justify-between items-center p-4 bg-green-100 rounded-lg border-2 border-green-300">
                        <span class="text-lg font-bold">Total Gajian 2</span>
                        <span class="text-xl font-bold text-green-700">{{ formatCurrency(payrollSlipDetail.gajian2.total_gaji_gajian2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Support Widget - Floating di semua halaman -->
    <LiveSupportWidget />
</div>
</template>

<style scoped>
.fas { font-family: 'Font Awesome 5 Free'; font-weight: 900; }

.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s ease;
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-slide-move {
    transition: transform 0.3s ease;
}

.group-title {
    letter-spacing: 0.5px;
    font-size: 12px;
    margin-bottom: 4px;
    border-radius: 6px;
}
.group-title:hover {
    background-color: #f8fafc;
}
.sidebar-menu {
    font-size: 14px;
    transition: all 0.2s ease;
    border-radius: 8px;
}
.sidebar-menu:hover {
    background-color: #f1f5f9 !important;
    transform: translateX(2px);
}
.sidebar-menu.router-link-exact-active,
.sidebar-menu.bg-blue-50 {
    background: #dbeafe !important;
    color: #1d4ed8 !important;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
}
hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 8px 0;
}
</style> 