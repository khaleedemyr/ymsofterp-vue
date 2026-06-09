<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import KpiFormFieldLabel from './KpiFormFieldLabel.vue';

const props = defineProps({
  show: Boolean,
  mode: String,
  row: Object,
  options: Object,
});

const emit = defineEmits(['close', 'success']);

const showErpMapping = computed(() => ['erp', 'hybrid'].includes(form.source_type));

const fieldHints = {
  code: {
    hint: 'ID unik parameter. Dipakai di formula KPI, misalnya: P001 / P002 * 100. Gunakan huruf + angka atau snake_case, tanpa spasi.',
    example: 'P001, P010, fb_actual_revenue',
  },
  name: {
    hint: 'Nama lengkap parameter yang mudah dibaca oleh HR/asesor. Ini yang tampil di form penilaian nanti.',
    example: 'MTD Actual F&B Revenue',
  },
  source_type: {
    hint: 'ERP = diambil otomatis dari modul ERP. Manual = asesor isi sendiri. Hybrid = default ERP, asesor boleh koreksi dengan alasan.',
    example: 'Revenue → ERP, INC Program → Manual',
  },
  scope_type: {
    hint: 'Scope filter saat ambil data: Outlet = per outlet karyawan (revenue, waste). Employee = per karyawan (training). Division = per divisi.',
    example: 'Revenue → Outlet, Training % → Employee',
  },
  data_type: {
    hint: 'Tipe nilai parameter. Decimal = angka/uang, Percent = persen, Hours = jam, Integer = jumlah/count, Text = teks bebas.',
    example: 'Revenue → Decimal, Training → Percent',
  },
  status: {
    hint: 'Aktif = parameter bisa dipilih di template KPI. Nonaktif = disembunyikan dari pilihan baru (data lama tetap aman).',
    example: 'Aktif untuk parameter yang masih dipakai',
  },
  is_shared: {
    hint: 'Centang jika 1 parameter dipakai di banyak KPI. Contoh: Actual Revenue (P001) dipakai di Revenue Achievement, COGS Ratio, dan Waste Ratio — cukup definisi sekali.',
    example: 'P001 Actual Revenue → shared',
  },
  description: {
    hint: 'Catatan internal opsional: dari modul mana datanya, rumus sumber, atau penjelasan untuk tim HR.',
    example: 'Diambil dari Daily Revenue Forecast, filter outlet + bulan',
  },
  resolver_key: {
    hint: 'Pilih modul/sumber data ERP yang paling sesuai. Sistem akan fetch nilai dari modul ini saat penilaian KPI (fase 2).',
    example: 'Actual revenue MTD → Daily Revenue Forecast',
  },
  aggregation: {
    hint: 'Cara menggabungkan data jika hasil query ERP banyak baris: sum = total, avg = rata-rata, count = jumlah baris.',
    example: 'Revenue → sum, Skor QA → avg',
  },
};

const form = useForm({
  code: '',
  name: '',
  source_type: 'manual',
  scope_type: 'outlet',
  data_type: 'decimal',
  description: '',
  is_shared: true,
  status: 'A',
  erp_mapping: {
    resolver_key: '',
    aggregation: 'sum',
    static_filters: null,
    dynamic_filter_bindings: null,
  },
});

