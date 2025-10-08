<template>
    <AppLayout title="Report Schedule/Attendance Correction">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Report Schedule/Attendance Correction
                </h2>
                <div class="flex gap-2">
                    <button @click="exportReport" 
                            :disabled="loading"
                            class="bg-green-500 hover:bg-green-600 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fa-solid fa-file-excel"></i>
                        Export Excel
                    </button>
                </div>
            </div>
        </template>

        <div>
            <div>
                <!-- Filter Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-4">
                        <h3 class="text-lg font-semibold mb-4">Filter Report</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                            <!-- Date Range -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                                <input type="date" v-model="filters.startDate" 
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                                <input type="date" v-model="filters.endDate" 
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <!-- Outlet -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                                <select v-model="filters.outletId" 
                                        :disabled="props.user?.id_outlet && props.user.id_outlet !== 1"
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <option value="">Semua Outlet</option>
                                    <option v-for="outlet in availableOutlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                                        {{ outlet.nama_outlet }}
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Division -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
                                <select v-model="filters.divisionId" 
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Semua Divisi</option>
                                    <option v-for="division in props.divisions" :key="division.id_divisi" :value="division.id_divisi">
                                        {{ division.nama_divisi }}
                                    </option>
                                </select>
                            </div>
                            
                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select v-model="filters.status" 
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Semua Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                            </div>
                            
                            <!-- Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                                <select v-model="filters.type" 
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Semua Tipe</option>
                                    <option value="schedule">Schedule</option>
                                    <option value="attendance">Attendance</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex gap-2 mt-4">
                            <button @click="loadReportData" 
                                    :disabled="loading || !hasValidFilters"
                                    class="bg-blue-500 hover:bg-blue-600 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                <i class="fa-solid fa-search"></i>
                                {{ loading ? 'Loading...' : 'Cari' }}
                            </button>
                            <button @click="resetFilters" 
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                <i class="fa-solid fa-refresh"></i>
                                Reset
                            </button>
                        </div>
                        
                        <!-- Validation message -->
                        <div v-if="!hasValidFilters" class="mt-2 text-sm text-red-600">
                            <i class="fa-solid fa-exclamation-triangle mr-1"></i>
                            Silakan pilih minimal tanggal mulai dan tanggal selesai untuk melakukan pencarian.
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" v-if="summary">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-list text-2xl text-blue-500"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ summary.total }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-clock text-2xl text-yellow-500"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Pending</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ summary.pending }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-check text-2xl text-green-500"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Disetujui</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ summary.approved }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fa-solid fa-times text-2xl text-red-500"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Ditolak</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ summary.rejected }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Karyawan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Lama</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Baru</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diminta Oleh</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disetujui Oleh</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="loading">
                                        <td colspan="10" class="px-4 py-3 text-center">
                                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                                            <p class="text-sm mt-2 text-gray-600">Memuat data...</p>
                                        </td>
                                    </tr>
                                    <tr v-else-if="reportData.length === 0">
                                        <td colspan="12" class="px-4 py-3 text-center text-gray-500">
                                            Tidak ada data ditemukan
                                        </td>
                                    </tr>
                                    <tr v-else v-for="item in reportData" :key="item.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                                  :class="item.type === 'schedule' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                                {{ item.type === 'schedule' ? 'Schedule' : 'Attendance' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ new Date(item.tanggal).toLocaleDateString('id-ID') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ item.employee_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ item.nama_outlet }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ item.nama_divisi }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ formatCorrectionValue(item, 'old') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ formatCorrectionValue(item, 'new') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                                  :class="getStatusClass(item.status)">
                                                {{ getStatusText(item.status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ item.requested_by_name }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ new Date(item.created_at).toLocaleDateString('id-ID') }} {{ new Date(item.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ item.approved_by_name || '-' }}</div>
                                                <div v-if="item.approved_at" class="text-xs text-green-600">
                                                    {{ new Date(item.approved_at).toLocaleDateString('id-ID') }} {{ new Date(item.approved_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}) }}
                                                </div>
                                                <div v-else class="text-xs text-gray-400">
                                                    <i class="fa-solid fa-clock mr-1"></i>
                                                    Belum disetujui
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div v-if="pagination && pagination.total > 0" class="px-4 py-3 border-t border-gray-200 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="text-sm text-gray-700">
                                        Menampilkan {{ pagination?.from || 0 }} sampai {{ pagination?.to || 0 }} dari {{ pagination?.total || 0 }} data
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-700">Per halaman:</label>
                                        <select 
                                            v-model="pagination.per_page" 
                                            @change="changePerPage(pagination.per_page)"
                                            class="px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <option value="10">10</option>
                                            <option value="15">15</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <!-- Previous Button -->
                                    <button
                                        @click="changePage((pagination?.current_page || 1) - 1)"
                                        :disabled="(pagination?.current_page || 1) <= 1"
                                        class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                    
                                    <!-- Page Numbers -->
                                    <div class="flex items-center gap-1">
                                        <!-- First page -->
                                        <button
                                            v-if="(pagination?.current_page || 1) > 3"
                                            @click="changePage(1)"
                                            class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"
                                        >
                                            1
                                        </button>
                                        
                                        <!-- Ellipsis -->
                                        <span v-if="(pagination?.current_page || 1) > 4" class="px-2 text-gray-500">...</span>
                                        
                                        <!-- Pages around current page -->
                                        <button
                                            v-for="page in getVisiblePages()"
                                            :key="page"
                                            @click="changePage(page)"
                                            :class="[
                                                'px-3 py-1 text-sm border rounded',
                                                page === (pagination?.current_page || 1)
                                                    ? 'bg-blue-600 text-white border-blue-600'
                                                    : 'border-gray-300 hover:bg-gray-50'
                                            ]"
                                        >
                                            {{ page }}
                                        </button>
                                        
                                        <!-- Ellipsis -->
                                        <span v-if="(pagination?.current_page || 1) < (pagination?.last_page || 1) - 3" class="px-2 text-gray-500">...</span>
                                        
                                        <!-- Last page -->
                                        <button
                                            v-if="(pagination?.current_page || 1) < (pagination?.last_page || 1) - 2"
                                            @click="changePage(pagination?.last_page || 1)"
                                            class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"
                                        >
                                            {{ pagination?.last_page || 1 }}
                                        </button>
                                    </div>
                                    
                                    <!-- Next Button -->
                                    <button
                                        @click="changePage((pagination?.current_page || 1) + 1)"
                                        :disabled="!pagination?.has_more_pages"
                                        class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    outlets: Array,
    divisions: Array,
    user: Object,
});

