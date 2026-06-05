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
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import { initializeFirebaseMessaging } from '@/firebase-config';
import { provideLoading } from '@/Composables/useLoading';

// Provide loading state for all child components
provideLoading();

const sidebarOpen = ref(true);
const showLang = ref(false);
const { locale, t } = useI18n();
let notificationPollInterval = null;

const page = usePage();
// Harus computed agar ikut refresh setelah login / perubahan role (Inertia layout persist)
const allowedMenus = computed(() => {
    const raw = page.props.allowedMenus ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw);
});
const crmMenusFromDb = computed(() => page.props.crmMenusFromDb ?? []);

/** Urutan menu omnichannel di grup CRM (sama pola erp_menu + permission view) */
const CRM_DB_MENU_ORDER = [
    'omnichannel_inbox',
    'omnichannel_chat_analytics',
    'wa_broadcast',
    'instagram_comments',
    'omnichannel_teams',
    'omnichannel_flows',
];

function menuLabelFromDb(code, fallbackName) {
    const key = `sidebar.menus.${code}`;
    const translated = t(key);
    return translated !== key ? translated : fallbackName;
}

/** Sisipkan menu dari erp_menu yang belum ada di hardcode AppLayout (mis. wa_broadcast) */
function enrichCrmMenus(staticMenus) {
    let list = [...staticMenus];

    for (const code of CRM_DB_MENU_ORDER) {
        if (list.some((m) => m.code === code)) {
            continue;
        }
        const db = crmMenusFromDb.value.find((m) => m.code === code);
        if (!db || !allowedMenus.value.includes(code)) {
            continue;
        }

        const item = {
            name: () => menuLabelFromDb(db.code, db.name),
            icon: db.icon || 'fa-solid fa-circle',
            route: db.route,
            code: db.code,
        };

        let insertAt = list.length;
        const pos = CRM_DB_MENU_ORDER.indexOf(code);
        for (let i = pos - 1; i >= 0; i--) {
            const prevCode = CRM_DB_MENU_ORDER[i];
            const prevIdx = list.findIndex((m) => m.code === prevCode);
            if (prevIdx >= 0) {
                insertAt = prevIdx + 1;
                break;
            }
        }
        list.splice(insertAt, 0, item);
    }

    return list.filter((menu) => !menu.code || allowedMenus.value.includes(menu.code));
}

