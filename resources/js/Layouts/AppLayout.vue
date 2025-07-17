<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import ESignatureModal from '@/Components/ESignatureModal.vue';
import NavLink from '@/Components/NavLink.vue';
import ProfileUpdateModal from '@/Components/ProfileUpdateModal.vue';

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
            { name: () => 'Video Tutorial Gallery', icon: 'fa-solid fa-play-circle', route: '/video-tutorials/gallery' },
            //{ name: () => 'Dashboard Outlet', icon: 'fa-solid fa-map-location-dot', route: '/dashboard-outlet', code: 'dashboard_outlet' },
        ],
    },
    {
        title: () => t('sidebar.maintenance'),
        icon: 'fa-solid fa-screwdriver-wrench',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'MT Dashboard', icon: 'fa-solid fa-gauge', route: route('dashboard.maintenance'), code: 'mt_dashboard' },
            { name: () => 'Maintenance Order', icon: 'fa-solid fa-clipboard-check', route: '/maintenance-order', code: 'maintenance_order' },
            { name: () => 'Kalender Jadwal', icon: 'fa-solid fa-calendar-alt', route: '/maintenance-order/schedule-calendar', code: 'maintenance_schedule_calendar' },
        ],
    },
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
            { name: () => 'Data Karyawan', icon: 'fa-solid fa-users', route: '/users', code: 'data_karyawan' },
            { name: () => 'Job Vacancy', icon: 'fa-solid fa-briefcase', route: '/admin/job-vacancy', code: 'job_vacancy' },
            { name: () => 'Master Data Outlet', icon: 'fa-solid fa-store', route: '/outlets', code: 'master-data-outlet' },
            { name: () => 'Master Jam Kerja', icon: 'fa-solid fa-clock', route: '/shifts', code: 'shift_view' },
            { name: () => 'Input Shift Mingguan', icon: 'fa-solid fa-calendar-days', route: '/user-shifts', code: 'user_shift_view' },
            { name: () => 'Kalender Jadwal Shift', icon: 'fa-solid fa-calendar-week', route: '/user-shifts/calendar', code: 'user_shift_calendar_view' },
            { name: () => 'Libur Nasional', icon: 'fa-solid fa-calendar-day', route: '/kalender-perusahaan', code: 'libur_nasional' },
            { name: () => 'Report Attendance', icon: 'fa-solid fa-fingerprint', route: '/attendance-report', code: 'attendance_report' },
            { name: () => 'Master Payroll', icon: 'fa-solid fa-money-check-dollar', route: '/payroll/master', code: 'payroll_master' },
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
            { name: () => 'Retail Food', icon: 'fa-solid fa-store', route: '/retail-food', code: 'view-retail-food' },
            { name: () => 'Retail Non Food', icon: 'fa-solid fa-shopping-bag', route: '/retail-non-food', code: 'view-retail-non-food' },
            { name: () => 'Report Invoice Outlet', icon: 'fa-solid fa-file-invoice', route: '/report-invoice-outlet', code: 'report_invoice_outlet' },
            { name: () => 'Stock Cut', icon: 'fa-solid fa-scissors', route: '/stock-cut', code: 'stock_cut' },
        ],
    },
    {
        title: () => 'Outlet Sales Report',
        icon: 'fa-solid fa-chart-line',
        collapsible: true,
        open: ref(false),
        menus: [
        { name: () => 'Sales Report', icon: 'fa-solid fa-chart-line', route: '/report-sales-simple', code: 'outlet_sales_report' },
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
            { name: () => 'Contra Bon', icon: 'fa-solid fa-file-circle-xmark', route: '/contra-bons', code: 'contra_bon' },
            { name: () => 'Food Payment', icon: 'fa-solid fa-money-bill-transfer', route: '/food-payments', code: 'food_payment' },
            { name: () => 'MT PO Payment', icon: 'fa-solid fa-money-bill-wave', route: route('mt-po-payment.index'), code: 'mt_po_payment' },
            { name: () => 'Outlet Payments', icon: 'fa-solid fa-money-bill', route: route('outlet-payments.index'), code: 'outlet_payments' },
            { name: () => 'Outlet Payment Supplier', icon: 'fa-solid fa-money-bill', route: route('outlet-payment-suppliers.index'), code: 'outlet_payment_suppliers' },
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
        ],
    },
    {
        title: () => 'Warehouse Management',
        icon: 'fa-solid fa-warehouse',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Purchase Requisition Foods', icon: 'fa-solid fa-file-invoice', route: '/pr-foods', code: 'pr_foods' },
            { name: () => 'Purchase Order Foods', icon: 'fa-solid fa-file-invoice-dollar', route: '/po-foods', code: 'po_foods' },
            { name: () => 'Good Receive', icon: 'fa-solid fa-truck', route: '/food-good-receive', code: 'food_good_receive' },
            { name: () => 'Pindah Gudang', icon: 'fa-solid fa-right-left', route: '/warehouse-transfer', code: 'warehouse_transfer' },
            { name: () => 'Stock Adjustment', icon: 'fa-solid fa-boxes-stacked', route: '/food-inventory-adjustment', code: 'stock_adjustment' },
            { name: () => 'Packing List', icon: 'fa-solid fa-box', route: '/packing-list', code: 'packing_list' },
            { name: () => 'Delivery Order', icon: 'fa-solid fa-truck-arrow-right', route: '/delivery-order', code: 'delivery_order' },
            { name: () => 'Penjualan Warehouse Retail', icon: 'fa-solid fa-store', route: '/retail-warehouse-sale', code: 'retail_warehouse_sale' },
            { name: () => 'Saldo Awal Stok', icon: 'fa-solid fa-money-bill-wave', route: '/food-stock-balances', code: 'food_stock_balances' },
            { name: () => 'Laporan Stok Akhir', icon: 'fa-solid fa-clipboard-list', route: '/inventory/stock-position', code: 'inventory_stock_position' },
            { name: () => 'Laporan Kartu Stok', icon: 'fa-solid fa-file-lines', route: '/inventory/stock-card', code: 'inventory_stock_card' },
            { name: () => 'Laporan Penerimaan Barang', icon: 'fa-solid fa-truck-ramp-box', route: '/inventory/goods-received-report', code: 'inventory_goods_received_report' },
            { name: () => 'Laporan Nilai Persediaan', icon: 'fa-solid fa-money-check-dollar', route: '/inventory/inventory-value-report', code: 'inventory_value_report' },
            { name: () => 'Laporan Riwayat Perubahan Harga Pokok', icon: 'fa-solid fa-history', route: '/inventory/cost-history-report', code: 'inventory_cost_history_report' },
            { name: () => 'Laporan Stok Minimum', icon: 'fa-solid fa-arrow-down-short-wide', route: '/inventory/minimum-stock-report', code: 'inventory_minimum_stock_report' },
            { name: () => 'Laporan Rekap Persediaan per Kategori', icon: 'fa-solid fa-layer-group', route: '/inventory/category-recap-report', code: 'inventory_category_recap_report' },
            { name: () => 'Laporan Aging Persediaan', icon: 'fa-solid fa-hourglass-half', route: '/inventory/aging-report', code: 'inventory_aging_report' },
            { name: () => 'Internal Use & Waste', icon: 'fa-solid fa-recycle', route: '/internal-use-waste', code: 'internal_use_waste' },
            { name: () => 'Penjualan Antar Gudang', icon: 'fas fa-exchange-alt', route: '/warehouse-sales', code: 'warehouse_sales' },
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
        title: () => 'Announcement',
        icon: 'fa-solid fa-bullhorn',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => 'Announcement', icon: 'fa-solid fa-bullhorn', route: '/announcement', code: 'announcement' },
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

