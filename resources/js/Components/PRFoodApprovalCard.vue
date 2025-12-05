<template>
    <div>
        <!-- PR Food Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-purple-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-shopping-basket mr-2 text-purple-500"></i>
                            PR Foods Approval
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelecting"
                            @click.stop="isSelecting = true"
                            class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600 transition"
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
                        <div class="bg-purple-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ approvalCount }}
                        </div>
                    </div>
                </div>
                
                <!-- Multi-approve actions -->
                <div v-if="isSelecting && selectedApprovals.size > 0" class="mb-3 p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-purple-800 dark:text-purple-200">
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
                            class="text-xs bg-purple-600 text-white px-2 py-1 rounded hover:bg-purple-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-purple-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- PR Food Approvals -->
                    <div v-for="pr in pendingApprovals.slice(0, 3)" :key="'pr-food-approval-' + pr.id"
                        @click="isSelecting ? toggleSelection(pr.id) : showDetails(pr.id)"
                        class="p-3 rounded-lg transition-all duration-200"
                        :class="[
                            isSelecting ? 'cursor-default' : 'cursor-pointer hover:scale-105',
                            isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-purple-50 hover:bg-purple-100',
                            selectedApprovals.has(pr.id) ? 'ring-2 ring-purple-500' : ''
                        ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelecting"
                                    type="checkbox"
                                    :checked="selectedApprovals.has(pr.id)"
                                    @click.stop="toggleSelection(pr.id)"
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                                />
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                        {{ pr.pr_number }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                        {{ pr.warehouse?.name || 'Unknown Warehouse' }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-box mr-1 text-purple-600"></i>
                                        {{ pr.items_count }} items
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-user mr-1 text-purple-500"></i>{{ pr.requester?.nama_lengkap }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        {{ formatDate(pr.tanggal) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-purple-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ pr.approver_name || pr.approval_level_display }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 PR Foods -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-purple-500 hover:text-purple-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} PR Foods lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PR Food Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedPR" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-shopping-basket mr-2 text-purple-500"></i>
                        Detail PR Food Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PR Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPR.pr_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tanggal</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPR.tanggal).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Warehouse</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPR.warehouse?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Requester</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPR.requester?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Items Count</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPR.items?.length || 0 }} items</p>
                            </div>
                        </div>
                        <div v-if="selectedPR.description" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPR.description }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div v-if="selectedPR.items && selectedPR.items.length > 0" class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-box mr-2 text-purple-500"></i>
                            Items ({{ selectedPR.items.length }})
                        </h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Item</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Qty</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Unit</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Note</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Arrival Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in selectedPR.items" :key="'item-' + item.id" class="border-t border-gray-200 dark:border-gray-600">
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.item?.name || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.qty || 0 }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.unit || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.note || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Approval Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="showRejectModal" 
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <i class="fa fa-times mr-2"></i>Tolak
                        </button>
                        <button @click="approvePR" 
                                class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors">
                            <i class="fa fa-check mr-2"></i>Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All PR Food Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-purple-500"></i>
                        Semua PR Foods Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600 transition"
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
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-purple-800 dark:text-purple-200">
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
                            class="text-xs bg-purple-600 text-white px-2 py-1 rounded hover:bg-purple-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <input v-model="searchQuery" type="text" placeholder="Cari nomor, warehouse, requester..." 
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
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} PR Foods
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
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada PR Foods yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="pr in paginatedApprovals" :key="'all-pr-' + pr.id"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-purple-500 dark:hover:border-purple-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-purple-50',
                             selectedAllApprovals.has(pr.id) ? 'ring-2 ring-purple-500' : ''
                         ]"
                         @click="isSelectingAll ? toggleAllSelection(pr.id) : showDetails(pr.id)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(pr.id)"
                                    @click.stop="toggleAllSelection(pr.id)"
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ pr.pr_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ pr.warehouse?.name || 'Unknown Warehouse' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-box mr-1 text-purple-600"></i>
                                    {{ pr.items_count }} items
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-purple-500"></i>{{ pr.requester?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(pr.tanggal) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-purple-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ pr.approver_name || pr.approval_level_display }}
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
                                        ? 'bg-purple-500 text-white border-purple-500' 
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
const selectedApprovals = ref(new Set()); // For multi-select
const isSelecting = ref(false); // Toggle select mode

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

// Toggle selection
function toggleSelection(prId) {
    if (selectedApprovals.value.has(prId)) {
        selectedApprovals.value.delete(prId);
    } else {
        selectedApprovals.value.add(prId);
    }
}