const menuGroups = [
    {
        title: () => t('sidebar.main'),
        icon: 'fa-solid fa-bars',
        menus: [
            { name: () => t('sidebar.dashboard'), icon: 'fa-solid fa-home', route: '/home', code: 'dashboard' },
            { name: () => t('sidebar.menus.sales_outlet_dashboard'), icon: 'fa-solid fa-chart-line', route: '/sales-outlet-dashboard', code: 'sales_outlet_dashboard' },
            { name: () => t('sidebar.menus.marketing_dashboard'), icon: 'fa-solid fa-bullhorn', route: '/marketing/dashboard', code: 'marketing_dashboard' },
            { name: () => t('sidebar.menus.dashboard_crm'), icon: 'fa-solid fa-chart-line', route: '/crm/dashboard', code: 'crm_dashboard' },
            { name: () => t('sidebar.menus.cashflow_outlet_dashboard'), icon: 'fa-solid fa-chart-pie', route: route('cashflow-outlet-dashboard.index'), code: 'cashflow_outlet_dashboard' },
            { name: () => t('sidebar.menus.my_attendance'), icon: 'fa-solid fa-user-clock', route: '/attendance', code: 'my_attendance' },
            { name: () => t('sidebar.menus.dokumen_bersama'), icon: 'fa-solid fa-folder-tree', route: '/shared-documents', code: 'shared_documents' },
            { name: () => t('sidebar.menus.payment'), icon: 'fa-solid fa-shopping-cart', route: '/purchase-requisitions', code: 'purchase_requisition_ops' },
            { name: () => t('sidebar.menus.pr_assets'), icon: 'fa-solid fa-boxes-stacked', route: '/purchase-requisitions/create?mode=pr_assets', code: 'pr_assets' },
            { name: () => t('sidebar.menus.payment_report'), icon: 'fa-solid fa-chart-bar', route: '/pr-ops/report', code: 'pr_ops_report' },
            { name: () => t('sidebar.menus.payment_approval_tracker'), icon: 'fa-solid fa-chart-line', route: '/purchase-requisitions/payment-tracker', code: 'payment_tracker' },
            { name: () => t('sidebar.menus.video_tutorial_gallery'), icon: 'fa-solid fa-play-circle', route: '/video-tutorials/gallery' },
            //{ name: () => t('sidebar.menus.dashboard_outlet'), icon: 'fa-solid fa-map-location-dot', route: '/dashboard-outlet', code: 'dashboard_outlet' },
        ],
    },
    //{
    //    title: () => t('sidebar.groups.dokumen_bersama'),
    //    icon: 'fa-solid fa-file-alt',
    //    collapsible: true,
    //    open: ref(false),
    //    menus: [
        //        { name: () => t('sidebar.menus.daftar_dokumen'), icon: 'fa-solid fa-list', route: '/shared-documents', code: 'shared_documents_list' },
        //        { name: () => t('sidebar.menus.upload_dokumen'), icon: 'fa-solid fa-upload', route: '/shared-documents/create', code: 'shared_documents_create' },
    //    ],
    //  },
    //{
    //    title: () => t('sidebar.maintenance'),
    //    icon: 'fa-solid fa-screwdriver-wrench',
    //    collapsible: true,
    //    open: ref(false),
    //    menus: [
    //        { name: () => t('sidebar.menus.mt_dashboard'), icon: 'fa-solid fa-gauge', route: route('dashboard.maintenance'), code: 'mt_dashboard' },
    //        { name: () => t('sidebar.menus.maintenance_order'), icon: 'fa-solid fa-clipboard-check', route: '/maintenance-order', code: 'maintenance_order' },
    //        { name: () => t('sidebar.menus.maintenance_order_list'), icon: 'fa-solid fa-list', route: '/maintenance-order/list', code: 'maintenance_order_list' },
    //        { name: () => t('sidebar.menus.kalender_jadwal'), icon: 'fa-solid fa-calendar-alt', route: '/maintenance-order/schedule-calendar', code: 'maintenance_schedule_calendar' },
    //    ],
    //},
    {
        title: () => t('sidebar.groups.asset_management'),
        icon: 'fa-solid fa-boxes-stacked',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.dashboard'), icon: 'fa-solid fa-gauge', route: '/asset-management/dashboard', code: 'asset_management_dashboard' },
            { name: () => t('sidebar.menus.asset_categories'), icon: 'fa-solid fa-tags', route: '/asset-management/categories', code: 'asset_management_categories' },
            { name: () => t('sidebar.menus.assets'), icon: 'fa-solid fa-box', route: '/asset-management/assets', code: 'asset_management_assets' },
            { name: () => t('sidebar.menus.transfers'), icon: 'fa-solid fa-exchange-alt', route: '/asset-management/transfers', code: 'asset_management_transfers' },
            { name: () => t('sidebar.menus.maintenance_schedules'), icon: 'fa-solid fa-calendar-check', route: '/asset-management/maintenance-schedules', code: 'asset_management_maintenance_schedules' },
            { name: () => t('sidebar.menus.maintenances'), icon: 'fa-solid fa-wrench', route: '/asset-management/maintenances', code: 'asset_management_maintenances' },
            { name: () => t('sidebar.menus.disposals'), icon: 'fa-solid fa-trash', route: '/asset-management/disposals', code: 'asset_management_disposals' },
            { name: () => t('sidebar.menus.documents'), icon: 'fa-solid fa-file', route: '/asset-management/documents', code: 'asset_management_documents' },
            { name: () => t('sidebar.menus.depreciations'), icon: 'fa-solid fa-chart-line', route: '/asset-management/depreciations', code: 'asset_management_depreciations' },
            { name: () => t('sidebar.menus.reports'), icon: 'fa-solid fa-chart-bar', route: '/asset-management/reports', code: 'asset_management_reports' },
            { name: () => t('sidebar.menus.lost_breakage'), icon: 'fa-solid fa-box-open', route: '/lost-breakage', code: 'lost_breakage' },
            { name: () => t('sidebar.menus.lost_breakage_replacement_backlog'), icon: 'fa-solid fa-list-check', route: '/lost-breakage/replacement-backlog', code: 'lost_breakage_replacement_backlog' },
            { name: () => t('sidebar.menus.asset_good_receive'), icon: 'fa-solid fa-truck-ramp-box', route: '/asset-good-receives', code: 'asset_good_receive' },
            { name: () => t('sidebar.menus.asset_inventory_transfer'), icon: 'fa-solid fa-right-left', route: '/asset-inventory-transfers', code: 'asset_inventory_transfer' },
            { name: () => t('sidebar.menus.asset_owner_transfer'), icon: 'fa-solid fa-people-arrows', route: '/asset-owner-transfers', code: 'asset_owner_transfer' },
            { name: () => t('sidebar.menus.asset_stock_adjustment'), icon: 'fa-solid fa-sliders', route: '/asset-inventory-adjustments', code: 'asset_stock_adjustment' },
            { name: () => t('sidebar.menus.asset_service'), icon: 'fa-solid fa-screwdriver-wrench', route: '/asset-service-orders', code: 'asset_service_order' },
            { name: () => t('sidebar.menus.asset_disposal'), icon: 'fa-solid fa-dumpster', route: '/asset-disposals', code: 'asset_disposal' },
            { name: () => t('sidebar.menus.asset_inventory_report'), icon: 'fa-solid fa-chart-line', route: '/asset-inventory-report/stock-position', code: 'asset_inventory_report' },
            { name: () => t('sidebar.menus.saldo_awal_stock_asset'), icon: 'fa-solid fa-scale-balanced', route: '/asset-stock-balances', code: 'asset_stock_balance' },
        ],
    },
    {
        title: () => t('sidebar.groups.master_data'),
        icon: 'fa-solid fa-database',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.categories'), icon: 'fa-solid fa-tags', route: '/categories', code: 'categories' },
            { name: () => t('sidebar.menus.sub_category'), icon: 'fa-solid fa-tag', route: '/sub-categories', code: 'sub_categories' },
            { name: () => t('sidebar.menus.units'), icon: 'fa-solid fa-ruler', route: '/units', code: 'units' },
            { name: () => t('sidebar.menus.items'), icon: 'fa-solid fa-boxes-stacked', route: '/items', code: 'items' },
            { name: () => t('sidebar.menus.repack'), icon: 'fa-solid fa-box-open', route: '/repack', code: 'repack' },
            { name: () => t('sidebar.menus.menu_type'), icon: 'fa-solid fa-list', route: '/menu-types', code: 'menu_types' },
            { name: () => t('sidebar.menus.modifiers'), icon: 'fa-solid fa-sliders', route: '/modifiers', code: 'modifiers' },
            { name: () => t('sidebar.menus.modifier_options'), icon: 'fa-solid fa-sliders', route: '/modifier-options', code: 'modifier_options' },
            { name: () => t('sidebar.menus.warehouses'), icon: 'fa-solid fa-warehouse', route: '/warehouses', code: 'warehouses' },
            { name: () => t('sidebar.menus.warehouse_outlet'), icon: 'fa-solid fa-store', route: '/warehouse-outlets', code: 'warehouse_outlets' },
            { name: () => t('sidebar.menus.warehouse_division'), icon: 'fa-solid fa-sitemap', route: '/warehouse-divisions', code: 'warehouse_divisions' },
            { name: () => t('sidebar.menus.outlets'), icon: 'fa-solid fa-store', route: '/outlets', code: 'outlets' },
            { name: () => t('sidebar.menus.customers'), icon: 'fa-solid fa-users', route: '/customers', code: 'customers' },
            { name: () => t('sidebar.menus.suppliers'), icon: 'fa-solid fa-truck', route: '/suppliers', code: 'suppliers' },
            { name: () => t('sidebar.menus.regions'), icon: 'fa-solid fa-globe-asia', route: '/regions', code: 'regions' },
            { name: () => t('sidebar.menus.item_schedule'), icon: 'fa-solid fa-calendar-days', route: '/item-schedules', code: 'item_schedules' },
            { name: () => t('sidebar.menus.ro_schedule'), icon: 'fa-solid fa-calendar-days', route: '/fo-schedules', code: 'fo_schedules' },
            { name: () => t('sidebar.menus.items_supplier'), icon: 'fa-solid fa-link', route: '/item-supplier', code: 'view-item-supplier' },
            { name: () => t('sidebar.menus.master_moq'), icon: 'fa-solid fa-scale-balanced', route: '/master-moq', code: 'master_moq' },
            { name: () => t('sidebar.menus.data_investor_outlet'), icon: 'fa-solid fa-user-tie', route: '/investors', code: 'data_investor_outlet' },
            { name: () => t('sidebar.menus.officer_check'), icon: 'fa-solid fa-user-check', route: '/officer-check', code: 'officer_check' },
            { name: () => t('sidebar.menus.jenis_pembayaran'), icon: 'fa-solid fa-money-bill', route: '/payment-types', code: 'payment_types' },
            { name: () => t('sidebar.menus.video_tutorial'), icon: 'fa-solid fa-video', route: '/video-tutorials', code: 'master-data-video-tutorials' },
            { name: () => t('sidebar.menus.group_video_tutorial'), icon: 'fa-solid fa-folder', route: '/video-tutorial-groups', code: 'master-data-video-tutorial-groups' },
            { name: () => t('sidebar.menus.locked_budget_food_categories'), icon: 'fa-solid fa-lock', route: '/locked-budget-food-categories', code: 'locked_budget_food_categories' },
            { name: () => t('sidebar.menus.budget_management'), icon: 'fa-solid fa-chart-pie', route: '/budget-management', code: 'budget_management' },
            { name: () => t('sidebar.menus.chart_of_account'), icon: 'fa-solid fa-chart-line', route: '/chart-of-accounts', code: 'chart_of_account' },
            { name: () => t('sidebar.menus.master_data_bank'), icon: 'fa-solid fa-building-columns', route: '/bank-accounts', code: 'bank_accounts' },
        ],
    },
    {
        title: () => t('sidebar.groups.quality_assurance'),
        icon: 'fa-solid fa-shield-halved',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.qa_categories'), icon: 'fa-solid fa-clipboard-list', route: '/qa-categories', code: 'qa_categories' },
            { name: () => t('sidebar.menus.qa_parameters'), icon: 'fa-solid fa-cogs', route: '/qa-parameters', code: 'qa_parameters' },
            { name: () => t('sidebar.menus.qa_guidance'), icon: 'fa-solid fa-clipboard-check', route: '/qa-guidances', code: 'qa_guidances' },
            { name: () => t('sidebar.menus.inspections'), icon: 'fa-solid fa-camera', route: '/inspections', code: 'inspections' },
        ],
    },
    {
          title: () => t('sidebar.groups.ops_management'),
          icon: 'fa-solid fa-cogs',
          collapsible: true,
          open: ref(false),
          menus: [
            { name: () => t('sidebar.menus.master_daily_report'), icon: 'fa-solid fa-chart-line', route: '/master-report', code: 'master_report' },
            { name: () => t('sidebar.menus.daily_report'), icon: 'fa-solid fa-clipboard-list', route: '/daily-report', code: 'daily_report' },
            { name: () => t('sidebar.menus.ticketing_system'), icon: 'fa-solid fa-ticket-alt', route: '/tickets', code: 'tickets' },
            { name: () => t('sidebar.menus.pr_tracking_report'), icon: 'fa-solid fa-timeline', route: '/purchase-requisitions/tracking-report', code: 'pr_tracking_report' },
            { name: () => t('sidebar.menus.ro_vs_forecast_harian'), icon: 'fa-solid fa-scale-balanced', route: '/reports/floor-order-vs-forecast', code: 'floor_order_vs_forecast' },
          ],
        },
    {
        title: () => t('sidebar.groups.human_resource'),
        icon: 'fa-solid fa-users-gear',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.data_level'), icon: 'fa-solid fa-layer-group', route: '/data-levels', code: 'data_levels' },
            { name: () => t('sidebar.menus.kategori_bpjs'), icon: 'fa-solid fa-percent', route: '/bpjs-kategori', code: 'bpjs_kategori' },
            { name: () => t('sidebar.menus.data_jabatan'), icon: 'fa-solid fa-user-tie', route: '/jabatans', code: 'data_jabatan' },
            { name: () => t('sidebar.menus.data_divisi'), icon: 'fa-solid fa-building', route: '/divisis', code: 'data_divisi' },
            { name: () => t('sidebar.menus.data_karyawan'), icon: 'fa-solid fa-users', route: '/users', code: 'data_karyawan' },
            { name: () => t('sidebar.menus.saldo_cuti_extra_off'), icon: 'fa-solid fa-scale-balanced', route: '/users/leave-balance-report', code: 'data_karyawan' },
            { name: () => t('sidebar.menus.report_transaksi_cuti'), icon: 'fa-solid fa-calendar-check', route: '/users/leave-transaction-report', code: 'data_karyawan' },
           // { name: () => t('sidebar.menus.struktur_organisasi'), icon: 'fa-solid fa-sitemap', route: '/organization-chart', code: 'organization_chart' },
            { name: () => t('sidebar.menus.regional_management'), icon: 'fa-solid fa-globe', route: '/regional', code: 'regional_management' },
            { name: () => t('sidebar.menus.report_man_power_outlet'), icon: 'fa-solid fa-users-gear', route: '/man-power-outlet', code: 'man_power_outlet_report' },
            { name: () => t('sidebar.menus.job_vacancy'), icon: 'fa-solid fa-briefcase', route: '/admin/job-vacancy', code: 'job_vacancy' },
            { name: () => t('sidebar.menus.master_data_outlet'), icon: 'fa-solid fa-store', route: '/outlets', code: 'master-data-outlet' },
            { name: () => t('sidebar.menus.master_jam_kerja'), icon: 'fa-solid fa-clock', route: '/shifts', code: 'shift_view' },
            { name: () => t('sidebar.menus.input_shift_mingguan'), icon: 'fa-solid fa-calendar-days', route: '/user-shifts', code: 'user_shift_view' },
            { name: () => t('sidebar.menus.kalender_jadwal_shift'), icon: 'fa-solid fa-calendar-week', route: '/user-shifts/calendar', code: 'user_shift_calendar_view' },
            { name: () => t('sidebar.menus.schedule_attendance_correction'), icon: 'fa-solid fa-edit', route: '/schedule-attendance-correction', code: 'schedule_attendance_correction' },
            { name: () => t('sidebar.menus.report_schedule_attendance_correction'), icon: 'fa-solid fa-chart-bar', route: '/schedule-attendance-correction/report', code: 'schedule_attendance_correction_report' },
            { name: () => t('sidebar.menus.report_absent'), icon: 'fa-solid fa-file-lines', route: '/attendance/report', code: 'absent-report' },
            { name: () => t('sidebar.menus.libur_nasional'), icon: 'fa-solid fa-calendar-day', route: '/kalender-perusahaan', code: 'libur_nasional' },
            { name: () => t('sidebar.menus.report_attendance'), icon: 'fa-solid fa-fingerprint', route: '/attendance-report', code: 'attendance_report' },
            { name: () => t('sidebar.menus.attendance_tracking'), icon: 'fa-solid fa-chart-pie', route: '/attendance-tracking', code: 'attendance_tracking' },
            { name: () => t('sidebar.menus.attendance_per_outlet'), icon: 'fa-solid fa-fingerprint', route: '/attendance-report/employee-summary', code: 'attendance_outlet_summary' },
            { name: () => t('sidebar.menus.holiday_attendance'), icon: 'fa-solid fa-calendar-day', route: '/holiday-attendance', code: 'holiday_attendance' },
            { name: () => t('sidebar.menus.extra_off_ph_report'), icon: 'fa-solid fa-chart-line', route: '/extra-off-report', code: 'extra_off_report' },
            { name: () => t('sidebar.menus.master_payroll'), icon: 'fa-solid fa-money-check-dollar', route: '/payroll/master', code: 'payroll_master' },
            { name: () => t('sidebar.menus.payroll'), icon: 'fa-solid fa-file-invoice-dollar', route: '/payroll/report', code: 'payroll_report' },
            { name: () => t('sidebar.menus.employee_movement'), icon: 'fa-solid fa-people-arrows', route: '/employee-movements', code: 'employee_movement' },
            { name: () => t('sidebar.menus.employee_resignation'), icon: 'fa-solid fa-user-minus', route: '/employee-resignations', code: 'employee_resignation' },
            { name: () => t('sidebar.menus.outlet_ho_inspection'), icon: 'fa-solid fa-clipboard-check', route: '/dynamic-inspections', code: 'dynamic_inspection' },
            { name: () => t('sidebar.menus.coaching'), icon: 'fa-solid fa-user-graduate', route: '/coaching', code: 'coaching' },
            { name: () => t('sidebar.menus.employee_survey'), icon: 'fa-solid fa-clipboard-list', route: '/employee-survey', code: 'employee_survey' },
            { name: () => t('sidebar.menus.employee_survey_report'), icon: 'fa-solid fa-chart-bar', route: '/employee-survey-report', code: 'employee_survey_report' },
            { name: () => t('sidebar.menus.master_soal'), icon: 'fa-solid fa-clipboard-question', route: '/master-soal-new', code: 'master_soal' },
            { name: () => t('sidebar.menus.enroll_test'), icon: 'fa-solid fa-user-graduate', route: '/enroll-test', code: 'enroll_test' },
            { name: () => t('sidebar.menus.my_tests'), icon: 'fa-solid fa-clipboard-check', route: '/my-tests', code: 'my_tests' },
            { name: () => t('sidebar.menus.report_hasil_test'), icon: 'fa-solid fa-chart-line', route: '/enroll-test-report', code: 'enroll_test_report' },
            { name: () => t('sidebar.menus.manajemen_cuti'), icon: 'fa-solid fa-calendar-days', route: '/leave-management', code: 'leave_management' },
            { name: () => t('sidebar.menus.report_travel_kasbon'), icon: 'fa-solid fa-plane', route: '/travel-kasbon-report', code: 'travel_kasbon_report' },
            { name: () => t('sidebar.menus.report_kasbon'), icon: 'fa-solid fa-money-bill-transfer', route: '/report-kasbon', code: 'report_kasbon' },
        ],
    },
    {
        title: () => t('sidebar.groups.outlet_management'),
        icon: 'fa-solid fa-store',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.dashboard_sales_outlet'), icon: 'fa-solid fa-store', route: '/outlet-dashboard', code: 'outlet_dashboard' },
            { name: () => t('sidebar.menus.pos_design_sync_monitor'), icon: 'fa-solid fa-arrows-rotate', route: '/admin/pos-design-sync-monitor', code: 'pos_design_sync_monitor' },
            { name: () => t('sidebar.menus.laporan_void_bill_pos'), icon: 'fa-solid fa-file-circle-xmark', route: '/pos-void-bill-report', code: 'pos_void_bill_report' },
            { name: () => t('sidebar.menus.request_order_ro'), icon: 'fa-solid fa-calendar-check', route: '/floor-order', code: 'floor_order' },
            { name: () => t('sidebar.menus.outlet_good_receive'), icon: 'fa-solid fa-truck-loading', route: '/outlet-food-good-receives', code: 'outlet_food_good_receive' },
            { name: () => t('sidebar.menus.gr_nomor_seri'), icon: 'fa-solid fa-barcode', route: '/outlet-serial-receive', code: 'outlet_serial_receive' },
            { name: () => t('sidebar.menus.good_receive_outlet_supplier'), icon: 'fa-solid fa-truck-arrow-right', route: '/good-receive-outlet-supplier', code: 'good_receive_outlet_supplier' },
            { name: () => t('sidebar.menus.outlet_stock_adjustment'), icon: 'fa-solid fa-boxes-stacked', route: '/outlet-food-inventory-adjustment', code: 'outlet_stock_adjustment' },
            { name: () => t('sidebar.menus.laporan_stok_akhir_outlet'), icon: 'fa-solid fa-clipboard-list', route: '/outlet-inventory/stock-position', code: 'outlet_inventory_stock_position' },
            { name: () => t('sidebar.menus.saldo_awal_stok_outlet'), icon: 'fa-solid fa-warehouse', route: '/outlet-stock-balances', code: 'outlet_stock_balances' },
            { name: () => t('sidebar.menus.kartu_stok_outlet'), icon: 'fa-solid fa-file-lines', route: '/outlet-inventory/stock-card', code: 'outlet_stock_card' },
            { name: () => t('sidebar.menus.laporan_nilai_persediaan_outlet'), icon: 'fa-solid fa-coins', route: '/outlet-inventory/inventory-value-report', code: 'outlet_inventory_value_report' },
            { name: () => t('sidebar.menus.laporan_rekap_persediaan_per_kategori_outlet'), icon: 'fa-solid fa-chart-pie', route: '/outlet-inventory/category-recap-report', code: 'outlet_category_recap_report' },
            { name: () => t('sidebar.menus.category_cost_outlet'), icon: 'fa-solid fa-trash', route: '/outlet-internal-use-waste', code: 'outlet_internal_use_waste' },
            { name: () => t('sidebar.menus.outlet_transfer'), icon: 'fa-solid fa-right-left', route: '/outlet-transfer', code: 'outlet_transfer' },
            { name: () => t('sidebar.menus.internal_warehouse_transfer'), icon: 'fas fa-exchange-alt', route: '/internal-warehouse-transfer', code: 'internal_warehouse_transfer' },
            { name: () => t('sidebar.menus.retail_food'), icon: 'fa-solid fa-store', route: '/retail-food', code: 'view-retail-food' },
            { name: () => t('sidebar.menus.retail_non_food'), icon: 'fa-solid fa-shopping-bag', route: '/retail-non-food', code: 'view-retail-non-food' },
            { name: () => t('sidebar.menus.outlet_food_return'), icon: 'fa-solid fa-undo', route: '/outlet-food-return', code: 'outlet_food_return' },
            { name: () => t('sidebar.menus.stock_opname'), icon: 'fa-solid fa-clipboard-check', route: '/stock-opnames', code: 'stock_opname' },
            { name: () => t('sidebar.menus.report_invoice_outlet'), icon: 'fa-solid fa-file-invoice', route: '/report-invoice-outlet', code: 'report_invoice_outlet' },
            { name: () => t('sidebar.menus.stock_cut'), icon: 'fa-solid fa-scissors', route: '/stock-cut', code: 'stock_cut' },
            { name: () => t('sidebar.menus.outlet_wip_production'), icon: 'fa-solid fa-industry', route: '/outlet-wip', code: 'outlet_wip_production' },
            { name: () => t('sidebar.menus.laporan_outlet_wip'), icon: 'fa-solid fa-file-lines', route: '/outlet-wip/report', code: 'outlet_wip_report' },
        ],
    },
    {
        title: () => t('sidebar.groups.outlet_report'),
        icon: 'fa-solid fa-chart-line',
        collapsible: true,
        open: ref(false),
        menus: [
        { name: () => t('sidebar.menus.sales_report'), icon: 'fa-solid fa-chart-line', route: '/report-sales-simple', code: 'outlet_sales_report' },
        { name: () => t('sidebar.menus.opex_outlet_dashboard'), icon: 'fa-solid fa-chart-pie', route: '/opex-outlet-dashboard', code: 'opex_outlet_dashboard' },
            { name: () => t('sidebar.menus.daily_outlet_revenue'), icon: 'fa-solid fa-chart-bar', route: '/report-daily-outlet-revenue', code: 'daily_outlet_revenue' },
            { name: () => t('sidebar.menus.weekly_outlet_fb_revenue'), icon: 'fa-solid fa-calendar-week', route: '/report-weekly-outlet-fb-revenue', code: 'weekly_outlet_fb_revenue' },
            { name: () => t('sidebar.menus.daily_revenue_forecast'), icon: 'fa-solid fa-chart-line', route: '/report-daily-revenue-forecast', code: 'daily_revenue_forecast' },
            { name: () => t('sidebar.menus.monthly_fb_revenue_performance'), icon: 'fa-solid fa-chart-bar', route: '/report-monthly-fb-revenue-performance', code: 'monthly_fb_revenue_performance' },
         
            { name: () => t('sidebar.menus.receiving_sheet'), icon: 'fa-solid fa-receipt', route: '/report-receiving-sheet', code: 'receiving_sheet' },
            { name: () => t('sidebar.menus.item_engineering'), icon: 'fa-solid fa-cogs', route: '/item-engineering', code: 'item_engineering' },
        ],
    },
    {
        title: () => t('sidebar.groups.ho_finance'),
        icon: 'fa-solid fa-building-columns',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.jurnal'), icon: 'fa-solid fa-book', route: '/jurnal', code: 'jurnal' },
            { name: () => t('sidebar.menus.buku_besar'), icon: 'fa-solid fa-book-open', route: '/report-jurnal-buku-besar', code: 'jurnal_buku_besar' },
            { name: () => t('sidebar.menus.neraca_saldo'), icon: 'fa-solid fa-balance-scale', route: '/report-jurnal-neraca-saldo', code: 'jurnal_neraca_saldo' },
            { name: () => t('sidebar.menus.laporan_arus_kas'), icon: 'fa-solid fa-water', route: '/report-arus-kas', code: 'jurnal_arus_kas' },
            { name: () => t('sidebar.menus.contra_bon'), icon: 'fa-solid fa-file-circle-xmark', route: '/contra-bons', code: 'contra_bon' },
            { name: () => t('sidebar.menus.food_payment'), icon: 'fa-solid fa-money-bill-transfer', route: '/food-payments', code: 'food_payment' },
            { name: () => t('sidebar.menus.non_food_payment'), icon: 'fa-solid fa-credit-card', route: '/non-food-payments', code: 'non_food_payment' },
            { name: () => t('sidebar.menus.retail_non_food_payment'), icon: 'fa-solid fa-money-bill-wave', route: route('retail-non-food-payment.index'), code: 'retail_non_food_payment' },
            { name: () => t('sidebar.menus.opex_report'), icon: 'fa-solid fa-chart-line', route: '/opex-report', code: 'opex_report' },
            { name: () => t('sidebar.menus.opex_by_category'), icon: 'fa-solid fa-chart-pie', route: '/opex-by-category', code: 'opex_by_category' },
            { name: () => t('sidebar.menus.mamp_report'), icon: 'fa-solid fa-table-list', route: '/mamp-report', code: 'mamp_report' },
            //{ name: () => t('sidebar.menus.mt_po_payment'), icon: 'fa-solid fa-money-bill-wave', route: route('mt-po-payment.index'), code: 'mt_po_payment' },
            { name: () => t('sidebar.menus.outlet_payments'), icon: 'fa-solid fa-money-bill', route: route('outlet-payments.index'), code: 'outlet_payments' },
            //{ name: () => t('sidebar.menus.outlet_payment_supplier'), icon: 'fa-solid fa-money-bill', route: route('outlet-payment-suppliers.index'), code: 'outlet_payment_suppliers' },
            { name: () => t('sidebar.menus.buku_bank'), icon: 'fa-solid fa-book', route: '/bank-books', code: 'bank_books' },
           
            //{ name: () => t('sidebar.menus.pr_payment'), icon: 'fa-solid fa-credit-card', route: '/payments', code: 'pr_payment' },
            {
                name: () => t('sidebar.menus.report_penjualan_pivot_per_outlet_per_sub_kategori'),
                icon: 'fa-solid fa-table-columns',
                route: '/report-sales-pivot-per-outlet-sub-category',
                code: 'report_sales_pivot_per_outlet_sub_category',
            },
            {
                name: () => t('sidebar.menus.report_rekap_fj'),
                icon: 'fa-solid fa-table-list',
                route: '/report-rekap-fj',
                code: 'report_rekap_fj',
            },
            {
                name: () => t('sidebar.menus.rekap_pb1_outlet'),
                icon: 'fa-solid fa-receipt',
                route: '/reports/rekap-pb1-outlet',
                code: 'rekap_pb1_outlet',
            },
            {
                name: () => t('sidebar.menus.payroll_finance_report'),
                icon: 'fa-solid fa-coins',
                route: '/payroll/finance-report',
                code: 'payroll_finance_report',
            },
            {
                name: () => t('sidebar.menus.rekap_payroll'),
                icon: 'fa-solid fa-table-list',
                route: '/payroll/rekap',
                code: 'rekap_payroll',
            },
            { name: () => t('sidebar.menus.report_hutang'), icon: 'fa-solid fa-file-invoice-dollar', route: '/debt-report', code: 'debt_report' },
            { name: () => t('sidebar.menus.partner_ledger'), icon: 'fa-solid fa-scale-balanced', route: '/partner-ledger', code: 'partner_ledger' },
        ],
    },
    {
        title: () => t('sidebar.groups.purchasing'),
        icon: 'fa-solid fa-shopping-bag',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.purchase_order_foods'), icon: 'fa-solid fa-file-invoice-dollar', route: '/po-foods', code: 'po_foods' },
            { name: () => t('sidebar.menus.purchase_order_ops'), icon: 'fa-solid fa-file-invoice', route: '/po-ops', code: 'purchase_order_ops' },
            { name: () => t('sidebar.menus.report_po_gr'), icon: 'fa-solid fa-chart-line', route: '/po-report', code: 'po_report' },
            { name: () => t('sidebar.menus.report_purchase_order_ops'), icon: 'fa-solid fa-chart-bar', route: '/po-ops/report', code: 'po_ops_report' },
        ],
    },
    {
        title: () => t('sidebar.groups.warehouse_management'),
        icon: 'fa-solid fa-warehouse',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.purchase_requisition_foods'), icon: 'fa-solid fa-file-invoice', route: '/pr-foods', code: 'pr_foods' },
            { name: () => t('sidebar.menus.good_receive'), icon: 'fa-solid fa-truck', route: '/food-good-receive', code: 'food_good_receive' },
            { name: () => t('sidebar.menus.food_good_receive_report'), icon: 'fa-solid fa-chart-bar', route: '/food-good-receive-report', code: 'food_good_receive_report' },
            { name: () => t('sidebar.menus.pindah_gudang'), icon: 'fa-solid fa-right-left', route: '/warehouse-transfer', code: 'warehouse_transfer' },
            { name: () => t('sidebar.menus.stock_adjustment'), icon: 'fa-solid fa-boxes-stacked', route: '/food-inventory-adjustment', code: 'stock_adjustment' },
            { name: () => t('sidebar.menus.packing_list'), icon: 'fa-solid fa-box', route: '/packing-list', code: 'packing_list' },
            { name: () => t('sidebar.menus.delivery_order'), icon: 'fa-solid fa-truck-arrow-right', route: '/delivery-order', code: 'delivery_order' },
            { name: () => t('sidebar.menus.penjualan_warehouse_retail'), icon: 'fa-solid fa-store', route: '/retail-warehouse-sale', code: 'retail_warehouse_sale' },
            { name: () => t('sidebar.menus.warehouse_retail_food'), icon: 'fa-solid fa-warehouse', route: '/retail-warehouse-food', code: 'view-retail-warehouse-food' },
            { name: () => t('sidebar.menus.saldo_awal_stok'), icon: 'fa-solid fa-money-bill-wave', route: '/food-stock-balances', code: 'food_stock_balances' },
            { name: () => t('sidebar.menus.laporan_stok_akhir'), icon: 'fa-solid fa-clipboard-list', route: '/inventory/stock-position', code: 'inventory_stock_position' },
            { name: () => t('sidebar.menus.stock_opname_2'), icon: 'fa-solid fa-clipboard-check', route: '/warehouse-stock-opnames', code: 'warehouse_stock_opname' },
            { name: () => t('sidebar.menus.laporan_kartu_stok'), icon: 'fa-solid fa-file-lines', route: '/inventory/stock-card', code: 'inventory_stock_card' },
            { name: () => t('sidebar.menus.laporan_penerimaan_barang'), icon: 'fa-solid fa-truck-ramp-box', route: '/inventory/goods-received-report', code: 'inventory_goods_received_report' },
            { name: () => t('sidebar.menus.laporan_nilai_persediaan'), icon: 'fa-solid fa-money-check-dollar', route: '/inventory/inventory-value-report', code: 'inventory_value_report' },
            { name: () => t('sidebar.menus.laporan_riwayat_perubahan_harga_pokok'), icon: 'fa-solid fa-history', route: '/inventory/cost-history-report', code: 'inventory_cost_history_report' },
            { name: () => t('sidebar.menus.laporan_stok_minimum'), icon: 'fa-solid fa-arrow-down-short-wide', route: '/inventory/minimum-stock-report', code: 'inventory_minimum_stock_report' },
            { name: () => t('sidebar.menus.laporan_rekap_persediaan_per_kategori'), icon: 'fa-solid fa-layer-group', route: '/inventory/category-recap-report', code: 'inventory_category_recap_report' },
            { name: () => t('sidebar.menus.laporan_aging_persediaan'), icon: 'fa-solid fa-hourglass-half', route: '/inventory/aging-report', code: 'inventory_aging_report' },
            { name: () => t('sidebar.menus.internal_use_waste'), icon: 'fa-solid fa-recycle', route: '/internal-use-waste', code: 'internal_use_waste' },
            { name: () => t('sidebar.menus.penjualan_antar_gudang'), icon: 'fas fa-exchange-alt', route: '/warehouse-sales', code: 'warehouse_sales' },
            { name: () => t('sidebar.menus.outlet_rejection'), icon: 'fas fa-undo', route: '/outlet-rejections', code: 'outlet_rejection' },
            { name: () => t('sidebar.menus.kelola_return_outlet'), icon: 'fa-solid fa-building', route: '/head-office-return', code: 'head_office_return' },
        ],
    },
    {
        title: () => t('sidebar.groups.cost_control'),
        icon: 'fa-solid fa-coins',
        collapsible: true,
        open: ref(false),
        menus: [
            {
                name: () => t('sidebar.menus.laporan_perubahan_harga_po'),
                icon: 'fa-solid fa-arrow-trend-up',
                route: '/inventory/po-price-change-report',
                code: 'po_price_change_report_view',
            },
            {
                name: () => t('sidebar.menus.mac_report'),
                icon: 'fa-solid fa-chart-line',
                route: '/mac-report',
                code: 'mac_report',
            },
            {
                name: () => t('sidebar.menus.outlet_mac_tracking'),
                icon: 'fa-solid fa-triangle-exclamation',
                route: '/mac-anomaly-tracking',
                code: 'mac_anomaly_tracking',
            },
            {
                name: () => t('sidebar.menus.warehouse_mac_tracking'),
                icon: 'fa-solid fa-warehouse',
                route: '/warehouse-mac-tracking',
                code: 'warehouse_mac_tracking',
            },
            {
                name: () => t('sidebar.menus.tracking_nomor_seri'),
                icon: 'fa-solid fa-barcode',
                route: '/serial-tracking',
                code: 'serial_tracking',
            },
            {
                name: () => t('sidebar.menus.outlet_stock_report'),
                icon: 'fa-solid fa-chart-line',
                route: '/outlet-stock-report',
                code: 'outlet_stock_report',
            },
            {
                name: () => t('sidebar.menus.cost_report'),
                icon: 'fa-solid fa-coins',
                route: '/cost-report',
                code: 'cost_report',
            },
            {
                name: () => t('sidebar.menus.cost_report_ho'),
                icon: 'fa-solid fa-building-columns',
                route: '/cost-report-ho',
                code: 'cost_report_ho',
            },
            {
                name: () => t('sidebar.menus.report_pembelanjaan_supplier_warehouse_gr'),
                icon: 'fa-solid fa-sack-dollar',
                route: '/food-good-receive-report-supplier-spending',
                code: 'warehouse_gr_supplier_spending_report',
            },
            {
                name: () => t('sidebar.menus.report_rnd_bm_wm'),
                icon: 'fa-solid fa-chart-line',
                route: '/internal-use-waste-report',
                code: 'internal_use_waste_report',
            },
            {
                name: () => t('sidebar.menus.report_penjualan_per_category'),
                icon: 'fa-solid fa-table-list',
                route: '/report-sales-per-category',
                code: 'report_sales_per_category',
            },
            {
                name: () => t('sidebar.menus.report_penjualan_per_tanggal'),
                icon: 'fa-solid fa-calendar-day',
                route: '/report-sales-per-tanggal',
                code: 'report_sales_per_tanggal',
            },
            {
                name: () => t('sidebar.menus.report_penjualan_all_item_ke_all_outlet'),
                icon: 'fa-solid fa-list-check',
                route: '/report-sales-all-item-all-outlet',
                code: 'report_sales_all_item_all_outlet',
            },
            {
                name: () => t('sidebar.menus.report_good_receive_outlet'),
                icon: 'fa-solid fa-table-cells-large',
                route: '/report-good-receive-outlet',
                code: 'report_good_receive_outlet',
            },
            {
                name: () => t('sidebar.menus.report_retail_food_per_supplier'),
                icon: 'fa-solid fa-chart-line',
                route: '/retail-food/report-supplier',
                code: 'retail_food_supplier_report',
            },
            {
                name: () => t('sidebar.menus.stock_opname_adjustment_report'),
                icon: 'fa-solid fa-chart-bar',
                route: '/stock-opname-adjustment-report',
                code: 'stock_opname_adjustment_report',
            },
            {
                name: () => t('sidebar.menus.cek_resep_bom'),
                icon: 'fa-solid fa-magnifying-glass',
                route: '/stock-cut/recipe-checker',
                code: 'recipe_checker',
            },
            {
                name: () => t('sidebar.menus.report_rekap_diskon'),
                icon: 'fa-solid fa-tags',
                route: '/report-rekap-diskon',
                code: 'report_rekap_diskon',
            },
        ],
    },
    {
        title: () => t('sidebar.groups.production'),
        icon: 'fa-solid fa-industry',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.butcher'), icon: 'fa-solid fa-cut', route: '/butcher-processes', code: 'butcher' },
            { name: () => t('sidebar.menus.butcher_report'), icon: 'fa-solid fa-file-lines', route: '/butcher-processes/report', code: 'butcher_report' },
            { name: () => t('sidebar.menus.laporan_stok_cost_butcher'), icon: 'fa-solid fa-money-bill-trend-up', route: '/butcher-processes/stock-cost-report', code: 'butcher_stock_cost_report' },
            { name: () => t('sidebar.menus.laporan_analisis_butcher'), icon: 'fa-solid fa-chart-line', route: '/butcher-processes/analysis-report', code: 'butcher_analysis_report' },
            { name: () => t('sidebar.menus.summary_hasil_butcher'), icon: 'fa-solid fa-list', route: '/butcher-summary-report', code: 'butcher_summary_report' },
            { name: () => t('sidebar.menus.mk_production'), icon: 'fa-solid fa-industry', route: '/mk-production', code: 'mk_production' },
            { name: () => t('sidebar.menus.laporan_mk_production'), icon: 'fa-solid fa-file-lines', route: '/mk-production/report', code: 'mk_production_report' },
        ],
    },
    {
        title: () => t('sidebar.groups.ops_kitchen'),
        icon: 'fa-solid fa-utensils',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.action_plan_guest_review'), icon: 'fa-solid fa-clipboard-list', route: '/ops-kitchen/action-plan-guest-review', code: 'ops_kitchen_action_plan_guest_review' },
        ],
    },
    {
        title: () => t('sidebar.groups.sales_marketing'),
        icon: 'fa-solid fa-bullhorn',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.scrapper_google_review'), icon: 'fa-brands fa-google', route: '/scrapper-google-review', code: 'scrapper_google_review' },
            { name: () => t('sidebar.menus.promo'), icon: 'fa-solid fa-tag', route: '/promos', code: 'promos' },
            { name: () => t('sidebar.menus.marketing_visit_checklist'), icon: 'fa-solid fa-clipboard-check', route: '/marketing-visit-checklist', code: 'marketing_visit_checklist_view' },
            { name: () => t('sidebar.menus.reservasi'), icon: 'fa-solid fa-calendar-check', route: '/reservations', code: 'reservations' },
            { name: () => t('sidebar.menus.data_roulette'), icon: 'fa-solid fa-dice', route: '/roulette', code: 'data_roulette' },
            { name: () => t('sidebar.menus.menu_book'), icon: 'fa-solid fa-book-open', route: '/menu-book', code: 'menu_book' },
            { name: () => t('sidebar.menus.web_profile'), icon: 'fa-solid fa-globe', route: '/web-profile', code: 'web_profile' },
            { name: () => t('sidebar.menus.rekap_transaksi_bank'), icon: 'fa-solid fa-university', route: '/report-bank-transaction', code: 'report_bank_transaction' },
            { name: () => t('sidebar.menus.revenue_targets'), icon: 'fa-solid fa-bullseye', route: '/outlet-revenue-targets', code: 'outlet_revenue_targets' },
        ],
    },
    {
        title: () => t('sidebar.groups.user_management'),
        icon: 'fa-solid fa-user-gear',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.role_management'), icon: 'fa-solid fa-user-shield', route: '/roles', code: 'role_management' },
            { name: () => t('sidebar.menus.user_role_setting'), icon: 'fa-solid fa-users-cog', route: '/user-roles', code: 'user_role_setting' },
            { name: () => t('sidebar.menus.menu_management'), icon: 'fa-solid fa-bars-progress', route: '/menus', code: 'menu_management' },
        ],
    },
    {
        title: () => t('sidebar.groups.support'),
        icon: 'fa-solid fa-headset',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.support_admin_panel'), icon: 'fa-solid fa-comments', route: '/support/admin', code: 'support_admin_panel' },
            { name: () => t('sidebar.menus.monitoring_user_aktif'), icon: 'fa-solid fa-users-line', route: '/monitoring/active-users', code: 'monitoring_active_users' },
            { name: () => t('sidebar.menus.server_performance_monitoring'), icon: 'fa-solid fa-server', route: '/monitoring/server-performance', code: 'server_performance_monitoring' },
            { name: () => t('sidebar.menus.activity_log_report'), icon: 'fa-solid fa-list-alt', route: '/report/activity-log', code: 'activity_log_report' },
            { name: () => t('sidebar.menus.cctv_access_request'), icon: 'fa-solid fa-video', route: '/cctv-access-requests', code: 'cctv_access_request' },
        ],
    },
    {
        title: () => t('sidebar.groups.announcement'),
        icon: 'fa-solid fa-bullhorn',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.announcement'), icon: 'fa-solid fa-bullhorn', route: '/announcement', code: 'announcement' },
        ],
    },
    {
        groupKey: 'crm',
        title: () => t('sidebar.groups.crm'),
        icon: 'fa-solid fa-handshake',
        collapsible: true,
        open: ref(false),
        menus: [
            { name: () => t('sidebar.menus.data_member'), icon: 'fa-solid fa-users', route: '/members', code: 'crm_members' },
            { name: () => t('sidebar.menus.guest_comment_ocr'), icon: 'fa-solid fa-comment-dots', route: '/guest-comment-forms', code: 'guest_comment_form' },
            { name: () => t('sidebar.menus.customer_voice_command_center'), icon: 'fa-solid fa-headset', route: '/customer-voice-command-center', code: 'customer_voice_command_center' },
            { name: () => t('sidebar.menus.omnichannel_inbox'), icon: 'fa-solid fa-inbox', route: '/crm/omnichannel-inbox', code: 'omnichannel_inbox' },
            { name: () => t('sidebar.menus.omnichannel_chat_analytics'), icon: 'fa-solid fa-chart-line', route: '/crm/omnichannel-chat-analytics', code: 'omnichannel_chat_analytics' },
            { name: () => t('sidebar.menus.wa_broadcast'), icon: 'fa-brands fa-whatsapp', route: '/crm/wa-broadcast', code: 'wa_broadcast' },
            { name: () => t('sidebar.menus.instagram_comments'), icon: 'fa-brands fa-instagram', route: '/crm/instagram-comments', code: 'instagram_comments' },
            { name: () => t('sidebar.menus.omnichannel_teams'), icon: 'fa-solid fa-people-group', route: '/crm/omnichannel-teams', code: 'omnichannel_teams' },
            { name: () => t('sidebar.menus.omnichannel_flows'), icon: 'fa-solid fa-diagram-project', route: '/crm/omnichannel-flows', code: 'omnichannel_flows' },
           // { name: () => t('sidebar.menus.migrasi_data_member'), icon: 'fa-solid fa-database', route: '/member-migration', code: 'member_migration' },
            { name: () => t('sidebar.menus.kirim_notifikasi_member'), icon: 'fa-solid fa-paper-plane', route: '/member-notification', code: 'member_notification' },
           // { name: () => t('sidebar.menus.dashboard_crm_2'), icon: 'fa-solid fa-chart-line', route: '/crm/dashboard', code: 'crm_dashboard' },
           // { name: () => t('sidebar.menus.customer_analytics'), icon: 'fa-solid fa-chart-pie', route: '/crm/customer-analytics', code: 'crm_analytics' },
           // { name: () => t('sidebar.menus.member_reports'), icon: 'fa-solid fa-file-lines', route: '/crm/member-reports', code: 'crm_reports' },   
            { name: () => t('sidebar.menus.inject_point_manual'), icon: 'fa-solid fa-syringe', route: '/manual-point', code: 'manual_point' },
            { name: () => t('sidebar.menus.member_apps_settings'), icon: 'fa-solid fa-mobile-screen-button', route: '/admin/member-apps-settings', code: 'member_apps_settings' },
        ],
    },
       
    {
        title: () => t('sidebar.groups.lms'),
        icon: 'fa-solid fa-graduation-cap',
        collapsible: true,
        open: ref(false),
        menus: [
           
            { name: () => t('sidebar.menus.kategori_training'), icon: 'fa-solid fa-folder', route: '/lms/categories', code: 'lms-categories' },
            { name: () => t('sidebar.menus.training'), icon: 'fa-solid fa-book', route: '/lms/courses', code: 'lms-courses' },      
            { name: () => t('sidebar.menus.quiz'), icon: 'fa-solid fa-question-circle', route: '/lms/quizzes', code: 'lms-quizzes' },
            { name: () => t('sidebar.menus.kuesioner'), icon: 'fa-solid fa-clipboard-list', route: '/lms/questionnaires', code: 'lms-questionnaires' },    
           // { name: () => t('sidebar.menus.sertifikat'), icon: 'fa-solid fa-certificate', route: '/lms/certificates', code: 'lms-certificates' },
            { name: () => t('sidebar.menus.template_sertifikat'), icon: 'fa-solid fa-certificate', route: '/lms/certificate-templates', code: 'lms-certificate-templates' },          
            { name: () => t('sidebar.menus.jadwal_training'), icon: 'fa-solid fa-calendar-alt', route: '/lms/schedules', code: 'lms-schedules' },
            { name: () => t('sidebar.menus.trainer_report'), icon: 'fa-solid fa-chart-line', route: '/lms/trainer-report-page', code: 'lms-trainer-report' },
            { name: () => t('sidebar.menus.laporan_training_karyawan'), icon: 'fa-solid fa-users', route: '/lms/employee-training-report-page', code: 'lms-employee-training-report' },
            { name: () => t('sidebar.menus.training_report'), icon: 'fa-solid fa-chart-bar', route: '/lms/training-report-page', code: 'lms-training-report' },
            { name: () => t('sidebar.menus.quiz_report'), icon: 'fa-solid fa-question-circle', route: '/lms/quiz-report-page', code: 'lms-quiz-report' },
        ],
    },
];

