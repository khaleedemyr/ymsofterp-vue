<script setup>
import { computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    show: { type: Boolean, default: false },
    module: { type: String, default: 'transfer' },
    data: { type: Object, default: null },
});

const emit = defineEmits(['update:show', 'done']);

const MODULE_META = {
    transfer: {
        title: 'Detail Asset Transfer',
        icon: 'fa-arrows-turn-to-dots',
        iconClass: 'text-cyan-500',
        approvePath: (id) => `/asset-inventory-transfers/${id}/approve`,
    },
    adjustment: {
        title: 'Detail Asset Stock Adjustment',
        icon: 'fa-sliders',
        iconClass: 'text-amber-500',
        approvePath: (id) => `/asset-inventory-adjustments/${id}/approve`,
    },
    service: {
        title: 'Detail Asset Service Order',
        icon: 'fa-wrench',
        iconClass: 'text-purple-500',
        approvePath: (id) => `/asset-service-orders/${id}/approve`,
    },
    disposal: {
        title: 'Detail Asset Disposal',
        icon: 'fa-trash-can',
        iconClass: 'text-rose-500',
        approvePath: (id) => `/asset-disposals/${id}/approve`,
    },
};

const meta = computed(() => MODULE_META[props.module] || MODULE_META.transfer);
const header = computed(() => props.data?.header || null);
const items = computed(() => props.data?.items || []);
const approvalFlows = computed(() => props.data?.approval_flows || []);
const photos = computed(() => props.data?.photos || []);

const docNumber = computed(() => {
    const h = header.value;
    if (!h) return 'N/A';
    return h.transfer_number || h.number || 'N/A';
});

const docDate = computed(() => {
    const h = header.value;
    if (!h) return '-';
    const d = h.transfer_date || h.date;
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID');
});

function close() {
    emit('update:show', false);
}

function itemUnit(item) {
    return item.unit_name || item.unit || '-';
}

async function approve() {
    if (!header.value?.id) return;
    const result = await Swal.fire({
        title: 'Setujui dokumen ini?',
        text: 'Tindakan ini akan meneruskan ke approver berikutnya jika masih ada level approval.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
    });
    if (!result.isConfirmed) return;

    try {
        const response = await axios.post(meta.value.approvePath(header.value.id), {
            action: 'approve',
            comments: '',
        });
        if (response.data?.success) {
            await Swal.fire('Berhasil', response.data.message || 'Dokumen disetujui.', 'success');
            close();
            emit('done', props.module);
        } else {
            Swal.fire('Error', response.data?.message || 'Gagal menyetujui', 'error');
        }
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Gagal menyetujui', 'error');
    }
}

function showReject() {
    if (!header.value?.id) return;
    Swal.fire({
        title: 'Tolak dokumen ini?',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: { 'aria-label': 'Alasan penolakan' },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        inputValidator: (value) => {
            if (!value?.trim()) return 'Alasan penolakan harus diisi!';
        },
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const response = await axios.post(meta.value.approvePath(header.value.id), {
                action: 'reject',
                comments: result.value,
            });
            if (response.data?.success) {
                await Swal.fire('Berhasil', response.data.message || 'Dokumen ditolak.', 'success');
                close();
                emit('done', props.module);
            } else {
                Swal.fire('Error', response.data?.message || 'Gagal menolak', 'error');
            }
        } catch (error) {
            Swal.fire('Error', error.response?.data?.message || 'Gagal menolak', 'error');
        }
    });
}
</script>

