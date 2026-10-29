<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    log: { type: Object, required: true },
    checkTimes: { type: Array, default: () => ['10:00', '16:00', '22:00'] },
    canChooseOutlet: { type: Boolean, default: false },
});

const form = useForm({
    check_date: props.log.check_date,
    check_time: props.log.check_time,
    temp_display: props.log.temp_display,
    temp_actual: props.log.temp_actual,
    notes: props.log.notes || '',
});

function submit() {
    form.put(`/chiller-temperature/${props.log.id}`);
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/chiller-temperature" class="text-cyan-700 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <h1 class="text-2xl font-bold text-cyan-800 mb-2">Edit Kontrol Suhu</h1>
            <p class="text-sm text-gray-500 mb-6">Ubah jam dan nilai suhu Display / Actual.</p>

            <div class="bg-cyan-50 border border-cyan-200 rounded-xl p-5 mb-6">
                <div class="text-lg font-bold text-gray-900">{{ log.item_name }}</div>
                <div class="font-mono text-cyan-800 mt-0.5">{{ log.serial_number }}</div>
                <div class="text-sm text-gray-600 mt-2">
                    Outlet: <span class="font-medium">{{ log.outlet_name || '-' }}</span>
                </div>
                <div class="text-sm text-gray-600">
                    Warehouse: <span class="font-medium">{{ log.warehouse_name || '-' }}</span>
                </div>
            </div>

            <form class="bg-white rounded-xl shadow p-5 space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input
                            v-model="form.check_date"
                            type="date"
                            required
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Jam Pengecekan</label>
                        <select
                            v-model="form.check_time"
                            required
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        >
                            <option v-for="t in checkTimes" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <p v-if="form.errors.check_time" class="text-xs text-red-600 mt-1">{{ form.errors.check_time }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Suhu Display (°C)</label>
                        <input
                            v-model="form.temp_display"
                            type="number"
                            step="0.1"
                            required
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Suhu Actual (°C)</label>
                        <input
                            v-model="form.temp_actual"
                            type="number"
                            step="0.1"
                            required
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Catatan</label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            class="w-full rounded-lg border-gray-300 text-sm focus:ring-cyan-500 focus:border-cyan-500"
                        />
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm"
                    >
                        Simpan Perubahan
                    </button>
                    <Link
                        :href="`/chiller-temperature?date=${log.check_date}`"
                        class="px-6 py-2.5 rounded-lg border text-sm text-gray-600 hover:bg-gray-50"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
