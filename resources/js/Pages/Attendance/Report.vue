<template>
    <AppLayout title="Report Absent">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Report Absent
            </h2>
        </template>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div class="h-full">
                <div class="bg-white dark:bg-gray-800 min-h-screen">
                    <div class="p-4 text-gray-900 dark:text-gray-100">
                        
                        <!-- Filters -->
                        <div class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-4">
                                <!-- Start Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Tanggal Mulai
                                    </label>
                                    <input
                                        type="date"
                                        v-model="filters.start_date"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                </div>

                                <!-- End Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Tanggal Selesai
                                    </label>
                                    <input
                                        type="date"
                                        v-model="filters.end_date"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Status
                                    </label>
                                    <select
                                        v-model="filters.status"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Semua Status</option>
                                        <option value="pending">Menunggu Persetujuan</option>
                                        <option value="supervisor_approved">Disetujui Atasan</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Ditolak</option>
                                    </select>
                                </div>

                                <!-- Outlet -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Outlet
                                    </label>
                                    <select
                                        v-model="filters.outlet_id"
                                        :disabled="props.user?.id_outlet && props.user.id_outlet !== 1"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                        <option value="">Semua Outlet</option>
                                        <option v-for="outlet in availableOutlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                                            {{ outlet.nama_outlet }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Division -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Divisi
                                    </label>
                                    <select
                                        v-model="filters.division_id"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Semua Divisi</option>
                                        <option v-for="division in divisions" :key="division.id" :value="division.id">
                                            {{ division.nama_divisi }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Search Employee Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Cari Nama Karyawan
                                    </label>
                                    <input
                                        type="text"
                                        v-model="filters.employee_name"
                                        placeholder="Masukkan nama karyawan..."
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col justify-end">
                                    <div class="flex gap-2">
                                        <button
                                            @click="loadData"
                                            :disabled="loading"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
                                            {{ loading ? 'Loading...' : 'Filter' }}
                                        </button>
                                        <button
                                            @click="exportToExcel"
                                            :disabled="loading || reportData.length === 0"
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i class="fa-solid fa-file-excel mr-2"></i>
                                            Export Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Data -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Data Report Absent
                                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                        ({{ reportData.length }} data)
                                    </span>
                                </h3>
                            </div>

                            <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Nama Karyawan
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Outlet
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Divisi
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Jenis Izin/Cuti
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Tanggal & Durasi
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Tanggal Pengajuan
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Approval Atasan
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Approval HRD
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Dokumen
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr v-if="loading">
                                            <td colspan="10" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                                <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                                                Loading...
                                            </td>
                                        </tr>
                                        <tr v-else-if="reportData.length === 0">
                                            <td colspan="10" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                                Tidak ada data
                                            </td>
                                        </tr>
                                        <tr v-else v-for="item in reportData" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ item.employee_name || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ item.outlet_name || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ item.nama_divisi || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ item.leave_type_name || '-' }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="space-y-1">
                                                    <div class="font-medium">{{ formatDateRange(item.date_from, item.date_to) }}</div>
                                                    <div class="text-xs text-blue-600 dark:text-blue-400">
                                                        <i class="fa-solid fa-calendar-days mr-1"></i>
                                                        {{ calculateAbsentDays(item.date_from, item.date_to) }} hari
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span :class="getStatusClass(item.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                                    {{ getStatusText(item.status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatDateTime(item.created_at) }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="space-y-1">
                                                    <div v-if="item.approver_name" class="font-medium text-green-600 dark:text-green-400">
                                                        <i class="fa-solid fa-user-check mr-1"></i>
                                                        {{ item.approver_name }}
                                                    </div>
                                                    <div v-else class="text-gray-400">
                                                        <i class="fa-solid fa-clock mr-1"></i>
                                                        Belum disetujui
                                                    </div>
                                                    <div v-if="item.approved_at" class="text-xs text-gray-500">
                                                        {{ formatDateTime(item.approved_at) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="space-y-1">
                                                    <div v-if="item.hrd_approver_name" class="font-medium text-blue-600 dark:text-blue-400">
                                                        <i class="fa-solid fa-user-tie mr-1"></i>
                                                        {{ item.hrd_approver_name }}
                                                    </div>
                                                    <div v-else class="text-gray-400">
                                                        <i class="fa-solid fa-clock mr-1"></i>
                                                        Belum disetujui
                                                    </div>
                                                    <div v-if="item.hrd_approved_at" class="text-xs text-gray-500">
                                                        {{ formatDateTime(item.hrd_approved_at) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div v-if="item.document_paths || item.document_path" class="flex gap-2">
                                                    <!-- Multiple documents -->
                                                    <div v-if="item.document_paths && item.document_paths.length > 0" class="flex gap-1">
                                                        <div v-for="(docPath, index) in item.document_paths" :key="index" class="relative group cursor-pointer" @click="openImageModal(`/storage/${docPath}`, item.document_paths)">
                                                            <div v-if="isImageFile(docPath)" class="relative">
                                                                <img :src="`/storage/${docPath}`" class="w-12 h-12 object-cover rounded border border-gray-300 dark:border-gray-600 hover:opacity-90 transition-opacity">
                                                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all rounded flex items-center justify-center pointer-events-none">
                                                                    <i class="fa-solid fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity text-xs"></i>
                                                                </div>
                                                            </div>
                                                            <div v-else class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                                                <a :href="`/storage/${docPath}`" target="_blank" class="text-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                    <i class="fa-solid fa-file-pdf text-lg"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Single document -->
                                                    <div v-else-if="item.document_path" class="relative group cursor-pointer" @click="openImageModal(`/storage/${item.document_path}`, [item.document_path])">
                                                        <div v-if="isImageFile(item.document_path)" class="relative">
                                                            <img :src="`/storage/${item.document_path}`" class="w-12 h-12 object-cover rounded border border-gray-300 dark:border-gray-600 hover:opacity-90 transition-opacity">
                                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all rounded flex items-center justify-center pointer-events-none">
                                                                <i class="fa-solid fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity text-xs"></i>
                                                            </div>
                                                        </div>
                                                        <div v-else class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded border border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                                            <a :href="`/storage/${item.document_path}`" target="_blank" class="text-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                <i class="fa-solid fa-file-pdf text-lg"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <span v-else class="text-gray-400">-</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <div v-if="pagination.total > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} data
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <label class="text-sm text-gray-700 dark:text-gray-300">Per halaman:</label>
                                            <select 
                                                v-model="pagination.per_page" 
                                                @change="changePerPage(pagination.per_page)"
                                                class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
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
                                            @click="changePage(pagination.current_page - 1)"
                                            :disabled="pagination.current_page <= 1"
                                            class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i class="fa-solid fa-chevron-left"></i>
                                        </button>
                                        
                                        <!-- Page Numbers -->
                                        <div class="flex items-center gap-1">
                                            <!-- First page -->
                                            <button
                                                v-if="pagination.current_page > 3"
                                                @click="changePage(1)"
                                                class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                            >
                                                1
                                            </button>
                                            
                                            <!-- Ellipsis -->
                                            <span v-if="pagination.current_page > 4" class="px-2 text-gray-500">...</span>
                                            
                                            <!-- Pages around current page -->
                                            <button
                                                v-for="page in getVisiblePages()"
                                                :key="page"
                                                @click="changePage(page)"
                                                :class="[
                                                    'px-3 py-1 text-sm border rounded',
                                                    page === pagination.current_page
                                                        ? 'bg-blue-600 text-white border-blue-600'
                                                        : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                ]"
                                            >
                                                {{ page }}
                                            </button>
                                            
                                            <!-- Ellipsis -->
                                            <span v-if="pagination.current_page < pagination.last_page - 3" class="px-2 text-gray-500">...</span>
                                            
                                            <!-- Last page -->
                                            <button
                                                v-if="pagination.current_page < pagination.last_page - 2"
                                                @click="changePage(pagination.last_page)"
                                                class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                            >
                                                {{ pagination.last_page }}
                                            </button>
                                        </div>
                                        
                                        <!-- Next Button -->
                                        <button
                                            @click="changePage(pagination.current_page + 1)"
                                            :disabled="!pagination.has_more_pages"
                                            class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
        </div>
    </AppLayout>

    <!-- Lightbox for Document Images -->
    <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
    />
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import axios from 'axios';

const props = defineProps({
    outlets: Array,
    divisions: Array,
    user: Object,
    filters: Object
});

const loading = ref(false);
const reportData = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
    has_more_pages: false
});

