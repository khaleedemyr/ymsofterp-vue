<template>
    <div>
        <!-- PO Food Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-file-invoice-dollar mr-2 text-indigo-500"></i>
                            PO Foods Approval
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelecting"
                            @click.stop="isSelecting = true"
                            class="text-xs bg-indigo-500 text-white px-2 py-1 rounded hover:bg-indigo-600 transition"
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
                        <div class="bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ approvalCount }}
                        </div>
                    </div>
                </div>
                
                <!-- Multi-approve actions -->
                <div v-if="isSelecting && selectedApprovals.size > 0" class="mb-3 p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
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
                            class="text-xs bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- PO Food Approvals -->
                    <div v-for="po in pendingApprovals.slice(0, 3)" :key="'po-food-approval-' + po.id"
                        @click="isSelecting ? toggleSelection(po.id) : showDetails(po.id)"
                        class="p-3 rounded-lg transition-all duration-200"
                        :class="[
                            isSelecting ? 'cursor-default' : 'cursor-pointer hover:scale-105',
                            isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-indigo-50 hover:bg-indigo-100',
                            selectedApprovals.has(po.id) ? 'ring-2 ring-indigo-500' : ''
                        ]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelecting"
                                    type="checkbox"
                                    :checked="selectedApprovals.has(po.id)"
                                    @click.stop="toggleSelection(po.id)"
                                    class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                />
                                <div class="flex-1">
                                    <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                        {{ po.number }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                        {{ po.supplier?.name || 'Unknown Supplier' }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-box mr-1 text-indigo-600"></i>
                                        {{ po.items_count }} items
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-money-bill-wave mr-1 text-indigo-600"></i>
                                        Rp {{ new Intl.NumberFormat('id-ID').format(po.grand_total) }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        <i class="fa fa-user mr-1 text-indigo-500"></i>{{ po.creator?.nama_lengkap }}
                                    </div>
                                    <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                        {{ formatDate(po.date) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-indigo-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ po.approver_name || po.approval_level_display }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 PO Foods -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-indigo-500 hover:text-indigo-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} PO Foods lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PO Food Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedPO" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-file-invoice-dollar mr-2 text-indigo-500"></i>
                        Detail PO Food Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PO Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPO.number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPO.date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Supplier</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPO.supplier?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Creator</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPO.creator?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Subtotal</label>
                                <p class="text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(selectedPO.subtotal || 0) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PPN</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPO.ppn_enabled ? 'Ya' : 'Tidak' }} ({{ selectedPO.ppn_enabled ? 'Rp ' + new Intl.NumberFormat('id-ID').format(selectedPO.ppn_amount || 0) : '-' }})</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Grand Total</label>
                                <p class="text-gray-900 dark:text-white font-semibold text-lg">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPO.grand_total || 0) }}
                                </p>
                            </div>
                            <div v-if="selectedPO.source_info">
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Source</label>
                                <p class="text-gray-900 dark:text-white">
                                    {{ selectedPO.source_info.type }}
                                    <span v-if="selectedPO.source_info.pr_numbers && selectedPO.source_info.pr_numbers.length > 0">
                                        : {{ selectedPO.source_info.pr_numbers.join(', ') }}
                                    </span>
                                    <span v-if="selectedPO.source_info.ro_numbers && selectedPO.source_info.ro_numbers.length > 0">
                                        : {{ selectedPO.source_info.ro_numbers.join(', ') }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div v-if="selectedPO.notes" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Notes</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPO.notes }}</p>
                        </div>
                    </div>

                    <!-- Items -->
                    <div v-if="selectedPO.items && selectedPO.items.length > 0" class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-md font-semibold text-gray-900 dark:text-white">
                                <i class="fa fa-box mr-2 text-indigo-500"></i>
                                Items ({{ selectedPO.items.length }})
                            </h4>
                            <button 
                                @click="fetchStockForPO" 
                                class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded text-sm hover:bg-blue-200 dark:hover:bg-blue-800 transition"
                            >
                                <i class="fas fa-sync-alt mr-1"></i>
                                Refresh Stock
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Item</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Qty</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Unit</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Price</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Total</th>
                                        <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Last Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in (selectedPO.items_with_stock || selectedPO.items)" :key="'item-' + item.id" class="border-t border-gray-200 dark:border-gray-600">
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.item?.name || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.quantity || 0 }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.unit?.name || '-' }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.price || 0) }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white font-semibold">Rp {{ new Intl.NumberFormat('id-ID').format(item.total || 0) }}</td>
                                        <td class="px-2 py-1 text-gray-900 dark:text-white text-xs">
                                            {{ formatStockDisplay(item.stock) }}
                                        </td>
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
                        <button @click="approvePO" 
                                class="px-4 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors">
                            <i class="fa fa-check mr-2"></i>Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All PO Food Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-indigo-500"></i>
                        Semua PO Foods Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-indigo-500 text-white px-2 py-1 rounded hover:bg-indigo-600 transition"
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
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
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
                            class="text-xs bg-indigo-600 text-white px-2 py-1 rounded hover:bg-indigo-700 transition"
                        >
                            <i class="fa fa-check mr-1"></i>Approve Selected
                        </button>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="mb-4 space-y-3">
                    <div class="flex flex-wrap gap-3">
                        <input v-model="searchQuery" type="text" placeholder="Cari nomor, supplier, creator..." 
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
                            <option value="amount">Jumlah</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} PO Foods
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
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada PO Foods yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="po in paginatedApprovals" :key="'all-po-' + po.id"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-indigo-500 dark:hover:border-indigo-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-indigo-50',
                             selectedAllApprovals.has(po.id) ? 'ring-2 ring-indigo-500' : ''
                         ]"
                         @click="isSelectingAll ? toggleAllSelection(po.id) : showDetails(po.id)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(po.id)"
                                    @click.stop="toggleAllSelection(po.id)"
                                    class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ po.number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ po.supplier?.name || 'Unknown Supplier' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-box mr-1 text-indigo-600"></i>
                                    {{ po.items_count }} items
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-money-bill-wave mr-1 text-indigo-600"></i>
                                    Rp {{ new Intl.NumberFormat('id-ID').format(po.grand_total) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-indigo-500"></i>{{ po.creator?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(po.date) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-indigo-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ po.approver_name || po.approval_level_display }}
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
                                        ? 'bg-indigo-500 text-white border-indigo-500' 
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
import { ref, computed, onMounted, watch, nextTick } from 'vue';
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
function toggleSelection(poId) {
    if (selectedApprovals.value.has(poId)) {
        selectedApprovals.value.delete(poId);
    } else {
        selectedApprovals.value.add(poId);
    }
}

// Select all approvals
function selectAllApprovals() {
    pendingApprovals.value.forEach(po => {
        selectedApprovals.value.add(po.id);
    });
}

// Approve multiple POs
async function approveMultiple() {
    if (selectedApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu PO Food untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple PO Foods?',
        text: `Apakah Anda yakin ingin approve ${selectedApprovals.value.size} PO Food?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#6366f1',
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
        
        const poIds = Array.from(selectedApprovals.value);
        const promises = poIds.map(async (poId) => {
            const po = pendingApprovals.value.find(p => p.id === poId);
            if (!po) return { error: new Error('PO not found'), poId };
            
            try {
                let endpoint = '';
                const approvalLevel = po.approval_level;
                
                if (approvalLevel === 'purchasing_manager') {
                    endpoint = `/po-foods/${poId}/approve`;
                } else if (approvalLevel === 'gm_finance') {
                    endpoint = `/po-foods/${poId}/approve-gm-finance`;
                } else {
                    return { error: new Error('Unknown approval level'), poId };
                }
                
                const response = await axios.post(endpoint, {
                    approved: true,
                    note: ''
                });
                
                // Check if response indicates success
                if (response.data && response.data.success === false) {
                    return { error: new Error(response.data.message || 'Gagal menyetujui PO Food'), poId };
                }
                
                return { success: true, poId };
            } catch (err) {
                return { error: err, poId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        
        selectedApprovals.value.clear();
        isSelecting.value = false;
        
        // Only reload if there's at least one success
        if (success > 0) {
            loadPendingApprovals();
        }
        
        if (failed === 0) {
            Swal.fire('Success', `${success} PO Food berhasil disetujui`, 'success');
        } else if (success === 0) {
            Swal.fire('Error', `Semua PO Food gagal disetujui (${failed} gagal)`, 'error');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple PO Foods:', error);
        Swal.fire('Error', 'Gagal menyetujui PO Foods', 'error');
    }
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/po-food/pending-approvals');
        if (response.data.success) {
            pendingApprovals.value = response.data.po_foods || [];
        }
    } catch (error) {
        console.error('Error loading pending PO Foods approvals:', error);
    } finally {
        loading.value = false;
    }
}

const showDetailModal = ref(false);
const selectedPO = ref(null);
const loadingDetail = ref(false);
const currentApprovalLevel = ref('');

async function showDetails(poId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
            // Wait for DOM update to ensure "All" modal is closed before showing detail modal
            await nextTick();
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        // Find approval level from pendingApprovals or allApprovals
        const po = pendingApprovals.value.find(p => p.id === poId) || allApprovals.value.find(p => p.id === poId);
        if (po) {
            currentApprovalLevel.value = po.approval_level;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/po-food/${poId}`);
        if (response.data && response.data.success && response.data.po_food) {
            selectedPO.value = response.data.po_food;
            // Add approval level to selectedPO
            if (!selectedPO.value.approval_level && currentApprovalLevel.value) {
                selectedPO.value.approval_level = currentApprovalLevel.value;
            }
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail PO Food', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading PO Food details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail PO Food';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedPO.value = null;
}

async function approvePO() {
    if (!selectedPO.value) return;
    
    try {
        let endpoint = '';
        const approvalLevel = selectedPO.value.approval_level || currentApprovalLevel.value;
        
        if (approvalLevel === 'purchasing_manager') {
            endpoint = `/po-foods/${selectedPO.value.id}/approve`;
        } else if (approvalLevel === 'gm_finance') {
            endpoint = `/po-foods/${selectedPO.value.id}/approve-gm-finance`;
        } else {
            Swal.fire('Error', 'Tidak dapat menentukan level approval', 'error');
            return;
        }
        
        const response = await axios.post(endpoint, {
            approved: true,
            note: ''
        });
        
        // Check if response indicates success
        if (response.data && response.data.success === false) {
            const errorMessage = response.data.message || 'Gagal menyetujui PO Food';
            Swal.fire('Error', errorMessage, 'error');
            return; // Don't reload if there's an error
        }
        
        // Only proceed if truly successful
        // Save id before closing modal (which sets selectedPO.value to null)
        const poId = selectedPO.value.id;
        Swal.fire('Success', 'PO Food berhasil disetujui', 'success');
        closeDetailModal();
        loadPendingApprovals();
        emit('approved', poId);
    } catch (error) {
        console.error('Error approving PO Food:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal menyetujui PO Food';
        Swal.fire('Error', errorMessage, 'error');
        // Don't reload on error - status should remain unchanged
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak PO Food',
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
        if (result.isConfirmed && selectedPO.value) {
            try {
                let endpoint = '';
                const approvalLevel = selectedPO.value.approval_level || currentApprovalLevel.value;
                
                if (approvalLevel === 'purchasing_manager') {
                    endpoint = `/po-foods/${selectedPO.value.id}/approve`;
                } else if (approvalLevel === 'gm_finance') {
                    endpoint = `/po-foods/${selectedPO.value.id}/approve-gm-finance`;
                } else {
                    Swal.fire('Error', 'Tidak dapat menentukan level approval', 'error');
                    return;
                }
                
                const response = await axios.post(endpoint, {
                    approved: false,
                    note: result.value
                });
                
                // Check if response indicates success
                if (response.data && response.data.success === false) {
                    const errorMessage = response.data.message || 'Gagal menolak PO Food';
                    Swal.fire('Error', errorMessage, 'error');
                    return; // Don't reload if there's an error
                }
                
                // Only proceed if truly successful
                // Save id before closing modal (which sets selectedPO.value to null)
                const poId = selectedPO.value.id;
                Swal.fire('Success', 'PO Food berhasil ditolak', 'success');
                closeDetailModal();
                loadPendingApprovals();
                emit('rejected', poId);
            } catch (error) {
                console.error('Error rejecting PO Food:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Gagal menolak PO Food';
                Swal.fire('Error', errorMessage, 'error');
                // Don't reload on error - status should remain unchanged
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
        const response = await axios.get('/api/po-food/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.po_foods || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all PO Foods approvals:', error);
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
function toggleAllSelection(poId) {
    if (selectedAllApprovals.value.has(poId)) {
        selectedAllApprovals.value.delete(poId);
    } else {
        selectedAllApprovals.value.add(poId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(po => {
        selectedAllApprovals.value.add(po.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu PO Food untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple PO Foods?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} PO Food?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#6366f1',
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
        
        const poIds = Array.from(selectedAllApprovals.value);
        const promises = poIds.map(async (poId) => {
            const po = allApprovals.value.find(p => p.id === poId);
            if (!po) return { error: new Error('PO not found'), poId };
            
            try {
                let endpoint = '';
                const approvalLevel = po.approval_level;
                
                if (approvalLevel === 'purchasing_manager') {
                    endpoint = `/po-foods/${poId}/approve`;
                } else if (approvalLevel === 'gm_finance') {
                    endpoint = `/po-foods/${poId}/approve-gm-finance`;
                } else {
                    return { error: new Error('Unknown approval level'), poId };
                }
                
                const response = await axios.post(endpoint, {
                    approved: true,
                    note: ''
                });
                
                // Check if response indicates success
                if (response.data && response.data.success === false) {
                    return { error: new Error(response.data.message || 'Gagal menyetujui PO Food'), poId };
                }
                
                return { success: true, poId };
            } catch (err) {
                return { error: err, poId };
            }
        });
        
        const results = await Promise.all(promises);
        const success = results.filter(r => r.success).length;
        const failed = results.filter(r => r.error).length;
        
        selectedAllApprovals.value.clear();
        isSelectingAll.value = false;
        
        // Only reload if there's at least one success
        if (success > 0) {
            loadAllApprovals();
            loadPendingApprovals();
        }
        
        if (failed === 0) {
            Swal.fire('Success', `${success} PO Food berhasil disetujui`, 'success');
        } else if (success === 0) {
            Swal.fire('Error', `Semua PO Food gagal disetujui (${failed} gagal)`, 'error');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple PO Foods:', error);
        Swal.fire('Error', 'Gagal menyetujui PO Foods', 'error');
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
        result = result.filter(po => {
            const number = (po.number || '').toLowerCase();
            const supplier = (po.supplier?.name || '').toLowerCase();
            const creator = (po.creator?.nama_lengkap || '').toLowerCase();
            return number.includes(q) || supplier.includes(q) || creator.includes(q);
        });
    }
    
    // Date filter
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(po => {
            const d = new Date(po.date);
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
                return new Date(a.date || a.created_at) - new Date(b.date || b.created_at);
            case 'number':
                return (a.number || '').localeCompare(b.number || '');
            case 'amount':
                return (b.grand_total || 0) - (a.grand_total || 0);
            case 'newest':
            default:
                return new Date(b.date || b.created_at) - new Date(a.date || a.created_at);
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

// Fetch stock untuk item PO
async function fetchStockForPO() {
    if (!selectedPO.value || !selectedPO.value.items || selectedPO.value.items.length === 0) return;
    if (!selectedPO.value.warehouse_outlet_id) {
        Swal.fire('Info', 'Warehouse outlet tidak tersedia untuk PO ini', 'info');
        return;
    }
    
    try {
        const stockPromises = selectedPO.value.items.map(async (item) => {
            if (!item.item_id || !selectedPO.value.warehouse_outlet_id) return item;
            
            try {
                const response = await axios.get('/api/inventory/stock', {
                    params: { 
                        item_id: item.item_id, 
                        warehouse_id: selectedPO.value.warehouse_outlet_id 
                    }
                });
                
                return {
                    ...item,
                    stock: response.data
                };
            } catch (error) {
                console.error(`Error fetching stock for item ${item.item_id}:`, error);
                return item;
            }
        });
        
        const itemsWithStock = await Promise.all(stockPromises);
        selectedPO.value.items_with_stock = itemsWithStock;
    } catch (error) {
        console.error('Error fetching stock for PO:', error);
        Swal.fire('Error', 'Gagal mengambil data stock', 'error');
    }
}

// Format stock display
function formatStockDisplay(stock) {
    if (!stock) return 'Stok: 0';
    
    const parts = [];
    if (stock.qty_small !== undefined && stock.qty_small > 0) {
        parts.push(`${Number(stock.qty_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_small || ''}`);
    }
    if (stock.qty_medium !== undefined && stock.qty_medium > 0) {
        parts.push(`${Number(stock.qty_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_medium || ''}`);
    }
    if (stock.qty_large !== undefined && stock.qty_large > 0) {
        parts.push(`${Number(stock.qty_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${stock.unit_large || ''}`);
    }
    
    return parts.length > 0 ? `Stok: ${parts.join(' | ')}` : 'Stok: 0';
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

