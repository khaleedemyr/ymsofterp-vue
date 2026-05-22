<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({ transfer: Object, canApprove: Boolean, user: Object });
const busy = ref(false);

async function submitApproval() {
    const { value: approvers } = await Swal.fire({
        title: 'Submit untuk approval',
        html: '<p class="text-sm text-gray-600">Masukkan ID approver dipisah koma, atau gunakan form create untuk draft lengkap.</p>',
        input: 'text',
        inputPlaceholder: '1,2,3',
        showCancelButton: true,
    });
    if (!approvers) return;
    const ids = approvers.split(',').map(s => parseInt(s.trim(), 10)).filter(Boolean);
    if (!ids.length) return Swal.fire('Error', 'Approver tidak valid', 'error');
    busy.value = true;
    try {
        await axios.post(`/asset-owner-transfers/${props.transfer.id}/submit`, { approvers: ids });
        Swal.fire('OK', 'Submitted', 'success');
        router.reload();
    } catch (e) { Swal.fire('Error', e.response?.data?.message || e.message, 'error'); }
    finally { busy.value = false; }
}

async function handleApproval(action) {
    let comments = '';
    if (action === 'reject') {
        const { value } = await Swal.fire({ title: 'Reject', input: 'textarea', inputValidator: v => !v && 'Wajib' });
        if (!value) return;
        comments = value;
    } else {
        const { isConfirmed, value } = await Swal.fire({ title: 'Approve?', showCancelButton: true });
        if (!isConfirmed) return;
        comments = value || '';
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
                            <td class="py-2">{{ it.qty }}</td>
                            <td class="py-2">{{ it.note || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="transfer.status === 'draft'" class="flex gap-2">
                <button @click="submitApproval" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm">Submit</button>
            </div>
            <div v-if="canApprove && transfer.status === 'submitted'" class="flex gap-2">
                <button @click="handleApproval('approve')" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm">Approve</button>
                <button @click="handleApproval('reject')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Reject</button>
            </div>
        </div>
    </AppLayout>
</template>
