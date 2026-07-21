<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import debounce from 'lodash/debounce';
import { formatAssetQty } from '@/utils/formatAssetQty';

const props = defineProps({ transfer: Object, canApprove: Boolean, user: Object });
const busy = ref(false);
const hasSavedApprovers = computed(() =>
    props.transfer?.status === 'draft' && (props.transfer?.approval_flows?.length ?? 0) > 0
);
const showSubmitModal = ref(false);
const approverSearch = ref('');
const approverResults = ref([]);
const selectedApprovers = ref([]);

const searchApprovers = debounce(async () => {
    const { data } = await axios.get('/asset-owner-transfer/approvers', { params: { search: approverSearch.value } });
    approverResults.value = data.users || [];
}, 350);
watch(approverSearch, searchApprovers);

function approverMeta(u) {
    const parts = [u.jabatan, u.outlet].filter(Boolean);
    return parts.length ? parts.join(' · ') : '-';
}

function toggleApprover(u) {
    const i = selectedApprovers.value.findIndex(a => a.id === u.id);
    if (i >= 0) selectedApprovers.value.splice(i, 1);
    else selectedApprovers.value.push(u);
}

function isApproverSelected(id) {
    return selectedApprovers.value.some(a => a.id === id);
}

function removeApprover(idx) {
    selectedApprovers.value.splice(idx, 1);
}

function moveApprover(idx, dir) {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= selectedApprovers.value.length) return;
    const tmp = selectedApprovers.value[idx];
    selectedApprovers.value[idx] = selectedApprovers.value[newIdx];
    selectedApprovers.value[newIdx] = tmp;
    selectedApprovers.value = [...selectedApprovers.value];
}

function openSubmitModal() {
    selectedApprovers.value = [];
    approverSearch.value = '';
    approverResults.value = [];
    showSubmitModal.value = true;
    searchApprovers();
}

async function handleSubmitClick() {
    if (hasSavedApprovers.value) {
        const list = props.transfer.approval_flows
            .map(f => `<div class="text-sm py-0.5"><span class="font-semibold text-violet-700">Level ${f.approval_level}:</span> ${f.approver_name} (${f.approver_jabatan})</div>`)
            .join('');
        const { isConfirmed } = await Swal.fire({
            title: 'Submit untuk Approval?',
            html: `<p class="text-sm text-gray-600 mb-2">Approver sudah dipilih saat create:</p>${list}`,
            showCancelButton: true,
            confirmButtonText: 'Submit',
            confirmButtonColor: '#7c3aed',
        });
        if (!isConfirmed) return;
        busy.value = true;
        try {
            await axios.post(`/asset-owner-transfers/${props.transfer.id}/submit`, {});
            Swal.fire('OK', 'Transfer berhasil di-submit untuk approval.', 'success');
            router.reload();
        } catch (e) {
            Swal.fire('Error', e.response?.data?.message || e.message, 'error');
        } finally {
            busy.value = false;
        }
        return;
    }
    openSubmitModal();
}

function closeSubmitModal() {
    showSubmitModal.value = false;
}

async function submitApproval() {
    if (!selectedApprovers.value.length) {
        return Swal.fire('Error', 'Pilih minimal 1 approver.', 'error');
    }
    const ids = selectedApprovers.value.map(a => a.id);
    busy.value = true;
    try {
        await axios.post(`/asset-owner-transfers/${props.transfer.id}/submit`, { approvers: ids });
        closeSubmitModal();
        Swal.fire('OK', 'Transfer berhasil di-submit untuk approval.', 'success');
        router.reload();
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || e.message, 'error');
    } finally {
        busy.value = false;
    }
}

async function handleApproval(action) {
    let comments = '';
    if (action === 'reject') {
        const { value } = await Swal.fire({ title: 'Reject', input: 'textarea', inputValidator: v => !v && 'Wajib' });
        if (!value) return;
        comments = String(value);
    } else {
        const { isConfirmed } = await Swal.fire({ title: 'Approve?', showCancelButton: true });
        if (!isConfirmed) return;
        comments = '';
    }
    busy.value = true;
    try {
        await axios.post(`/asset-owner-transfers/${props.transfer.id}/approve`, { action, comments });
        Swal.fire('OK', '', 'success');
        router.reload();
    } catch (e) { Swal.fire('Error', e.response?.data?.message || e.message, 'error'); }
    finally { busy.value = false; }
}
</script>

