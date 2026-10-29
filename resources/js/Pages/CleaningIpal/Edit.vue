<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import WatermarkCameraCapture from '@/Components/WatermarkCameraCapture.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    log: { type: Object, required: true },
    canChooseOutlet: { type: Boolean, default: false },
});

const cleaningDate = ref(props.log.cleaning_date);
const keterangan = ref(props.log.keterangan || '');
const saving = ref(false);

const photosBefore = ref(
    (props.log.photos_before || []).map((path, idx) => ({
        key: `before-old-${idx}`,
        file: null,
        previewUrl: `/storage/${path}`,
        isNew: false,
        path,
        metaLabel: path.split('/').pop(),
    }))
);

const photosAfter = ref(
    (props.log.photos_after || []).map((path, idx) => ({
        key: `after-old-${idx}`,
        file: null,
        previewUrl: `/storage/${path}`,
        isNew: false,
        path,
        metaLabel: path.split('/').pop(),
    }))
);

function submit() {
    if (!photosBefore.value.length) {
        Swal.fire('Perhatian', 'Minimal 1 foto before wajib ada.', 'warning');
        return;
    }

    saving.value = true;
    const fd = new FormData();
    fd.append('_method', 'put');
    fd.append('cleaning_date', cleaningDate.value);
    fd.append('keterangan', keterangan.value || '');

    photosBefore.value.forEach((item) => {
        if (item.isNew && item.file) {
            fd.append('photos_before[]', item.file);
        } else if (item.path) {
            fd.append('keep_before[]', item.path);
        }
    });

    photosAfter.value.forEach((item) => {
        if (item.isNew && item.file) {
            fd.append('photos_after[]', item.file);
        } else if (item.path) {
            fd.append('keep_after[]', item.path);
        }
    });

    router.post(`/cleaning-ipal/${props.log.id}`, fd, {
        forceFormData: true,
        onFinish: () => { saving.value = false; },
        onError: (errors) => {
            const msg = errors.photos_before || errors.message || 'Gagal menyimpan perubahan.';
            Swal.fire('Gagal', msg, 'error');
        },
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
                {{ (log.photos_after || []).length ? 'Edit Cleaning IPAL' : 'Lengkapi Foto After' }}
            </h1>
            <p class="text-sm text-gray-500 mb-6">Capture foto tambahan (before/after bisa banyak). Upload file tidak diizinkan.</p>

            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 mb-6">
                <div class="text-lg font-bold text-gray-900">{{ log.outlet_name }}</div>
                <div class="font-mono text-emerald-800 mt-0.5">{{ log.outlet_qr_code || '-' }}</div>
            </div>

            <div class="bg-white rounded-xl shadow p-5 space-y-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                    <input
                        v-model="cleaningDate"
                        type="date"
                        required
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Keterangan</label>
                    <textarea
                        v-model="keterangan"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 text-sm focus:ring-emerald-500 focus:border-emerald-500"
                    />
                </div>

                <WatermarkCameraCapture
                    v-model="photosBefore"
                    title="Foto Before"
                    subtitle="Minimal 1 foto. Capture tambahan atau hapus yang tidak perlu."
                />

                <WatermarkCameraCapture
                    v-model="photosAfter"
                    title="Foto After"
                    subtitle="Capture foto after (bisa lebih dari satu)."
                />

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        :disabled="saving"
                        class="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white px-6 py-2.5 rounded-lg font-semibold text-sm"
                        @click="submit"
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
            </div>
        </div>
    </AppLayout>
</template>
