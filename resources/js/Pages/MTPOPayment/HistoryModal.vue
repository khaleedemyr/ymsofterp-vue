<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { debounce } from 'lodash';
import PaymentDetailModal from './PaymentDetailModal.vue';

const emit = defineEmits(['close']);

const paymentHistory = ref([]);
const loading = ref(false);
const error = ref(null);
const selectedDetailPO = ref(null);

const filters = ref({
    startDate: '',
    endDate: '',
    paymentStatus: '',
    paymentMethod: '',
    search: ''
});

const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0
});

// Debounced search
const debouncedSearch = debounce(() => {
    fetchPaymentHistory();
}, 300);

// Watch filters
watch(filters, () => {
    pagination.value.currentPage = 1;
    debouncedSearch();
}, { deep: true });

async function fetchPaymentHistory() {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/mt-po-payment/history', {
            params: {
                page: pagination.value.currentPage,
                ...filters.value
            }
        });
        
        paymentHistory.value = response.data.data;
        pagination.value = {
            currentPage: response.data.current_page,
            lastPage: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total
        };
    } catch (err) {
        error.value = err.response?.data?.message || 'Error fetching payment history';
    } finally {
        loading.value = false;
    }
}

function prevPage() {
    if (pagination.value.currentPage > 1) {
        pagination.value.currentPage--;
        fetchPaymentHistory();
    }
}

function nextPage() {
    if (pagination.value.currentPage < pagination.value.lastPage) {
        pagination.value.currentPage++;
        fetchPaymentHistory();
    }
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
    }).format(amount);
}

function getStatusClass(status) {
    const classes = {
        'unpaid': 'bg-red-100 text-red-800',
        'partial_paid': 'bg-yellow-100 text-yellow-800',
        'paid': 'bg-green-100 text-green-800'
    };
    return `px-2 py-1 rounded-full text-xs ${classes[status]}`;
}

function viewPaymentDetails(history) {
    selectedDetailPO.value = history;
}

function closeDetailModal() {
    selectedDetailPO.value = null;
}

// Initial fetch
fetchPaymentHistory();
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-6xl w-full max-h-[90vh] overflow-y-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Payment History</h2>
                <button @click="$emit('close')" class="text-gray-500">×</button>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                {{ error }}
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" v-model="filters.startDate" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <input type="date" v-model="filters.endDate" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" v-model="filters.search" 
                           placeholder="PO Number/Supplier/Status/Method"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center h-48">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>

            <!-- History Table -->
            <div v-else class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="history in paymentHistory" :key="history.id">
                            <td class="px-6 py-4 whitespace-nowrap">{{ history.po_number }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(history.created_at) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ history.supplier.name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ formatCurrency(history.total_amount) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-green-700 font-semibold">
                                {{ formatCurrency(history.payments?.reduce((sum, p) => sum + Number(p.payment_amount), 0) || 0) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-red-600 font-semibold">
                                {{ formatCurrency(history.total_amount - (history.payments?.reduce((sum, p) => sum + Number(p.payment_amount), 0) || 0)) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="getStatusClass(history.payment_status)">
                                    {{ history.payment_status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ history.payments[0]?.payment_method }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(history.payments[0]?.payment_date) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <button @click="viewPaymentDetails(history)" class="text-blue-600 hover:text-blue-900" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="!loading" class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
                </div>
                <div class="flex gap-2">
                    <button @click="prevPage" 
                            :disabled="pagination.currentPage === 1" 
                            class="btn-secondary">
                        Previous
                    </button>
                    <button @click="nextPage" 
                            :disabled="pagination.currentPage === pagination.lastPage" 
                            class="btn-secondary">
                        Next
                    </button>
                </div>
            </div>
        </div>
        <PaymentDetailModal v-if="selectedDetailPO" :po="selectedDetailPO" @close="closeDetailModal" />
    </div>
</template> 