// Lightbox state
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const filters = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    status: props.filters.status || '',
    outlet_id: props.filters.outlet_id || '',
    division_id: props.filters.division_id || '',
    employee_name: props.filters.employee_name || ''
});

// ✅ VALIDASI: Filter outlet berdasarkan user
const availableOutlets = computed(() => {
    if (props.user?.id_outlet && props.user.id_outlet !== 1) {
        // Jika user bukan dari outlet 1, hanya tampilkan outlet mereka
        return props.outlets.filter(outlet => outlet.id_outlet === props.user.id_outlet);
    }
    // Jika user dari outlet 1 (head office), tampilkan semua outlet
    return props.outlets;
});

const loadData = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            ...filters.value,
            page: page,
            per_page: pagination.value.per_page
        };
        
        const response = await axios.get('/attendance/report/data', {
            params: params
        });
        
        if (response.data.success) {
            reportData.value = response.data.data;
            pagination.value = response.data.pagination;
        }
    } catch (error) {
        console.error('Error loading report data:', error);
        reportData.value = [];
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => {
    loadData(page);
};

const changePerPage = (perPage) => {
    pagination.value.per_page = perPage;
    loadData(1);
};

const exportToExcel = async () => {
    try {
        const response = await axios.get('/attendance/report/export', {
            params: filters.value,
            responseType: 'blob'
        });
        
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `absent_report_${new Date().toISOString().split('T')[0]}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error exporting to Excel:', error);
    }
};

const formatDateRange = (dateFrom, dateTo) => {
    if (!dateFrom) return '-';
    
    const from = new Date(dateFrom).toLocaleDateString('id-ID');
    const to = dateTo ? new Date(dateTo).toLocaleDateString('id-ID') : from;
    
    return from === to ? from : `${from} - ${to}`;
};

const calculateAbsentDays = (dateFrom, dateTo) => {
    if (!dateFrom) return 0;
    
    const startDate = new Date(dateFrom);
    const endDate = dateTo ? new Date(dateTo) : startDate;
    
    // Calculate difference in days
    const timeDiff = endDate.getTime() - startDate.getTime();
    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // +1 to include both start and end dates
    
    return daysDiff;
};

const formatDateTime = (dateTime) => {
    if (!dateTime) return '-';
    return new Date(dateTime).toLocaleString('id-ID');
};

const getStatusText = (status) => {
    switch (status) {
        case 'pending':
            return 'Menunggu Persetujuan';
        case 'supervisor_approved':
            return 'Disetujui Atasan';
        case 'approved':
            return 'Disetujui';
        case 'rejected':
            return 'Ditolak';
        default:
            return status;
    }
};

const getStatusClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'supervisor_approved':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'approved':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

// Lightbox functions
const getImageUrl = (imagePath) => {
    if (!imagePath) return null;
    try {
        return `/storage/${imagePath}`;
    } catch (error) {
        console.error('Error processing image:', error);
        return null;
    }
}

const openImageModal = (imageUrl, allImagePaths = []) => {
    console.log('=== OPENING LIGHTBOX ===');
    console.log('imageUrl:', imageUrl);
    console.log('allImagePaths:', allImagePaths);
    
    if (!allImagePaths || allImagePaths.length === 0) {
        console.log('No image paths provided, using single image');
        lightboxImages.value = [imageUrl];
        lightboxIndex.value = 0;
    } else {
        lightboxImages.value = allImagePaths.map(path => getImageUrl(path)).filter(url => url);
        console.log('Converted lightboxImages:', lightboxImages.value);
        
        lightboxIndex.value = allImagePaths.findIndex(path => 
            imageUrl.includes(path.split('/').pop())
        );
        
        if (lightboxIndex.value === -1) {
            lightboxIndex.value = 0;
        }
    }
    
    lightboxVisible.value = true;
    
    console.log('Final state:', { 
        lightboxVisible: lightboxVisible.value,
        lightboxImages: lightboxImages.value, 
        lightboxIndex: lightboxIndex.value
    });
    console.log('=== LIGHTBOX OPENED ===');
}

const isImageFile = (filename) => {
    if (!filename) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    const extension = filename.toLowerCase().substring(filename.lastIndexOf('.'));
    return imageExtensions.includes(extension);
};

const getVisiblePages = () => {
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
    loadData();
});
</script>