<template>
    <div
        v-if="show && header"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click="close"
    >
        <div
            class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto"
            @click.stop
        >
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    <i :class="['fa mr-2', meta.icon, meta.iconClass]"></i>
                    {{ meta.title }}
                </h3>
                <button type="button" @click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Number</div>
                            <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ docNumber }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal</div>
                            <div class="text-gray-900 dark:text-white">{{ docDate }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Dibuat Oleh</div>
                            <div class="text-gray-900 dark:text-white">{{ header.creator_name || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                {{ header.status || 'submitted' }}
                            </span>
                        </div>

                        <template v-if="module === 'transfer'">
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Dari Warehouse</div>
                                <div class="text-gray-900 dark:text-white">{{ header.from_warehouse || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Ke Warehouse</div>
                                <div class="text-gray-900 dark:text-white">{{ header.to_warehouse || '-' }}</div>
                            </div>
                        </template>

                        <template v-if="module === 'adjustment'">
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipe</div>
                                <span
                                    :class="header.type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ header.type === 'in' ? 'Stock In' : 'Stock Out' }}
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet</div>
                                <div class="text-gray-900 dark:text-white">{{ header.outlet_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Warehouse</div>
                                <div class="text-gray-900 dark:text-white">{{ header.warehouse_name || '-' }}</div>
                            </div>
                        </template>

                        <template v-if="module === 'service'">
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet</div>
                                <div class="text-gray-900 dark:text-white">{{ header.outlet_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Warehouse</div>
                                <div class="text-gray-900 dark:text-white">{{ header.warehouse_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Supplier / Vendor</div>
                                <div class="text-gray-900 dark:text-white">{{ header.supplier_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipe Service</div>
                                <div class="text-gray-900 dark:text-white capitalize">{{ header.service_type || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Estimasi Biaya</div>
                                <div class="text-gray-900 dark:text-white">
                                    {{ header.estimated_cost != null ? 'Rp ' + Number(header.estimated_cost).toLocaleString('id-ID') : '-' }}
                                </div>
                            </div>
                        </template>

                        <template v-if="module === 'disposal'">
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipe</div>
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="header.type === 'sold' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'"
                                >
                                    {{ header.type === 'sold' ? 'Dijual' : 'Dibuang' }}
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet</div>
                                <div class="text-gray-900 dark:text-white">{{ header.outlet_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Warehouse</div>
                                <div class="text-gray-900 dark:text-white">{{ header.warehouse_name || '-' }}</div>
                            </div>
                            <div v-if="header.type === 'sold'">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Pembeli</div>
                                <div class="text-gray-900 dark:text-white">{{ header.buyer_name || '-' }}</div>
                            </div>
                        </template>
                    </div>

                    <div v-if="header.notes || header.description || header.reason" class="mt-4">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Catatan / Deskripsi</div>
                        <div class="text-gray-900 dark:text-white mt-1 whitespace-pre-wrap">
                            {{ header.notes || header.description || header.reason }}
                        </div>
                    </div>
                </div>

                <div v-if="items.length > 0" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Items</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                            <thead class="bg-gray-100 dark:bg-gray-600">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Item</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qty</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                <tr v-for="item in items" :key="item.id">
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.item_name || '-' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.qty }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ itemUnit(item) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.note || item.notes || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="module === 'disposal' && photos.length > 0" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Foto</h4>
                    <div class="flex flex-wrap gap-3">
                        <a
                            v-for="(photo, idx) in photos"
                            :key="photo.id || idx"
                            :href="photo.url || ('/storage/' + photo.photo_path)"
                            target="_blank"
                            class="block w-24 h-24 rounded-lg overflow-hidden border border-gray-200 hover:ring-2 ring-rose-400"
                        >
                            <img
                                :src="photo.url || ('/storage/' + photo.photo_path)"
                                class="w-full h-full object-cover"
                                alt="Foto disposal"
                            />
                        </a>
                    </div>
                </div>

                <div v-if="approvalFlows.length > 0" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                        <i class="fa fa-users mr-2 text-blue-500"></i>
                        Approval Flow
                    </h4>
                    <div class="space-y-3">
                        <div
                            v-for="flow in approvalFlows"
                            :key="flow.id"
                            class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border"
                            :class="{
                                'border-green-500': flow.status === 'APPROVED',
                                'border-red-500': flow.status === 'REJECTED',
                                'border-yellow-500': flow.status === 'PENDING',
                            }"
                        >
                            <div class="flex items-center space-x-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Level {{ flow.approval_level }}
                                </span>
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ flow.approver_name }}</div>
                                    <div v-if="flow.approver_jabatan" class="text-xs text-blue-600 font-medium">{{ flow.approver_jabatan }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="text-sm font-medium"
                                    :class="{
                                        'text-green-600': flow.status === 'APPROVED',
                                        'text-red-600': flow.status === 'REJECTED',
                                        'text-yellow-600': flow.status === 'PENDING',
                                    }"
                                >
                                    {{ flow.status }}
                                </div>
                                <div v-if="flow.approved_at" class="text-xs text-gray-500">
                                    {{ new Date(flow.approved_at).toLocaleDateString('id-ID') }}
                                </div>
                                <div v-if="flow.rejected_at" class="text-xs text-gray-500">
                                    {{ new Date(flow.rejected_at).toLocaleDateString('id-ID') }}
                                </div>
                                <div v-if="flow.comments" class="text-xs text-gray-500 mt-1">{{ flow.comments }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    Tutup
                </button>
                <button
                    type="button"
                    @click="approve"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                >
                    <i class="fa fa-check mr-2"></i>Approve
                </button>
                <button
                    type="button"
                    @click="showReject"
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
                >
                    <i class="fa fa-times mr-2"></i>Reject
                </button>
            </div>
        </div>
    </div>
</template>
