<template>
    <div>
        <!-- RO Khusus Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-teal-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-clipboard-list mr-2 text-teal-500"></i>
                            RO Khusus Approval
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelecting"
                            @click.stop="isSelecting = true"
                            class="text-xs bg-teal-500 text-white px-2 py-1 rounded hover:bg-teal-600 transition"
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
                        <div class="bg-teal-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ approvalCount }}
                        </div>
                    </div>
                </div>
                
                <!-- Multi-approve actions -->
                <div v-if="isSelecting && selectedApprovals.size > 0" class="mb-3 p-2 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-teal-800 dark:text-teal-200">
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
                            class="text-xs bg-teal-600 text-white px-2 py-1 rounded hover:bg-teal-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-teal-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- RO Khusus Approvals -->
                    <div v-for="ro in pendingApprovals.slice(0, 3)" :key="'ro-khusus-approval-' + ro.id"
                        @click="isSelecting ? toggleSelection(ro.id) : showDetails(ro.id)"
                        class="p-3 rounded-lg transition-all duration-200"
                        :class="[
                            isSelecting ? 'cursor-default' : 'cursor-pointer hover:scale-105',
                            isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-teal-50 hover:bg-teal-100',
                            selectedApprovals.has(ro.id) ? 'ring-2 ring-teal-500' : ''
                        ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelecting"
                                    type="checkbox"
                                    :checked="selectedApprovals.has(ro.id)"
                                    @click.stop="toggleSelection(ro.id)"
                                    class="w-4 h-4 text-teal-600 rounded focus:ring-teal-500"
                                />
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                        {{ ro.order_number }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                        {{ ro.outlet?.nama_outlet || 'Unknown Outlet' }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-warehouse mr-1 text-teal-600"></i>
                                        {{ ro.warehouse_outlet?.name || 'Unknown Warehouse' }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-box mr-1 text-teal-600"></i>
                                        {{ ro.items_count }} items
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-user mr-1 text-teal-500"></i>{{ ro.requester?.nama_lengkap }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        {{ formatDate(ro.tanggal) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-teal-500 font-medium">
                                <i class="fa fa-clipboard-list mr-1"></i>{{ ro.approval_level_display }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 RO Khusus -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-teal-500 hover:text-teal-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} RO Khusus lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RO Khusus Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedRO" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-clipboard-list mr-2 text-teal-500"></i>
                        Detail RO Khusus Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">RO Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedRO.order_number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tanggal</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedRO.tanggal).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Outlet</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedRO.outlet?.nama_outlet || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Warehouse Outlet</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedRO.warehouse_outlet?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Requester</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedRO.requester?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                            <div v-if="selectedRO.arrival_date">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Arrival Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedRO.arrival_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Items Count</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedRO.items?.length || 0 }} items</p>
                            </div>
                        </div>
                        <div v-if="selectedRO.description" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Description</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedRO.description }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div v-if="selectedRO.items && selectedRO.items.length > 0" class="bg-teal-50 dark:bg-teal-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-box mr-2 text-teal-500"></i>
                            Items ({{ selectedRO.items.length }})
                        </h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Item</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Qty</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Unit</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Price</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in selectedRO.items" :key="'item-' + item.id" class="border-t border-gray-200 dark:border-gray-600">
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.item?.name || item.item_name || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.qty || 0 }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.unit || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.price || 0) }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white font-semibold">Rp {{ new Intl.NumberFormat('id-ID').format(item.subtotal || 0) }}</td>
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
                        <button @click="approveRO" 
                                class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition-colors">
                            <i class="fa fa-check mr-2"></i>Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All RO Khusus Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-teal-500"></i>
                        Semua RO Khusus Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-teal-500 text-white px-2 py-1 rounded hover:bg-teal-600 transition"
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
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-teal-800 dark:text-teal-200">
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
                            class="text-xs bg-teal-600 text-white px-2 py-1 rounded hover:bg-teal-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <input v-model="searchQuery" type="text" placeholder="Cari nomor, outlet, warehouse, requester..." 
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
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} RO Khusus
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
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada RO Khusus yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="ro in paginatedApprovals" :key="'all-ro-' + ro.id"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-teal-500 dark:hover:border-teal-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-teal-50',
                             selectedAllApprovals.has(ro.id) ? 'ring-2 ring-teal-500' : ''
                         ]"
                         @click="isSelectingAll ? toggleAllSelection(ro.id) : showDetails(ro.id)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(ro.id)"
                                    @click.stop="toggleAllSelection(ro.id)"
                                    class="w-4 h-4 text-teal-600 rounded focus:ring-teal-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ ro.order_number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ ro.outlet?.nama_outlet || 'Unknown Outlet' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-warehouse mr-1 text-teal-600"></i>
                                    {{ ro.warehouse_outlet?.name || 'Unknown Warehouse' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-box mr-1 text-teal-600"></i>
                                    {{ ro.items_count }} items
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-teal-500"></i>{{ ro.requester?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(ro.tanggal) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-teal-500 font-medium">
                                <i class="fa fa-clipboard-list mr-1"></i>{{ ro.approval_level_display }}
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
                                        ? 'bg-teal-500 text-white border-teal-500' 
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
function toggleSelection(roId) {
    if (selectedApprovals.value.has(roId)) {
        selectedApprovals.value.delete(roId);
    } else {
        selectedApprovals.value.add(roId);
    }
}

// Select all approvals
function selectAllApprovals() {
    pendingApprovals.value.forEach(ro => {
        selectedApprovals.value.add(ro.id);
    });
}

// Approve multiple ROs
async function approveMultiple() {
    if (selectedApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu RO Khusus untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple RO Khusus?',
        text: `Apakah Anda yakin ingin approve ${selectedApprovals.value.size} RO Khusus?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#14b8a6',
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
        
        const roIds = Array.from(selectedApprovals.value);
        const promises = roIds.map(async (roId) => {
            try {
                await axios.post(`/floor-order/${roId}/approve`, {
                    notes: ''
                });
                return { success: true, roId };
            } catch (err) {
                return { error: err, roId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        
        selectedApprovals.value.clear();
        isSelecting.value = false;
        loadPendingApprovals();
        
        if (failed === 0) {
            Swal.fire('Success', `${success} RO Khusus berhasil disetujui`, 'success');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple RO Khusus:', error);
        Swal.fire('Error', 'Gagal menyetujui RO Khusus', 'error');
    }
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/ro-khusus/pending-approvals');
        if (response.data.success) {
            pendingApprovals.value = response.data.ro_khusus || [];
        }
    } catch (error) {
        console.error('Error loading pending RO Khusus approvals:', error);
    } finally {
        loading.value = false;
    }
}

const showDetailModal = ref(false);
const selectedRO = ref(null);
const loadingDetail = ref(false);

async function showDetails(roId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/ro-khusus/${roId}`);
        if (response.data && response.data.success && response.data.ro_khusus) {
            selectedRO.value = response.data.ro_khusus;
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail RO Khusus', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading RO Khusus details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail RO Khusus';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedRO.value = null;
}

async function approveRO() {
    if (!selectedRO.value) return;
    
    try {
        const result = await Swal.fire({
            title: 'Setujui RO Khusus?',
            text: `Anda akan menyetujui RO Khusus ${selectedRO.value.order_number}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Setujui',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#10B981',
            cancelButtonColor: '#6B7280'
        });

        if (!result.isConfirmed) return;

        // Save ID before closing modal
        const roId = selectedRO.value.id;
        
        const response = await axios.post(`/floor-order/${roId}/approve`, {
            notes: ''
        });
        
        Swal.fire('Success', 'RO Khusus berhasil disetujui', 'success');
        closeDetailModal();
        loadPendingApprovals();
        emit('approved', roId);
    } catch (error) {
        console.error('Error approving RO Khusus:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal menyetujui RO Khusus';
        Swal.fire('Error', errorMessage, 'error');
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak RO Khusus',
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
        if (result.isConfirmed && selectedRO.value) {
            try {
                // Save ID before closing modal
                const roId = selectedRO.value.id;
                
                // Note: Reject mungkin perlu endpoint khusus atau update status
                // Untuk sementara, kita bisa update status ke rejected via API
                const response = await axios.post(`/floor-order/${roId}/approve`, {
                    notes: result.value,
                    approved: false
                });
                
                Swal.fire('Success', 'RO Khusus berhasil ditolak', 'success');
                closeDetailModal();
                loadPendingApprovals();
                emit('rejected', roId);
            } catch (error) {
                console.error('Error rejecting RO Khusus:', error);
                Swal.fire('Error', error.response?.data?.message || 'Gagal menolak RO Khusus', 'error');
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
        const response = await axios.get('/api/ro-khusus/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.ro_khusus || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all RO Khusus approvals:', error);
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
function toggleAllSelection(roId) {
    if (selectedAllApprovals.value.has(roId)) {
        selectedAllApprovals.value.delete(roId);
    } else {
        selectedAllApprovals.value.add(roId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(ro => {
        selectedAllApprovals.value.add(ro.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu RO Khusus untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple RO Khusus?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} RO Khusus?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#14b8a6',
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
        
        const roIds = Array.from(selectedAllApprovals.value);
        const promises = roIds.map(async (roId) => {
            try {
                await axios.post(`/floor-order/${roId}/approve`, {
                    notes: ''
                });
                return { success: true, roId };
            } catch (err) {
                return { error: err, roId };
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
            Swal.fire('Success', `${success} RO Khusus berhasil disetujui`, 'success');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple RO Khusus:', error);
        Swal.fire('Error', 'Gagal menyetujui RO Khusus', 'error');
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
        result = result.filter(ro => {
            const number = (ro.order_number || '').toLowerCase();
            const outlet = (ro.outlet?.nama_outlet || '').toLowerCase();
            const warehouse = (ro.warehouse_outlet?.name || '').toLowerCase();
            const requester = (ro.requester?.nama_lengkap || '').toLowerCase();
            return number.includes(q) || outlet.includes(q) || warehouse.includes(q) || requester.includes(q);
        });
    }
    
    // Date filter
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(ro => {
            const d = new Date(ro.tanggal);
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
                return (a.order_number || '').localeCompare(b.order_number || '');
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

<style scoped>
.animate-fade-in {
    animation: fadeIn 1s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.hover\:shadow-3xl:hover {
    box-shadow: 0 35px 60px -12px rgba(0, 0, 0, 0.25);
}
.backdrop-blur-md {
    backdrop-filter: blur(12px);
}
</style>