const filteredMenuGroups = computed(() =>
  menuGroups.map((group) => {
    const isCrm = group.groupKey === 'crm';
    const menus = isCrm
      ? enrichCrmMenus(group.menus)
      : group.menus.filter(
            (menu) => !menu.code || allowedMenus.value.includes(menu.code)
        );

    return { ...group, menus };
  }).filter((group) => group.menus.length > 0)
);

const languages = [
    { code: 'id', label: 'Indonesia' },
    { code: 'en', label: 'English' },
];
const currentLang = ref(localStorage.getItem('currentLang') || 'id');

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

const user = computed(() => page.props.auth?.user || { nama_lengkap: '', avatar: null });
const isExternalUser = computed(() => Boolean(user.value?.is_external));
const logoutRouteName = computed(() => (isExternalUser.value ? 'external.logout' : 'logout'));
const mobileAppPlayStoreUrl =
    'https://play.google.com/store/apps/details?id=com.ymsoft.erp&pcampaignid=web_share';
const mobileAppAppStoreUrl =
    'https://apps.apple.com/id/app/ymsoft-erp/id6761749215';
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
        unreadCount.value = newNotifications.filter(n => !n.is_read).length;
    } catch (error) {
        console.error('Error fetching notifications:', error);
        // Don't show error to user, just log it
    } finally {
        loading.value = false;
    }
}