watch(
  () => props.show,
  (val) => {
    if (!val) return;
    if (props.mode === 'edit' && props.row) {
      form.code = props.row.code;
      form.name = props.row.name;
      form.source_type = props.row.source_type;
      form.scope_type = props.row.scope_type;
      form.data_type = props.row.data_type;
      form.description = props.row.description || '';
      form.is_shared = !!props.row.is_shared;
      form.status = props.row.status;
      const mapping = props.row.erp_mapping || {};
      form.erp_mapping = {
        resolver_key: mapping.resolver_key || '',
        aggregation: mapping.aggregation || 'sum',
        static_filters: mapping.static_filters || null,
        dynamic_filter_bindings: mapping.dynamic_filter_bindings || null,
      };
    } else {
      form.reset();
      form.source_type = 'manual';
      form.scope_type = 'outlet';
      form.data_type = 'decimal';
      form.is_shared = true;
      form.status = 'A';
      form.erp_mapping = { resolver_key: '', aggregation: 'sum', static_filters: null, dynamic_filter_bindings: null };
    }
  },
);

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  const opts = {
    onSuccess: () => { emit('success'); emit('close'); },
    onFinish: () => { isSubmitting.value = false; },
  };
  if (props.mode === 'create') {
    form.post(route('kpi-parameters.store'), opts);
  } else if (props.row) {
    form.put(route('kpi-parameters.update', props.row.id), opts);
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-8">
      <div class="px-6 pt-6 pb-2 border-b">
        <h3 class="text-xl font-bold">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Parameter KPI</h3>
        <p class="text-sm text-gray-500 mt-1">
          Arahkan kursor ke ikon
          <i class="fa-solid fa-circle-question text-indigo-400 text-xs mx-0.5"></i>
          di setiap field untuk penjelasan cara mengisi.
        </p>
      </div>
      <div class="px-6 py-4 max-h-[70vh] overflow-y-auto space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <KpiFormFieldLabel label="Kode" required :hint="fieldHints.code.hint" :example="fieldHints.code.example" />
            <input v-model="form.code" class="mt-1 w-full rounded-lg border-gray-300" placeholder="P001" />
            <div v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</div>
          </div>
          <div>
            <KpiFormFieldLabel label="Nama" required :hint="fieldHints.name.hint" :example="fieldHints.name.example" />
            <input v-model="form.name" class="mt-1 w-full rounded-lg border-gray-300" placeholder="MTD Actual F&B Revenue" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
          </div>
          <div>
            <KpiFormFieldLabel label="Source" required :hint="fieldHints.source_type.hint" :example="fieldHints.source_type.example" />
            <select v-model="form.source_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.source_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <KpiFormFieldLabel label="Scope" required :hint="fieldHints.scope_type.hint" :example="fieldHints.scope_type.example" />
            <select v-model="form.scope_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.scope_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <KpiFormFieldLabel label="Data Type" required :hint="fieldHints.data_type.hint" :example="fieldHints.data_type.example" />
            <select v-model="form.data_type" class="mt-1 w-full rounded-lg border-gray-300">
              <option v-for="opt in options.data_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <KpiFormFieldLabel label="Status" required :hint="fieldHints.status.hint" :example="fieldHints.status.example" />
            <select v-model="form.status" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="A">Aktif</option>
              <option value="N">Nonaktif</option>
            </select>
          </div>
        </div>
        <div>
          <KpiFormFieldLabel label="Parameter shared" :hint="fieldHints.is_shared.hint" :example="fieldHints.is_shared.example" />
          <label class="inline-flex items-center gap-2 text-sm mt-1">
            <input v-model="form.is_shared" type="checkbox" class="rounded border-gray-300" />
            Ya, parameter ini bisa dipakai banyak KPI
          </label>
        </div>
        <div>
          <KpiFormFieldLabel label="Deskripsi" :hint="fieldHints.description.hint" :example="fieldHints.description.example" />
          <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Catatan internal (opsional)" />
        </div>
        <div v-if="showErpMapping" class="border rounded-xl p-4 bg-indigo-50/50 space-y-3">
          <KpiFormFieldLabel
            label="ERP Mapping"
            hint="Konfigurasi ini menghubungkan parameter ke modul ERP. Muncul hanya jika Source = ERP atau Hybrid."
          />
          <div>
            <KpiFormFieldLabel label="Resolver Key" required :hint="fieldHints.resolver_key.hint" :example="fieldHints.resolver_key.example" />
            <select v-model="form.erp_mapping.resolver_key" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="">-- Pilih sumber data ERP --</option>
              <option v-for="opt in options.resolver_keys" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <div v-if="form.errors['erp_mapping.resolver_key']" class="text-xs text-red-500 mt-1">{{ form.errors['erp_mapping.resolver_key'] }}</div>
          </div>
          <div>
            <KpiFormFieldLabel label="Aggregation" :hint="fieldHints.aggregation.hint" :example="fieldHints.aggregation.example" />
            <input v-model="form.erp_mapping.aggregation" class="mt-1 w-full rounded-lg border-gray-300" placeholder="sum, avg, count" />
          </div>
          <p class="text-xs text-indigo-700 bg-indigo-100/60 rounded-lg px-3 py-2">
            <i class="fa-solid fa-lightbulb mr-1"></i>
            Filter outlet &amp; bulan otomatis saat penilaian KPI — tidak perlu diisi manual di sini (fase 2).
          </p>
        </div>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 rounded-b-2xl">
        <button type="button" class="px-4 py-2 rounded-lg border" @click="$emit('close')">Batal</button>
        <button type="button" :disabled="isSubmitting" class="px-4 py-2 rounded-lg bg-indigo-600 text-white disabled:opacity-50" @click="submit">
          {{ isSubmitting ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </div>
    </div>
  </div>
</template>