// Select all approvals
function selectAllApprovals() {
    pendingApprovals.value.forEach(pr => {
        selectedApprovals.value.add(pr.id);
    });
}

// Approve multiple PRs
async function approveMultiple() {
    if (selectedApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu PR Food untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple PR Foods?',
        text: `Apakah Anda yakin ingin approve ${selectedApprovals.value.size} PR Food?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#9333ea',
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
        
        const prIds = Array.from(selectedApprovals.value);
        const promises = prIds.map(async (prId) => {
            const pr = pendingApprovals.value.find(p => p.id === prId);
            if (!pr) return { error: new Error('PR not found'), prId };
            
            try {
                let endpoint = '';
                const approvalLevel = pr.approval_level;
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    endpoint = `/pr-foods/${prId}/approve-assistant-ssd-manager`;
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    endpoint = `/pr-foods/${prId}/approve-ssd-manager`;
                } else {
                    return { error: new Error('Unknown approval level'), prId };
                }
                
                const requestData = {
                    approved: true
                };
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    requestData.assistant_ssd_manager_note = '';
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    requestData.ssd_manager_note = '';
                }
                
                await axios.post(endpoint, requestData);
                
                return { success: true, prId };
            } catch (err) {
                return { error: err, prId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        
        selectedApprovals.value.clear();
        isSelecting.value = false;
        loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} PR Food berhasil disetujui`, 'success');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple PR Foods:', error);
        Swal.fire('Error', 'Gagal menyetujui PR Foods', 'error');
    }
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/pr-food/pending-approvals');
        if (response.data.success) {
            pendingApprovals.value = response.data.pr_foods || [];
        }
    } catch (error) {
        console.error('Error loading pending PR Foods approvals:', error);
    } finally {
        loading.value = false;
    }
}

const showDetailModal = ref(false);
const selectedPR = ref(null);
const loadingDetail = ref(false);

const currentApprovalLevel = ref('');