async function markAsRead(id) {
    try {
        await axios.post(`/api/notifications/${id}/read`);
        const notif = notifications.value.find(n => n.id === id);
        if (notif && !notif.is_read) {
            notif.is_read = true;
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        await axios.post('/api/notifications/mark-all-read');
        notifications.value = notifications.value.map(n => ({ ...n, is_read: true }));
        unreadCount.value = 0;
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

// Fetch notifications on mount and every 120 seconds to reduce server load
onMounted(async () => {
    const savedLang = localStorage.getItem('currentLang');
    if (savedLang && (savedLang === 'id' || savedLang === 'en')) {
        currentLang.value = savedLang;
        locale.value = savedLang;
    }

    if (isExternalUser.value) {
        return;
    }

    await fetchNotifications();
    
    // Changed from 60s to 120s to further reduce server load
    notificationPollInterval = setInterval(async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        await fetchNotifications();
    }, 120000); // 120 seconds

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

onBeforeUnmount(() => {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
        notificationPollInterval = null;
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
    <aside v-if="!isExternalUser" :class="['transition-all duration-300 flex flex-col fixed z-30 h-full bg-white shadow-xl border-r border-gray-200', sidebarOpen ? 'w-72' : 'w-20']">
        <!-- Sidebar Header -->
        <div class="flex items-center justify-between h-20 px-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="flex-shrink-0">
                    <img v-if="sidebarOpen" src="/images/logo.png" alt="Logo" class="h-12 w-auto transition-all duration-300" />
                    <img v-else src="/images/logo-icon.png" alt="Logo Icon" class="h-10 w-10 transition-all duration-300 rounded-lg" />
                </div>
            </div>
            <button @click="toggleSidebar" class="flex-shrink-0 text-gray-500 hover:text-blue-600 hover:bg-gray-100 rounded-lg p-2 transition-all duration-200">
                <i :class="['fas transition-transform duration-300', sidebarOpen ? 'fa-angle-double-left' : 'fa-angle-double-right']"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto py-4">
            <div v-for="(group, idx) in filteredMenuGroups" :key="group.title" class="mb-4">
                <div
                    class="px-4 py-2.5 text-xs font-semibold uppercase flex items-center gap-3 cursor-pointer group-title-modern mx-2 rounded-lg"
                    :class="sidebarOpen ? 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' : 'text-gray-400 justify-center'"
                    @click="toggleGroup(group)"
                    v-if="group.collapsible"
                    :title="!sidebarOpen ? (typeof group.title === 'function' ? group.title() : group.title) : ''"
                >
                    <span class="text-base flex-shrink-0"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen" class="truncate flex-1 tracking-wider">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                    <svg v-if="sidebarOpen" :class="['w-3.5 h-3.5 transition-transform duration-300 flex-shrink-0', group.open.value ? 'rotate-90' : '']" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </div>
                <div
                    v-else
                    class="px-4 py-2.5 text-xs font-semibold uppercase flex items-center gap-3 group-title-modern mx-2 rounded-lg"
                    :class="sidebarOpen ? 'text-gray-500' : 'text-gray-400 justify-center'"
                    :title="!sidebarOpen ? (typeof group.title === 'function' ? group.title() : group.title) : ''"
                >
                    <span class="text-base flex-shrink-0"><i :class="group.icon"></i></span>
                    <span v-if="sidebarOpen" class="truncate flex-1 tracking-wider">{{ typeof group.title === 'function' ? group.title() : group.title }}</span>
                </div>
                <div v-show="!group.collapsible || group.open.value" class="mt-1">
                    <Link
                        v-for="menu in group.menus"
                        :key="menu.route"
                        :href="menu.route"
                        class="flex items-center gap-3 px-4 py-2.5 my-1 mx-2 rounded-lg text-gray-700 hover:text-blue-700 transition-all duration-200 sidebar-menu-modern relative group"
                        :class="[
                            sidebarOpen ? 'justify-start' : 'justify-center',
                            $page.url.startsWith(menu.route) ? 'bg-blue-50 text-blue-700 font-semibold shadow-sm menu-active' : 'hover:bg-gray-50'
                        ]"
                        :title="!sidebarOpen ? (typeof menu.name === 'function' ? menu.name() : menu.name) : ''"
                    >
                        <span class="text-base w-6 flex justify-center flex-shrink-0 transition-transform duration-200 group-hover:scale-110"><i :class="menu.icon"></i></span>
                        <span v-if="sidebarOpen" class="text-sm leading-tight truncate">{{ typeof menu.name === 'function' ? menu.name() : menu.name }}</span>
                        <span v-if="sidebarOpen && $page.url.startsWith(menu.route)" class="absolute right-2 w-1.5 h-1.5 bg-blue-600 rounded-full"></span>
                    </Link>
                </div>
                <div v-if="idx < filteredMenuGroups.length - 1" class="my-3 mx-2 h-px bg-gray-200"></div>
            </div>
        </nav>
    </aside>
    <!-- Main Content -->
    <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', isExternalUser ? 'ml-0' : (sidebarOpen ? 'ml-72' : 'ml-20')]">
        <!-- Navbar -->
        <header class="h-16 bg-white/95 backdrop-blur-md border-b border-gray-200/50 flex items-center px-6 justify-between shadow-sm navbar-modern sticky top-0 z-20">
            <div class="flex items-center gap-4">
                <button v-if="!isExternalUser" @click="toggleSidebar" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none p-2 rounded-lg hover:bg-gray-100 transition-all duration-200">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div v-if="!isExternalUser" class="hidden md:flex items-center gap-2 text-sm text-gray-600">
                    <i class="fas fa-home text-gray-400"></i>
                    <span class="font-medium">{{ $page.component }}</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <!-- Language -->
                <div v-if="!isExternalUser" class="relative">
                    <button class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-100 transition-all duration-200 group" @click="showLang = !showLang">
                        <img :src="currentLang === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full ring-2 ring-gray-200 group-hover:ring-blue-300 transition-all" />
                        <span class="hidden md:inline text-sm font-medium text-gray-700">{{ languages.find(l => l.code === currentLang)?.label }}</span>
                        <i class="fas fa-chevron-down text-xs text-gray-500 group-hover:text-blue-600 transition-colors"></i>
                    </button>
                    <div v-if="showLang" class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden backdrop-blur-md">
                        <div v-for="lang in languages" :key="lang.code" @click="setLang(lang.code); showLang = false" class="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50 cursor-pointer transition-colors duration-150">
                            <img :src="lang.code === 'id' ? '/images/indonesia.png' : '/images/united-states.png'" alt="Lang" class="w-5 h-5 rounded-full" />
                            <span class="text-sm font-medium text-gray-700">{{ lang.label }}</span>
                        </div>
                    </div>
                </div>
                <!-- Fullscreen -->
                <button v-if="!isExternalUser" @click="toggleFullscreen" class="p-2.5 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-blue-600 transition-all duration-200 group relative" title="Toggle Fullscreen">
                    <i class="fas fa-expand group-hover:scale-110 transition-transform duration-200"></i>
                </button>
                <!-- Notif -->
                <div v-if="!isExternalUser" class="relative">
                    <button class="p-2.5 rounded-xl hover:bg-gray-100 text-gray-600 hover:text-blue-600 transition-all duration-200 relative group" @click="showNotifDropdown = !showNotifDropdown" title="Notifications">
                        <i class="fas fa-bell group-hover:animate-pulse"></i>
                        <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-red-600 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center shadow-lg animate-bounce">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
                    </button>
                    <div v-if="showNotifDropdown" class="absolute right-0 mt-2 w-80 bg-white/95 backdrop-blur-md border border-gray-200 rounded-xl shadow-2xl z-50 max-h-96 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-white">
                            <span class="font-bold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-bell text-blue-600"></i>
                                Notifikasi
                            </span>
                            <button class="text-xs text-blue-600 hover:text-blue-700 font-medium hover:underline transition-colors" @click="markAllAsRead">Tandai semua dibaca</button>
                        </div>
                        <div v-if="loading" class="px-4 py-6 text-center">
                            <i class="fas fa-spinner fa-spin text-blue-500"></i>
                        </div>
                        <div v-else-if="notifications.length === 0" class="px-4 py-6 text-center text-gray-400">Tidak ada notifikasi</div>
                        <div v-else class="overflow-y-auto max-h-80">
                            <div v-for="notif in notifications" :key="notif.id" @click="handleNotifClick(notif)" class="px-4 py-3 border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-blue-50/50 flex gap-3 transition-all duration-150 group" :class="notif.is_read ? 'bg-white' : 'bg-blue-50/30'">
                                <div class="flex-shrink-0 mt-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="notif.type === 'success' ? 'bg-green-100' : notif.type === 'error' ? 'bg-red-100' : 'bg-blue-100'">
                                        <i :class="['fas text-sm', notif.type === 'success' ? 'fa-check-circle text-green-600' : notif.type === 'error' ? 'fa-exclamation-circle text-red-600' : 'fa-info-circle text-blue-600']"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm text-gray-800 mb-1">{{ notif.type === 'success' ? 'Success' : notif.type === 'error' ? 'Error' : 'Info' }}</div>
                                    <div class="text-xs text-gray-600 leading-relaxed">{{ notif.message }}</div>
                                    <div class="text-xs text-gray-400 mt-1.5 flex items-center gap-1">
                                        <i class="fas fa-clock text-gray-300"></i>
                                        {{ notif.time }}
                                    </div>
                                    <div v-if="notif.url" class="text-xs text-blue-600 mt-1.5 flex items-center gap-1 group-hover:text-blue-700">
                                        <i class="fas fa-link"></i>
                                        <span class="truncate">{{ notif.url }}</span>
                                    </div>
                                </div>
                                <div v-if="!notif.is_read" class="flex-shrink-0 mt-1">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Avatar -->
                <div class="relative">
                    <button @click="showProfileDropdown = !showProfileDropdown" class="flex items-center gap-2.5 focus:outline-none px-2 py-1.5 rounded-xl hover:bg-gray-100 transition-all duration-200 group">
                        <div class="relative">
                            <img :src="avatarUrl" alt="Avatar" class="w-10 h-10 rounded-full object-cover border-2 border-blue-300 shadow-md group-hover:border-blue-500 transition-all duration-200" />
                            <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></div>
                        </div>
                        <div class="hidden md:flex flex-col items-start">
                            <span class="font-semibold text-sm text-gray-800">{{ user.nama_lengkap }}</span>
                            <span class="text-xs text-gray-500">{{ userOutlet }}</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-500 group-hover:text-blue-600 transition-colors"></i>
                    </button>
                    <div v-if="showProfileDropdown" class="absolute right-0 mt-2 w-72 bg-white/95 backdrop-blur-md border border-gray-200 rounded-xl shadow-2xl z-50 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-white">
                            <div class="flex items-center gap-3 mb-3">
                                <img :src="avatarUrl" alt="Avatar" class="w-12 h-12 rounded-full object-cover border-2 border-blue-300 shadow-md" />
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-gray-800 text-sm truncate">{{ user.nama_lengkap }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ userOutlet }}</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-gray-600" v-if="userJabatan !== 'N/A'">
                                    <span class="font-medium text-gray-500">Jabatan:</span>
                                    <div class="text-gray-700 truncate">{{ userJabatan }}</div>
                                </div>
                                <div class="text-gray-600" v-if="userLevel !== 'N/A'">
                                    <span class="font-medium text-gray-500">Level:</span>
                                    <div class="text-gray-700 truncate">{{ userLevel }}</div>
                                </div>
                                <div class="text-gray-600 col-span-2" v-if="userDivisi !== 'N/A'">
                                    <span class="font-medium text-gray-500">Divisi:</span>
                                    <div class="text-gray-700 truncate">{{ userDivisi }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="py-2">
                            <template v-if="!isExternalUser">
                            <button @click="showProfileModal = true; showProfileDropdown = false" class="flex items-center gap-3 w-full text-left px-5 py-2.5 hover:bg-blue-50 text-gray-700 hover:text-blue-700 transition-all duration-150 group">
                                <i class="fa-solid fa-user w-5 text-center text-gray-400 group-hover:text-blue-600"></i>
                                <span class="text-sm font-medium">{{ t('profile.profile') }}</span>
                            </button>
                            <button @click="showESignatureModal = true; showProfileDropdown = false" class="flex items-center gap-3 w-full text-left px-5 py-2.5 hover:bg-blue-50 text-gray-700 hover:text-blue-700 transition-all duration-150 group">
                                <i class="fa-solid fa-pen-nib w-5 text-center text-gray-400 group-hover:text-blue-600"></i>
                                <span class="text-sm font-medium">{{ t('profile.esign') }}</span>
                            </button>
                            <button @click="showUserPinModal = true; showProfileDropdown = false" class="flex items-center gap-3 w-full text-left px-5 py-2.5 hover:bg-blue-50 text-gray-700 hover:text-blue-700 transition-all duration-150 group">
                                <i class="fa-solid fa-key w-5 text-center text-gray-400 group-hover:text-blue-600"></i>
                                <span class="text-sm font-medium">Kelola PIN Outlet</span>
                            </button>
                            </template>
                            <div class="px-4 py-3 space-y-2.5 border-t border-gray-200 bg-gray-50/60">
                                <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                                    Download aplikasi mobile
                                </p>
                                <a
                                    :href="mobileAppPlayStoreUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block rounded-lg overflow-hidden bg-black px-3 py-2.5 ring-1 ring-gray-800 hover:ring-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-shadow"
                                    @click="showProfileDropdown = false"
                                >
                                    <img
                                        src="/images/btn_download_mobile_playstore.png"
                                        alt="Unduh di Google Play"
                                        class="w-full h-auto block"
                                    />
                                </a>
                                <a
                                    :href="mobileAppAppStoreUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block rounded-lg overflow-hidden bg-black px-3 py-2.5 ring-1 ring-gray-800 hover:ring-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-shadow"
                                    title="Unduh di App Store"
                                    @click="showProfileDropdown = false"
                                >
                                    <img
                                        src="/images/btn_download_mobile_appstore.png"
                                        alt="Unduh di App Store"
                                        class="w-full h-auto block"
                                    />
                                </a>
                            </div>
                            <Link
                                :href="route(logoutRouteName)"
                                method="post"
                                as="button"
                                class="flex items-center gap-3 w-full text-left px-5 py-2.5 border-t border-gray-200 hover:bg-red-50 text-red-600 hover:text-red-700 transition-all duration-150 group"
                            >
                                <i class="fa-solid fa-right-from-bracket w-5 text-center group-hover:scale-110 transition-transform"></i>
                                <span class="text-sm font-medium">{{ t('profile.logout') }}</span>
                            </Link>
                        </div>
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
    
    <!-- Global Loading Spinner -->
    <LoadingSpinner />
</div>
</template>

<style scoped>
.fas { font-family: 'Font Awesome 5 Free'; font-weight: 900; }

/* Modern Sidebar Styling - Light Theme */
.group-title-modern {
    letter-spacing: 0.5px;
    font-size: 11px;
    transition: all 0.2s ease;
}

.sidebar-menu-modern {
    font-size: 14px;
    position: relative;
    overflow: hidden;
}

.sidebar-menu-modern::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 3px;
    background: linear-gradient(180deg, #60a5fa, #3b82f6);
    transform: scaleY(0);
    transition: transform 0.2s ease;
}

.sidebar-menu-modern:hover::before,
.sidebar-menu-modern.menu-active::before {
    transform: scaleY(1);
}

.sidebar-menu-modern:hover {
    transform: translateX(3px);
}

.sidebar-menu-modern.menu-active {
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}

/* Modern Navbar Styling */
.navbar-modern {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Toast Animations */
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}

.toast-slide-move {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Custom Scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Smooth Transitions */
.sidebar-menu-modern,
.group-title-modern,
button {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
</style> 