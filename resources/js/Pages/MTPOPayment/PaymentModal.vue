<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    po: Object
});

const emit = defineEmits(['close', 'payment-success']);

const totalPaid = computed(() => {
    return props.po.payments?.reduce((sum, p) => sum + Number(p.payment_amount), 0) || 0;
});
const remaining = computed(() => {
    return props.po.total_amount - totalPaid.value;
});

const payment = ref({
    payment_date: new Date().toISOString().split('T')[0],
    payment_amount: props.po.payment_status === 'partial_paid' ? remaining.value : props.po.total_amount,
    payment_type: 'full',
    payment_method: 'transfer',
    payment_reference: '',
    payment_proof: null,
    notes: ''
});

// Jika status partial_paid, payment_amount otomatis update jika sisa berubah
watch(() => props.po.payment_status, (val) => {
    if (val === 'partial_paid') {
        payment.value.payment_amount = remaining.value;
    } else {
        payment.value.payment_amount = props.po.total_amount;
    }
});

const loading = ref(false);
const error = ref(null);
const showLightbox = ref(false);
const lightboxImg = ref('');

const paymentMethods = [
    { value: 'transfer', label: 'Bank Transfer' },
    { value: 'cash', label: 'Cash' },
    { value: 'cheque', label: 'Cheque' }
];

const maxPaymentAmount = computed(() => {
    return props.po.payment_status === 'partial_paid' ? remaining.value : props.po.total_amount;
});

function openLightbox(img) {
    lightboxImg.value = img;
    showLightbox.value = true;
}
function closeLightbox() {
    showLightbox.value = false;
    lightboxImg.value = '';
}