const user = usePage().props.auth?.user || { nama_lengkap: '', avatar: null };
const avatarUrl = user.avatar ? `/storage/${user.avatar}` : '/images/avatar-default.png';
const showProfileDropdown = ref(false);
const showESignatureModal = ref(false);
const showProfileModal = ref(false);

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
        const newNotifications = response.data;
        
        // Check for new unread notifications
        newNotifications.forEach(notif => {
            if (!lastNotificationIds.value.has(notif.id) && !notif.is_read) {
                // This is a new unread notification, show toast
                showToast({ 
                    title: notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info',
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
        await axios.post('/api/notifications/read-all');
        await fetchUnreadCount();
        await fetchNotifications();
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

function handleNotifClick(notif) {
    markAsRead(notif.id);
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
</script>

<template>
<div class="min-h-screen flex bg-gray-100">
    <!-- Audio element for notification sound -->
    <audio ref="notificationSound" src="/sounds/aya_gawean_anyar_ringtone.mp3" preload="auto"></audio>
    
    <!-- Sidebar -->
    <aside :class="['transition-all duration-300 bg-white shadow-lg border-r border-gray-200 flex flex-col fixed z-30 h-full', sidebarOpen ? 'w-64' : 'w-20']">
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
                    class="px-6 py-2 text-xs font-bold uppercase flex items-center gap-2 justify-between cursor-pointer group-title"
                    :class="sidebarOpen ? 'text-gray-500' : 'text-gray-400'"
                    @click="toggleGroup(group)"
                    v-if="group.collapsible"
                >
                    <span class="text-lg"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                    <svg v-if="sidebarOpen" :class="['w-4 h-4 transition-transform', group.open.value ? 'rotate-90' : '']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </div>
                <div
                    v-else
                    class="px-6 py-2 text-xs font-bold uppercase flex items-center gap-2 group-title"
                    :class="sidebarOpen ? 'text-gray-500' : 'text-gray-400'"
                >
                    <span class="text-lg"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                </div>
                <div v-show="!group.collapsible || group.open.value">
                    <Link
                        v-for="menu in group.menus"
                        :key="menu.name"
                        :href="menu.route"
                        class="flex items-center gap-3 px-6 py-2 my-1 rounded-lg text-gray-700 hover:bg-blue-100 transition-all sidebar-menu"
                        :class="[
                            sidebarOpen ? 'justify-start' : 'justify-center',
                            $page.url.startsWith(menu.route) ? 'bg-blue-50 font-bold text-blue-700' : ''
                        ]"
                    >
                        <span class="text-lg w-7 flex justify-center"><i :class="menu.icon"></i></span>
                        <span v-if="sidebarOpen" class="whitespace-nowrap">{{ typeof menu.name === 'function' ? menu.name() : menu.name }}</span>
                    </Link>
                </div>
                <hr v-if="idx < filteredMenuGroups.length - 1" class="my-2 border-gray-200" />
            </div>
        </nav>
    </aside>
    <!-- Main Content -->
    <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', sidebarOpen ? 'ml-64' : 'ml-20']">
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
                            <div class="text-xs text-gray-500" v-if="user.jabatan">{{ user.jabatan }}</div>
                            <div class="text-xs text-gray-500" v-if="user.divisi">{{ user.divisi }}</div>
                            <div class="text-xs text-gray-500" v-if="user.outlet">{{ user.outlet }}</div>
                        </div>
                        <button @click="showProfileModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-user"></i> {{ t('profile.profile') }}
                        </button>
                        <button @click="showESignatureModal = true; showProfileDropdown = false" class="flex items-center gap-2 w-full text-left px-4 py-2 hover:bg-blue-50">
                            <i class="fa-solid fa-pen-nib"></i> {{ t('profile.esign') }}
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
        <main class="flex-1 p-6 bg-gray-50">
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
    letter-spacing: 1px;
    font-size: 13px;
    margin-bottom: 2px;
}
.sidebar-menu {
    font-size: 15px;
    transition: background 0.2s, color 0.2s;
}
.sidebar-menu.router-link-exact-active,
.sidebar-menu.bg-blue-50 {
    background: #e8f0fe !important;
    color: #2563eb !important;
    font-weight: bold;
}
hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 8px 0;
}
</style> 