async function showDetails(prId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
        }
        
        // Find approval level from pendingApprovals or allApprovals
        const pr = pendingApprovals.value.find(p => p.id === prId) || allApprovals.value.find(p => p.id === prId);
        if (pr) {
            currentApprovalLevel.value = pr.approval_level;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/pr-food/${prId}`);
        if (response.data && response.data.success && response.data.pr_food) {
            selectedPR.value = response.data.pr_food;
            // Add approval level to selectedPR
            if (!selectedPR.value.approval_level && currentApprovalLevel.value) {
                selectedPR.value.approval_level = currentApprovalLevel.value;
            }
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail PR Food', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading PR Food details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail PR Food';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedPR.value = null;
}

async function approvePR() {
    if (!selectedPR.value) return;
    
    try {
        let endpoint = '';
        const approvalLevel = selectedPR.value.approval_level || currentApprovalLevel.value;
        
        if (approvalLevel === 'assistant_ssd_manager') {
            endpoint = `/pr-foods/${selectedPR.value.id}/approve-assistant-ssd-manager`;
        } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
            endpoint = `/pr-foods/${selectedPR.value.id}/approve-ssd-manager`;
        } else {
            Swal.fire('Error', 'Tidak dapat menentukan level approval', 'error');
            return;
        }
        
        const requestData = {
            approved: true
        };
        
        if (approvalLevel === 'assistant_ssd_manager') {
            requestData.assistant_ssd_manager_note = '';
        } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
            requestData.ssd_manager_note = '';
        }
        
        const response = await axios.post(endpoint, requestData);
        
        if (response.data && response.data.success) {
            Swal.fire('Success', response.data.message || 'PR Food berhasil disetujui', 'success');
        } else {
            Swal.fire('Success', 'PR Food berhasil disetujui', 'success');
        }
        closeDetailModal();
        loadPendingApprovals();
        emit('approved', selectedPR.value.id);
    } catch (error) {
        console.error('Error approving PR Food:', error);
        Swal.fire('Error', error.response?.data?.message || 'Gagal menyetujui PR Food', 'error');
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak PR Food',
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
        if (result.isConfirmed && selectedPR.value) {
            try {
                let endpoint = '';
                const approvalLevel = selectedPR.value.approval_level || currentApprovalLevel.value;
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    endpoint = `/pr-foods/${selectedPR.value.id}/approve-assistant-ssd-manager`;
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    endpoint = `/pr-foods/${selectedPR.value.id}/approve-ssd-manager`;
                } else {
                    Swal.fire('Error', 'Tidak dapat menentukan level approval', 'error');
                    return;
                }
                
                const requestData = {
                    approved: false
                };
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    requestData.assistant_ssd_manager_note = result.value;
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    requestData.ssd_manager_note = result.value;
                }
                
                const response = await axios.post(endpoint, requestData, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.data && response.data.success) {
                    Swal.fire('Success', response.data.message || 'PR Food berhasil ditolak', 'success');
                } else {
                    Swal.fire('Success', 'PR Food berhasil ditolak', 'success');
                }
                closeDetailModal();
                loadPendingApprovals();
                emit('rejected', selectedPR.value.id);
            } catch (error) {
                console.error('Error rejecting PR Food:', error);
                Swal.fire('Error', error.response?.data?.message || 'Gagal menolak PR Food', 'error');
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
// Multi-select for All Modal
const isSelectingAll = ref(false);
const selectedAllApprovals = ref(new Set());

async function loadAllApprovals() {
    loadingAll.value = true;
    try {
        const response = await axios.get('/api/pr-food/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.pr_foods || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all PR Foods approvals:', error);
    } finally {
        loadingAll.value = false;
    }
}

function openAllModal() {
    showAllModal.value = true;
    loadAllApprovals();
    // Reset selection when opening modal
    isSelectingAll.value = false;
    selectedAllApprovals.value.clear();
}

// Multi-select functions for All Modal
function toggleAllSelection(prId) {
    if (selectedAllApprovals.value.has(prId)) {
        selectedAllApprovals.value.delete(prId);
    } else {
        selectedAllApprovals.value.add(prId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(pr => {
        selectedAllApprovals.value.add(pr.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu PR Food untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple PR Foods?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} PR Food?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#9333ea',
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
        
        const prIds = Array.from(selectedAllApprovals.value);
        const promises = prIds.map(async (prId) => {
            const pr = allApprovals.value.find(p => p.id === prId);
            if (!pr) return { error: new Error('PR not found'), prId };
            
            try {
                let endpoint = '';
                const approvalLevel = pr.approval_level;
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    endpoint = `/pr-foods/${prId}/approve-assistant-ssd-manager`;
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    endpoint = `/pr-foods/${prId}/approve-ssd-manager`;
                } else {
                    return { error: new Error('Unknown approval level'), prId };
                }
                
                const requestData = {
                    approved: true
                };
                
                if (approvalLevel === 'assistant_ssd_manager') {
                    requestData.assistant_ssd_manager_note = '';
                } else if (approvalLevel === 'ssd_manager' || approvalLevel === 'sous_chef_mk') {
                    requestData.ssd_manager_note = '';
                }
                
                await axios.post(endpoint, requestData);
                
                return { success: true, prId };
            } catch (err) {
                return { error: err, prId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        
        selectedAllApprovals.value.clear();
        isSelectingAll.value = false;
        loadAllApprovals();
        loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} PR Food berhasil disetujui`, 'success');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple PR Foods:', error);
        Swal.fire('Error', 'Gagal menyetujui PR Foods', 'error');
    }
}

function closeAllModal() {
    showAllModal.value = false;
}

// Computed properties for filtering and pagination
const filteredApprovals = computed(() => {
    let result = [...allApprovals.value];
    
    // Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(pr => {
            const number = (pr.pr_number || '').toLowerCase();
            const warehouse = (pr.warehouse?.name || '').toLowerCase();
            const requester = (pr.requester?.nama_lengkap || '').toLowerCase();
            return number.includes(q) || warehouse.includes(q) || requester.includes(q);
        });
    }
    
    // Date filter
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(pr => {
            const d = new Date(pr.tanggal);
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
    
    // Sort
    result.sort((a, b) => {
        switch (sortBy.value) {
            case 'oldest':
                return new Date(a.tanggal || a.created_at) - new Date(b.tanggal || b.created_at);
            case 'number':
                return (a.pr_number || '').localeCompare(b.pr_number || '');
            case 'newest':
            default:
                return new Date(b.tanggal || b.created_at) - new Date(a.tanggal || a.created_at);
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

// Watchers
watch([searchQuery, dateFilter, sortBy], () => {
    currentPage.value = 1;
});

// Lifecycle
onMounted(() => {
    loadPendingApprovals();
});

// Expose methods for parent component
defineExpose({
    loadPendingApprovals,
    refresh: loadPendingApprovals
});
</script>