async function submitPayment() {
    if (!payment.value.payment_proof) {
        error.value = 'Please upload payment proof';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('po_id', props.po.id);
        formData.append('payment_date', payment.value.payment_date);
        formData.append('payment_amount', payment.value.payment_amount);
        formData.append('payment_type', payment.value.payment_type);
        formData.append('payment_method', payment.value.payment_method);
        formData.append('payment_reference', payment.value.payment_reference);
        formData.append('payment_proof', payment.value.payment_proof);
        formData.append('notes', payment.value.notes);

        await axios.post('/mt-po-payment/store', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        if (window.Swal) {
            await window.Swal.fire({
                icon: 'success',
                title: 'Pembayaran berhasil!',
                text: 'Data pembayaran PO berhasil disimpan.',
                timer: 1800,
                showConfirmButton: false
            });
        }
        emit('payment-success');
    } catch (err) {
        error.value = err.response?.data?.message || 'Error processing payment';
    } finally {
        loading.value = false;
    }
}

function handleFileUpload(event) {
    payment.value.payment_proof = event.target.files[0];
}

function isImage(filePath) {
    return /\.(jpg|jpeg|png|gif|webp)$/i.test(filePath);
}

function getInvoiceUrl(path) {
    if (!path) return '';
    if (path.startsWith('storage/')) return '/' + path;
    return '/storage/' + path.replace(/^\/?/, '');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR'
    }).format(amount);
}
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-blue-100 relative">
            <!-- Lightbox -->
            <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80">
                <div class="absolute top-4 right-8">
                    <button @click="closeLightbox" class="text-white text-3xl font-bold">&times;</button>
                </div>
                <img :src="lightboxImg" class="max-h-[80vh] max-w-[90vw] rounded shadow-lg border-4 border-white" />
            </div>
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-blue-800">Make Payment</h2>
                <button @click="$emit('close')" class="text-gray-400 hover:text-blue-600 text-2xl">×</button>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                {{ error }}
            </div>

            <!-- PO Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100 shadow-sm">
                    <h3 class="font-semibold mb-2 text-blue-700">PO Information</h3>
                    <p><span class="font-bold">PO Number:</span> {{ po.po_number }}</p>
                    <p><span class="font-bold">Date:</span> {{ new Date(po.created_at).toLocaleDateString('id-ID') }}</p>
                    <p><span class="font-bold">Total Amount:</span> {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(po.total_amount) }}</p>
                </div>
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100 shadow-sm">
                    <h3 class="font-semibold mb-2 text-blue-700">Supplier Information</h3>
                    <p><span class="font-bold">Name:</span> {{ po.supplier.name }}</p>
                    <p><span class="font-bold">Contact:</span> {{ po.supplier.contact_person }}</p>
                    <p><span class="font-bold">Phone:</span> {{ po.supplier.phone }}</p>
                    <p><span class="font-bold">Email:</span> {{ po.supplier.email }}</p>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="mb-6 bg-blue-50 rounded-lg p-4 border border-blue-100 shadow-sm">
                <h3 class="font-semibold mb-2 text-blue-700">Bank Details</h3>
                <p><span class="font-bold">Bank:</span> {{ po.supplier.bank_name }}</p>
                <p><span class="font-bold">Account Number:</span> {{ po.supplier.bank_account_number }}</p>
                <p><span class="font-bold">Account Name:</span> {{ po.supplier.bank_account_name }}</p>
            </div>

            <!-- Payment History for partial_paid -->
            <div v-if="po.payment_status === 'partial_paid' && po.payments && po.payments.length" class="mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-semibold mb-2 text-yellow-700">Previous Payments</h3>
                    <ul class="text-sm">
                        <li v-for="pay in po.payments" :key="pay.id" class="mb-1">
                            <span class="font-semibold">{{ formatCurrency(pay.payment_amount) }}</span>
                            &nbsp;on&nbsp;
                            <span>{{ new Date(pay.payment_date).toLocaleDateString('id-ID') }}</span>
                        </li>
                    </ul>
                    <div class="mt-2 text-blue-900 font-semibold">
                        Remaining to pay: <span class="text-red-600">{{ formatCurrency(remaining) }}</span>
                    </div>
                </div>
            </div>

            <!-- Invoice Preview -->
            <div class="mb-6" v-if="po.invoices && po.invoices.length > 0">
                <h3 class="font-semibold mb-2 text-blue-700">Invoice</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div v-for="invoice in po.invoices" :key="invoice.id" class="rounded-lg border border-blue-100 bg-white shadow-sm p-2 flex flex-col items-center">
                        <template v-if="isImage(invoice.invoice_file_path)">
                            <img :src="getInvoiceUrl(invoice.invoice_file_path)" class="w-full h-32 object-cover rounded cursor-pointer hover:scale-105 transition" @click="openLightbox(getInvoiceUrl(invoice.invoice_file_path))" alt="Invoice" />
                            <div class="text-xs mt-2 text-blue-700 font-semibold">{{ invoice.invoice_number }}</div>
                        </template>
                        <template v-else>
                            <a :href="getInvoiceUrl(invoice.invoice_file_path)" target="_blank" class="flex flex-col items-center text-blue-700 hover:underline">
                                <i class="fas fa-file-alt text-3xl mb-2"></i>
                                <span class="text-xs font-semibold">{{ invoice.invoice_number }}</span>
                            </a>
                        </template>
                        <div class="text-xs text-gray-400 mt-1">{{ invoice.invoice_date ? new Date(invoice.invoice_date).toLocaleDateString('id-ID') : '' }}</div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <form @submit.prevent="submitPayment" class="mt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Type</label>
                        <select v-model="payment.payment_type" class="mt-1 block w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="full">Full Payment</option>
                            <option value="partial">Partial Payment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                        <select v-model="payment.payment_method" class="mt-1 block w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option v-for="method in paymentMethods" :key="method.value" :value="method.value">
                                {{ method.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Amount</label>
                    <input type="number" v-model="payment.payment_amount" :max="maxPaymentAmount" class="mt-1 block w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Reference</label>
                    <input type="text" v-model="payment.payment_reference" class="mt-1 block w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Payment Proof</label>
                    <input type="file" @change="handleFileUpload" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea v-model="payment.notes" class="mt-1 block w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-2 mt-2">
                    <button type="button" @click="$emit('close')" :disabled="loading"
                        class="px-4 py-2 rounded font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition disabled:opacity-60">
                        Cancel
                    </button>
                    <button type="submit" :disabled="loading"
                        class="px-4 py-2 rounded font-semibold bg-blue-600 hover:bg-blue-700 text-white transition disabled:opacity-60">
                        <span v-if="loading">Processing...</span>
                        <span v-else>Submit Payment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template> 