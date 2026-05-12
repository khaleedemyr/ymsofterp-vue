<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    transfer: Object,
    canApprove: Boolean,
    user: Object,
});

const isSubmitting = ref(false);

function statusBadge(status) {
    const map = {
        draft: 'bg-gray-100 text-gray-700',
        submitted: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
        PENDING: 'bg-gray-100 text-gray-600',
        APPROVED: 'bg-green-100 text-green-700',
        REJECTED: 'bg-red-100 text-red-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

async function submitForApproval() {
    const { value: formValues } = await Swal.fire({
        title: 'Submit untuk Approval',
        text: 'Transfer akan di-submit. Pastikan approver sudah ditentukan saat pembuatan.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Submit',
        cancelButtonText: 'Batal',
    });
    if (!formValues) return;

    isSubmitting.value = true;
    try {
        await axios.post(`/asset-inventory-transfers/${props.transfer.id}/submit`, {
            approvers: props.transfer.approval_flows.map(f => f.approver_id || f.id),
        });
        Swal.fire('Berhasil', 'Transfer berhasil di-submit.', 'success');
        router.reload();
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Gagal submit.', 'error');
    }
    isSubmitting.value = false;
}

async function handleApproval(action) {
    let comments = '';

    if (action === 'reject') {
        const { value } = await Swal.fire({
            title: 'Reject Transfer',
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
            title: 'Approve Transfer?',
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
        await axios.post(`/asset-inventory-transfers/${props.transfer.id}/approve`, {
            action, comments,
        });
        Swal.fire('Berhasil', action === 'approve' ? 'Transfer berhasil disetujui.' : 'Transfer ditolak.', 'success');
        router.reload();
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Gagal memproses.', 'error');
    }
    isSubmitting.value = false;
}

function deleteTransfer() {
    Swal.fire({
        title: 'Hapus Transfer?',
        text: 'Data akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-inventory-transfers/${props.transfer.id}`, {
                onSuccess: () => Swal.fire('Terhapus!', 'Transfer berhasil dihapus.', 'success'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center gap-3 mb-6">
                <button @click="router.visit('/asset-inventory-transfers')" class="text-teal-600 hover:text-teal-800">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </button>
                <h1 class="text-2xl font-bold text-teal-700">Detail Transfer Asset</h1>
            </div>

            <!-- Info Card -->
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <div class="flex flex-wrap justify-between items-start gap-4 mb-5">
                    <div>
                        <h2 class="text-xl font-bold text-teal-700">{{ transfer.transfer_number }}</h2>
                        <p class="text-sm text-gray-500">{{ transfer.transfer_date }}</p>
                    </div>
                    <span :class="statusBadge(transfer.status)"
                        class="px-3 py-1 rounded-full text-sm font-semibold capitalize">{{ transfer.status }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <span class="text-gray-500 w-28">Dari Outlet:</span>
                            <span class="font-medium">{{ transfer.outlet_from_name }}</span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-500 w-28">Warehouse:</span>
                            <span class="font-medium">{{ transfer.warehouse_outlet_from_name }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <span class="text-gray-500 w-28">Ke Outlet:</span>
                            <span class="font-medium">{{ transfer.outlet_to_name }}</span>
                        </div>
                        <div class="flex gap-2">
                            <span class="text-gray-500 w-28">Warehouse:</span>
                            <span class="font-medium">{{ transfer.warehouse_outlet_to_name }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-gray-500 w-28">Dibuat oleh:</span>
                        <span class="font-medium">{{ transfer.creator_name || '-' }}</span>
                    </div>
                    <div v-if="transfer.notes" class="flex gap-2">
                        <span class="text-gray-500 w-28">Catatan:</span>
                        <span>{{ transfer.notes }}</span>
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
                    <button v-if="transfer.status === 'draft'" @click="deleteTransfer"
                        class="px-5 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-semibold text-sm border border-red-200 transition">
                        <i class="fa-solid fa-trash mr-1"></i> Hapus
                    </button>
                </div>
            </div>

            <!-- Approval Flow Timeline -->
            <div v-if="transfer.approval_flows && transfer.approval_flows.length" class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-timeline text-teal-500 mr-2"></i>Approval Flow</h2>
                <div class="relative">
                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                    <div v-for="flow in transfer.approval_flows" :key="flow.id" class="relative pl-10 pb-6 last:pb-0">
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
                <h2 class="text-lg font-semibold text-gray-800 mb-4"><i class="fa-solid fa-boxes-stacked text-teal-500 mr-2"></i>Item Transfer</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Item</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Unit</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Qty</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Qty Small</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Qty Medium</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Qty Large</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in transfer.items" :key="item.id" class="hover:bg-gray-50/50">
                                <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-800">{{ item.item_name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ item.unit_name }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ item.qty }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-500">{{ item.qty_small }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-500">{{ item.qty_medium }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-500">{{ item.qty_large }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ item.note || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
