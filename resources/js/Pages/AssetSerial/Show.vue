<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    serial: Object,
    movements: Array,
    canDelete: { type: Boolean, default: false },
});

function statusBadge(status) {
    const map = {
        available: 'bg-green-100 text-green-700',
        in_transfer: 'bg-blue-100 text-blue-700',
        in_service: 'bg-purple-100 text-purple-700',
        disposed: 'bg-red-100 text-red-700',
        lost: 'bg-orange-100 text-orange-700',
        replaced: 'bg-gray-100 text-gray-500',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = {
        available: 'Available',
        in_transfer: 'In Transfer',
        in_service: 'In Service',
        disposed: 'Disposed',
        lost: 'Lost',
        replaced: 'Replaced',
    };
    return map[status] || status;
}

function movementLabel(type) {
    const map = { tagged: 'Terdaftar / Di-tag' };
    return map[type] || type;
}

function deleteSerial() {
    Swal.fire({
        title: 'Hapus Nomor Seri?',
        html: `Nomor seri <b>${props.serial.serial_number}</b> akan dihapus permanen.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(`/asset-serials/${props.serial.id}`, {
                onSuccess: () => Swal.fire('Terhapus', 'Nomor seri berhasil dihapus.', 'success'),
                onError: (errors) => Swal.fire('Gagal', errors.message || 'Tidak dapat menghapus.', 'error'),
            });
        }
    });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/asset-serials" class="text-teal-600 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <div class="bg-white rounded-xl shadow p-6 mb-6">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-teal-700 font-mono">{{ serial.serial_number }}</h1>
                        <p class="text-gray-600 mt-1">{{ serial.item_name }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="statusBadge(serial.status)">
                            {{ statusLabel(serial.status) }}
                        </span>
                        <button
                            v-if="canDelete"
                            type="button"
                            @click="deleteSerial"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 text-sm font-medium transition"
                        >
                            <i class="fa-solid fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><span class="text-gray-500">Pemilik:</span> <span class="font-medium">{{ serial.owner_outlet_name }}</span></div>
                    <div><span class="text-gray-500">Lokasi:</span> <span class="font-medium">{{ serial.location_outlet_name || '-' }}</span></div>
                    <div><span class="text-gray-500">Warehouse:</span> <span class="font-medium">{{ serial.warehouse_name || '-' }}</span></div>
                    <div><span class="text-gray-500">UID Tag RFID:</span> <span class="font-mono text-xs">{{ serial.tag_uid || '-' }}</span></div>
                    <div><span class="text-gray-500">Unit Level:</span> <span class="font-medium uppercase">{{ serial.unit_level }}</span></div>
                    <div><span class="text-gray-500">Sumber:</span> <span class="font-medium">{{ serial.source_type || '-' }}</span></div>
                    <div><span class="text-gray-500">Di-tag oleh:</span> <span class="font-medium">{{ serial.tagged_by_name || '-' }}</span></div>
                    <div><span class="text-gray-500">Di-tag pada:</span> <span class="font-medium">{{ serial.tagged_at ? new Date(serial.tagged_at).toLocaleString('id-ID') : '-' }}</span></div>
                    <div v-if="serial.notes" class="sm:col-span-2"><span class="text-gray-500">Catatan:</span> {{ serial.notes }}</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Riwayat Pergerakan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Tanggal</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Tipe</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Referensi</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Oleh</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-if="!movements?.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada riwayat.</td>
                            </tr>
                            <tr v-for="m in movements" :key="m.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600">{{ m.created_at ? new Date(m.created_at).toLocaleString('id-ID') : '-' }}</td>
                                <td class="px-4 py-3">{{ movementLabel(m.movement_type) }}</td>
                                <td class="px-4 py-3 text-xs">{{ m.reference_type || '-' }}</td>
                                <td class="px-4 py-3">{{ m.moved_by_name || '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ m.notes || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