<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8">
            <button @click="router.visit('/asset-owner-transfers')" class="text-violet-600 mb-4"><i class="fa fa-arrow-left"></i> Kembali</button>
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <h1 class="text-xl font-bold text-violet-700 mb-2">{{ transfer.transfer_number }}</h1>
                <p class="text-sm text-gray-500">{{ transfer.transfer_date }} · {{ transfer.status }}</p>
                <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                    <div><span class="text-gray-500">Pemilik asal:</span> {{ transfer.owner_from_name }}</div>
                    <div><span class="text-gray-500">Pemilik tujuan:</span> {{ transfer.owner_to_name }}</div>
                    <div><span class="text-gray-500">Lokasi:</span> {{ transfer.location_outlet_name }}</div>
                    <div><span class="text-gray-500">Gudang:</span> {{ transfer.warehouse_outlet_name }}</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-2">Item</th><th>Qty</th><th>Catatan</th></tr></thead>
                    <tbody>
                        <tr v-for="it in transfer.items" :key="it.id" class="border-t">
                            <td class="py-2">{{ it.item_name }}</td>
                            <td class="py-2 tabular-nums">{{ formatAssetQty(it.qty) }}<span v-if="it.unit_name" class="text-gray-500 ml-1">{{ it.unit_name }}</span></td>
                            <td class="py-2">{{ it.note || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transfer.approval_flows?.length" class="bg-white rounded-xl shadow p-6 mb-6">
                <h2 class="font-semibold text-gray-800 mb-3">Approval Flow</h2>
                <div class="space-y-2">
                    <div v-for="flow in transfer.approval_flows" :key="flow.approval_level"
                        class="flex items-center justify-between p-3 rounded-lg border text-sm"
                        :class="{
                            'bg-green-50 border-green-200': flow.status === 'APPROVED',
                            'bg-red-50 border-red-200': flow.status === 'REJECTED',
                            'bg-yellow-50 border-yellow-200': flow.status === 'PENDING',
                        }">
                        <div>
                            <span class="font-medium">Level {{ flow.approval_level }}:</span>
                            {{ flow.approver_name }} ({{ flow.approver_jabatan }})
                        </div>
                        <span class="text-xs font-semibold uppercase">{{ flow.status }}</span>
                    </div>
                </div>
            </div>

            <div v-if="transfer.status === 'draft'" class="flex gap-2">
                <button @click="handleSubmitClick" :disabled="busy" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm disabled:opacity-50">
                    <i v-if="busy" class="fa fa-spinner fa-spin mr-1"></i>
                    Submit
                </button>
            </div>
            <div v-if="canApprove && transfer.status === 'submitted'" class="flex gap-2">
                <button @click="handleApproval('approve')" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Approve</button>
                <button @click="handleApproval('reject')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Reject</button>
            </div>
        </div>

        <!-- Modal pilih approver -->
        <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-bold text-violet-700 mb-1">Submit untuk Approval</h3>
                <p class="text-sm text-gray-500 mb-4">Pilih approver berurutan (level 1 = pertama).</p>

                <div v-if="selectedApprovers.length" class="space-y-2 mb-4">
                    <div v-for="(a, idx) in selectedApprovers" :key="a.id"
                        class="flex items-center justify-between gap-2 p-3 bg-violet-50 border border-violet-200 rounded-lg">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="bg-violet-600 text-white rounded-full w-6 h-6 flex-shrink-0 flex items-center justify-center text-xs font-bold">{{ idx + 1 }}</span>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-violet-900 truncate">{{ a.name }}</div>
                                <div class="text-xs text-violet-600 truncate">{{ approverMeta(a) }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" @click="moveApprover(idx, -1)" :disabled="idx === 0"
                                class="text-violet-400 hover:text-violet-700 disabled:opacity-30 p-1">
                                <i class="fa-solid fa-chevron-up text-xs"></i>
                            </button>
                            <button type="button" @click="moveApprover(idx, 1)" :disabled="idx === selectedApprovers.length - 1"
                                class="text-violet-400 hover:text-violet-700 disabled:opacity-30 p-1">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </button>
                            <button type="button" @click="removeApprover(idx)" class="text-red-500 hover:text-red-700 p-1 ml-1">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <input v-model="approverSearch" type="text" placeholder="Cari approver (nama / jabatan / outlet)..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-violet-500 focus:border-violet-500 mb-2" />

                <div v-if="approverResults.length" class="border rounded-lg divide-y max-h-48 overflow-auto mb-4">
                    <div v-for="u in approverResults" :key="u.id" @click="toggleApprover(u)"
                        :class="isApproverSelected(u.id) ? 'bg-violet-50' : 'hover:bg-gray-50'"
                        class="px-4 py-2.5 flex items-center justify-between cursor-pointer">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-800">{{ u.name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ approverMeta(u) }}</div>
                        </div>
                        <i v-if="isApproverSelected(u.id)" class="fa-solid fa-check text-violet-600 flex-shrink-0 ml-2"></i>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t">
                    <button @click="closeSubmitModal" class="px-4 py-2 border rounded-lg text-sm text-gray-700">Batal</button>
                    <button @click="submitApproval" :disabled="busy || !selectedApprovers.length"
                        class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
                        <i v-if="busy" class="fa fa-spinner fa-spin mr-1"></i>
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
