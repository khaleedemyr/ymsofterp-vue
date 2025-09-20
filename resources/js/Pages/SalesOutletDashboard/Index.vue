<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';
import Swal from 'sweetalert2';
import OutletDetailsModal from './Components/OutletDetailsModal.vue';
import OutletDailyRevenueModal from './Components/OutletDailyRevenueModal.vue';
import OutletLunchDinnerModal from './Components/OutletLunchDinnerModal.vue';
import OutletWeekendWeekdayModal from './Components/OutletWeekendWeekdayModal.vue';

const props = defineProps({
    dashboardData: Object,
    filters: Object
});

const loading = ref(false);

// Modal state for menu region analysis
const showMenuModal = ref(false);
const selectedMenu = ref(null);
const menuRegionData = ref(null);
const menuRegionLoading = ref(false);

// Modal state for outlet details
const showOutletModal = ref(false);
const selectedDate = ref(null);

// Modal state for outlet daily revenue
const showDailyRevenueModal = ref(false);
const selectedOutlet = ref(null);

// Modal state for lunch/dinner detail
const showLunchDinnerModal = ref(false);
const selectedLunchDinnerOutlet = ref(null);
const selectedMealPeriod = ref(null);
const selectedRegion = ref(null);

// Modal state for weekend/weekday detail
const showWeekendWeekdayModal = ref(false);
const selectedWeekendWeekdayOutlet = ref(null);
const selectedDayType = ref(null);
const selectedWeekendWeekdayRegion = ref(null);

// Holiday data
const holidaysData = ref([]);

// Bank Promo Discount data
const bankPromoTransactions = ref([]);
const bankPromoPagination = ref(null);
const bankPromoLoading = ref(false);
const bankPromoSearch = ref('');
const bankPromoPerPage = ref(10);
const bankPromoCurrentPage = ref(1);
const bankPromoGrandTotal = ref({ total_grand_total: 0, total_discount_amount: 0 });
const bankPromoOutletFilter = ref('');
const bankPromoRegionFilter = ref('');
const bankPromoOutlets = ref([]);
const bankPromoRegions = ref([]);

// Non Promo Bank Discount data
const nonPromoBankTransactions = ref([]);
const nonPromoBankPagination = ref(null);
const nonPromoBankLoading = ref(false);
const nonPromoBankSearch = ref('');
const nonPromoBankPerPage = ref(10);
const nonPromoBankCurrentPage = ref(1);
const nonPromoBankGrandTotal = ref({ total_grand_total: 0, total_discount_amount: 0 });
const nonPromoBankBreakdown = ref([]);
const nonPromoBankOutletFilter = ref('');
const nonPromoBankRegionFilter = ref('');
const nonPromoBankOutlets = ref([]);
const nonPromoBankRegions = ref([]);

// Promo Usage Modal states
const showPromoUsageModal = ref(false);
const promoUsageData = ref([]);
const promoUsageRegionSummary = ref([]);
const promoUsageComparison = ref(null);
const promoUsageLoading = ref(false);
const expandedRegions = ref(new Set());
const expandedOutlets = ref(new Set());

// Filter states
const filters = ref({
    date_from: props.filters.date_from || new Date().toISOString().split('T')[0],
    date_to: props.filters.date_to || new Date().toISOString().split('T')[0]
});

// Computed property for dashboard data
const dashboardData = computed(() => props.dashboardData);

// ApexCharts data
const salesTrendSeries = computed(() => {
    if (!props.dashboardData?.salesTrend) return [];
    
    console.log('Sales Trend Data:', props.dashboardData.salesTrend);
    console.log('Holidays Data:', holidaysData.value);
    
    // Create separate series for weekday, weekend, and holiday orders
    const weekdayOrders = [];
    const weekendOrders = [];
    const holidayOrders = [];
    
    props.dashboardData.salesTrend.forEach((item, index) => {
        const date = new Date(item.period);
        const dayOfWeek = date.getDay(); // 0 = Sunday, 6 = Saturday
        const dateString = item.period;
        
        console.log(`Date ${index}: ${dateString}, Day: ${dayOfWeek}, Orders: ${item.orders}`);
        
        // Check if this date is a holiday
        const isHoliday = holidaysData.value.some(holiday => holiday.date === dateString);
        
        // Ensure we always have a value for orders, even if 0
        const ordersValue = item.orders || 0;
        
        // Push to appropriate series, others get null to avoid rendering issues
        if (isHoliday) {
            weekdayOrders.push(null);
            weekendOrders.push(null);
            holidayOrders.push(ordersValue);
            console.log(`Holiday: ${dateString} - Orders: ${ordersValue} (pushed to holidayOrders)`);
        } else if (dayOfWeek === 0 || dayOfWeek === 6) {
            weekdayOrders.push(null);
            weekendOrders.push(ordersValue);
            holidayOrders.push(null);
            console.log(`Weekend: ${dateString} - Orders: ${ordersValue} (pushed to weekendOrders)`);
        } else {
            weekdayOrders.push(ordersValue);
            weekendOrders.push(null);
            holidayOrders.push(null);
            console.log(`Weekday: ${dateString} - Orders: ${ordersValue} (pushed to weekdayOrders)`);
        }
    });
    
    console.log('Weekday Orders:', weekdayOrders);
    console.log('Weekend Orders:', weekendOrders);
    console.log('Holiday Orders:', holidayOrders);
    console.log('First 3 weekday orders:', weekdayOrders.slice(0, 3));
    console.log('First 3 weekend orders:', weekendOrders.slice(0, 3));
    console.log('First 3 holiday orders:', holidayOrders.slice(0, 3));
    
    const series = [
        {
            name: 'Revenue',
            type: 'line',
            data: props.dashboardData.salesTrend.map(item => item.revenue),
            zIndex: 1
        },
        {
            name: 'Orders (Weekday)',
            type: 'column',
            data: weekdayOrders,
            zIndex: 2
        },
        {
            name: 'Orders (Weekend)',
            type: 'column',
            data: weekendOrders,
            zIndex: 2
        },
        {
            name: 'Orders (Holiday)',
            type: 'column',
            data: holidayOrders,
            zIndex: 2
        }
    ];
    
    console.log('Final Series:', series);
    return series;
});



const salesTrendOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        dropShadow: {
            enabled: true,
            top: 4,
            left: 2,
            blur: 8,
            opacity: 0.18
        },
        events: {
            dataPointSelection: function(event, chartContext, config) {
                // Handle clicks on Orders series (index 1, 2, 3)
                if (config.seriesIndex === 1 || config.seriesIndex === 2 || config.seriesIndex === 3) {
                    const dateIndex = config.dataPointIndex;
                    const salesTrendData = props.dashboardData?.salesTrend;
                    if (salesTrendData && salesTrendData[dateIndex]) {
                        const selectedDateValue = salesTrendData[dateIndex].period;
                        openOutletDetailsModal(selectedDateValue);
                    }
                }
            }
        },
        animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 800,
            animateGradually: {
                enabled: true,
                delay: 150
            },
            dynamicAnimation: {
                enabled: true,
                speed: 350
            }
        },
        stacked: false,
        group: 'orders'
    },
    markers: { 
        size: 0, 
        colors: ['#fff'], 
        strokeColors: ['#3B82F6'], 
        strokeWidth: 0, 
        hover: { size: 0 } 
    },
    xaxis: {
        categories: props.dashboardData?.salesTrend?.map(item => {
            return new Date(item.period).toLocaleDateString('id-ID');
        }) || [],
        offsetX: 0,
        offsetY: 0,
        labels: {
            offsetX: 0,
            offsetY: 0,
            rotate: -45,
            style: {
                fontSize: '12px'
            }
        }
    },
    yaxis: [
        {
            title: { text: 'Revenue (Rp)' },
            labels: {
                formatter: function(value) {
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0
                    }).format(value);
                }
            }
        },
        {
            opposite: true,
            title: { text: 'Orders' }
        }
    ],
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'], // Blue, Green, Orange, Red
    legend: { position: 'top' },
    grid: { borderColor: '#e5e7eb' },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const date = w.globals.labels[dataPointIndex];
            const salesTrendData = props.dashboardData?.salesTrend;
            const dayData = salesTrendData[dataPointIndex];
            
            if (!dayData) return '';
            
            const revenue = dayData.revenue || 0;
            const orders = dayData.orders || 0;
            const pax = dayData.customers || 0; // Backend uses 'customers' field for pax
            const avgPerOrder = orders > 0 ? revenue / orders : 0;
            const avgPerPax = pax > 0 ? revenue / pax : 0;
            
            // Format revenue with proper Indonesian Rupiah format
            const formatCurrency = (value) => {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(value);
            };
            
            return `
                <div class="px-3 py-2 bg-white border border-gray-200 rounded-lg shadow-lg">
                    <div class="text-sm font-semibold text-gray-900 mb-2">${date}</div>
                    <div class="text-sm space-y-1">
                        <div><span class="font-medium">Revenue:</span> ${formatCurrency(revenue)}</div>
                        <div><span class="font-medium">Pax:</span> ${pax.toLocaleString('id-ID')}</div>
                        <div><span class="font-medium">Orders:</span> ${orders.toLocaleString('id-ID')}</div>
                        <div><span class="font-medium">Avg per Order:</span> ${formatCurrency(avgPerOrder)}</div>
                        <div><span class="font-medium">Avg per Pax:</span> ${formatCurrency(avgPerPax)}</div>
                    </div>
                </div>
            `;
        }
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%',
            dataLabels: {
                enabled: false
            },
            horizontal: false,
            hideZeroBarsWhenGrouped: false,
            barHeight: '100%',
            rangeBarOverlap: false,
            rangeBarGroupRows: false,
            startingShape: 'flat',
            endingShape: 'flat'
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        width: [4, 0, 0, 0], // Line for Revenue, 0 for columns
        curve: 'smooth'
    },
    fill: {
        type: ['gradient', 'solid', 'solid', 'solid'], // Gradient for line, solid for columns
        opacity: [0.8, 0.8, 0.8, 0.8]
    },
    noData: {
        text: 'No data available',
        align: 'center',
        verticalAlign: 'middle',
        style: {
            color: '#999',
            fontSize: '14px'
        }
    },
    states: {
        hover: {
            filter: {
                type: 'lighten',
                value: 0.15
            }
        },
        active: {
            allowMultipleDataPointsSelection: false,
            filter: {
                type: 'darken',
                value: 0.35
            }
        }
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom'
            }
        }
    }]
}));

const hourlySalesSeries = computed(() => {
    if (!props.dashboardData?.hourlySales) return [];
    
    return [
        {
            name: 'Orders',
            type: 'column',
            data: props.dashboardData.hourlySales.map(item => item.orders)
        },
        {
            name: 'Revenue',
            type: 'line',
            data: props.dashboardData.hourlySales.map(item => item.revenue)
        }
    ];
});

const hourlySalesOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: props.dashboardData?.hourlySales?.map(item => `${item.hour}:00`) || [],
        title: { text: 'Hour' },
        labels: { style: { fontWeight: 600 } }
    },
    yaxis: [
        {
            title: { text: 'Number of Orders' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => value.toLocaleString()
            }
        },
        {
            opposite: true,
            title: { text: 'Revenue (Rp)' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => formatNumber(value)
            }
        }
    ],
    colors: ['#6366F1', '#10b981'],
    stroke: {
        width: [0, 3],
        curve: 'smooth'
    },
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '60%'
        }
    },
    dataLabels: { 
        enabled: false 
    },
    legend: { 
        position: 'top',
        fontSize: '14px',
        fontWeight: 600
    },
    grid: { 
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.hourlySales?.[dataPointIndex];
            if (data) {
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${data.hour}:00</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-indigo-500 rounded"></div>
                                <span>Orders: ${data.orders.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Revenue: ${formatCurrency(data.revenue)}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-400 rounded"></div>
                                <span>Avg Order: ${formatCurrency(data.avg_order_value)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            return '';
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));

const paymentMethodsSeries = computed(() => {
    if (!props.dashboardData?.paymentMethods || props.dashboardData.paymentMethods.length === 0) return [];
    
    return props.dashboardData.paymentMethods.map(item => parseFloat(item.total_amount) || 0);
});

const paymentMethodsOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        toolbar: { show: true }
    },
    labels: props.dashboardData?.paymentMethods?.map(item => item.payment_code) || [],
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#F97316', '#84CC16', '#06B6D4', '#8B5A2B', '#DC2626', '#059669'],
    legend: {
        position: 'bottom',
        fontSize: '12px',
        fontFamily: 'Inter, sans-serif'
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    name: {
                        show: true,
                        fontSize: '16px',
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: 600,
                        color: '#374151'
                    },
                    value: {
                        show: true,
                        fontSize: '14px',
                        fontFamily: 'Inter, sans-serif',
                        fontWeight: 400,
                        color: '#6B7280',
                        formatter: function (val) {
                            return new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                minimumFractionDigits: 0
                            }).format(val);
                        }
                    }
                }
            }
        }
    },
    tooltip: {
        custom: function({series, seriesIndex, w}) {
            const data = props.dashboardData?.paymentMethods?.[seriesIndex];
            if (data) {
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${data.payment_code}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div>Total: ${new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                minimumFractionDigits: 0
                            }).format(data.total_amount)}</div>
                            <div>Transactions: ${data.transaction_count.toLocaleString()}</div>
                            <div>Average: ${new Intl.NumberFormat('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                minimumFractionDigits: 0
                            }).format(data.avg_amount)}</div>
                        </div>
                    </div>
                `;
            }
            return '';
        }
    },
    dataLabels: {
        enabled: false
    },
    responsive: [{
        breakpoint: 480,
        options: {
            chart: {
                width: 200
            },
            legend: {
                position: 'bottom',
                fontSize: '10px'
            }
        }
    }]
}));

const lunchDinnerSeries = computed(() => {
    if (!props.dashboardData?.lunchDinnerOrders) return [];
    
    const data = props.dashboardData.lunchDinnerOrders;
    return [
        {
            name: 'Revenue',
            type: 'column',
            data: [data.lunch?.total_revenue || 0, data.dinner?.total_revenue || 0]
        },
        {
            name: 'Orders',
            type: 'column',
            data: [data.lunch?.order_count || 0, data.dinner?.order_count || 0]
        },
        {
            name: 'Pax',
            type: 'column',
            data: [data.lunch?.total_pax || 0, data.dinner?.total_pax || 0]
        }
    ];
});

const lunchDinnerOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: ['Lunch (≤17:00)', 'Dinner (>17:00)'],
        title: { text: 'Period' },
        labels: { style: { fontWeight: 600 } }
    },
    yaxis: [
        {
            title: { text: 'Count' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => value.toLocaleString()
            }
        },
        {
            opposite: true,
            title: { text: 'Revenue (Rp)' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => formatNumber(value)
            }
        }
    ],
    colors: ['#10b981', '#3b82f6', '#f59e0b'],
    stroke: {
        width: [0, 0, 0],
        curve: 'smooth'
    },
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '60%'
        }
    },
    dataLabels: { 
        enabled: false 
    },
    legend: { 
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.lunchDinnerOrders;
            if (!data) return seriesName;
            
            if (seriesName === 'Revenue') {
                const lunchRevenue = data.lunch?.total_revenue || 0;
                const dinnerRevenue = data.dinner?.total_revenue || 0;
                const totalRevenue = lunchRevenue + dinnerRevenue;
                return `${seriesName}: ${formatCurrency(totalRevenue)}`;
            } else if (seriesName === 'Orders') {
                const lunchOrders = data.lunch?.order_count || 0;
                const dinnerOrders = data.dinner?.order_count || 0;
                const totalOrders = lunchOrders + dinnerOrders;
                return `${seriesName}: ${totalOrders.toLocaleString()}`;
        } else if (seriesName === 'Pax') {
            const lunchPax = data.lunch?.total_pax || 0;
            const dinnerPax = data.dinner?.total_pax || 0;
            const totalPax = lunchPax + dinnerPax;
            return `${seriesName}: ${totalPax.toLocaleString()}`;
        }
            return seriesName;
        }
    },
    grid: { 
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.lunchDinnerOrders;
            const period = dataPointIndex === 0 ? 'lunch' : 'dinner';
            const periodData = data?.[period];
            
            if (periodData) {
                const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${dataPointIndex === 0 ? 'Lunch (≤17:00)' : 'Dinner (>17:00)'}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-400 rounded"></div>
                                <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            return '';
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));

const weekdayWeekendSeries = computed(() => {
    if (!props.dashboardData?.weekdayWeekendRevenue) return [];
    
    const data = props.dashboardData.weekdayWeekendRevenue;
    return [
        {
            name: 'Revenue',
            type: 'column',
            data: [data.weekday?.total_revenue || 0, data.weekend?.total_revenue || 0]
        },
        {
            name: 'Orders',
            type: 'column',
            data: [data.weekday?.order_count || 0, data.weekend?.order_count || 0]
        },
        {
            name: 'Pax',
            type: 'column',
            data: [data.weekday?.total_pax || 0, data.weekend?.total_pax || 0]
        }
    ];
});

const weekdayWeekendOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 350,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: ['Weekday (Mon-Fri)', 'Weekend (Sat-Sun)'],
        title: { text: 'Period' },
        labels: { style: { fontWeight: 600 } }
    },
    yaxis: [
        {
            title: { text: 'Count' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => value.toLocaleString()
            }
        },
        {
            opposite: true,
            title: { text: 'Revenue (Rp)' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => formatNumber(value)
            }
        }
    ],
    colors: ['#3b82f6', '#10b981', '#f59e0b'],
    stroke: {
        width: [0, 0, 0],
        curve: 'smooth'
    },
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '60%'
        }
    },
    dataLabels: { 
        enabled: false 
    },
    legend: { 
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.weekdayWeekendRevenue;
            if (!data) return seriesName;
            
            if (seriesName === 'Revenue') {
                const weekdayRevenue = data.weekday?.total_revenue || 0;
                const weekendRevenue = data.weekend?.total_revenue || 0;
                const totalRevenue = weekdayRevenue + weekendRevenue;
                return `${seriesName}: ${formatCurrency(totalRevenue)}`;
            } else if (seriesName === 'Orders') {
                const weekdayOrders = data.weekday?.order_count || 0;
                const weekendOrders = data.weekend?.order_count || 0;
                const totalOrders = weekdayOrders + weekendOrders;
                return `${seriesName}: ${totalOrders.toLocaleString()}`;
        } else if (seriesName === 'Pax') {
            const weekdayPax = data.weekday?.total_pax || 0;
            const weekendPax = data.weekend?.total_pax || 0;
            const totalPax = weekdayPax + weekendPax;
            return `${seriesName}: ${totalPax.toLocaleString()}`;
        }
            return seriesName;
        }
    },
    grid: { 
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.weekdayWeekendRevenue;
            const period = dataPointIndex === 0 ? 'weekday' : 'weekend';
            const periodData = data?.[period];
            
            if (periodData) {
                const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
                return `
                    <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                        <div class="font-semibold text-gray-900">${dataPointIndex === 0 ? 'Weekday (Mon-Fri)' : 'Weekend (Sat-Sun)'}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-500 rounded"></div>
                                <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-500 rounded"></div>
                                <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-orange-500 rounded"></div>
                                <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-400 rounded"></div>
                                <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
            return '';
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));

// Revenue per Outlet by Region (Lunch/Dinner)
const revenuePerOutletLunchDinnerSeries = computed(() => {
    if (!props.dashboardData?.revenuePerOutletLunchDinner) return [];

    const data = props.dashboardData.revenuePerOutletLunchDinner;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    // Create series for each region and meal period
    const series = [];
    const categories = [];
    
    // Get all unique outlets across all regions
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    categories.push(...Array.from(allOutlets));
    
    // Create series for each region and meal period
    regions.forEach(region => {
        // Lunch series
        const lunchData = new Array(categories.length).fill(0);
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                lunchData[index] = outlet.lunch?.total_revenue || 0;
            }
        });
        
        series.push({
            name: `${region} - Lunch`,
            data: lunchData
        });
        
        // Dinner series
        const dinnerData = new Array(categories.length).fill(0);
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                dinnerData[index] = outlet.dinner?.total_revenue || 0;
            }
        });
        
        series.push({
            name: `${region} - Dinner`,
            data: dinnerData
        });
    });

    return series;
});

const revenuePerOutletLunchDinnerOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
        events: {
            dataPointSelection: function(event, chartContext, config) {
                console.log('Lunch/Dinner Chart clicked:', config);
                handleLunchDinnerChartClick(config);
            },
            click: function(event, chartContext, config) {
                console.log('Lunch/Dinner Chart click event:', config);
                handleLunchDinnerChartClick(config);
            }
        }
    },
    xaxis: {
        categories: revenuePerOutletLunchDinnerSeries.value.length > 0 ? 
            (() => {
                const data = props.dashboardData?.revenuePerOutletLunchDinner;
                if (!data) return [];
                
                const allOutlets = new Set();
                Object.keys(data).forEach(region => {
                    data[region].outlets.forEach(outlet => {
                        allOutlets.add(outlet.outlet_name);
                    });
                });
                
                return Array.from(allOutlets);
            })() : [],
        title: { text: 'Outlets' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45,
            maxHeight: 120
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#3b82f6', '#1d4ed8', '#10b981', '#059669', '#f59e0b', '#d97706', '#ef4444', '#dc2626', '#8b5cf6', '#7c3aed', '#06b6d4', '#0891b2'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerOutletLunchDinner;
            if (!data) return seriesName;
            
            // Parse series name to get region and meal period
            const parts = seriesName.split(' - ');
            if (parts.length !== 2) return seriesName;
            
            const regionName = parts[0];
            const mealPeriod = parts[1];
            
            if (!data[regionName]) return seriesName;
            
            const regionData = data[regionName];
            const mealData = mealPeriod === 'Lunch' ? regionData.lunch : regionData.dinner;
            
            return `${seriesName}: ${formatCurrency(mealData.total_revenue)} (${mealData.total_orders} orders)`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: false,
        intersect: true,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerOutletLunchDinner;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const seriesName = w.globals.seriesNames[seriesIndex];
            const parts = seriesName.split(' - ');
            
            if (parts.length !== 2) return '';
            
            const regionName = parts[0];
            const mealPeriod = parts[1];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            // Find the outlet that corresponds to this data point
            const outletName = w.globals.labels[dataPointIndex];
            const outlet = regionData.outlets.find(o => o.outlet_name === outletName);
            
            if (!outlet) return '';
            
            const mealData = mealPeriod === 'Lunch' ? outlet.lunch : outlet.dinner;
            const avgCheck = mealData.total_pax > 0 ? mealData.total_revenue / mealData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${outlet.outlet_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex justify-between">
                            <span>Region:</span>
                            <span class="font-medium">${regionName}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Meal Period:</span>
                            <span class="font-medium">${mealPeriod}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Revenue:</span>
                            <span class="font-medium text-green-600">${formatCurrency(mealData.total_revenue)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Orders:</span>
                            <span class="font-medium">${formatNumber(mealData.order_count)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pax:</span>
                            <span class="font-medium">${formatNumber(mealData.total_pax)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Avg Check:</span>
                            <span class="font-medium">${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    }
}));

// Revenue per Outlet by Region (Weekend/Weekday)
const revenuePerOutletWeekendWeekdaySeries = computed(() => {
    if (!props.dashboardData?.revenuePerOutletWeekendWeekday) return [];

    const data = props.dashboardData.revenuePerOutletWeekendWeekday;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    // Create series for each region and day type
    const series = [];
    const categories = [];
    
    // Get all unique outlets across all regions
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    categories.push(...Array.from(allOutlets));
    
    // Create series for each region and day type
    regions.forEach(region => {
        // Weekend series
        const weekendData = new Array(categories.length).fill(0);
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                weekendData[index] = outlet.weekend?.total_revenue || 0;
            }
        });
        
        series.push({
            name: `${region} - Weekend`,
            data: weekendData
        });
        
        // Weekday series
        const weekdayData = new Array(categories.length).fill(0);
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                weekdayData[index] = outlet.weekday?.total_revenue || 0;
            }
        });
        
        series.push({
            name: `${region} - Weekday`,
            data: weekdayData
        });
    });

    return series;
});

const revenuePerOutletWeekendWeekdayOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
        events: {
            dataPointSelection: function(event, chartContext, config) {
                console.log('Weekend/Weekday Chart clicked:', config);
                handleWeekendWeekdayChartClick(config);
            },
            click: function(event, chartContext, config) {
                console.log('Weekend/Weekday Chart click event:', config);
                handleWeekendWeekdayChartClick(config);
            }
        }
    },
    xaxis: {
        categories: revenuePerOutletWeekendWeekdaySeries.value.length > 0 ? 
            (() => {
                const data = props.dashboardData?.revenuePerOutletWeekendWeekday;
                if (!data) return [];
                
                const allOutlets = new Set();
                Object.keys(data).forEach(region => {
                    data[region].outlets.forEach(outlet => {
                        allOutlets.add(outlet.outlet_name);
                    });
                });
                
                return Array.from(allOutlets);
            })() : [],
        title: { text: 'Outlets' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45,
            maxHeight: 120
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#8b5cf6', '#7c3aed', '#06b6d4', '#0891b2', '#84cc16', '#65a30d', '#f97316', '#ea580c', '#ef4444', '#dc2626', '#ec4899', '#db2777'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerOutletWeekendWeekday;
            if (!data) return seriesName;
            
            // Parse series name to get region and day type
            const parts = seriesName.split(' - ');
            if (parts.length !== 2) return seriesName;
            
            const regionName = parts[0];
            const dayType = parts[1];
            
            if (!data[regionName]) return seriesName;
            
            const regionData = data[regionName];
            const dayData = dayType === 'Weekend' ? regionData.weekend : regionData.weekday;
            
            return `${seriesName}: ${formatCurrency(dayData.total_revenue)} (${dayData.total_orders} orders)`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: false,
        intersect: true,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerOutletWeekendWeekday;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const seriesName = w.globals.seriesNames[seriesIndex];
            const parts = seriesName.split(' - ');
            
            if (parts.length !== 2) return '';
            
            const regionName = parts[0];
            const dayType = parts[1];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            // Find the outlet that corresponds to this data point
            const outletName = w.globals.labels[dataPointIndex];
            const outlet = regionData.outlets.find(o => o.outlet_name === outletName);
            
            if (!outlet) return '';
            
            const dayData = dayType === 'Weekend' ? outlet.weekend : outlet.weekday;
            const avgCheck = dayData.total_pax > 0 ? dayData.total_revenue / dayData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${outlet.outlet_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex justify-between">
                            <span>Region:</span>
                            <span class="font-medium">${regionName}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Day Type:</span>
                            <span class="font-medium">${dayType}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Revenue:</span>
                            <span class="font-medium text-green-600">${formatCurrency(dayData.total_revenue)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Orders:</span>
                            <span class="font-medium">${formatNumber(dayData.order_count)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pax:</span>
                            <span class="font-medium">${formatNumber(dayData.total_pax)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Avg Check:</span>
                            <span class="font-medium">${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    }
}));

// Revenue per Outlet by Region
const revenuePerOutletSeries = computed(() => {
    if (!props.dashboardData?.revenuePerOutlet) return [];

    const data = props.dashboardData.revenuePerOutlet;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    // Create series for each region
    const series = [];
    const categories = [];
    
    // Get all unique outlets across all regions
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    categories.push(...Array.from(allOutlets));
    
    // Create series for each region
    regions.forEach(region => {
        const regionData = new Array(categories.length).fill(0);
        
        data[region].outlets.forEach(outlet => {
            const index = categories.indexOf(outlet.outlet_name);
            if (index !== -1) {
                regionData[index] = outlet.total_revenue;
            }
        });
        
        series.push({
            name: region,
            data: regionData
        });
    });

    return series;
});

const revenuePerOutletOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 },
        events: {
            dataPointSelection: function(event, chartContext, config) {
                console.log('Chart clicked:', config);
                handleChartClick(config);
            },
            click: function(event, chartContext, config) {
                console.log('Chart click event:', config);
                handleChartClick(config);
            }
        }
    },
    xaxis: {
        categories: revenuePerOutletSeries.value.length > 0 ? 
            (() => {
                const data = props.dashboardData?.revenuePerOutlet;
                if (!data) return [];
                
                const allOutlets = new Set();
                Object.keys(data).forEach(region => {
                    data[region].outlets.forEach(outlet => {
                        allOutlets.add(outlet.outlet_name);
                    });
                });
                
                return Array.from(allOutlets);
            })() : [],
        title: { text: 'Outlets' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45,
            maxHeight: 120
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerOutlet;
            if (!data || !data[seriesName]) return seriesName;
            
            const regionData = data[seriesName];
            return `${seriesName}: ${formatCurrency(regionData.total_revenue)} (${regionData.outlets.length} outlets)`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: false,
        intersect: true,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerOutlet;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[seriesIndex];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            // Find the outlet that corresponds to this data point
            const outletName = w.globals.labels[dataPointIndex];
            const outlet = regionData.outlets.find(o => o.outlet_name === outletName);
            
            if (!outlet) return '';
            
            const avgCheck = outlet.total_pax > 0 ? outlet.total_revenue / outlet.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${outlet.outlet_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Region: ${regionName}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(outlet.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Orders: ${outlet.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-purple-500 rounded"></div>
                            <span>Pax: ${outlet.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            },
            xaxis: {
                labels: {
                    rotate: -90,
                    maxHeight: 100
                }
            }
        }
    }]
}));

// Revenue per Region Charts
const revenuePerRegionTotalSeries = computed(() => {
    if (!props.dashboardData?.revenuePerRegion?.total_revenue) return [];

    const data = props.dashboardData.revenuePerRegion.total_revenue;
    
    return [{
        name: 'Revenue',
        data: data.map(region => region.total_revenue)
    }];
});

const revenuePerRegionTotalOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 350,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: props.dashboardData?.revenuePerRegion?.total_revenue?.map(region => region.region_name) || [],
        title: { text: 'Regions' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#3b82f6'],
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerRegion?.total_revenue;
            if (!data || !data[dataPointIndex]) return '';
            
            const region = data[dataPointIndex];
            const avgCheck = region.total_pax > 0 ? region.total_revenue / region.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${region.region_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(region.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Orders: ${region.total_orders.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Pax: ${region.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));

// Lunch/Dinner Revenue per Region
const revenuePerRegionLunchDinnerSeries = computed(() => {
    if (!props.dashboardData?.revenuePerRegion?.lunch_dinner) return [];

    const data = props.dashboardData.revenuePerRegion.lunch_dinner;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    return [
        {
            name: 'Lunch',
            data: regions.map(region => data[region].lunch?.total_revenue || 0)
        },
        {
            name: 'Dinner',
            data: regions.map(region => data[region].dinner?.total_revenue || 0)
        }
    ];
});

const revenuePerRegionLunchDinnerOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: Object.keys(props.dashboardData?.revenuePerRegion?.lunch_dinner || {}),
        title: { text: 'Regions' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#10b981', '#f59e0b'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerRegion?.lunch_dinner;
            if (!data) return seriesName;
            
            let totalRevenue = 0;
            Object.keys(data).forEach(region => {
                if (seriesName === 'Lunch') {
                    totalRevenue += data[region].lunch?.total_revenue || 0;
                } else if (seriesName === 'Dinner') {
                    totalRevenue += data[region].dinner?.total_revenue || 0;
                }
            });
            
            return `${seriesName}: ${formatCurrency(totalRevenue)}`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: false,
        intersect: true,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerRegion?.lunch_dinner;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[dataPointIndex];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            const period = seriesIndex === 0 ? 'lunch' : 'dinner';
            const periodData = regionData[period];
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${regionName} - ${seriesIndex === 0 ? 'Lunch' : 'Dinner'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 ${seriesIndex === 0 ? 'bg-green-500' : 'bg-orange-500'} rounded"></div>
                            <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-purple-500 rounded"></div>
                            <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));

// Weekday/Weekend Revenue per Region
const revenuePerRegionWeekdayWeekendSeries = computed(() => {
    if (!props.dashboardData?.revenuePerRegion?.weekday_weekend) return [];

    const data = props.dashboardData.revenuePerRegion.weekday_weekend;
    const regions = Object.keys(data);
    
    if (regions.length === 0) return [];

    return [
        {
            name: 'Weekday',
            data: regions.map(region => data[region].weekday?.total_revenue || 0)
        },
        {
            name: 'Weekend',
            data: regions.map(region => data[region].weekend?.total_revenue || 0)
        }
    ];
});

const revenuePerRegionWeekdayWeekendOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 350,
        stacked: true,
        toolbar: { show: true },
        animations: { enabled: true, easing: 'easeinout', speed: 800 }
    },
    xaxis: {
        categories: Object.keys(props.dashboardData?.revenuePerRegion?.weekday_weekend || {}),
        title: { text: 'Regions' },
        labels: { 
            style: { fontWeight: 600 },
            rotate: -45
        }
    },
    yaxis: {
        title: { text: 'Revenue (Rp)' },
        min: 0,
        labels: {
            style: { fontWeight: 600 },
            formatter: (value) => formatNumber(value)
        }
    },
    colors: ['#3b82f6', '#8b5cf6'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '60%'
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'top',
        fontSize: '14px',
        fontWeight: 600,
        formatter: function(seriesName, opts) {
            const data = props.dashboardData?.revenuePerRegion?.weekday_weekend;
            if (!data) return seriesName;
            
            let totalRevenue = 0;
            Object.keys(data).forEach(region => {
                if (seriesName === 'Weekday') {
                    totalRevenue += data[region].weekday?.total_revenue || 0;
                } else if (seriesName === 'Weekend') {
                    totalRevenue += data[region].weekend?.total_revenue || 0;
                }
            });
            
            return `${seriesName}: ${formatCurrency(totalRevenue)}`;
        }
    },
    grid: {
        borderColor: '#e5e7eb',
        strokeDashArray: 4
    },
    tooltip: {
        shared: false,
        intersect: true,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = props.dashboardData?.revenuePerRegion?.weekday_weekend;
            if (!data) return '';
            
            const regions = Object.keys(data);
            const regionName = regions[dataPointIndex];
            const regionData = data[regionName];
            
            if (!regionData) return '';
            
            const period = seriesIndex === 0 ? 'weekday' : 'weekend';
            const periodData = regionData[period];
            const avgCheck = periodData.total_pax > 0 ? periodData.total_revenue / periodData.total_pax : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${regionName} - ${seriesIndex === 0 ? 'Weekday' : 'Weekend'}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 ${seriesIndex === 0 ? 'bg-blue-500' : 'bg-purple-500'} rounded"></div>
                            <span>Revenue: ${formatCurrency(periodData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Orders: ${periodData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-orange-500 rounded"></div>
                            <span>Pax: ${periodData.total_pax.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Check: ${formatCurrency(avgCheck)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    },
    responsive: [{
        breakpoint: 768,
        options: {
            chart: {
                height: 300
            },
            legend: {
                position: 'bottom',
                fontSize: '12px'
            }
        }
    }]
}));


// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(date) {
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getGrowthColor(growth) {
    if (growth > 0) return 'text-green-600';
    if (growth < 0) return 'text-red-600';
    return 'text-gray-600';
}

function getGrowthIcon(growth) {
    if (growth > 0) return 'fa-arrow-up';
    if (growth < 0) return 'fa-arrow-down';
    return 'fa-minus';
}

// Filter functions
function applyFilters() {
    loading.value = true;
    router.get(route('sales-outlet-dashboard.index'), filters.value, {
        preserveState: true,
        onFinish: () => {
            loading.value = false;
        }
    });
}

function resetFilters() {
    filters.value = {
        date_from: new Date().toISOString().split('T')[0],
        date_to: new Date().toISOString().split('T')[0]
    };
    applyFilters();
}


// Watch for filter changes
watch(filters, () => {
    applyFilters();
    fetchHolidays();
}, { deep: true });

// Initialize holidays data on mount
onMounted(() => {
    fetchHolidays();
    fetchBankPromoTransactions();
    fetchBankPromoOutlets();
    fetchBankPromoRegions();
    fetchNonPromoBankTransactions();
    fetchNonPromoBankOutlets();
    fetchNonPromoBankRegions();
});

// Watch for dashboard data changes to fetch bank promo transactions
watch(dashboardData, () => {
    fetchBankPromoTransactions();
    fetchNonPromoBankTransactions();
}, { deep: true });

// Menu region analysis functions
async function openMenuModal(menuItem) {
    selectedMenu.value = menuItem;
    showMenuModal.value = true;
    menuRegionLoading.value = true;
    
    try {
        const response = await axios.get(route('sales-outlet-dashboard.menu-region'), {
            params: {
                item_name: menuItem.item_name,
                date_from: filters.value.date_from,
                date_to: filters.value.date_to
            }
        });
        
        menuRegionData.value = response.data;
    } catch (error) {
        console.error('Error fetching menu region data:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load menu region data'
        });
    } finally {
        menuRegionLoading.value = false;
    }
}

function closeMenuModal() {
    showMenuModal.value = false;
    selectedMenu.value = null;
    menuRegionData.value = null;
}

function openOutletDetailsModal(date) {
    selectedDate.value = date;
    showOutletModal.value = true;
}

function closeOutletModal() {
    showOutletModal.value = false;
    selectedDate.value = null;
}

function openDailyRevenueModal(outlet) {
    selectedOutlet.value = outlet;
    showDailyRevenueModal.value = true;
}

function closeDailyRevenueModal() {
    showDailyRevenueModal.value = false;
    selectedOutlet.value = null;
}

function openLunchDinnerModal(outlet, region, mealPeriod) {
    selectedLunchDinnerOutlet.value = outlet;
    selectedRegion.value = region;
    selectedMealPeriod.value = mealPeriod;
    showLunchDinnerModal.value = true;
}

function closeLunchDinnerModal() {
    showLunchDinnerModal.value = false;
    selectedLunchDinnerOutlet.value = null;
    selectedRegion.value = null;
    selectedMealPeriod.value = null;
}

function openWeekendWeekdayModal(outlet, region, dayType) {
    selectedWeekendWeekdayOutlet.value = outlet;
    selectedWeekendWeekdayRegion.value = region;
    selectedDayType.value = dayType;
    showWeekendWeekdayModal.value = true;
}

function closeWeekendWeekdayModal() {
    showWeekendWeekdayModal.value = false;
    selectedWeekendWeekdayOutlet.value = null;
    selectedWeekendWeekdayRegion.value = null;
    selectedDayType.value = null;
}

// Promo Usage Modal functions
async function openPromoUsageModal() {
    showPromoUsageModal.value = true;
    promoUsageLoading.value = true;
    expandedRegions.value.clear();
    expandedOutlets.value.clear();
    
    try {
        const response = await axios.get('/sales-outlet-dashboard/promo-usage-by-outlet', {
            params: {
                date_from: filters.value.date_from,
                date_to: filters.value.date_to
            }
        });
        
        promoUsageData.value = response.data.data || [];
        promoUsageRegionSummary.value = response.data.region_summary || [];
        promoUsageComparison.value = response.data.comparison || null;
    } catch (error) {
        console.error('Error fetching promo usage data:', error);
        promoUsageData.value = [];
        promoUsageRegionSummary.value = [];
    } finally {
        promoUsageLoading.value = false;
    }
}

function closePromoUsageModal() {
    showPromoUsageModal.value = false;
    promoUsageData.value = [];
    promoUsageRegionSummary.value = [];
    promoUsageComparison.value = null;
    expandedRegions.value.clear();
    expandedOutlets.value.clear();
}

function toggleRegionExpansion(regionName) {
    if (expandedRegions.value.has(regionName)) {
        expandedRegions.value.delete(regionName);
    } else {
        expandedRegions.value.add(regionName);
    }
}

function toggleOutletExpansion(outletCode) {
    if (expandedOutlets.value.has(outletCode)) {
        expandedOutlets.value.delete(outletCode);
    } else {
        expandedOutlets.value.add(outletCode);
    }
}

async function fetchHolidays() {
    try {
        const response = await axios.get('/sales-outlet-dashboard/holidays', {
            params: {
                date_from: filters.value.date_from,
                date_to: filters.value.date_to
            }
        });
        holidaysData.value = response.data;
    } catch (error) {
        console.error('Error fetching holidays:', error);
        holidaysData.value = [];
    }
}

// Bank Promo Discount functions
async function fetchBankPromoTransactions() {
    if (!dashboardData.value?.bankPromoDiscount?.orders_with_bank_promo) return;
    
    bankPromoLoading.value = true;
    try {
        const response = await axios.get('/sales-outlet-dashboard/bank-promo-discount-transactions', {
            params: {
                date_from: filters.value.date_from,
                date_to: filters.value.date_to,
                search: bankPromoSearch.value,
                outlet: bankPromoOutletFilter.value,
                region: bankPromoRegionFilter.value,
                page: bankPromoCurrentPage.value,
                per_page: bankPromoPerPage.value
            }
        });
        
        bankPromoTransactions.value = response.data.transactions || [];
        bankPromoPagination.value = response.data.pagination || null;
        bankPromoGrandTotal.value = response.data.grand_total || { total_grand_total: 0, total_discount_amount: 0 };
    } catch (error) {
        console.error('Error fetching bank promo transactions:', error);
        bankPromoTransactions.value = [];
        bankPromoPagination.value = null;
    } finally {
        bankPromoLoading.value = false;
    }
}

// Fetch outlets for bank promo filter
async function fetchBankPromoOutlets() {
    try {
        const response = await axios.get('/api/outlets/report');
        bankPromoOutlets.value = response.data.outlets || [];
    } catch (error) {
        console.error('Error fetching outlets:', error);
        bankPromoOutlets.value = [];
    }
}

// Fetch regions for bank promo filter
async function fetchBankPromoRegions() {
    try {
        const response = await axios.get('/api/regions');
        bankPromoRegions.value = response.data.regions || [];
    } catch (error) {
        console.error('Error fetching regions:', error);
        bankPromoRegions.value = [];
    }
}

function searchBankPromoTransactions() {
    // Debounce search
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        bankPromoCurrentPage.value = 1;
        fetchBankPromoTransactions();
    }, 500);
}

// Handle outlet filter change
function onBankPromoOutletChange() {
    bankPromoCurrentPage.value = 1;
    fetchBankPromoTransactions();
}

// Handle region filter change
function onBankPromoRegionChange() {
    bankPromoOutletFilter.value = ''; // Reset outlet filter when region changes
    bankPromoCurrentPage.value = 1;
    fetchBankPromoTransactions();
}

function goToBankPromoPage(page) {
    if (page < 1 || page > bankPromoPagination.value.total_pages) return;
    bankPromoCurrentPage.value = page;
    fetchBankPromoTransactions();
}

function getBankPromoPageNumbers() {
    if (!bankPromoPagination.value) return [];
    
    const current = bankPromoPagination.value.current_page;
    const total = bankPromoPagination.value.total_pages;
    const pages = [];
    
    // Show max 5 page numbers
    let start = Math.max(1, current - 2);
    let end = Math.min(total, start + 4);
    
    // Adjust start if we're near the end
    if (end - start < 4) {
        start = Math.max(1, end - 4);
    }
    
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    
    return pages;
}

let searchTimeout = null;

// Export function
async function exportBankPromoTransactions() {
    if (!filters.value.date_from || !filters.value.date_to) {
        alert('Silakan pilih rentang tanggal terlebih dahulu');
        return;
    }
    
    if (!bankPromoTransactions.value.length) {
        alert('Tidak ada data untuk di-export');
        return;
    }
    
    try {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>Exporting...';
        button.disabled = true;
        
        // Use axios to download the file (same as Report Rekap FJ)
        const response = await axios.get('/sales-outlet-dashboard/export-bank-promo-discount-transactions', {
            params: { 
                date_from: filters.value.date_from, 
                date_to: filters.value.date_to,
                search: bankPromoSearch.value,
                outlet: bankPromoOutletFilter.value,
                region: bankPromoRegionFilter.value
            },
            responseType: 'blob'
        });
        
        // Create blob and download
        const blob = new Blob([response.data], { 
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Bank_Promo_Discount_Transactions_${filters.value.date_from}_to_${filters.value.date_to}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
        
    } catch (error) {
        console.error('Export error:', error);
        alert('Terjadi kesalahan saat export. Silakan coba lagi.');
        
        // Reset button
        const button = event.target;
        button.innerHTML = '<i class="fa-solid fa-file-excel mr-2"></i>Export Excel';
        button.disabled = false;
    }
}

// Non Promo Bank Discount functions
async function fetchNonPromoBankTransactions() {
    nonPromoBankLoading.value = true;
    try {
        const response = await axios.get('/sales-outlet-dashboard/non-promo-bank-discount-transactions', {
            params: {
                date_from: filters.value.date_from,
                date_to: filters.value.date_to,
                search: nonPromoBankSearch.value,
                outlet: nonPromoBankOutletFilter.value,
                region: nonPromoBankRegionFilter.value,
                page: nonPromoBankCurrentPage.value,
                per_page: nonPromoBankPerPage.value
            }
        });
        
        nonPromoBankTransactions.value = response.data.transactions || [];
        nonPromoBankPagination.value = response.data.pagination || null;
        nonPromoBankGrandTotal.value = response.data.grand_total || { total_grand_total: 0, total_discount_amount: 0 };
        nonPromoBankBreakdown.value = response.data.breakdown_by_reason || [];
    } catch (error) {
        console.error('Error fetching non promo bank transactions:', error);
        nonPromoBankTransactions.value = [];
        nonPromoBankPagination.value = null;
    } finally {
        nonPromoBankLoading.value = false;
    }
}

// Fetch outlets for non promo bank filter
async function fetchNonPromoBankOutlets() {
    try {
        const response = await axios.get('/api/outlets/report');
        nonPromoBankOutlets.value = response.data.outlets || [];
    } catch (error) {
        console.error('Error fetching outlets:', error);
        nonPromoBankOutlets.value = [];
    }
}

// Fetch regions for non promo bank filter
async function fetchNonPromoBankRegions() {
    try {
        const response = await axios.get('/api/regions');
        nonPromoBankRegions.value = response.data.regions || [];
    } catch (error) {
        console.error('Error fetching regions:', error);
        nonPromoBankRegions.value = [];
    }
}

function searchNonPromoBankTransactions() {
    // Debounce search
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        nonPromoBankCurrentPage.value = 1;
        fetchNonPromoBankTransactions();
    }, 500);
}

// Handle outlet filter change
function onNonPromoBankOutletChange() {
    nonPromoBankCurrentPage.value = 1;
    fetchNonPromoBankTransactions();
}

// Handle region filter change
function onNonPromoBankRegionChange() {
    nonPromoBankOutletFilter.value = ''; // Reset outlet filter when region changes
    nonPromoBankCurrentPage.value = 1;
    fetchNonPromoBankTransactions();
}

function goToNonPromoBankPage(page) {
    if (page < 1 || page > nonPromoBankPagination.value.total_pages) return;
    nonPromoBankCurrentPage.value = page;
    fetchNonPromoBankTransactions();
}

function getNonPromoBankPageNumbers() {
    if (!nonPromoBankPagination.value) return [];
    
    const current = nonPromoBankPagination.value.current_page;
    const total = nonPromoBankPagination.value.total_pages;
    const pages = [];
    
    // Show up to 5 pages around current page
    const start = Math.max(1, current - 2);
    const end = Math.min(total, current + 2);
    
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    
    return pages;
}

async function exportNonPromoBankTransactions(event) {
    if (!filters.value.date_from || !filters.value.date_to) {
        alert('Pilih tanggal terlebih dahulu');
        return;
    }
    
    if (!nonPromoBankTransactions.value.length) {
        alert('Tidak ada data untuk di-export');
        return;
    }
    
    try {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="mr-2"><i class="fas fa-spinner fa-spin"></i></span>Exporting...';
        button.disabled = true;
        
        // Use axios to download the file
        const response = await axios.get('/sales-outlet-dashboard/export-non-promo-bank-discount-transactions', {
            params: { 
                date_from: filters.value.date_from, 
                date_to: filters.value.date_to,
                search: nonPromoBankSearch.value,
                outlet: nonPromoBankOutletFilter.value,
                region: nonPromoBankRegionFilter.value
            },
            responseType: 'blob'
        });
        
        // Create blob and download
        const blob = new Blob([response.data], { 
            type: 'text/csv' 
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Non_Promo_Bank_Discount_Transactions_${filters.value.date_from}_to_${filters.value.date_to}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        
        // Reset button
        button.innerHTML = originalText;
        button.disabled = false;
        
    } catch (error) {
        console.error('Export error:', error);
        alert('Terjadi kesalahan saat export. Silakan coba lagi.');
        
        // Reset button
        const button = event.target;
        button.innerHTML = '<i class="fa-solid fa-download mr-2"></i>Export CSV';
        button.disabled = false;
    }
}

function handleChartClick(config) {
    if (!config || config.dataPointIndex === undefined || config.seriesIndex === undefined) {
        console.log('Invalid click config:', config);
        return;
    }
    
    const outletIndex = config.dataPointIndex;
    const regionIndex = config.seriesIndex;
    
    const data = props.dashboardData?.revenuePerOutlet;
    if (!data) {
        console.log('No revenue data available');
        return;
    }
    
    const regions = Object.keys(data);
    const regionName = regions[regionIndex];
    
    console.log('Click - Outlet index:', outletIndex, 'Region index:', regionIndex, 'Region name:', regionName);
    console.log('Available regions:', regions);
    console.log('Region data:', data[regionName]);
    
    if (!data[regionName] || !data[regionName].outlets) {
        console.log('No outlets found for region:', regionName);
        return;
    }
    
    // Get all unique outlets across all regions to find the correct outlet
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    const allOutletsArray = Array.from(allOutlets);
    const clickedOutletName = allOutletsArray[outletIndex];
    
    console.log('All outlets:', allOutletsArray);
    console.log('Clicked outlet name:', clickedOutletName);
    
    // Find the outlet in the clicked region
    const outlet = data[regionName].outlets.find(outlet => outlet.outlet_name === clickedOutletName);
    
    if (outlet) {
        console.log('Found outlet:', outlet);
        openDailyRevenueModal(outlet);
    } else {
        console.log('Outlet not found in region:', regionName, 'for outlet name:', clickedOutletName);
    }
}

function handleLunchDinnerChartClick(config) {
    if (!config || config.dataPointIndex === undefined || config.seriesIndex === undefined) {
        console.log('Invalid lunch/dinner click config:', config);
        return;
    }
    
    const outletIndex = config.dataPointIndex;
    const seriesIndex = config.seriesIndex;
    
    const data = props.dashboardData?.revenuePerOutletLunchDinner;
    if (!data) {
        console.log('No lunch/dinner revenue data available');
        return;
    }
    
    const regions = Object.keys(data);
    
    // Get all unique outlets across all regions to find the correct outlet
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    const allOutletsArray = Array.from(allOutlets);
    const clickedOutletName = allOutletsArray[outletIndex];
    
    // Determine meal period from series index
    // Each region has 2 series: Lunch and Dinner
    // So seriesIndex 0,2,4... = Lunch, seriesIndex 1,3,5... = Dinner
    const mealPeriod = seriesIndex % 2 === 0 ? 'Lunch' : 'Dinner';
    const regionIndex = Math.floor(seriesIndex / 2);
    const regionName = regions[regionIndex];
    
    console.log('Lunch/Dinner Click - Outlet index:', outletIndex, 'Series index:', seriesIndex, 'Outlet name:', clickedOutletName);
    console.log('Meal Period:', mealPeriod, 'Region:', regionName);
    
    // Find the outlet in the specific region
    const regionData = data[regionName];
    if (!regionData) {
        console.log('Region not found:', regionName);
        return;
    }
    
    const outlet = regionData.outlets.find(outlet => outlet.outlet_name === clickedOutletName);
    
    if (outlet) {
        console.log('Found outlet for lunch/dinner:', outlet, 'in region:', regionName, 'meal period:', mealPeriod);
        openLunchDinnerModal(outlet, regionName, mealPeriod);
    } else {
        console.log('Outlet not found for lunch/dinner:', clickedOutletName);
    }
}

function handleWeekendWeekdayChartClick(config) {
    if (!config || config.dataPointIndex === undefined || config.seriesIndex === undefined) {
        console.log('Invalid weekend/weekday click config:', config);
        return;
    }
    
    const outletIndex = config.dataPointIndex;
    const seriesIndex = config.seriesIndex;
    
    const data = props.dashboardData?.revenuePerOutletWeekendWeekday;
    if (!data) {
        console.log('No weekend/weekday revenue data available');
        return;
    }
    
    const regions = Object.keys(data);
    
    // Get all unique outlets across all regions to find the correct outlet
    const allOutlets = new Set();
    regions.forEach(region => {
        data[region].outlets.forEach(outlet => {
            allOutlets.add(outlet.outlet_name);
        });
    });
    
    const allOutletsArray = Array.from(allOutlets);
    const clickedOutletName = allOutletsArray[outletIndex];
    
    // Determine day type from series index
    // Each region has 2 series: Weekend and Weekday
    // So seriesIndex 0,2,4... = Weekend, seriesIndex 1,3,5... = Weekday
    const dayType = seriesIndex % 2 === 0 ? 'Weekend' : 'Weekday';
    const regionIndex = Math.floor(seriesIndex / 2);
    const regionName = regions[regionIndex];
    
    console.log('Weekend/Weekday Click - Outlet index:', outletIndex, 'Series index:', seriesIndex, 'Outlet name:', clickedOutletName);
    console.log('Day Type:', dayType, 'Region:', regionName);
    
    // Find the outlet in the specific region
    const regionData = data[regionName];
    if (!regionData) {
        console.log('Region not found:', regionName);
        return;
    }
    
    const outlet = regionData.outlets.find(outlet => outlet.outlet_name === clickedOutletName);
    
    if (outlet) {
        console.log('Found outlet for weekend/weekday:', outlet, 'in region:', regionName, 'day type:', dayType);
        openWeekendWeekdayModal(outlet, regionName, dayType);
    } else {
        console.log('Outlet not found for weekend/weekday:', clickedOutletName);
    }
}

// Computed properties for menu region chart
const menuRegionChartSeries = computed(() => {
    if (!menuRegionData.value) return [];
    
    return [
        {
            name: 'Revenue',
            data: menuRegionData.value.map(item => item.total_revenue)
        },
        {
            name: 'Orders',
            data: menuRegionData.value.map(item => item.order_count)
        },
        {
            name: 'Quantity',
            data: menuRegionData.value.map(item => item.total_quantity)
        }
    ];
});

const menuRegionChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 400,
        toolbar: { show: true }
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '55%',
            endingShape: 'rounded'
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        show: true,
        width: 2,
        colors: ['transparent']
    },
    xaxis: {
        categories: menuRegionData.value?.map(item => item.region_name) || []
    },
    yaxis: [
        {
            title: { text: 'Revenue (Rp)' },
            labels: {
                formatter: function(value) {
                    return new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0
                    }).format(value);
                }
            }
        },
        {
            opposite: true,
            title: { text: 'Orders & Quantity' }
        }
    ],
    fill: {
        opacity: 1
    },
    colors: ['#3B82F6', '#10B981', '#F59E0B'],
    legend: { position: 'top' },
    grid: { borderColor: '#e5e7eb' },
    tooltip: {
        shared: true,
        intersect: false,
        custom: function({series, seriesIndex, dataPointIndex, w}) {
            const data = menuRegionData.value;
            if (!data || !data[dataPointIndex]) return '';
            
            const regionData = data[dataPointIndex];
            const avgPrice = regionData.total_quantity > 0 ? regionData.total_revenue / regionData.total_quantity : 0;
            
            return `
                <div class="px-3 py-2 rounded-lg bg-white shadow-lg border">
                    <div class="font-semibold text-gray-900">${regionData.region_name}</div>
                    <div class="text-sm text-gray-600 mt-1">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-blue-500 rounded"></div>
                            <span>Revenue: ${formatCurrency(regionData.total_revenue)}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-green-500 rounded"></div>
                            <span>Orders: ${regionData.order_count.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-yellow-500 rounded"></div>
                            <span>Quantity: ${regionData.total_quantity.toLocaleString()}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-gray-400 rounded"></div>
                            <span>Avg Price: ${formatCurrency(avgPrice)}</span>
                        </div>
                    </div>
                </div>
            `;
        }
    }
}));
</script>

