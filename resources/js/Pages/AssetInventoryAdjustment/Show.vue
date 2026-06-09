<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { formatAssetQty } from '@/utils/formatAssetQty';

const props = defineProps({
    adjustment: Object,
    canApprove: Boolean,
    user: Object,
});

const isSubmitting = ref(false);

function statusBadge(status) {
    const map = {
        waiting_approval: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
        PENDING: 'bg-gray-100 text-gray-600',
        APPROVED: 'bg-green-100 text-green-700',
        REJECTED: 'bg-red-100 text-red-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = {
        waiting_approval: 'Waiting Approval',
        approved: 'Approved',
        rejected: 'Rejected',
    };
    return map[status] || status;
}

function typeBadge(type) {
    return type === 'in' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
}

function typeLabel(type) {
    return type === 'in' ? 'Stock In' : 'Stock Out';
}

async function handleApproval(action) {
    let comments = '';

    if (action === 'reject') {
        const { value } = await Swal.fire({
            title: 'Reject Adjustment',
            input: 'textarea',
            inputLabel: 'Alasan penolakan (wajib)',
            inputPlaceholder: 'Tulis alasan...',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Reject',
            cancelButtonText: 'Batal',
            inputValidator: (val) => !val && 'Alasan wajib diisi!',
        });
        if (!value) return;
        comments = value;
    } else {
        const { isConfirmed, value } = await Swal.fire({
            title: 'Approve Adjustment?',
            input: 'textarea',
            inputLabel: 'Komentar (opsional)',
            inputPlaceholder: 'Tulis komentar...',
            showCancelButton: true,
            confirmButtonColor: '#0d9488',
            confirmButtonText: 'Approve',
            cancelButtonText: 'Batal',
        });
        if (!isConfirmed) return;
        comments = value || '';
    }

    isSubmitting.value = true;
    try {
        await axios.post(`/asset-inventory-adjustments/${props.adjustment.id}/approve`, {
            action, comments,
        });
        Swal.fire('Berhasil', action === 'approve' ? 'Adjustment berhasil disetujui.' : 'Adjustment ditolak.', 'success');
        router.reload();
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Gagal memproses.', 'error');
    }
    isSubmitting.value = false;
}

function deleteAdjustment() {
    Swal.fire({
        title: 'Hapus Adjustment?',
        text: 'Data akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-inventory-adjustments/${props.adjustment.id}`, {
                onSuccess: () => Swal.fire('Terhapus!', 'Adjustment berhasil dihapus.', 'success'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-inventory-adjustments')" class="text-teal-600 hover:text-teal-800">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </button>
                <h1 class="text-2xl font-bold text-teal-700">Detail Asset Stock Adjustment</h1>
            </div>

            <!-- Info Card -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <div class="flex flex-wrap justify-between items-start gap-4 mb-5">
                    <div>
                        <h2 class="text-xl font-bold text-teal-700">{{ adjustment.number }}</h2>
                        <p class="text-sm text-gray-500">{{ adjustment.date }}</p>
                    </div>
                    <div class="flex gap-2">
                        <span :class="typeBadge(adjustment.type)"
                            class="px-3 py-1 rounded-full text-sm font-semibold">{{ typeLabel(adjustment.type) }}</span>
                        <span :class="statusBadge(adjustment.status)"
                            class="px-3 py-1 rounded-full text-sm font-semibold">{{ statusLabel(adjustment.status) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Pemilik:</span>
                        <span class="font-medium">{{ adjustment.owner_outlet_name || '-' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Lokasi Outlet:</span>
                        <span class="font-medium">{{ adjustment.outlet_name }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Warehouse:</span>
                        <span class="font-medium">{{ adjustment.warehouse_outlet_name }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Dibuat oleh:</span>
                        <span class="font-medium">{{ adjustment.creator_name || '-' }}</span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Alasan:</span>
                        <span>{{ adjustment.reason }}</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 mt-5 pt-4 border-t">
                    <button v-if="canApprove" @click="handleApproval('approve')" :disabled="isSubmitting"
                        class="px-5 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold text-sm shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-check mr-1"></i> Approve
                    </button>
                    <button v-if="canApprove" @click="handleApproval('reject')" :disabled="isSubmitting"
                        class="px-5 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold text-sm shadow transition disabled:opacity-50">
                        <i class="fa-solid fa-times mr-1"></i> Reject
                    </button>
                    <button v-if="adjustment.status === 'waiting_approval'" @click="deleteAdjustment"
                        class="px-5 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-semibold text-sm border border-red-200 transition">
                        <i class="fa-solid fa-trash mr-1"></i> Hapus
                    </button>
                </div>
            </div>

            <!-- Approval Flow Timeline -->
            <div v-if="adjustment.approval_flows && adjustment.approval_flows.length" class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-timeline text-teal-500 mr-2"></i>Approval Flow</h2>
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                    <div v-for="flow in adjustment.approval_flows" :key="flow.id" class="relative pl-10 pb-6 last:pb-0">
                        <div class="absolute left-2.5 w-3.5 h-3.5 rounded-full border-2 border-white shadow"
                            :class="{
                                'bg-gray-300': flow.status === 'PENDING',
                                'bg-green-500': flow.status === 'APPROVED',
                                'bg-red-500': flow.status === 'REJECTED',
                            }"></div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-semibold">Level {{ flow.approval_level }}</span>
                                <span class="font-medium text-sm text-gray-800">{{ flow.approver_name }}</span>
                                <span class="text-xs text-gray-400">({{ flow.approver_jabatan }})</span>
                                <span :class="statusBadge(flow.status)"
                                    class="px-2 py-0.5 rounded-full text-xs font-semibold ml-auto">{{ flow.status }}</span>
                            </div>
                            <div v-if="flow.approved_at" class="text-xs text-gray-400">Approved: {{ flow.approved_at }}</div>
                            <div v-if="flow.rejected_at" class="text-xs text-gray-400">Rejected: {{ flow.rejected_at }}</div>
                            <div v-if="flow.comments" class="text-sm text-gray-600 mt-1 italic">"{{ flow.comments }}"</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-boxes-stacked text-teal-500 mr-2"></i>Item Adjustment</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Item</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Unit</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Qty</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in adjustment.items" :key="item.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ item.item_name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.unit }}</td>
                                <td class="px-4 py-2 text-sm text-right tabular-nums">{{ formatAssetQty(item.qty) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ item.note || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
