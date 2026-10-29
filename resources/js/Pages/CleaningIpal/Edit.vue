<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, onBeforeUnmount } from 'vue';

const props = defineProps({
    log: { type: Object, required: true },
    canChooseOutlet: { type: Boolean, default: false },
});

const beforePreview = ref(props.log.photo_before ? `/storage/${props.log.photo_before}` : '');
const afterPreview = ref(props.log.photo_after ? `/storage/${props.log.photo_after}` : '');
const newBeforeObjectUrl = ref('');
const newAfterObjectUrl = ref('');

const form = useForm({
    cleaning_date: props.log.cleaning_date,
    keterangan: props.log.keterangan || '',
    photo_before: null,
    photo_after: null,
});

function onBeforeChange(e) {
    const file = e.target.files?.[0] || null;
    form.photo_before = file;
    if (newBeforeObjectUrl.value) URL.revokeObjectURL(newBeforeObjectUrl.value);
    if (file) {
        newBeforeObjectUrl.value = URL.createObjectURL(file);
        beforePreview.value = newBeforeObjectUrl.value;
    }
}

function onAfterChange(e) {
    const file = e.target.files?.[0] || null;
    form.photo_after = file;
    if (newAfterObjectUrl.value) URL.revokeObjectURL(newAfterObjectUrl.value);
    if (file) {
        newAfterObjectUrl.value = URL.createObjectURL(file);
        afterPreview.value = newAfterObjectUrl.value;
    }
}

onBeforeUnmount(() => {
    if (newBeforeObjectUrl.value) URL.revokeObjectURL(newBeforeObjectUrl.value);
    if (newAfterObjectUrl.value) URL.revokeObjectURL(newAfterObjectUrl.value);
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(`/cleaning-ipal/${props.log.id}`, {
            forceFormData: true,
        });
}
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <Link href="/cleaning-ipal" class="text-emerald-700 hover:underline text-sm">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar
                </Link>
            </div>

            <h1 class="text-2xl font-bold text-emerald-800 mb-2">
                {{ log.photo_after ? 'Edit Cleaning IPAL' : 'Lengkapi Foto After' }}
            </h1>
            <p class="text-sm text-gray-500 mb-6">Update keterangan dan/atau unggah foto after.</p>

            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 mb-6">
                <div class="text-lg font-bold text-gray-900">{{ log.outlet_name }}</div>
                <div class="font-mono text-emerald-800 mt-0.5">{{ log.outlet_qr_code || '-' }}</div>
            </div>

            <form class="bg-white rounded-xl shadow p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                    <input
                        v-model="form.cleaning_date"
                        type="date"
                        required
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Keterangan</label>
                    <textarea
                        v-model="form.keterangan"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Foto Before</label>
                        <img v-if="beforePreview" :src="beforePreview" alt="Before" class="w-full h-40 object-cover rounded-lg border mb-2" />
                        <input type="file" accept="image/*" capture="environment" class="w-full text-sm" @change="onBeforeChange" />
                        <p class="text-xs text-gray-400 mt-1">Kosongkan jika tidak diganti.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Foto After
                            <span v-if="!log.photo_after" class="text-amber-600">(belum diisi)</span>
                        </label>
                        <img v-if="afterPreview" :src="afterPreview" alt="After" class="w-full h-40 object-cover rounded-lg border mb-2" />
                        <div
                            v-else
                            class="w-full h-40 rounded-lg border border-dashed border-amber-300 bg-amber-50 flex items-center justify-center text-amber-700 text-sm mb-2"
                        >
                            Belum ada foto after
                        </div>
                        <input type="file" accept="image/*" capture="environment" class="w-full text-sm" @change="onAfterChange" />
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm"
                    >
                        Simpan Perubahan
                    </button>
                    <Link
                        :href="`/cleaning-ipal?date=${log.cleaning_date}`"
                        class="px-6 py-2.5 rounded-lg border text-sm text-gray-600 hover:bg-gray-50"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
