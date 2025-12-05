<template>
    <div>
        <!-- Employee Resignation Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-user-minus mr-2 text-red-500"></i>
                            Employee Resignation Approval
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelecting"
                            @click.stop="isSelecting = true"
                            class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 transition"
                        >
                            <i class="fa fa-check-square mr-1"></i>Multi Approve
                        </button>
                        <button 
                            v-else
                            @click.stop="isSelecting = false; selectedApprovals.clear()"
                            class="text-xs bg-gray-500 text-white px-2 py-1 rounded hover:bg-gray-600 transition"
                        >
                            <i class="fa fa-times mr-1"></i>Cancel
                        </button>
                        <div class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ approvalCount }}
                        </div>
                    </div>
                </div>
                
                <!-- Multi-approve actions -->
                <div v-if="isSelecting && selectedApprovals.size > 0" class="mb-3 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ selectedApprovals.size }} item dipilih
                    </span>
                    <div class="flex gap-2">
                        <button 
                            @click="selectAllApprovals"
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition"
                        >
                            <i class="fa fa-check-double mr-1"></i>Select All
                        </button>
                        <button 
                            @click="approveMultiple"
                            class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-red-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- Employee Resignation Approvals -->
                    <div v-for="resignation in pendingApprovals.slice(0, 3)" :key="'resignation-approval-' + resignation.id"
                        @click="isSelecting ? toggleSelection(resignation.id) : showDetails(resignation.id)"
                        class="p-3 rounded-lg transition-all duration-200"
                        :class="[
                            isSelecting ? 'cursor-default' : 'cursor-pointer hover:scale-105',
                            isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-red-50 hover:bg-red-100',
                            selectedApprovals.has(resignation.id) ? 'ring-2 ring-red-500' : ''
                        ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelecting"
                                    type="checkbox"
                                    :checked="selectedApprovals.has(resignation.id)"
                                    @click.stop="toggleSelection(resignation.id)"
                                    class="w-4 h-4 text-red-600 rounded focus:ring-red-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ resignation.resignation_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <i class="fa fa-user mr-1 text-red-500"></i>
                                    {{ resignation.employee?.nama_lengkap || 'Unknown Employee' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-store mr-1 text-red-600"></i>
                                    {{ resignation.outlet?.nama_outlet || 'Unknown Outlet' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-calendar mr-1 text-red-600"></i>
                                    {{ formatDate(resignation.resignation_date) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-tag mr-1 text-red-600"></i>
                                    {{ resignation.resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-red-500"></i>{{ resignation.creator?.nama_lengkap }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-red-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>Level {{ resignation.approval_level || 1 }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-red-500 hover:text-red-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} Employee Resignation lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Resignation Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedResignation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-user-minus mr-2 text-red-500"></i>
                        Detail Employee Resignation Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Resignation Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedResignation.resignation_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Resignation Date</label>
                                <p class="text-gray-900 dark:text-white">{{ formatDate(selectedResignation.resignation_date) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Employee</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedResignation.employee?.nama_lengkap || 'Unknown' }} ({{ selectedResignation.employee?.nik || '-' }})</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Outlet</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedResignation.outlet?.nama_outlet || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Resignation Type</label>
                                <p class="text-gray-900 dark:text-white">
                                    <span :class="selectedResignation.resignation_type === 'prosedural' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 py-1 rounded text-xs font-medium">
                                        {{ selectedResignation.resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural' }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</label>
                                <p class="text-gray-900 dark:text-white">
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ selectedResignation.status }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created By</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedResignation.creator?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created At</label>
                                <p class="text-gray-900 dark:text-white">{{ formatDateTime(selectedResignation.created_at) }}</p>
                            </div>
                        </div>
                        <div v-if="selectedResignation.notes" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Notes</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedResignation.notes }}</p>
                        </div>
                    </div>

                    <!-- Approval Flow -->
                    <div v-if="selectedResignation.approval_flows && selectedResignation.approval_flows.length > 0" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-users mr-2 text-blue-500"></i>
                            Approval Flow
                        </h4>
                        <div class="space-y-2">
                            <div v-for="flow in selectedResignation.approval_flows" :key="flow.id"
                                class="flex items-center justify-between p-3 rounded-lg"
                                :class="flow.status === 'APPROVED' ? 'bg-green-100 dark:bg-green-900/30' : flow.status === 'REJECTED' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700'">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Level {{ flow.approval_level }}
                                    </span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ flow.approver?.nama_lengkap || 'Unknown' }}</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ flow.approver?.email || '' }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span :class="[
                                        'px-2 py-1 rounded text-xs font-medium',
                                        flow.status === 'APPROVED' ? 'bg-green-500 text-white' : 
                                        flow.status === 'REJECTED' ? 'bg-red-500 text-white' : 
                                        'bg-yellow-500 text-white'
                                    ]">
                                        {{ flow.status }}
                                    </span>
                                    <div v-if="flow.approved_at" class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(flow.approved_at) }}
                                    </div>
                                    <div v-if="flow.rejected_at" class="text-xs text-gray-500 mt-1">
                                        {{ formatDateTime(flow.rejected_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="showRejectModal" 
                                :disabled="isRejecting"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i v-if="!isRejecting" class="fa fa-times mr-2"></i>
                            <i v-else class="fa fa-spinner fa-spin mr-2"></i>
                            {{ isRejecting ? 'Memproses...' : 'Tolak' }}
                        </button>
                        <button @click="approveResignation" 
                                :disabled="isApproving"
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i v-if="!isApproving" class="fa fa-check mr-2"></i>
                            <i v-else class="fa fa-spinner fa-spin mr-2"></i>
                            {{ isApproving ? 'Memproses...' : 'Setujui' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All Employee Resignation Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-red-500"></i>
                        Semua Employee Resignation Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 transition"
                        >
                            <i class="fa fa-check-square mr-1"></i>Multi Approve
                        </button>
                        <button 
                            v-else
                            @click.stop="isSelectingAll = false; selectedAllApprovals.clear()"
                            class="text-xs bg-gray-500 text-white px-2 py-1 rounded hover:bg-gray-600 transition"
                        >
                            <i class="fa fa-times mr-1"></i>Cancel
                        </button>
                        <button @click="closeAllModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fa fa-times text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Multi-approve actions -->
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ selectedAllApprovals.size }} item dipilih
                    </span>
                    <div class="flex gap-2">
                        <button 
                            @click="selectAllAllApprovals"
                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition"
                        >
                            <i class="fa fa-check-double mr-1"></i>Select All
                        </button>
                        <button 
                            @click="approveMultipleAll"
                            class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <input v-model="searchQuery" type="text" placeholder="Cari nomor, employee, outlet..." 
                               class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        <select v-model="dateFilter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="">Semua Tanggal</option>
                            <option value="today">Hari Ini</option>
                            <option value="week">Minggu Ini</option>
                            <option value="month">Bulan Ini</option>
                        </select>
                        <select v-model="sortBy" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="newest">Terbaru</option>
                            <option value="oldest">Terlama</option>
                            <option value="number">Nomor</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} Employee Resignation
                        </div>
                        <select v-model="perPage" @change="currentPage = 1" 
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                            <option :value="10">10 per halaman</option>
                            <option :value="25">25 per halaman</option>
                            <option :value="50">50 per halaman</option>
                            <option :value="100">100 per halaman</option>
                        </select>
                    </div>
                </div>

                <!-- List -->
                <div v-if="loadingAll" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-red-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada Employee Resignation yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="resignation in paginatedApprovals" :key="'all-resignation-' + resignation.id"
                         @click="isSelectingAll ? toggleAllSelection(resignation.id) : showDetails(resignation.id)"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-red-500 dark:hover:border-red-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-red-50',
                             selectedAllApprovals.has(resignation.id) ? 'ring-2 ring-red-500' : ''
                         ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(resignation.id)"
                                    @click.stop="toggleAllSelection(resignation.id)"
                                    class="w-4 h-4 text-red-600 rounded focus:ring-red-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ resignation.resignation_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    <i class="fa fa-user mr-1 text-red-500"></i>
                                    {{ resignation.employee?.nama_lengkap || 'Unknown Employee' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-store mr-1 text-red-600"></i>
                                    {{ resignation.outlet?.nama_outlet || 'Unknown Outlet' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-calendar mr-1 text-red-600"></i>
                                    {{ formatDate(resignation.resignation_date) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-tag mr-1 text-red-600"></i>
                                    {{ resignation.resignation_type === 'prosedural' ? 'Prosedural' : 'Non Prosedural' }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-red-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>Level {{ resignation.approval_level || 1 }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Halaman {{ currentPage }} dari {{ totalPages }}
                    </div>
                    <div class="flex gap-2">
                        <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-left"></i>
                        </button>
                        <template v-for="page in pageRange" :key="'page-' + page">
                            <button v-if="page !== '...'" @click="changePage(page)"
                                    :class="['px-3 py-1 border rounded-lg', currentPage === page 
                                        ? 'bg-red-500 text-white border-red-500' 
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white']">
                                {{ page }}
                            </button>
                            <span v-else class="px-3 py-1 text-gray-500">...</span>
                        </template>
                        <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages"
                                class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['approved', 'rejected']);

// State
const pendingApprovals = ref([]);
const loading = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const selectedApprovals = ref(new Set());
const isSelecting = ref(false);
const isSelectingAll = ref(false);
const selectedAllApprovals = ref(new Set());

// Computed
const approvalCount = computed(() => pendingApprovals.value.length);

// Methods
function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const timestamp = new Date().getTime();
        const response = await axios.get(`/api/employee-resignations/pending-approvals?t=${timestamp}`);
        if (response.data.success) {
            pendingApprovals.value = response.data.resignations || [];
        }
    } catch (error) {
        console.error('Error loading pending Employee Resignation approvals:', error);
    } finally {
        loading.value = false;
    }
}

const showDetailModal = ref(false);
const selectedResignation = ref(null);
const loadingDetail = ref(false);

async function showDetails(resignationId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/employee-resignations/${resignationId}`);
        if (response.data && response.data.resignation) {
            selectedResignation.value = response.data.resignation;
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail Employee Resignation', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading Employee Resignation details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail Employee Resignation';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedResignation.value = null;
}

// Multi-select functions
function toggleSelection(resignationId) {
    if (selectedApprovals.value.has(resignationId)) {
        selectedApprovals.value.delete(resignationId);
    } else {
        selectedApprovals.value.add(resignationId);
    }
}

function selectAllApprovals() {
    pendingApprovals.value.forEach(resignation => {
        selectedApprovals.value.add(resignation.id);
    });
}

async function approveMultiple() {
    if (selectedApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu Employee Resignation untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple Employee Resignations?',
        text: `Apakah Anda yakin ingin approve ${selectedApprovals.value.size} Employee Resignation?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
    });
    
    if (!result.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Processing...',
            text: 'Sedang memproses approval...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const resignationIds = Array.from(selectedApprovals.value);
        const promises = resignationIds.map(async (resignationId) => {
            try {
                await axios.post(`/employee-resignations/${resignationId}/approve`, {
                    note: ''
                });
                return { success: true, resignationId };
            } catch (err) {
                return { error: err, resignationId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        const approvedIds = results.filter(r => r.success && r.resignationId).map(r => r.resignationId);
        
        selectedApprovals.value.clear();
        isSelecting.value = false;
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} Employee Resignation berhasil disetujui`, 'success');
            approvedIds.forEach(id => {
                if (id) emit('approved', id);
            });
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
            approvedIds.forEach(id => {
                if (id) emit('approved', id);
            });
        }
    } catch (error) {
        console.error('Error approving multiple Employee Resignations:', error);
        Swal.fire('Error', 'Gagal menyetujui Employee Resignations', 'error');
    }
}

function toggleAllSelection(resignationId) {
    if (selectedAllApprovals.value.has(resignationId)) {
        selectedAllApprovals.value.delete(resignationId);
    } else {
        selectedAllApprovals.value.add(resignationId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(resignation => {
        selectedAllApprovals.value.add(resignation.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu Employee Resignation untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple Employee Resignations?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} Employee Resignation?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
    });
    
    if (!result.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Processing...',
            text: 'Sedang memproses approval...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const resignationIds = Array.from(selectedAllApprovals.value);
        const promises = resignationIds.map(async (resignationId) => {
            try {
                await axios.post(`/employee-resignations/${resignationId}/approve`, {
                    note: ''
                });
                return { success: true, resignationId };
            } catch (err) {
                return { error: err, resignationId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        const approvedIds = results.filter(r => r.success && r.resignationId).map(r => r.resignationId);
        
        selectedAllApprovals.value.clear();
        isSelectingAll.value = false;
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadAllApprovals();
        await loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} Employee Resignation berhasil disetujui`, 'success');
            approvedIds.forEach(id => {
                if (id) emit('approved', id);
            });
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
            approvedIds.forEach(id => {
                if (id) emit('approved', id);
            });
        }
    } catch (error) {
        console.error('Error approving multiple Employee Resignations:', error);
        Swal.fire('Error', 'Gagal menyetujui Employee Resignations', 'error');
    }
}

async function approveResignation() {
    if (!selectedResignation.value || isApproving.value) return;
    
    isApproving.value = true;
    try {
        const response = await axios.post(`/employee-resignations/${selectedResignation.value.id}/approve`, {
            note: ''
        });
        
        if (response.data && response.data.success) {
            const resignationId = selectedResignation.value?.id;
            
            Swal.fire('Success', response.data.message || 'Employee Resignation berhasil disetujui', 'success');
            closeDetailModal();
            await new Promise(resolve => setTimeout(resolve, 500));
            await loadPendingApprovals();
            
            if (resignationId) {
                emit('approved', resignationId);
            }
        } else {
            const errorMessage = response.data?.message || 'Gagal menyetujui Employee Resignation';
            Swal.fire('Error', errorMessage, 'error');
        }
    } catch (error) {
        console.error('Error approving Employee Resignation:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal menyetujui Employee Resignation';
        Swal.fire('Error', errorMessage, 'error');
    } finally {
        isApproving.value = false;
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak Employee Resignation',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        }
    }).then(async (result) => {
        if (result.isConfirmed && selectedResignation.value) {
            isRejecting.value = true;
            try {
                const response = await axios.post(`/employee-resignations/${selectedResignation.value.id}/reject`, {
                    note: result.value
                });
                if (response.data.success) {
                    const resignationId = selectedResignation.value?.id;
                    
                    Swal.fire('Success', 'Employee Resignation berhasil ditolak', 'success');
                    closeDetailModal();
                    await new Promise(resolve => setTimeout(resolve, 500));
                    await loadPendingApprovals();
                    
                    if (resignationId) {
                        emit('rejected', resignationId);
                    }
                }
            } catch (error) {
                console.error('Error rejecting Employee Resignation:', error);
                Swal.fire('Error', error.response?.data?.message || 'Gagal menolak Employee Resignation', 'error');
            } finally {
                isRejecting.value = false;
            }
        }
    });
}

const showAllModal = ref(false);
const allApprovals = ref([]);
const loadingAll = ref(false);
const searchQuery = ref('');
const dateFilter = ref('');
const sortBy = ref('newest');
const currentPage = ref(1);
const perPage = ref(10);

async function loadAllApprovals() {
    loadingAll.value = true;
    try {
        const response = await axios.get('/api/employee-resignations/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.resignations || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all Employee Resignation approvals:', error);
    } finally {
        loadingAll.value = false;
    }
}

function openAllModal() {
    showAllModal.value = true;
    loadAllApprovals();
    isSelectingAll.value = false;
    selectedAllApprovals.value.clear();
}

function closeAllModal() {
    showAllModal.value = false;
}

const filteredApprovals = computed(() => {
    let result = [...allApprovals.value];
    
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(resignation => {
            const number = (resignation.resignation_number || '').toLowerCase();
            const employee = (resignation.employee?.nama_lengkap || '').toLowerCase();
            const outlet = (resignation.outlet?.nama_outlet || '').toLowerCase();
            return number.includes(q) || employee.includes(q) || outlet.includes(q);
        });
    }
    
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(resignation => {
            const d = new Date(resignation.resignation_date);
            switch (dateFilter.value) {
                case 'today':
                    return d >= today;
                case 'week':
                    return d >= new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                case 'month':
                    return d >= new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                default:
                    return true;
            }
        });
    }
    
    result.sort((a, b) => {
        switch (sortBy.value) {
            case 'oldest':
                return new Date(a.resignation_date || a.created_at) - new Date(b.resignation_date || b.created_at);
            case 'number':
                return (a.resignation_number || '').localeCompare(b.resignation_number || '');
            case 'newest':
            default:
                return new Date(b.resignation_date || b.created_at) - new Date(a.resignation_date || a.created_at);
        }
    });
    
    return result;
});

const totalPages = computed(() => {
    return Math.ceil(filteredApprovals.value.length / perPage.value);
});

const paginatedApprovals = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    const end = start + perPage.value;
    return filteredApprovals.value.slice(start, end);
});

const pageRange = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;
    const range = [];
    
    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            range.push(i);
        }
    } else {
        if (current <= 3) {
            for (let i = 1; i <= 5; i++) {
                range.push(i);
            }
            range.push('...');
            range.push(total);
        } else if (current >= total - 2) {
            range.push(1);
            range.push('...');
            for (let i = total - 4; i <= total; i++) {
                range.push(i);
            }
        } else {
            range.push(1);
            range.push('...');
            for (let i = current - 1; i <= current + 1; i++) {
                range.push(i);
            }
            range.push('...');
            range.push(total);
        }
    }
    
    return range;
});

function changePage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}

watch([searchQuery, dateFilter, sortBy], () => {
    currentPage.value = 1;
});

onMounted(() => {
    loadPendingApprovals();
});

defineExpose({
    loadPendingApprovals,
    refresh: loadPendingApprovals
});
</script>

