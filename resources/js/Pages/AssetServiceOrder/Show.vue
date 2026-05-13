<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    serviceOrder: Object,
    linkedNonFoodPayment: Object,
    canCreateNonFoodPayment: Boolean,
    canApprove: Boolean,
    canReceiveReturn: Boolean,
    user: Object,
});

const so = props.serviceOrder;

const isExternalService = computed(() => (props.serviceOrder?.service_type ?? 'external') === 'external');

function statusBadge(status) {
    const map = {
        waiting_approval: 'bg-yellow-100 text-yellow-700',
        in_service: 'bg-blue-100 text-blue-700',
        partially_returned: 'bg-orange-100 text-orange-700',
        returned: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = {
        waiting_approval: 'Waiting Approval',
        in_service: 'In Service',
        partially_returned: 'Partially Returned',
        returned: 'Returned',
        rejected: 'Rejected',
    };
    return map[status] || status;
}

function flowDot(status) {
    const map = {
        APPROVED: 'bg-green-500',
        REJECTED: 'bg-red-500',
        PENDING: 'bg-gray-300',
    };
    return map[status] || 'bg-gray-300';
}

function flowBadge(status) {
    const map = {
        APPROVED: 'bg-green-100 text-green-700',
        REJECTED: 'bg-red-100 text-red-700',
        PENDING: 'bg-gray-100 text-gray-500',
    };
    return map[status] || 'bg-gray-100 text-gray-500';
}

function formatCurrency(val) {
    if (!val && val !== 0) return '-';
    return Number(val).toLocaleString('id-ID');
}

function progressPercent(item) {
    if (!item.qty_out || item.qty_out <= 0) return 0;
    return Math.min(100, Math.round((item.qty_returned / item.qty_out) * 100));
}

// ── Approve / Reject ──
function doApprove() {
    Swal.fire({
        title: 'Approve Service Order?',
        input: 'textarea',
        inputLabel: 'Komentar (opsional)',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Approve',
        cancelButtonText: 'Batal',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const { data } = await axios.post(`/asset-service-orders/${so.id}/approve`, {
                    action: 'approve', comments: result.value || null,
                });
                Swal.fire('Berhasil', data.message, 'success').then(() => router.reload());
            } catch (err) {
                Swal.fire('Error', err.response?.data?.message || 'Gagal approve.', 'error');
            }
        }
    });
}

function doReject() {
    Swal.fire({
        title: 'Reject Service Order?',
        input: 'textarea',
        inputLabel: 'Alasan penolakan (wajib)',
        inputValidator: (v) => !v ? 'Alasan wajib diisi' : null,
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Reject',
        cancelButtonText: 'Batal',
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const { data } = await axios.post(`/asset-service-orders/${so.id}/approve`, {
                    action: 'reject', comments: result.value,
                });
                Swal.fire('Ditolak', data.message, 'info').then(() => router.reload());
            } catch (err) {
                Swal.fire('Error', err.response?.data?.message || 'Gagal reject.', 'error');
            }
        }
    });
}

// ── Receive Return ──
const showReturnForm = ref(false);
const returnItems = ref([]);
const actualCost = ref('');
const submittingReturn = ref(false);

function openReturnForm() {
    returnItems.value = so.items
        .filter(i => i.qty_returned < i.qty_out)
        .map(i => ({
            item_id: i.item_id,
            item_name: i.item_name,
            unit: i.unit,
            remaining: parseFloat((i.qty_out - i.qty_returned).toFixed(4)),
            qty_returned: '',
            return_note: '',
        }));
    actualCost.value = so.actual_cost || '';
    showReturnForm.value = true;
}

async function submitReturn() {
    const items = returnItems.value
        .filter(i => i.qty_returned && Number(i.qty_returned) > 0)
        .map(i => ({
            item_id: i.item_id,
            qty_returned: Number(i.qty_returned),
            return_note: i.return_note || null,
        }));
    if (!items.length) {
        Swal.fire('Error', 'Isi minimal 1 item yang dikembalikan.', 'error');
        return;
    }
    for (const i of items) {
        const src = returnItems.value.find(r => r.item_id === i.item_id);
        if (i.qty_returned > src.remaining) {
            Swal.fire('Error', `Qty return ${src.item_name} melebihi sisa.`, 'error');
            return;
        }
    }
    submittingReturn.value = true;
    try {
        const { data } = await axios.post(`/asset-service-orders/${so.id}/receive-return`, {
            items,
            actual_cost: actualCost.value ? Number(actualCost.value) : null,
        });
        Swal.fire('Berhasil', data.message, 'success').then(() => router.reload());
    } catch (err) {
        Swal.fire('Error', err.response?.data?.message || 'Gagal menyimpan.', 'error');
    } finally {
        submittingReturn.value = false;
    }
}

