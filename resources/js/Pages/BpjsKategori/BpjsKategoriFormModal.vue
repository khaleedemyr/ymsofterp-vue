<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String,
  row: Object,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  nama_kategori: '',
  pct_kes_perusahaan: 0,
  pct_kes_karyawan: 0,
  pct_jht_perusahaan: 0,
  pct_jp_perusahaan: 0,
  pct_jkk_perusahaan: 0,
  pct_jkm_perusahaan: 0,
  pct_jht_karyawan: 0,
  pct_jp_karyawan: 0,
});

watch(
  () => props.show,
  (val) => {
    if (!val) return;
    if (props.mode === 'edit' && props.row) {
      form.nama_kategori = props.row.nama_kategori;
      form.pct_kes_perusahaan = Number(props.row.pct_kes_perusahaan);
      form.pct_kes_karyawan = Number(props.row.pct_kes_karyawan);
      form.pct_jht_perusahaan = Number(props.row.pct_jht_perusahaan);
      form.pct_jp_perusahaan = Number(props.row.pct_jp_perusahaan);
      form.pct_jkk_perusahaan = Number(props.row.pct_jkk_perusahaan);
      form.pct_jkm_perusahaan = Number(props.row.pct_jkm_perusahaan);
      form.pct_jht_karyawan = Number(props.row.pct_jht_karyawan);
      form.pct_jp_karyawan = Number(props.row.pct_jp_karyawan);
    } else if (props.mode === 'create') {
      form.reset();
      form.nama_kategori = '';
      form.pct_kes_perusahaan = 0;
      form.pct_kes_karyawan = 0;
      form.pct_jht_perusahaan = 0;
      form.pct_jp_perusahaan = 0;
      form.pct_jkk_perusahaan = 0;
      form.pct_jkm_perusahaan = 0;
      form.pct_jht_karyawan = 0;
      form.pct_jp_karyawan = 0;
    }
  },
);

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('bpjs-kategori.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Kategori BPJS ditambahkan.', 'success');
        emit('success');
        emit('close');
      },
      onError: () => {},
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  } else if (props.mode === 'edit' && props.row) {
    form.put(route('bpjs-kategori.update', props.row.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Kategori BPJS diperbarui.', 'success');
        emit('success');
        emit('close');
      },
      onError: () => {},
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  }
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto"
  >
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-8">
      <div class="px-6 pt-6 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold text-gray-900">
          {{ mode === 'edit' ? 'Edit' : 'Tambah' }} kategori BPJS
        </h3>
        <p class="text-sm text-gray-500 mt-1">Isi persentase iuran (%). Gunakan titik desimal jika perlu (mis. 0,54).</p>
      </div>
      <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
        <div class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama kategori</label>
            <input
              v-model="form.nama_kategori"
              maxlength="150"
              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-emerald-500 focus:border-emerald-500"
              required
            />
            <div v-if="form.errors.nama_kategori" class="text-xs text-red-500 mt-1">{{ form.errors.nama_kategori }}</div>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-emerald-800 mb-2">Perusahaan</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600">BPJS Kesehatan (%)</label>
                <input v-model.number="form.pct_kes_perusahaan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_kes_perusahaan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_kes_perusahaan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JHT (%)</label>
                <input v-model.number="form.pct_jht_perusahaan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jht_perusahaan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jht_perusahaan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JP (%)</label>
                <input v-model.number="form.pct_jp_perusahaan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jp_perusahaan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jp_perusahaan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JKK (%)</label>
                <input v-model.number="form.pct_jkk_perusahaan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jkk_perusahaan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jkk_perusahaan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JKM (%)</label>
                <input v-model.number="form.pct_jkm_perusahaan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jkm_perusahaan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jkm_perusahaan }}</div>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-teal-800 mb-2">Karyawan</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600">BPJS Kesehatan (%)</label>
                <input v-model.number="form.pct_kes_karyawan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_kes_karyawan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_kes_karyawan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JHT (%)</label>
                <input v-model.number="form.pct_jht_karyawan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jht_karyawan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jht_karyawan }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600">JP (%)</label>
                <input v-model.number="form.pct_jp_karyawan" type="number" step="0.0001" min="0" max="100" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" />
                <div v-if="form.errors.pct_jp_karyawan" class="text-xs text-red-500 mt-1">{{ form.errors.pct_jp_karyawan }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 rounded-b-2xl">
        <button
          type="button"
          class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
          @click="closeModal"
        >
          Batal
        </button>
        <button
          type="button"
          :disabled="isSubmitting"
          class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-medium hover:bg-emerald-700 disabled:opacity-50"
          @click="submit"
        >
          {{ isSubmitting ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>
