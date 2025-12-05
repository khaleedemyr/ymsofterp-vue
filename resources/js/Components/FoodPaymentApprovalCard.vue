<template>
    <div>
        <!-- Food Payment Approval Section -->
        <div v-if="approvalCount > 0" class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            <i class="fa fa-utensils mr-2 text-green-500"></i>
                            Food Payment Approval
                        </h3>
                    </div>
                    <div class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        {{ approvalCount }}
                    </div>
                </div>
                
                <div v-if="loading" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-green-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- Food Payment Approvals -->
                    <div v-for="fp in pendingApprovals.slice(0, 3)" :key="'food-payment-approval-' + fp.id"
                        @click="showDetails(fp.id)"
                        class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                        :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-green-50 hover:bg-green-100'">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ fp.number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ fp.supplier?.name || 'Unknown Supplier' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-credit-card mr-1 text-green-600"></i>
                                    {{ fp.payment_type }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(fp.total) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-green-500"></i>{{ fp.creator?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(fp.date) }}
                                </div>
                            </div>
                            <div class="text-xs text-green-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ fp.approver_name || fp.approval_level_display }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Show more button if there are more than 3 Food Payments -->
                    <div v-if="pendingApprovals.length > 3" class="text-center pt-2">
                        <button @click="openAllModal" class="text-sm text-green-500 hover:text-green-700 font-medium">
                            Lihat {{ pendingApprovals.length - 3 }} Food Payment lainnya...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Food Payment Approval Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedPayment" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-utensils mr-2 text-green-500"></i>
                        Detail Food Payment Approval
                    </h3>
                    <button @click="closeDetailModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa fa-times text-xl"></i>
                    </button>
                </div>

                <div v-if="loadingDetail" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat detail...</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">FP Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPayment.number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPayment.date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Supplier</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.supplier?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment Type</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.payment_type }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</label>
                                <p class="text-gray-900 dark:text-white font-semibold text-lg">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPayment.total) }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created By</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPayment.creator?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                        </div>
                        <div v-if="selectedPayment.notes" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Notes</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPayment.notes }}</p>
                        </div>
                        <div v-if="selectedPayment.bukti_transfer_path" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Bukti Transfer</label>
                            <div class="mt-2">
                                <a :href="`/storage/${selectedPayment.bukti_transfer_path}`" target="_blank" 
                                   class="text-blue-500 hover:text-blue-700 underline">
                                    <i class="fa fa-file-image mr-1"></i>Lihat Bukti Transfer
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Contra Bons Information -->
                    <div v-if="selectedPayment.contra_bons && selectedPayment.contra_bons.length > 0" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-file-invoice-dollar mr-2 text-blue-500"></i>
                            Contra Bons ({{ selectedPayment.contra_bons.length }})
                        </h4>
                        <div class="space-y-4">
                            <div v-for="cb in selectedPayment.contra_bons" :key="'cb-' + cb.id" 
                                 class="bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Contra Bon Number</label>
                                        <p class="text-sm text-gray-900 dark:text-white font-semibold">{{ cb.number }}</p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Source Type</label>
                                        <p class="text-sm text-gray-900 dark:text-white">{{ cb.source_type_display }}</p>
                                    </div>
                                    <div v-if="cb.source_numbers && cb.source_numbers.length > 0">
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Source Numbers</label>
                                        <p class="text-sm text-gray-900 dark:text-white">{{ cb.source_numbers.join(', ') }}</p>
                                    </div>
                                    <div v-if="cb.source_outlets && cb.source_outlets.length > 0">
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Outlets</label>
                                        <p class="text-sm text-gray-900 dark:text-white">{{ cb.source_outlets.join(', ') }}</p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Total Amount</label>
                                        <p class="text-sm text-gray-900 dark:text-white font-semibold">
                                            Rp {{ new Intl.NumberFormat('id-ID').format(cb.total_amount) }}
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Items -->
                                <div v-if="cb.items && cb.items.length > 0" class="mt-3">
                                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2 block">Items</label>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Item</th>
                                                    <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Qty</th>
                                                    <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Unit</th>
                                                    <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Price</th>
                                                    <th class="px-2 py-1 text-left text-gray-700 dark:text-gray-300">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="item in cb.items" :key="'item-' + item.id" class="border-t border-gray-200 dark:border-gray-600">
                                                    <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.item?.name || item.item_name || '-' }}</td>
                                                    <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.quantity || 0 }}</td>
                                                    <td class="px-2 py-1 text-gray-900 dark:text-white">{{ item.unit?.name || item.unit_name || '-' }}</td>
                                                    <td class="px-2 py-1 text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.price || 0) }}</td>
                                                    <td class="px-2 py-1 text-gray-900 dark:text-white font-semibold">Rp {{ new Intl.NumberFormat('id-ID').format(item.total || 0) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Actions -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button @click="showRejectModal" 
                                class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                            <i class="fa fa-times mr-2"></i>Tolak
                        </button>
                        <button @click="approvePayment" 
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fa fa-check mr-2"></i>Setujui
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- All Food Payment Modal -->
        <Teleport to="body">
            <div v-if="showAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[9998]" @click="closeAllModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-list mr-2 text-green-500"></i>
                        Semua Food Payment Pending
                    </h3>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="!isSelectingAll"
                            @click.stop="isSelectingAll = true"
                            class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600 transition"
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
                <div v-if="isSelectingAll && selectedAllApprovals.size > 0" class="mb-4 p-2 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-between">
                    <span class="text-sm font-medium text-green-800 dark:text-green-200">
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
                            class="text-xs bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700 transition"
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
                        <select v-model="statusFilter" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="">Semua Status</option>
                            <option value="paid">Paid</option>
                        </select>
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
                            Menampilkan {{ paginatedApprovals.length }} dari {{ filteredApprovals.length }} Food Payment
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
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <div v-else-if="paginatedApprovals.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Tidak ada Food Payment yang ditemukan
                </div>

                <div v-else class="space-y-2">
                    <div v-for="fp in paginatedApprovals" :key="'all-fp-' + fp.id"
                         class="p-3 rounded-lg transition-all duration-200 border border-gray-200 dark:border-gray-700"
                         :class="[
                             isSelectingAll ? 'cursor-default' : 'cursor-pointer hover:scale-[1.02] hover:border-green-500 dark:hover:border-green-500',
                             isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-gray-50 hover:bg-green-50',
                             selectedAllApprovals.has(fp.id) ? 'ring-2 ring-green-500' : ''
                         ]"
                         @click="isSelectingAll ? toggleAllSelection(fp.id) : showDetails(fp.id)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 flex-1">
                                <input 
                                    v-if="isSelectingAll"
                                    type="checkbox"
                                    :checked="selectedAllApprovals.has(fp.id)"
                                    @click.stop="toggleAllSelection(fp.id)"
                                    class="w-4 h-4 text-green-600 rounded focus:ring-green-500"
                                />
                                <div class="flex-1">
                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    {{ fp.number }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ fp.supplier?.name || 'Unknown Supplier' }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-credit-card mr-1 text-green-600"></i>
                                    {{ fp.payment_type }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(fp.total) }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    <i class="fa fa-user mr-1 text-green-500"></i>{{ fp.creator?.nama_lengkap }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ formatDate(fp.date) }}
                                </div>
                                </div>
                            </div>
                            <div class="text-xs text-green-500 font-medium">
                                <i class="fa fa-user-check mr-1"></i>{{ fp.approver_name || fp.approval_level_display }}
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
                                        ? 'bg-green-500 text-white border-green-500' 
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
import { ref, computed, watch, onMounted } from 'vue';
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
const showDetailModal = ref(false);
const selectedPayment = ref(null);
const loadingDetail = ref(false);
const showAllModal = ref(false);
const allApprovals = ref([]);
const loadingAll = ref(false);
// Multi-select for All Modal
const isSelectingAll = ref(false);
const selectedAllApprovals = ref(new Set());

