<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentModal from './PaymentModal.vue';
import HistoryModal from './HistoryModal.vue';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
    unpaidPOs: Array
});

const showPaymentModal = ref(false);
const showHistoryModal = ref(false);
const selectedPO = ref(null);
const selectedHistoryPO = ref(null);

const filters = ref({
    search: '',
    status: '',
    startDate: '',
    endDate: ''
});

const poList = ref(props.unpaidPOs);

const fetchPOs = async () => {
    const res = await axios.get('/mt-po-payment', { params: filters.value });
    poList.value = res.data.unpaidPOs;
};

const debouncedFetch = debounce(fetchPOs, 400);

function openPaymentModal(po) {
    selectedPO.value = po;
    showPaymentModal.value = true;
}

function closePaymentModal() {
    showPaymentModal.value = false;
    selectedPO.value = null;
}

function openHistoryModal(po = null) {
    if (po) {
        selectedHistoryPO.value = po;
    }
    showHistoryModal.value = true;
}

function closeHistoryModal() {
    showHistoryModal.value = false;
    selectedHistoryPO.value = null;
}

function handlePaymentSuccess() {
    closePaymentModal();
    fetchPOs();
}

function formatDate(date) {
    if (!date) return '-';
    const d = new Date(date);
    return isNaN(d) ? '-' : d.toLocaleDateString('id-ID');
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

onMounted(() => {
    // Optionally, set default date range here
});
</script>

<template>
    <Head title="MT PO Payment" />

    <AppLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    MT PO Payment
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-lg sm:rounded-2xl">
                    <div class="p-6 text-gray-900">
                        <!-- Filter Bar -->
                        <div class="flex flex-wrap gap-3 mb-6 items-end bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl px-4 py-4 shadow-md">
                            <input v-model="filters.search" @input="debouncedFetch" type="text" placeholder="Cari PO/Supplier..." class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition w-56 bg-white shadow-sm" />
                            <select v-model="filters.status" @change="fetchPOs" class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition bg-white shadow-sm">
                                <option value="">Semua Status</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="partial_paid">Partial Paid</option>
                            </select>
                            <input v-model="filters.startDate" @change="fetchPOs" type="date" class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition bg-white shadow-sm" />
                            <span class="text-gray-400">-</span>
                            <input v-model="filters.endDate" @change="fetchPOs" type="date" class="rounded-lg border border-blue-200 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition bg-white shadow-sm" />
                        </div>
                        <!-- Global History Button -->
                        <div class="flex justify-end mb-4">
                            <button @click="openHistoryModal()" class="btn-secondary">
                                <i class="fas fa-history mr-2"></i>Payment History
                            </button>
                        </div>
                        <!-- List PO -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-xl shadow-md overflow-hidden">
                                <thead class="bg-blue-100">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PO NUMBER</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">DATE</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">SUPPLIER</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">TOTAL AMOUNT</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PAID / REMAINING</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">PAYMENT STATUS</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="po in poList" :key="po.id" class="hover:bg-blue-50 transition-all duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap font-semibold text-blue-900">{{ po.po_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(po.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ po.supplier.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatCurrency(po.total_amount) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-green-700 font-semibold">
                                                {{ formatCurrency(po.payments?.reduce((sum, p) => sum + Number(p.payment_amount), 0) || 0) }} <span class="text-xs">paid</span>
                                            </div>
                                            <div class="text-red-600 font-semibold">
                                                {{ formatCurrency(po.total_amount - (po.payments?.reduce((sum, p) => sum + Number(p.payment_amount), 0) || 0)) }} <span class="text-xs">remaining</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getStatusClass(po.payment_status)">
                                                <i v-if="po.payment_status==='unpaid'" class="fas fa-times-circle mr-1 text-red-500"></i>
                                                <i v-else-if="po.payment_status==='partial_paid'" class="fas fa-hourglass-half mr-1 text-yellow-500"></i>
                                                <i v-else class="fas fa-check-circle mr-1 text-green-500"></i>
                                                {{ po.payment_status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap flex gap-2 items-center">
                                            <button @click="openPaymentModal(po)" class="btn-primary transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-money-bill-wave mr-1"></i> Make Payment
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="poList.length === 0">
                                        <td colspan="7" class="text-center text-gray-400 py-8">Tidak ada data PO sesuai filter</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <PaymentModal 
            v-if="showPaymentModal"
            :po="selectedPO"
            @close="closePaymentModal"
            @payment-success="handlePaymentSuccess"
        />

        <!-- History Modal -->
        <HistoryModal 
            v-if="showHistoryModal"
            :po="selectedHistoryPO"
            @close="closeHistoryModal"
        />
    </AppLayout>
</template> 