<template>
    <AppLayout>
        <Head title="Sales Outlet Dashboard" />
        
        <div class="w-full min-h-screen bg-gray-50">
            <div class="w-full px-2 py-2">
                <!-- Header -->
                <div class="mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Sales Outlet Dashboard</h1>
                        <p class="text-gray-600 mt-1">Analisis komprehensif performa sales outlet</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Date From -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
                            <input 
                                v-model="filters.date_from"
                                type="date"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <!-- Date To -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
                            <input 
                                v-model="filters.date_to"
                                type="date"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4">
                        <button 
                            @click="resetFilters"
                            class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            Reset
                        </button>
                        <button 
                            @click="applyFilters"
                            :disabled="loading"
                            class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                        >
                            {{ loading ? 'Loading...' : 'Apply Filters' }}
                        </button>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="text-center py-12">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="text-gray-600 mt-2">Memuat data dashboard...</p>
                </div>

                <!-- Dashboard Content -->
                <div v-else>
                    <!-- Overview Metrics -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-4 mb-6">
                        <!-- Total Orders -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Total Orders</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(dashboardData?.overview?.total_orders || 0) }}</p>
                                    <p class="text-sm flex items-center mt-1" :class="getGrowthColor(dashboardData?.overview?.order_growth || 0)">
                                        <i :class="['fa-solid', getGrowthIcon(dashboardData?.overview?.order_growth || 0), 'mr-1']"></i>
                                        {{ Math.abs(dashboardData?.overview?.order_growth || 0).toFixed(1) }}%
                                    </p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fa-solid fa-shopping-cart text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Total Revenue -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.total_revenue || 0) }}</p>
                                    <p class="text-sm flex items-center mt-1" :class="getGrowthColor(dashboardData?.overview?.revenue_growth || 0)">
                                        <i :class="['fa-solid', getGrowthIcon(dashboardData?.overview?.revenue_growth || 0), 'mr-1']"></i>
                                        {{ Math.abs(dashboardData?.overview?.revenue_growth || 0).toFixed(1) }}%
                                    </p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fa-solid fa-chart-line text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Average Order Value -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Avg Order Value</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.avg_order_value || 0) }}</p>
                                    <p class="text-sm text-gray-500 mt-1">Per order</p>
                                </div>
                                <div class="p-3 bg-purple-100 rounded-full">
                                    <i class="fa-solid fa-calculator text-purple-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Total Customers -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Total Customers</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(dashboardData?.overview?.total_customers || 0) }}</p>
                                    <p class="text-sm text-gray-500 mt-1">Avg {{ dashboardData?.overview?.avg_pax_per_order?.toFixed(1) || 0 }} pax/order</p>
                                </div>
                                <div class="p-3 bg-orange-100 rounded-full">
                                    <i class="fa-solid fa-users text-orange-600 text-xl"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Average Check -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Average Check</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.avg_check || 0) }}</p>
                                    <p class="text-sm text-gray-500 mt-1">Per customer</p>
                                </div>
                                <div class="p-3 bg-indigo-100 rounded-full">
                                    <i class="fa-solid fa-receipt text-indigo-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Metrics -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-4 mb-6">
                        <!-- Total Discount -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Total Discount</p>
                                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.total_discount || 0) }}</p>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fa-solid fa-percentage text-red-600 text-lg"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Service Charge -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Service Charge</p>
                                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.total_service_charge || 0) }}</p>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <i class="fa-solid fa-hand-holding-dollar text-yellow-600 text-lg"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Commission Fee -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Commission Fee</p>
                                    <p class="text-xl font-bold text-gray-900">{{ formatCurrency(dashboardData?.overview?.total_commission_fee || 0) }}</p>
                                </div>
                                <div class="p-3 bg-indigo-100 rounded-full">
                                    <i class="fa-solid fa-coins text-indigo-600 text-lg"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Promo Usage -->
                        <div class="bg-white rounded-lg shadow-sm border p-6 cursor-pointer hover:shadow-md transition-shadow duration-200" @click="openPromoUsageModal">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Promo Usage</p>
                                    <p class="text-xl font-bold text-gray-900">{{ dashboardData?.promoUsage?.orders_with_promo || 0 }}</p>
                                    <p class="text-sm text-gray-500 mt-1">{{ dashboardData?.promoUsage?.promo_usage_percentage?.toFixed(1) || 0 }}% of orders</p>
                                </div>
                                <div class="p-3 bg-pink-100 rounded-full">
                                    <i class="fa-solid fa-tags text-pink-600 text-lg"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Promo Discount -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Bank Promo Discount</p>
                                    <p class="text-xl font-bold text-gray-900">{{ dashboardData?.bankPromoDiscount?.orders_with_bank_promo || 0 }}</p>
                                    <p class="text-sm text-gray-500 mt-1">{{ formatCurrency(dashboardData?.bankPromoDiscount?.total_bank_discount_amount || 0) }}</p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fa-solid fa-credit-card text-green-600 text-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 1 -->
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
                        <!-- Sales Trend -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Sales Trend</h3>
                                <div class="text-xs text-gray-500 bg-blue-50 px-2 py-1 rounded">
                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                    Klik batang untuk detail outlet • Hijau: Weekday • Orange: Weekend • Merah: Libur
                                </div>
                            </div>
                            <apexchart 
                                v-if="salesTrendSeries.length > 0" 
                                type="line" 
                                height="350" 
                                :options="salesTrendOptions" 
                                :series="salesTrendSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-chart-line text-4xl mb-2"></i>
                                <p>No sales trend data available</p>
                            </div>
                        </div>

                        <!-- Hourly Sales -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Orders by Hour</h3>
                            <apexchart 
                                v-if="hourlySalesSeries.length > 0" 
                                type="bar" 
                                height="350" 
                                :options="hourlySalesOptions" 
                                :series="hourlySalesSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-clock text-4xl mb-2"></i>
                                <p>No hourly sales data available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-6">
                        <!-- Lunch/Dinner Orders -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Orders by Lunch or Dinner</h3>
                            <apexchart 
                                v-if="lunchDinnerSeries.length > 0 && lunchDinnerSeries.some(series => series.data && series.data.some(val => val > 0))" 
                                type="bar" 
                                height="350" 
                                :options="lunchDinnerOptions" 
                                :series="lunchDinnerSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-utensils text-4xl mb-2"></i>
                                <p>No lunch/dinner data available</p>
                            </div>
                            
                            <!-- Lunch/Dinner Details -->
                            <div v-if="dashboardData?.lunchDinnerOrders" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Period Details</h4>
                                <div class="space-y-3">
                                    <!-- Lunch Details -->
                                    <div class="border rounded-lg p-4 bg-green-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900 flex items-center">
                                                <i class="fa-solid fa-sun text-green-600 mr-2"></i>
                                                Lunch (≤17:00)
                                            </h5>
                                            <span class="text-sm font-medium text-green-600">
                                                {{ formatCurrency(dashboardData.lunchDinnerOrders.lunch?.total_revenue || 0) }}
                                            </span>
                                        </div>
                                         <div class="grid grid-cols-2 gap-2 text-sm">
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Orders:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.lunch?.order_count || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Pax:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.lunch?.total_pax || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Order:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.lunchDinnerOrders.lunch?.avg_order_value || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Check:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency((dashboardData.lunchDinnerOrders.lunch?.total_pax || 0) > 0 ? (dashboardData.lunchDinnerOrders.lunch?.total_revenue || 0) / (dashboardData.lunchDinnerOrders.lunch?.total_pax || 1) : 0) }}</span>
                                             </div>
                                         </div>
                                    </div>

                                    <!-- Dinner Details -->
                                    <div class="border rounded-lg p-4 bg-orange-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900 flex items-center">
                                                <i class="fa-solid fa-moon text-orange-600 mr-2"></i>
                                                Dinner (>17:00)
                                            </h5>
                                            <span class="text-sm font-medium text-orange-600">
                                                {{ formatCurrency(dashboardData.lunchDinnerOrders.dinner?.total_revenue || 0) }}
                                            </span>
                                        </div>
                                         <div class="grid grid-cols-2 gap-2 text-sm">
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Orders:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.dinner?.order_count || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Pax:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.lunchDinnerOrders.dinner?.total_pax || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Order:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.lunchDinnerOrders.dinner?.avg_order_value || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Check:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency((dashboardData.lunchDinnerOrders.dinner?.total_pax || 0) > 0 ? (dashboardData.lunchDinnerOrders.dinner?.total_revenue || 0) / (dashboardData.lunchDinnerOrders.dinner?.total_pax || 1) : 0) }}</span>
                                             </div>
                                         </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weekday/Weekend Revenue -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue per Weekday and Weekend</h3>
                            <apexchart 
                                v-if="weekdayWeekendSeries.length > 0 && weekdayWeekendSeries.some(series => series.data && series.data.some(val => val > 0))" 
                                type="bar" 
                                height="350" 
                                :options="weekdayWeekendOptions" 
                                :series="weekdayWeekendSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-calendar-week text-4xl mb-2"></i>
                                <p>No weekday/weekend data available</p>
                            </div>
                            
                            <!-- Weekday/Weekend Details -->
                            <div v-if="dashboardData?.weekdayWeekendRevenue" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Period Details</h4>
                                <div class="space-y-3">
                                    <!-- Weekday Details -->
                                    <div class="border rounded-lg p-4 bg-blue-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900 flex items-center">
                                                <i class="fa-solid fa-briefcase text-blue-600 mr-2"></i>
                                                Weekday (Mon-Fri)
                                            </h5>
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekday?.total_revenue || 0) }}
                                            </span>
                                        </div>
                                         <div class="grid grid-cols-2 gap-2 text-sm">
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Orders:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekday?.order_count || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Pax:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekday?.total_pax || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Order:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekday?.avg_order_value || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Check:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency((dashboardData.weekdayWeekendRevenue.weekday?.total_pax || 0) > 0 ? (dashboardData.weekdayWeekendRevenue.weekday?.total_revenue || 0) / (dashboardData.weekdayWeekendRevenue.weekday?.total_pax || 1) : 0) }}</span>
                                             </div>
                                         </div>
                                    </div>

                                    <!-- Weekend Details -->
                                    <div class="border rounded-lg p-4 bg-orange-50">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900 flex items-center">
                                                <i class="fa-solid fa-calendar-weekend text-orange-600 mr-2"></i>
                                                Weekend (Sat-Sun)
                                            </h5>
                                            <span class="text-sm font-medium text-orange-600">
                                                {{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekend?.total_revenue || 0) }}
                                            </span>
                                        </div>
                                         <div class="grid grid-cols-2 gap-2 text-sm">
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Orders:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekend?.order_count || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Pax:</span>
                                                 <span class="font-medium text-gray-900">{{ formatNumber(dashboardData.weekdayWeekendRevenue.weekend?.total_pax || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Order:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency(dashboardData.weekdayWeekendRevenue.weekend?.avg_order_value || 0) }}</span>
                                             </div>
                                             <div class="flex justify-between">
                                                 <span class="text-gray-600">Avg Check:</span>
                                                 <span class="font-medium text-gray-900">{{ formatCurrency((dashboardData.weekdayWeekendRevenue.weekend?.total_pax || 0) > 0 ? (dashboardData.weekdayWeekendRevenue.weekend?.total_revenue || 0) / (dashboardData.weekdayWeekendRevenue.weekend?.total_pax || 1) : 0) }}</span>
                                             </div>
                                         </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 3 - Payment Methods (Full Width) -->
                    <div class="mb-6">
                        <!-- Payment Methods -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Methods Distribution</h3>
                            <apexchart 
                                v-if="paymentMethodsSeries.length > 0" 
                                type="donut" 
                                height="350" 
                                :options="paymentMethodsOptions" 
                                :series="paymentMethodsSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-credit-card text-4xl mb-2"></i>
                                <p>No payment methods data available</p>
                            </div>
                            
                            <!-- Payment Details by Type -->
                            <div v-if="dashboardData?.paymentMethods?.length > 0" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Detail per Payment Type</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <div v-for="paymentMethod in dashboardData.paymentMethods" :key="paymentMethod.payment_code" class="border rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900">{{ paymentMethod.payment_code }}</h5>
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ formatCurrency(paymentMethod.total_amount) }}
                                            </span>
                                        </div>
                                        <div class="space-y-1">
                                            <div v-for="detail in paymentMethod.details" :key="`${paymentMethod.payment_code}-${detail.payment_type}`" 
                                                 class="flex items-center justify-between text-sm">
                                                <span class="text-gray-600">{{ detail.payment_type }}</span>
                                                <span class="font-medium text-gray-900">{{ formatCurrency(detail.total_amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 4 - Revenue per Outlet by Region (Full Width) -->
                    <div class="mb-6">
                        <!-- Revenue per Outlet by Region -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Revenue per Outlet by Region</h3>
                                <div class="text-xs text-gray-500 bg-blue-50 px-2 py-1 rounded">
                                    <i class="fa-solid fa-info-circle mr-1"></i>
                                    Klik batang untuk detail revenue harian
                                </div>
                            </div>
                            <apexchart 
                                v-if="revenuePerOutletSeries.length > 0" 
                                type="bar" 
                                height="400" 
                                :options="revenuePerOutletOptions" 
                                :series="revenuePerOutletSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-building text-4xl mb-2"></i>
                                <p>No outlet revenue data available</p>
                            </div>
                            
                            <!-- Region Summary -->
                            <div v-if="dashboardData?.revenuePerOutlet && Object.keys(dashboardData.revenuePerOutlet).length > 0" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Region Summary</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <div v-for="(regionData, regionName) in dashboardData.revenuePerOutlet" :key="regionName" class="border rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900">{{ regionName }}</h5>
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ formatCurrency(regionData.total_revenue) }}
                                            </span>
                                        </div>
                                        <div class="space-y-1 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Outlets:</span>
                                                <span class="font-medium text-gray-900">{{ regionData.outlets.length }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Orders:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_orders) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Pax:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_pax) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Avg Check:</span>
                                                <span class="font-medium text-gray-900">{{ formatCurrency(regionData.total_pax > 0 ? regionData.total_revenue / regionData.total_pax : 0) }}</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Top Outlets in Region -->
                                        <div class="mt-3 pt-3 border-t">
                                            <h6 class="text-xs font-semibold text-gray-700 mb-2">Top Outlets:</h6>
                                            <div class="space-y-1">
                                                <div v-for="outlet in regionData.outlets.slice(0, 3)" :key="outlet.outlet_code" class="flex justify-between text-xs">
                                                    <span class="text-gray-600 truncate">{{ outlet.outlet_name }}</span>
                                                    <span class="font-medium text-gray-900">{{ formatCurrency(outlet.total_revenue) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 4.1 - Revenue per Outlet by Region (Lunch/Dinner) (Full Width) -->
                    <div class="mb-6">
                        <!-- Revenue per Outlet by Region (Lunch/Dinner) -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Revenue per Outlet by Region (Lunch/Dinner)</h3>
                                <div class="text-xs text-gray-500 bg-green-50 px-2 py-1 rounded">
                                    <i class="fa-solid fa-utensils mr-1"></i>
                                    Breakdown berdasarkan waktu makan
                                </div>
                            </div>
                            <apexchart 
                                v-if="revenuePerOutletLunchDinnerSeries.length > 0" 
                                type="bar" 
                                height="400" 
                                :options="revenuePerOutletLunchDinnerOptions" 
                                :series="revenuePerOutletLunchDinnerSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-utensils text-4xl mb-2"></i>
                                <p>No lunch/dinner revenue data available</p>
                            </div>
                            
                            <!-- Region Summary (Lunch/Dinner) -->
                            <div v-if="dashboardData?.revenuePerOutletLunchDinner && Object.keys(dashboardData.revenuePerOutletLunchDinner).length > 0" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Region Summary (Lunch/Dinner)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <div v-for="(regionData, regionName) in dashboardData.revenuePerOutletLunchDinner" :key="regionName" class="border rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900">{{ regionName }}</h5>
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ formatCurrency(regionData.total_revenue) }}
                                            </span>
                                        </div>
                                        
                                        <!-- Lunch Summary -->
                                        <div class="mb-3 p-2 bg-blue-50 rounded">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-medium text-blue-800">🍽️ Lunch</span>
                                                <span class="text-sm font-semibold text-blue-600">
                                                    {{ formatCurrency(regionData.lunch.total_revenue) }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between text-xs text-blue-700">
                                                <span>{{ regionData.lunch.total_orders }} orders</span>
                                                <span>{{ regionData.lunch.total_pax }} pax</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Dinner Summary -->
                                        <div class="mb-3 p-2 bg-orange-50 rounded">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-medium text-orange-800">🍽️ Dinner</span>
                                                <span class="text-sm font-semibold text-orange-600">
                                                    {{ formatCurrency(regionData.dinner.total_revenue) }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between text-xs text-orange-700">
                                                <span>{{ regionData.dinner.total_orders }} orders</span>
                                                <span>{{ regionData.dinner.total_pax }} pax</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Total Summary -->
                                        <div class="space-y-1 text-sm border-t pt-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Outlets:</span>
                                                <span class="font-medium text-gray-900">{{ regionData.outlets.length }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Orders:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_orders) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Pax:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_pax) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Avg Check:</span>
                                                <span class="font-medium text-gray-900">{{ formatCurrency(regionData.total_pax > 0 ? regionData.total_revenue / regionData.total_pax : 0) }}</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Top Outlets in Region -->
                                        <div class="mt-3 pt-3 border-t">
                                            <h6 class="text-xs font-semibold text-gray-700 mb-2">Top Outlets:</h6>
                                            <div class="space-y-1">
                                                <div v-for="outlet in regionData.outlets.slice(0, 3)" :key="outlet.outlet_code" class="flex justify-between text-xs">
                                                    <span class="text-gray-600 truncate">{{ outlet.outlet_name }}</span>
                                                    <span class="font-medium text-gray-900">{{ formatCurrency(outlet.total_revenue) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 4.2 - Revenue per Outlet by Region (Weekend/Weekday) (Full Width) -->
                    <div class="mb-6">
                        <!-- Revenue per Outlet by Region (Weekend/Weekday) -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Revenue per Outlet by Region (Weekend/Weekday)</h3>
                                <div class="text-xs text-gray-500 bg-purple-50 px-2 py-1 rounded">
                                    <i class="fa-solid fa-calendar-week mr-1"></i>
                                    Breakdown berdasarkan hari kerja
                                </div>
                            </div>
                            <apexchart 
                                v-if="revenuePerOutletWeekendWeekdaySeries.length > 0" 
                                type="bar" 
                                height="400" 
                                :options="revenuePerOutletWeekendWeekdayOptions" 
                                :series="revenuePerOutletWeekendWeekdaySeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-calendar-week text-4xl mb-2"></i>
                                <p>No weekend/weekday revenue data available</p>
                            </div>
                            
                            <!-- Region Summary (Weekend/Weekday) -->
                            <div v-if="dashboardData?.revenuePerOutletWeekendWeekday && Object.keys(dashboardData.revenuePerOutletWeekendWeekday).length > 0" class="mt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-3">Region Summary (Weekend/Weekday)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                    <div v-for="(regionData, regionName) in dashboardData.revenuePerOutletWeekendWeekday" :key="regionName" class="border rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h5 class="font-semibold text-gray-900">{{ regionName }}</h5>
                                            <span class="text-sm font-medium text-blue-600">
                                                {{ formatCurrency(regionData.total_revenue) }}
                                            </span>
                                        </div>
                                        
                                        <!-- Weekend Summary -->
                                        <div class="mb-3 p-2 bg-purple-50 rounded">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-medium text-purple-800">📅 Weekend</span>
                                                <span class="text-sm font-semibold text-purple-600">
                                                    {{ formatCurrency(regionData.weekend.total_revenue) }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between text-xs text-purple-700">
                                                <span>{{ regionData.weekend.total_orders }} orders</span>
                                                <span>{{ regionData.weekend.total_pax }} pax</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Weekday Summary -->
                                        <div class="mb-3 p-2 bg-blue-50 rounded">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm font-medium text-blue-800">📅 Weekday</span>
                                                <span class="text-sm font-semibold text-blue-600">
                                                    {{ formatCurrency(regionData.weekday.total_revenue) }}
                                                </span>
                                            </div>
                                            <div class="flex justify-between text-xs text-blue-700">
                                                <span>{{ regionData.weekday.total_orders }} orders</span>
                                                <span>{{ regionData.weekday.total_pax }} pax</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Total Summary -->
                                        <div class="space-y-1 text-sm border-t pt-2">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Outlets:</span>
                                                <span class="font-medium text-gray-900">{{ regionData.outlets.length }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Orders:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_orders) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Pax:</span>
                                                <span class="font-medium text-gray-900">{{ formatNumber(regionData.total_pax) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Avg Check:</span>
                                                <span class="font-medium text-gray-900">{{ formatCurrency(regionData.total_pax > 0 ? regionData.total_revenue / regionData.total_pax : 0) }}</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Top Outlets in Region -->
                                        <div class="mt-3 pt-3 border-t">
                                            <h6 class="text-xs font-semibold text-gray-700 mb-2">Top Outlets:</h6>
                                            <div class="space-y-1">
                                                <div v-for="outlet in regionData.outlets.slice(0, 3)" :key="outlet.outlet_code" class="flex justify-between text-xs">
                                                    <span class="text-gray-600 truncate">{{ outlet.outlet_name }}</span>
                                                    <span class="font-medium text-gray-900">{{ formatCurrency(outlet.total_revenue) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 5 - Revenue per Region (3 columns) -->
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
                        <!-- Total Revenue per Region -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Total Revenue per Region</h3>
                            <apexchart 
                                v-if="revenuePerRegionTotalSeries.length > 0" 
                                type="bar" 
                                height="350" 
                                :options="revenuePerRegionTotalOptions" 
                                :series="revenuePerRegionTotalSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-map text-4xl mb-2"></i>
                                <p>No region revenue data available</p>
                            </div>
                        </div>

                        <!-- Lunch/Dinner Revenue per Region -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Lunch/Dinner Revenue per Region</h3>
                            <apexchart 
                                v-if="revenuePerRegionLunchDinnerSeries.length > 0" 
                                type="bar" 
                                height="350" 
                                :options="revenuePerRegionLunchDinnerOptions" 
                                :series="revenuePerRegionLunchDinnerSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-utensils text-4xl mb-2"></i>
                                <p>No lunch/dinner region data available</p>
                            </div>
                        </div>

                        <!-- Weekday/Weekend Revenue per Region -->
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Weekday/Weekend Revenue per Region</h3>
                            <apexchart 
                                v-if="revenuePerRegionWeekdayWeekendSeries.length > 0" 
                                type="bar" 
                                height="350" 
                                :options="revenuePerRegionWeekdayWeekendOptions" 
                                :series="revenuePerRegionWeekdayWeekendSeries" 
                            />
                            <div v-else class="text-center py-8 text-gray-500">
                                <i class="fa-solid fa-calendar-week text-4xl mb-2"></i>
                                <p>No weekday/weekend region data available</p>
                            </div>
                        </div>
                    </div>

                    <!-- Top Items -->
                    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Selling Items</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Sold</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in dashboardData?.topItems || []" :key="item.item_name">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <button 
                                                @click="openMenuModal(item)"
                                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer transition-colors"
                                            >
                                                {{ item.item_name }}
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatNumber(item.total_qty) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(item.total_revenue) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatNumber(item.order_count) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(item.avg_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pax</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in dashboardData?.recentOrders || []" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.nomor }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.outlet_name || order.kode_outlet || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.table || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.member_name || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.pax || 0 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(order.grand_total) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="{
                                                      'bg-green-100 text-green-800': order.status === 'completed',
                                                      'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                                      'bg-red-100 text-red-800': order.status === 'cancelled',
                                                      'bg-blue-100 text-blue-800': order.status === 'processing'
                                                  }">
                                                {{ order.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(order.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bank Promo Discount Transactions -->
                    <div v-if="dashboardData?.bankPromoDiscount?.orders_with_bank_promo > 0" class="bg-white rounded-lg shadow-sm border p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Bank Promo Discount Transactions</h3>
                            <div class="flex items-center gap-4">
                                <!-- Region Filter -->
                                <select v-model="bankPromoRegionFilter" @change="onBankPromoRegionChange" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Regions</option>
                                    <option v-for="region in bankPromoRegions" :key="region.id" :value="region.id">
                                        {{ region.name }}
                                    </option>
                                </select>
                                
                                <!-- Outlet Filter -->
                                <select v-model="bankPromoOutletFilter" @change="onBankPromoOutletChange" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Outlets</option>
                                    <option v-for="outlet in bankPromoOutlets" :key="outlet.id" :value="outlet.qr_code">
                                        {{ outlet.name }}
                                    </option>
                                </select>
                                
                                <!-- Search Input -->
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="bankPromoSearch" 
                                        @input="searchBankPromoTransactions"
                                        placeholder="Search discount reason..."
                                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-search text-gray-400"></i>
                                    </div>
                                </div>
                                <!-- Per Page Selector -->
                                <select v-model="bankPromoPerPage" @change="fetchBankPromoTransactions" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50">50 per page</option>
                                    <option value="100">100 per page</option>
                                    <option value="200">200 per page</option>
                                    <option value="300">300 per page</option>
                                    <option value="400">400 per page</option>
                                    <option value="500">500 per page</option>
                                    <option value="1000">1000 per page</option>
                                </select>
                                
                                <!-- Export Button -->
                                <button 
                                    @click="exportBankPromoTransactions"
                                    :disabled="bankPromoLoading || !bankPromoTransactions.length"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fa-solid fa-file-excel mr-2"></i>
                                    Export Excel
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="bankPromoLoading" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="mt-2 text-gray-500">Loading transactions...</p>
                        </div>

                        <!-- Table -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Order</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kasir</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount Reason</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in bankPromoTransactions" :key="order.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.paid_number || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.outlet_name || order.kode_outlet || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.region_name || 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.kasir || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span v-if="order.payment_code && order.payment_type" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ order.payment_code }} - {{ order.payment_type }}
                                                <span v-if="order.payment_type === 'credit' && order.card_first4 && order.card_last4" class="ml-1 text-blue-600">
                                                    (****{{ order.card_first4 }}****{{ order.card_last4 }})
                                                </span>
                                                <span v-if="order.payment_type === 'credit' && order.approval_code" class="ml-1 text-blue-600">
                                                    [{{ order.approval_code }}]
                                                </span>
                                            </span>
                                            <span v-else-if="order.payment_code" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ order.payment_code }}
                                                <span v-if="order.payment_type === 'credit' && order.card_first4 && order.card_last4" class="ml-1 text-gray-600">
                                                    (****{{ order.card_first4 }}****{{ order.card_last4 }})
                                                </span>
                                                <span v-if="order.payment_type === 'credit' && order.approval_code" class="ml-1 text-gray-600">
                                                    [{{ order.approval_code }}]
                                                </span>
                                            </span>
                                            <span v-else-if="order.payment_type" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ order.payment_type }}
                                                <span v-if="order.payment_type === 'credit' && order.card_first4 && order.card_last4" class="ml-1 text-gray-600">
                                                    (****{{ order.card_first4 }}****{{ order.card_last4 }})
                                                </span>
                                                <span v-if="order.payment_type === 'credit' && order.approval_code" class="ml-1 text-gray-600">
                                                    [{{ order.approval_code }}]
                                                </span>
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(order.grand_total) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">{{ formatCurrency(order.manual_discount_amount) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.manual_discount_reason || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(order.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="bankPromoPagination && bankPromoPagination.total_pages > 1" class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ ((bankPromoPagination.current_page - 1) * bankPromoPagination.per_page) + 1 }} to 
                                {{ Math.min(bankPromoPagination.current_page * bankPromoPagination.per_page, bankPromoPagination.total) }} of 
                                {{ bankPromoPagination.total }} results
                            </div>
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="goToBankPromoPage(bankPromoPagination.current_page - 1)"
                                    :disabled="!bankPromoPagination.has_prev_page"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Previous
                                </button>
                                
                                <div class="flex items-center gap-1">
                                    <button 
                                        v-for="page in getBankPromoPageNumbers()" 
                                        :key="page"
                                        @click="goToBankPromoPage(page)"
                                        :class="[
                                            'px-3 py-2 text-sm font-medium rounded-md',
                                            page === bankPromoPagination.current_page 
                                                ? 'bg-blue-600 text-white' 
                                                : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                </div>
                                
                                <button 
                                    @click="goToBankPromoPage(bankPromoPagination.current_page + 1)"
                                    :disabled="!bankPromoPagination.has_next_page"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <!-- Grand Total Summary -->
                        <div v-if="bankPromoGrandTotal.total_grand_total > 0" class="mt-6 bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-bold text-green-800">Grand Total Summary</h4>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-green-800">{{ formatCurrency(bankPromoGrandTotal.total_grand_total) }}</div>
                                    <div class="text-sm text-green-600">Total Grand Total</div>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-green-700">Total Discount Amount:</div>
                                <div class="text-lg font-semibold text-red-600">{{ formatCurrency(bankPromoGrandTotal.total_discount_amount) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Non Promo Bank Discount Transactions -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Non Promo Bank Discount Transactions</h3>
                            <div class="flex items-center gap-4">
                                <!-- Region Filter -->
                                <select v-model="nonPromoBankRegionFilter" @change="onNonPromoBankRegionChange" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Regions</option>
                                    <option v-for="region in nonPromoBankRegions" :key="region.id" :value="region.id">
                                        {{ region.name }}
                                    </option>
                                </select>
                                
                                <!-- Outlet Filter -->
                                <select v-model="nonPromoBankOutletFilter" @change="onNonPromoBankOutletChange" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Outlets</option>
                                    <option v-for="outlet in nonPromoBankOutlets" :key="outlet.id" :value="outlet.qr_code">
                                        {{ outlet.name }}
                                    </option>
                                </select>
                                
                                <!-- Search Input -->
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="nonPromoBankSearch" 
                                        @input="searchNonPromoBankTransactions"
                                        placeholder="Search discount reason..."
                                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    >
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-search text-gray-400"></i>
                                    </div>
                                </div>
                                <!-- Per Page Selector -->
                                <select v-model="nonPromoBankPerPage" @change="fetchNonPromoBankTransactions" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="10">10 per page</option>
                                    <option value="25">25 per page</option>
                                    <option value="50">50 per page</option>
                                    <option value="100">100 per page</option>
                                    <option value="200">200 per page</option>
                                    <option value="300">300 per page</option>
                                    <option value="400">400 per page</option>
                                    <option value="500">500 per page</option>
                                    <option value="1000">1000 per page</option>
                                </select>
                                
                                <!-- Export Button -->
                                <button 
                                    @click="exportNonPromoBankTransactions"
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
                                >
                                    <i class="fa-solid fa-download"></i>
                                    Export CSV
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="nonPromoBankLoading" class="flex justify-center items-center py-12">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        </div>

                        <!-- Table -->
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID ORDER</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PAID NUMBER</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OUTLET</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">REGION</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">KASIR</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PAYMENT METHOD</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GRAND TOTAL</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DISCOUNT AMOUNT</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DISCOUNT REASON</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CREATED AT</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="order in nonPromoBankTransactions" :key="order.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ order.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.paid_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.outlet_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.region_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.kasir || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                                            <span v-if="order.payment_method">{{ order.payment_method }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(order.grand_total) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">{{ formatCurrency(order.manual_discount_amount) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.manual_discount_reason || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(order.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="nonPromoBankPagination && nonPromoBankPagination.total_pages > 1" class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ ((nonPromoBankPagination.current_page - 1) * nonPromoBankPagination.per_page) + 1 }} to 
                                {{ Math.min(nonPromoBankPagination.current_page * nonPromoBankPagination.per_page, nonPromoBankPagination.total) }} of 
                                {{ nonPromoBankPagination.total }} results
                            </div>
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="goToNonPromoBankPage(nonPromoBankPagination.current_page - 1)"
                                    :disabled="!nonPromoBankPagination.has_prev_page"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Previous
                                </button>
                                
                                <div class="flex items-center gap-1">
                                    <button 
                                        v-for="page in getNonPromoBankPageNumbers()" 
                                        :key="page"
                                        @click="goToNonPromoBankPage(page)"
                                        :class="[
                                            'px-3 py-2 text-sm font-medium rounded-md',
                                            page === nonPromoBankPagination.current_page 
                                                ? 'bg-blue-600 text-white' 
                                                : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                </div>
                                
                                <button 
                                    @click="goToNonPromoBankPage(nonPromoBankPagination.current_page + 1)"
                                    :disabled="!nonPromoBankPagination.has_next_page"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Next
                                </button>
                            </div>
                        </div>

                        <!-- Grand Total Summary -->
                        <div v-if="nonPromoBankGrandTotal.total_grand_total > 0" class="mt-6 bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
                            <div class="flex items-center justify-between">
                                <h4 class="text-lg font-bold text-green-800">Grand Total Summary</h4>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-green-800">{{ formatCurrency(nonPromoBankGrandTotal.total_grand_total) }}</div>
                                    <div class="text-sm text-green-600">Total Grand Total</div>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-green-700">Total Discount Amount:</div>
                                <div class="text-lg font-semibold text-red-600">{{ formatCurrency(nonPromoBankGrandTotal.total_discount_amount) }}</div>
                            </div>
                        </div>

                        <!-- Breakdown by Reason -->
                        <div v-if="nonPromoBankBreakdown.length > 0" class="mt-6 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-lg font-bold text-blue-800 mb-4">Breakdown by Discount Reason</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-blue-200">
                                    <thead class="bg-blue-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-blue-700 uppercase tracking-wider">Discount Reason</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-blue-700 uppercase tracking-wider">Transactions</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-blue-700 uppercase tracking-wider">Total Grand Total</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-blue-700 uppercase tracking-wider">Total Discount</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-blue-700 uppercase tracking-wider">% of Total Discount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-blue-200">
                                        <tr v-for="breakdown in nonPromoBankBreakdown" :key="breakdown.discount_reason" class="hover:bg-blue-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ breakdown.discount_reason }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ breakdown.transaction_count }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(breakdown.total_grand_total) }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-red-600">{{ formatCurrency(breakdown.total_discount_amount) }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div 
                                                            class="bg-red-500 h-2 rounded-full" 
                                                            :style="{ width: nonPromoBankGrandTotal.total_discount_amount > 0 ? (breakdown.total_discount_amount / nonPromoBankGrandTotal.total_discount_amount * 100) + '%' : '0%' }"
                                                        ></div>
                                                    </div>
                                                    <span class="text-xs text-gray-600">
                                                        {{ nonPromoBankGrandTotal.total_discount_amount > 0 ? ((breakdown.total_discount_amount / nonPromoBankGrandTotal.total_discount_amount) * 100).toFixed(1) : '0' }}%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Region Analysis Modal -->
        <div v-if="showMenuModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeMenuModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white" @click.stop>
                <div class="mt-3">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Menu Performance by Region: {{ selectedMenu?.item_name }}
                        </h3>
                        <button 
                            @click="closeMenuModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div v-if="menuRegionLoading" class="flex justify-center items-center py-12">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>

                    <!-- Chart Content -->
                    <div v-else-if="menuRegionData && menuRegionData.length > 0">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-sm text-blue-600 font-medium">Total Revenue</div>
                                <div class="text-2xl font-bold text-blue-900">
                                    {{ formatCurrency(menuRegionData.reduce((sum, item) => sum + item.total_revenue, 0)) }}
                                </div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm text-green-600 font-medium">Total Orders</div>
                                <div class="text-2xl font-bold text-green-900">
                                    {{ formatNumber(menuRegionData.reduce((sum, item) => sum + item.order_count, 0)) }}
                                </div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-sm text-yellow-600 font-medium">Total Quantity</div>
                                <div class="text-2xl font-bold text-yellow-900">
                                    {{ formatNumber(menuRegionData.reduce((sum, item) => sum + item.total_quantity, 0)) }}
                                </div>
                            </div>
                        </div>

                        <!-- Chart -->
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Performance by Region</h4>
                            <apexchart 
                                type="bar" 
                                height="400" 
                                :options="menuRegionChartOptions" 
                                :series="menuRegionChartSeries"
                            ></apexchart>
                        </div>

                        <!-- Region Details Table -->
                        <div class="mt-6 bg-white p-4 rounded-lg border">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Region Details</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="region in menuRegionData" :key="region.region_name">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ region.region_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(region.total_revenue) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatNumber(region.order_count) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatNumber(region.total_quantity) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatCurrency(region.total_quantity > 0 ? region.total_revenue / region.total_quantity : 0) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- No Data State -->
                    <div v-else class="text-center py-12">
                        <i class="fa-solid fa-chart-bar text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500">No region data available for this menu item</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outlet Details Modal -->
        <OutletDetailsModal
            :is-open="showOutletModal"
            :selected-date="selectedDate"
            @close="closeOutletModal"
        />

        <!-- Outlet Daily Revenue Modal -->
        <OutletDailyRevenueModal
            :is-open="showDailyRevenueModal"
            :selected-outlet="selectedOutlet"
            :date-from="filters.date_from"
            :date-to="filters.date_to"
            @close="closeDailyRevenueModal"
        />

        <!-- Outlet Lunch/Dinner Modal -->
        <OutletLunchDinnerModal
            :is-open="showLunchDinnerModal"
            :selected-outlet="selectedLunchDinnerOutlet"
            :selected-region="selectedRegion"
            :selected-meal-period="selectedMealPeriod"
            :date-from="filters.date_from"
            :date-to="filters.date_to"
            @close="closeLunchDinnerModal"
        />

        <!-- Outlet Weekend/Weekday Modal -->
        <OutletWeekendWeekdayModal
            :is-open="showWeekendWeekdayModal"
            :selected-outlet="selectedWeekendWeekdayOutlet"
            :selected-region="selectedWeekendWeekdayRegion"
            :selected-day-type="selectedDayType"
            :date-from="filters.date_from"
            :date-to="filters.date_to"
            @close="closeWeekendWeekdayModal"
        />

        <!-- Promo Usage Modal -->
        <div v-if="showPromoUsageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closePromoUsageModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white" @click.stop>
                <div class="mt-3">
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fa-solid fa-tags text-pink-600 mr-2"></i>
                            Promo Usage by Outlet
                        </h3>
                        <button @click="closePromoUsageModal" class="text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Loading State -->
                    <div v-if="promoUsageLoading" class="flex justify-center items-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-pink-600"></div>
                        <span class="ml-2 text-gray-600">Loading promo usage data...</span>
                    </div>

                    <!-- Comparison Info -->
                    <div v-if="promoUsageComparison" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="text-lg font-semibold text-blue-900 mb-3">Discount Comparison</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div class="bg-white p-3 rounded border">
                                <div class="text-gray-600">Total Discount (All Orders)</div>
                                <div class="font-semibold text-lg">{{ formatCurrency(promoUsageComparison.total_discount_all_orders) }}</div>
                            </div>
                            <div class="bg-white p-3 rounded border">
                                <div class="text-gray-600">Discount from Promo Orders</div>
                                <div class="font-semibold text-lg text-green-600">{{ formatCurrency(promoUsageComparison.total_discount_promo_orders) }}</div>
                            </div>
                            <div class="bg-white p-3 rounded border">
                                <div class="text-gray-600">Difference (Non-Promo Discount)</div>
                                <div class="font-semibold text-lg text-orange-600">{{ formatCurrency(promoUsageComparison.difference) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Region Summary -->
                    <div v-if="promoUsageRegionSummary.length > 0" class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Summary by Region</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div v-for="region in promoUsageRegionSummary" :key="region.region_name" 
                                 class="bg-gradient-to-r from-pink-50 to-purple-50 border border-pink-200 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow duration-200"
                                 @click="toggleRegionExpansion(region.region_name)">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">{{ region.region_name }}</h5>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs bg-pink-100 text-pink-800 px-2 py-1 rounded-full">{{ region.region_code }}</span>
                                        <i class="fa-solid fa-chevron-right text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': expandedRegions.has(region.region_name) }"></i>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Total Usage:</span>
                                        <span class="font-medium text-gray-900">{{ region.total_usage_count }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Total Discount:</span>
                                        <span class="font-medium text-red-600">{{ formatCurrency(region.total_discount_amount) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Outlets:</span>
                                        <span class="font-medium text-gray-900">{{ region.total_outlets }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Expanded Region Outlets -->
                        <div v-if="promoUsageData.length > 0" class="mt-6 space-y-6">
                            <div v-for="region in promoUsageData" :key="region.region_name" v-show="expandedRegions.has(region.region_name)" class="border border-gray-200 rounded-lg">
                                <!-- Region Header -->
                                <div class="bg-gradient-to-r from-pink-100 to-purple-100 px-4 py-3 border-b border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <i class="fa-solid fa-chevron-right text-gray-600 rotate-90"></i>
                                            <div>
                                                <h4 class="font-semibold text-gray-900">{{ region.region_name }}</h4>
                                                <p class="text-sm text-gray-600">{{ region.total_outlets }} outlets • {{ region.total_usage_count }} total usage</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-900">{{ formatCurrency(region.total_transaction_value) }}</div>
                                            <div class="text-sm font-semibold text-red-600">{{ formatCurrency(region.total_discount_amount) }} discount</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Outlets in Region -->
                                <div class="p-4 bg-white space-y-3">
                                    <div v-for="outlet in region.outlets" :key="outlet.outlet_code" class="border border-gray-200 rounded-lg">
                                        <!-- Outlet Header -->
                                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 cursor-pointer" @click="toggleOutletExpansion(outlet.outlet_code)">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <i class="fa-solid fa-chevron-right text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': expandedOutlets.has(outlet.outlet_code) }"></i>
                                                    <div>
                                                        <h5 class="font-semibold text-gray-900">{{ outlet.outlet_name }}</h5>
                                                        <p class="text-sm text-gray-600">{{ outlet.outlet_code }}</p>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium text-gray-900">{{ outlet.total_usage_count }} uses</div>
                                                    <div class="text-sm text-gray-600">{{ formatCurrency(outlet.total_discount_amount) }} discount</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Promo Details (Expandable) -->
                                        <div v-if="expandedOutlets.has(outlet.outlet_code)" class="p-4 bg-white">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Code</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Name</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage Count</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaction</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Discount</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Discount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <tr v-for="promo in outlet.promos" :key="promo.promo_code" class="hover:bg-gray-50">
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pink-100 text-pink-800">
                                                                    {{ promo.promo_code }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ promo.promo_name || 'N/A' }}</td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    {{ promo.usage_count }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(promo.total_transaction_value) }}</td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold text-red-600">{{ formatCurrency(promo.total_discount_amount) }}</td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(promo.avg_discount_amount) }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content by Region (Fallback) -->
                    <div v-else-if="promoUsageData.length > 0" class="space-y-6">
                        <div v-for="region in promoUsageData" :key="region.region_name" class="border border-gray-200 rounded-lg">
                            <!-- Region Header -->
                            <div class="bg-gradient-to-r from-pink-100 to-purple-100 px-4 py-3 border-b border-gray-200 cursor-pointer" @click="toggleRegionExpansion(region.region_name)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fa-solid fa-chevron-right text-gray-600 transition-transform duration-200" :class="{ 'rotate-90': expandedRegions.has(region.region_name) }"></i>
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ region.region_name }}</h4>
                                            <p class="text-sm text-gray-600">{{ region.total_outlets }} outlets • {{ region.total_usage_count }} total usage</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">{{ formatCurrency(region.total_transaction_value) }}</div>
                                        <div class="text-sm font-semibold text-red-600">{{ formatCurrency(region.total_discount_amount) }} discount</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Outlets in Region (Expandable) -->
                            <div v-if="expandedRegions.has(region.region_name)" class="p-4 bg-white space-y-3">
                                <div v-for="outlet in region.outlets" :key="outlet.outlet_code" class="border border-gray-200 rounded-lg">
                                    <!-- Outlet Header -->
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 cursor-pointer" @click="toggleOutletExpansion(outlet.outlet_code)">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <i class="fa-solid fa-chevron-right text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': expandedOutlets.has(outlet.outlet_code) }"></i>
                                                <div>
                                                    <h5 class="font-semibold text-gray-900">{{ outlet.outlet_name }}</h5>
                                                    <p class="text-sm text-gray-600">{{ outlet.outlet_code }}</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-medium text-gray-900">{{ outlet.total_usage_count }} uses</div>
                                                <div class="text-sm text-gray-600">{{ formatCurrency(outlet.total_discount_amount) }} discount</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Promo Details (Expandable) -->
                                    <div v-if="expandedOutlets.has(outlet.outlet_code)" class="p-4 bg-white">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Code</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Name</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage Count</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaction</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Discount</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Discount</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <tr v-for="promo in outlet.promos" :key="promo.promo_code" class="hover:bg-gray-50">
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pink-100 text-pink-800">
                                                                {{ promo.promo_code }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ promo.promo_name || 'N/A' }}</td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                {{ promo.usage_count }}
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(promo.total_transaction_value) }}</td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold text-red-600">{{ formatCurrency(promo.total_discount_amount) }}</td>
                                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(promo.avg_discount_amount) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-8">
                        <i class="fa-solid fa-tags text-gray-300 text-4xl mb-4"></i>
                        <p class="text-gray-500">No promo usage data found for the selected date range.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
export default {
    components: {
        apexchart: VueApexCharts,
        OutletDetailsModal,
        OutletDailyRevenueModal,
        OutletLunchDinnerModal,
        OutletWeekendWeekdayModal
    }
}
</script>

<style scoped>
/* Custom styles for the dashboard */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