// Reactive data
const loading = ref(false);
const reportData = ref([]);
const summary = ref(null);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
    has_more_pages: false
});

// Filters
const filters = ref({
    startDate: '',
    endDate: '',
    outletId: '',
    divisionId: '',
    status: '',
    type: ''
});

// ✅ VALIDASI: Filter outlet berdasarkan user
const availableOutlets = computed(() => {
    if (!props.outlets || !Array.isArray(props.outlets)) {
        return [];
    }
    
    if (props.user?.id_outlet && props.user.id_outlet !== 1) {
        // Jika user bukan dari outlet 1, hanya tampilkan outlet mereka
        return props.outlets.filter(outlet => outlet.id_outlet === props.user.id_outlet);
    }
    // Jika user dari outlet 1 (head office), tampilkan semua outlet
    return props.outlets;
});

// Validasi filter minimal
const hasValidFilters = computed(() => {
    return filters.value.startDate && filters.value.endDate;
});

// Watch for filter changes
watch(filters, (newFilters, oldFilters) => {
    console.log('Filter changed:', {
        old: oldFilters,
        new: newFilters
    });
    
    // Specifically watch divisionId changes
    if (oldFilters && newFilters.divisionId !== oldFilters.divisionId) {
        console.log('Division ID changed from', oldFilters.divisionId, 'to', newFilters.divisionId);
    }
}, { deep: true });

// Watch specifically for divisionId changes
watch(() => filters.value.divisionId, (newVal, oldVal) => {
    console.log('Division ID specifically changed:', {
        from: oldVal,
        to: newVal,
        timestamp: new Date().toISOString()
    });
});