async function uploadVendorInvoice(ev) {
    const file = ev.target?.files?.[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('invoice', file);
    try {
        await axios.post(`/asset-service-orders/${so.id}/vendor-invoice`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        Swal.fire('Berhasil', 'Invoice vendor berhasil diunggah.', 'success').then(() => router.reload());
    } catch (err) {
        Swal.fire('Gagal', err.response?.data?.message || 'Upload gagal.', 'error');
    }
    ev.target.value = '';
}

function goNonFoodPaymentCreate() {
    router.visit(`/non-food-payments/create-from-asset-service/${so.id}`);
}

function goNonFoodPaymentShow() {
    if (!props.linkedNonFoodPayment?.id) return;
    router.visit(`/non-food-payments/${props.linkedNonFoodPayment.id}`);
}

function deleteOrder() {
    Swal.fire({
        title: 'Hapus Service Order?',
        text: 'Data akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-service-orders/${so.id}`, {
                onSuccess: () => Swal.fire('Terhapus!', 'Berhasil dihapus.', 'success'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-service-orders')"
                    class="text-gray-500 hover:text-teal-700 transition"><i class="fa-solid fa-arrow-left text-lg"></i></button>
                <h1 class="text-2xl font-bold text-teal-700">Detail Service Order</h1>
            </div>

            <!-- Header Info -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Nomor</div>
                        <div class="font-bold text-teal-700">{{ so.number }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Tanggal</div>
                        <div class="font-medium">{{ so.date }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Tipe</div>
                        <span
                            :class="(so.service_type ?? 'external') === 'internal' ? 'bg-slate-100 text-slate-800' : 'bg-violet-100 text-violet-800'"
                            class="px-2.5 py-1 rounded-full text-xs font-semibold"
                        >
                            {{ (so.service_type ?? 'external') === 'internal' ? 'Internal' : 'External' }}
                        </span>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Status</div>
                        <span :class="statusBadge(so.status)" class="px-2.5 py-1 rounded-full text-xs font-semibold">{{ statusLabel(so.status) }}</span>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Supplier</div>
                        <div class="font-medium">{{ so.supplier_name || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Outlet</div>
                        <div>{{ so.outlet_name }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Warehouse</div>
                        <div>{{ so.warehouse_outlet_name }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Dibuat Oleh</div>
                        <div>{{ so.creator_name }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Deskripsi</div>
                        <div>{{ so.description || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Estimasi Biaya</div>
                        <div class="font-medium">Rp {{ formatCurrency(so.estimated_cost) }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 text-xs mb-1">Biaya Aktual</div>
                        <div class="font-medium">Rp {{ formatCurrency(so.actual_cost) }}</div>
                    </div>
                    <div v-if="so.sent_date">
                        <div class="text-gray-400 text-xs mb-1">Tanggal Kirim</div>
                        <div>{{ so.sent_date }}</div>
                    </div>
                    <div v-if="so.return_date">
                        <div class="text-gray-400 text-xs mb-1">Tanggal Kembali</div>
                        <div>{{ so.return_date }}</div>
                    </div>
                </div>
            </div>

            <!-- Invoice vendor & Non Food Payment (hanya External) -->
            <div v-if="isExternalService" class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-teal-700 mb-4">Invoice &amp; pembayaran vendor</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="text-sm font-medium text-gray-700 mb-2">Upload invoice vendor (PDF / JPG / PNG)</div>
                        <p v-if="so.vendor_invoice_path" class="text-sm mb-2">
                            <a :href="`/storage/${so.vendor_invoice_path}`" target="_blank" class="text-teal-600 hover:underline font-medium">
                                <i class="fa fa-file-pdf mr-1"></i> Lihat file terunggah
                            </a>
                        </p>
                        <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-teal-50 border border-teal-200 text-teal-800 text-sm font-medium cursor-pointer hover:bg-teal-100 transition">
                            <i class="fa fa-upload"></i>
                            Pilih file
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" @change="uploadVendorInvoice" />
                        </label>
                        <p class="text-xs text-gray-500 mt-2">Maks. 15 MB. Ganti file akan menimpa yang lama.</p>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-700 mb-2">Non Food Payment</div>
                        <p v-if="linkedNonFoodPayment" class="text-sm text-gray-700 mb-2">
                            Terhubung:
                            <button type="button" class="text-teal-700 font-semibold hover:underline" @click="goNonFoodPaymentShow">
                                {{ linkedNonFoodPayment.payment_number }}
                            </button>
                            <span class="text-gray-500">({{ linkedNonFoodPayment.status }})</span>
                        </p>
                        <p v-else class="text-sm text-gray-600 mb-2">Belum ada pembayaran tercatat untuk service order ini.</p>
                        <button
                            v-if="canCreateNonFoodPayment"
                            type="button"
                            @click="goNonFoodPaymentCreate"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow transition"
                        >
                            <i class="fa-solid fa-credit-card"></i>
                            Buat Non Food Payment
                        </button>
                        <p v-if="!canCreateNonFoodPayment && !linkedNonFoodPayment && isExternalService" class="text-xs text-amber-700 mt-2">
                            Tombol aktif setelah approval (in service / return) dan migrasi SQL Non Food Payment terpasang.
                        </p>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white rounded-xl shadow p-6 mb-6 border border-slate-100">
                <h2 class="text-lg font-bold text-slate-700 mb-2">Service internal</h2>
                <p class="text-sm text-gray-600">
                    Non Food Payment dan upload invoice vendor hanya untuk service <strong>External</strong> (vendor luar). Order ini bersifat internal.
                </p>
            </div>

            <!-- Approval Flow -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-teal-700 mb-4">Approval Flow</h2>
                <div class="space-y-4">
                    <div v-for="flow in so.approval_flows" :key="flow.id" class="flex items-start gap-3">
                        <div class="flex flex-col items-center">
                            <div :class="flowDot(flow.status)" class="w-4 h-4 rounded-full mt-1"></div>
                            <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                        </div>
                        <div class="flex-1 pb-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="bg-teal-100 text-teal-700 px-2 py-0.5 rounded text-xs font-bold">Level {{ flow.approval_level }}</span>
                                <span :class="flowBadge(flow.status)" class="px-2 py-0.5 rounded text-xs font-semibold">{{ flow.status }}</span>
                            </div>
                            <div class="font-medium text-sm">{{ flow.approver_name }}</div>
                            <div class="text-xs text-gray-400">{{ flow.approver_jabatan }}</div>
                            <div v-if="flow.approved_at" class="text-xs text-green-600 mt-1">Approved: {{ flow.approved_at }}</div>
                            <div v-if="flow.rejected_at" class="text-xs text-red-600 mt-1">Rejected: {{ flow.rejected_at }}</div>
                            <div v-if="flow.comments" class="text-xs text-gray-500 mt-1 italic">"{{ flow.comments }}"</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-teal-700 mb-4">Item Service</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-teal-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700">#</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700">Item</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700">Unit</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-teal-700">Qty Out</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-teal-700">Qty Returned</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700 w-40">Progress</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700">Catatan</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-teal-700">Catatan Return</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in so.items" :key="item.id">
                                <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ item.item_name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.unit }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ item.qty_out }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-700">{{ item.qty_returned }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-teal-500 h-2.5 rounded-full transition-all" :style="{ width: progressPercent(item) + '%' }"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 whitespace-nowrap">{{ progressPercent(item) }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ item.note || '-' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ item.return_note || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3 mb-6">
                <template v-if="canApprove">
                    <button @click="doApprove"
                        class="px-5 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold text-sm shadow transition">
                        <i class="fa-solid fa-check mr-1"></i> Approve
                    </button>
                    <button @click="doReject"
                        class="px-5 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold text-sm shadow transition">
                        <i class="fa-solid fa-xmark mr-1"></i> Reject
                    </button>
                </template>
                <button v-if="canReceiveReturn && !showReturnForm" @click="openReturnForm"
                    class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm shadow transition">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Terima Kembali
                </button>
                <button v-if="so.status === 'waiting_approval'" @click="deleteOrder"
                    class="px-5 py-2.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 font-semibold text-sm transition">
                    <i class="fa-solid fa-trash mr-1"></i> Hapus
                </button>
            </div>

            <!-- Receive Return Form -->
            <div v-if="showReturnForm" class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-blue-700 mb-4"><i class="fa-solid fa-rotate-left mr-2"></i>Terima Kembali dari Service</h2>
                <div class="space-y-3 mb-4">
                    <div v-for="item in returnItems" :key="item.item_id"
                        class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end p-3 bg-gray-50 rounded-lg">
                        <div class="md:col-span-2">
                            <div class="text-sm font-medium text-gray-800">{{ item.item_name }}</div>
                            <div class="text-xs text-gray-400">{{ item.unit }} &middot; Sisa: {{ item.remaining }}</div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Qty Return</label>
                            <input v-model.number="item.qty_returned" type="number" step="0.01" min="0" :max="item.remaining"
                                class="w-full rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Catatan Return</label>
                            <input v-model="item.return_note" type="text" placeholder="Catatan..."
                                class="w-full rounded border-gray-300 text-xs focus:ring-blue-500 focus:border-blue-500" />
                        </div>
                    </div>
                </div>
                <div class="max-w-xs mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Biaya Aktual</label>
                    <input v-model="actualCost" type="number" step="0.01" min="0" placeholder="0"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div class="flex gap-3">
                    <button @click="submitReturn" :disabled="submittingReturn"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-save mr-1"></i> Simpan Return
                    </button>
                    <button @click="showReturnForm = false"
                        class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium">Batal</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