// Filters and pagination for "All" modal
const searchQuery = ref('');
const statusFilter = ref('');
const dateFilter = ref('');
const sortBy = ref('newest');
const currentPage = ref(1);
const perPage = ref(10);

// Computed
const approvalCount = computed(() => pendingApprovals.value.length);

const filteredApprovals = computed(() => {
    let result = [...allApprovals.value];
    
    // Search
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(fp => {
            const number = (fp.number || '').toLowerCase();
            const supplier = (fp.supplier?.name || '').toLowerCase();
            const creator = (fp.creator?.nama_lengkap || '').toLowerCase();
            return number.includes(q) || supplier.includes(q) || creator.includes(q);
        });
    }
    
    // Status filter
    if (statusFilter.value) {
        result = result.filter(fp => (fp.status || 'paid').toString().toLowerCase() === statusFilter.value);
    }
    
    // Date filter
    if (dateFilter.value) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        result = result.filter(fp => {
            const d = new Date(fp.date);
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
                return (b.total || 0) - (a.total || 0);
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

// Methods
function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

async function loadPendingApprovals() {
    loading.value = true;
    try {
        const response = await axios.get('/api/food-payment/pending-approvals');
        if (response.data.success) {
            pendingApprovals.value = response.data.food_payments || [];
        }
    } catch (error) {
        console.error('Error loading pending Food Payment approvals:', error);
    } finally {
        loading.value = false;
    }
}

const currentApprovalLevel = ref('');

async function showDetails(fpId) {
    try {
        if (showAllModal.value) {
            showAllModal.value = false;
        }
        
        // Find approval level from pendingApprovals or allApprovals
        const fp = pendingApprovals.value.find(p => p.id === fpId) || allApprovals.value.find(p => p.id === fpId);
        if (fp) {
            currentApprovalLevel.value = fp.approval_level;
        }
        
        loadingDetail.value = true;
        showDetailModal.value = true;
        
        const response = await axios.get(`/api/food-payment/${fpId}`);
        if (response.data && response.data.success && response.data.food_payment) {
            selectedPayment.value = response.data.food_payment;
            // Add approval level to selectedPayment
            if (!selectedPayment.value.approval_level && currentApprovalLevel.value) {
                selectedPayment.value.approval_level = currentApprovalLevel.value;
            }
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal memuat detail Food Payment', 'error');
            closeDetailModal();
        }
    } catch (error) {
        console.error('Error loading Food Payment details:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Gagal memuat detail Food Payment';
        Swal.fire('Error', errorMessage, 'error');
        closeDetailModal();
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedPayment.value = null;
}

async function approvePayment() {
    if (!selectedPayment.value) return;
    
    try {
        const response = await axios.post(`/food-payments/${selectedPayment.value.id}/approve`, {
            approved: true,
            note: ''
        });
        
        // Check if response is successful (status 200-299) and has success flag
        if (response.status >= 200 && response.status < 300) {
            if (response.data && response.data.success) {
                Swal.fire('Success', response.data.message || 'Food Payment berhasil disetujui', 'success');
                closeDetailModal();
                loadPendingApprovals();
                emit('approved', selectedPayment.value.id);
            } else {
                // Response OK but success is false
                const errorMsg = response.data?.message || 'Gagal menyetujui Food Payment';
                Swal.fire('Error', errorMsg, 'error');
            }
        } else {
            Swal.fire('Error', 'Gagal menyetujui Food Payment', 'error');
        }
    } catch (error) {
        console.error('Error approving Food Payment:', error);
        const errorMsg = error.response?.data?.message || error.message || 'Gagal menyetujui Food Payment';
        Swal.fire('Error', errorMsg, 'error');
    }
}

function showRejectModal() {
    Swal.fire({
        title: 'Tolak Food Payment',
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
        if (result.isConfirmed && selectedPayment.value) {
            // Store the ID before closing modal
            const paymentId = selectedPayment.value.id;
            
            try {
                const response = await axios.post(`/food-payments/${paymentId}/approve`, {
                    approved: false,
                    note: result.value
                });
                
                // Check if response is successful (status 200-299) and has success flag
                if (response.status >= 200 && response.status < 300) {
                    if (response.data && response.data.success) {
                        Swal.fire('Success', response.data.message || 'Food Payment berhasil ditolak', 'success');
                        closeDetailModal();
                        loadPendingApprovals();
                        emit('rejected', paymentId);
                    } else {
                        // Response OK but success is false
                        const errorMsg = response.data?.message || 'Gagal menolak Food Payment';
                        Swal.fire('Error', errorMsg, 'error');
                    }
                } else {
                    Swal.fire('Error', 'Gagal menolak Food Payment', 'error');
                }
            } catch (error) {
                console.error('Error rejecting Food Payment:', error);
                const errorMsg = error.response?.data?.message || error.message || 'Gagal menolak Food Payment';
                Swal.fire('Error', errorMsg, 'error');
            }
        }
    });
}

async function loadAllApprovals() {
    loadingAll.value = true;
    try {
        const response = await axios.get('/api/food-payment/pending-approvals?limit=500');
        if (response.data.success) {
            allApprovals.value = response.data.food_payments || [];
            currentPage.value = 1;
        }
    } catch (error) {
        console.error('Error loading all Food Payment approvals:', error);
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
function toggleAllSelection(fpId) {
    if (selectedAllApprovals.value.has(fpId)) {
        selectedAllApprovals.value.delete(fpId);
    } else {
        selectedAllApprovals.value.add(fpId);
    }
}

function selectAllAllApprovals() {
    paginatedApprovals.value.forEach(fp => {
        selectedAllApprovals.value.add(fp.id);
    });
}

async function approveMultipleAll() {
    if (selectedAllApprovals.value.size === 0) {
        Swal.fire('Warning', 'Pilih minimal satu Food Payment untuk di-approve', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: 'Approve Multiple Food Payments?',
        text: `Apakah Anda yakin ingin approve ${selectedAllApprovals.value.size} Food Payment?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Approve',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981',
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
        
        const fpIds = Array.from(selectedAllApprovals.value);
        const promises = fpIds.map(async (fpId) => {
            const fp = allApprovals.value.find(p => p.id === fpId);
            if (!fp) return { error: new Error('Food Payment not found'), fpId };
            
            try {
                const response = await axios.post(`/food-payments/${fpId}/approve`, {
                    approved: true,
                    note: ''
                });
                
                if (response.status >= 200 && response.status < 300 && response.data && response.data.success) {
                    return { success: true, fpId };
                } else {
                    return { error: new Error(response.data?.message || 'Approval failed'), fpId };
                }
            } catch (err) {
                return { error: err, fpId };
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
            Swal.fire('Success', `${success} Food Payment berhasil disetujui`, 'success');
        } else {
            Swal.fire('Partial Success', `${success} berhasil, ${failed} gagal`, 'warning');
        }
    } catch (error) {
        console.error('Error approving multiple Food Payments:', error);
        Swal.fire('Error', 'Gagal menyetujui Food Payments', 'error');
    }
}

function closeAllModal() {
    showAllModal.value = false;
}

function changePage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}

// Watchers
watch([searchQuery, statusFilter, dateFilter, sortBy], () => {
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