// Methods
function loadReportData(page = 1) {
    loading.value = true;
    
    // Update pagination current page
    pagination.value.current_page = page;
    
    const params = new URLSearchParams();
    if (filters.value.startDate) params.append('start_date', filters.value.startDate);
    if (filters.value.endDate) params.append('end_date', filters.value.endDate);
    if (filters.value.outletId) params.append('outlet_id', filters.value.outletId);
    if (filters.value.divisionId) params.append('division_id', filters.value.divisionId);
    if (filters.value.status) params.append('status', filters.value.status);
    if (filters.value.type) params.append('type', filters.value.type);
    params.append('page', page);
    params.append('per_page', pagination.value?.per_page || 15);
    
    console.log('Loading report data with filters:', {
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
        outletId: filters.value.outletId,
        divisionId: filters.value.divisionId,
        status: filters.value.status,
        type: filters.value.type,
        page: page,
        per_page: pagination.value?.per_page
    });
    
    console.log('Current filter state:', JSON.stringify(filters.value, null, 2));
    
    axios.get(`/api/schedule-attendance-correction/report-data?${params.toString()}`)
        .then(response => {
            if (response.data && response.data.success) {
                reportData.value = response.data.data || [];
                summary.value = response.data.summary || null;
                pagination.value = response.data.pagination || {
                    current_page: 1,
                    last_page: 1,
                    per_page: 15,
                    total: 0,
                    from: 0,
                    to: 0,
                    has_more_pages: false
                };
            }
        })
        .catch(error => {
            console.error('Error loading report data:', error);
            reportData.value = [];
            summary.value = null;
            pagination.value = {
                current_page: 1,
                last_page: 1,
                per_page: 15,
                total: 0,
                from: 0,
                to: 0,
                has_more_pages: false
            };
        })
        .finally(() => {
            loading.value = false;
        });
}

function resetFilters() {
    // Reset filters to default values
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    filters.value = {
        startDate: firstDay.toISOString().split('T')[0],
        endDate: lastDay.toISOString().split('T')[0],
        outletId: props.user?.id_outlet && props.user.id_outlet !== 1 ? props.user.id_outlet : '',
        divisionId: '',
        status: '',
        type: ''
    };
    
    // Clear data
    reportData.value = [];
    summary.value = null;
    pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
        from: 0,
        to: 0,
        has_more_pages: false
    };
}

function changePage(page) {
    if (page >= 1 && page <= (pagination.value?.last_page || 1)) {
        loadReportData(page);
    }
}

function changePerPage(perPage) {
    pagination.value.per_page = parseInt(perPage);
    loadReportData(1);
}

function exportReport() {
    const params = new URLSearchParams();
    if (filters.value.startDate) params.append('start_date', filters.value.startDate);
    if (filters.value.endDate) params.append('end_date', filters.value.endDate);
    if (filters.value.outletId) params.append('outlet_id', filters.value.outletId);
    if (filters.value.divisionId) params.append('division_id', filters.value.divisionId);
    if (filters.value.status) params.append('status', filters.value.status);
    if (filters.value.type) params.append('type', filters.value.type);
    
    window.open(`/schedule-attendance-correction/export-report?${params.toString()}`, '_blank');
}


function getStatusClass(status) {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText(status) {
    switch (status) {
        case 'pending':
            return 'Pending';
        case 'approved':
            return 'Disetujui';
        case 'rejected':
            return 'Ditolak';
        default:
            return status;
    }
}

// Load initial data
// Format correction value for display
function formatCorrectionValue(item, type) {
    const value = type === 'old' ? item.old_value : item.new_value;
    
    if (item.type === 'schedule') {
        return value;
    } else if (item.type === 'attendance') {
        try {
            // Try to parse JSON data for new format
            const data = JSON.parse(value);
            
            const time = new Date(data.scan_date).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            const inoutMode = data.inoutmode === 1 ? 'Masuk' : 'Keluar';
            
            return `${inoutMode} ${time}`;
        } catch (error) {
            // Fallback for old format (non-JSON)
            return value;
        }
    }
    return value;
}

const getVisiblePages = () => {
    if (!pagination.value || !pagination.value.current_page || !pagination.value.last_page) {
        return [];
    }
    
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const pages = [];
    
    // Show 2 pages before and after current page
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    
    return pages;
};

onMounted(() => {
    // Debug props data
    console.log('Report component mounted with props:', {
        divisions: props.divisions,
        outlets: props.outlets,
        user: props.user
    });
    
    // Set default date range to current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    filters.value.startDate = firstDay.toISOString().split('T')[0];
    filters.value.endDate = lastDay.toISOString().split('T')[0];
    
    // ✅ VALIDASI: Set outlet default berdasarkan user
    if (props.user?.id_outlet && props.user.id_outlet !== 1) {
        filters.value.outletId = props.user.id_outlet;
    }
    
    // Don't auto-load data on mount, let user set filters first
    // loadReportData();
});
</script>
