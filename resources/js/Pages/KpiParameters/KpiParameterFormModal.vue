<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import KpiFormFieldLabel from './KpiFormFieldLabel.vue';

const props = defineProps({
  show: Boolean,
  mode: String,
  row: Object,
  options: Object,
});

const emit = defineEmits(['close', 'success']);

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
  manual_input_hint: {
    hint: 'Petunjuk untuk asesor saat mengisi kolom Manual/Override di evaluasi KPI. Jika diisi, teks ini yang tampil di tooltip (menggantikan petunjuk otomatis).',
    example: 'Isi persentase COGS (%) dari menu Manual COGS outlet & bulan data. Contoh: 42,5 — tanpa simbol %.',
  },
  resolver_key: {
    hint: 'Wajib untuk source ERP. Opsional untuk Hybrid — kosongkan jika nilai diisi manual saat penilaian.',
    example: 'Actual revenue MTD → Daily Revenue Forecast',
  },
  aggregation: {
    hint: 'Cara menggabungkan data jika hasil query ERP banyak baris: sum = total, avg = rata-rata, count = jumlah baris.',
    example: 'Revenue → sum, Skor QA → avg',
  },
  target_value: {
    hint: 'Target KPI saat penilaian. Dipakai otomatis saat parameter dipilih di template KPI.',
    example: '100%, <=24 hours, <=5%',
  },
  target_direction: {
    hint: 'Higher is better = nilai lebih tinggi lebih baik. Lower is better = nilai lebih rendah lebih baik (COGS, waste, complaint).',
    example: 'Revenue → Higher, Waste ratio → Lower',
  },
  frequency: {
    hint: 'Seberapa sering KPI ini dinilai.',
    example: 'Monthly untuk kebanyakan KPI outlet',
  },
  formula: {
    hint: 'Rumus perhitungan KPI. Gunakan kode parameter lain jika perlu, mis. P001 / P002 * 100.',
    example: 'P001 / P002 * 100',
  },
};

const emptyErpMapping = () => ({
  resolver_key: '',
  aggregation: 'sum',
  static_filters: null,
  dynamic_filter_bindings: null,
});

const createDefaults = () => ({
  code: '',
  name: '',
  source_type: 'manual',
  scope_type: 'outlet',
  data_type: 'decimal',
  description: '',
  manual_input_hint: '',
  target_value: '',
  target_direction: 'higher_better',
  frequency: 'monthly',
  formula: '',
  is_shared: true,
  status: 'A',
  erp_mapping: emptyErpMapping(),
});

const form = useForm(createDefaults());

const showErpMapping = computed(() => ['erp', 'hybrid'].includes(form.source_type));
const resolverRequired = computed(() => form.source_type === 'erp');

const resolverOptions = computed(() => {
  const base = props.options?.resolver_keys || [];
  const current = form.erp_mapping?.resolver_key;
  if (!current || base.some((o) => o.value === current)) {
    return base;
  }
  return [{ value: current, label: `${current} (tersimpan)` }, ...base];
});

function rowErpMapping(row) {
  return row?.erp_mapping || row?.erpMapping || {};
}

function populateForm() {
  form.clearErrors();

  if (props.mode === 'edit' && props.row) {
    const row = props.row;
    const mapping = rowErpMapping(row);

    form.defaults({
      code: row.code,
      name: row.name,
      source_type: row.source_type,
      scope_type: row.scope_type,
      data_type: row.data_type,
      description: row.description || '',
      manual_input_hint: row.manual_input_hint || '',
      target_value: row.target_value || '',
      target_direction: row.target_direction || 'higher_better',
      frequency: row.frequency || 'monthly',
      formula: row.formula || '',
      is_shared: !!row.is_shared,
      status: row.status,
      erp_mapping: {
        resolver_key: mapping.resolver_key || '',
        aggregation: mapping.aggregation || 'sum',
        static_filters: mapping.static_filters || null,
        dynamic_filter_bindings: mapping.dynamic_filter_bindings || null,
      },
    });
    form.reset();
    return;
  }

  form.defaults(createDefaults());
  form.reset();
}

watch(
  () => [props.show, props.mode, props.row?.id],
  ([show]) => {
    if (!show) return;
    populateForm();
  },
);

const isSubmitting = ref(false);

function firstError(errors) {
  const values = Object.values(errors || {});
  return values.length ? values[0] : 'Periksa kembali isian form.';
}

function submit() {
  isSubmitting.value = true;

  const opts = {
    onSuccess: () => {
      Swal.fire(
        'Berhasil',
        props.mode === 'edit' ? 'Parameter KPI diperbarui.' : 'Parameter KPI ditambahkan.',
        'success',
      );
      emit('success');
      emit('close');
    },
    onError: (errors) => {
      Swal.fire('Gagal menyimpan', firstError(errors), 'error');
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  };

  if (props.mode === 'create') {
    form.post(route('kpi-parameters.store'), opts);
    return;
  }

  if (props.mode === 'edit' && props.row?.id) {
    form._method = 'PUT';
    form.post(route('kpi-parameters.update', props.row.id), opts);
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
            <input
              v-model="form.code"
              class="mt-1 w-full rounded-lg border-gray-300"
              :class="{ 'bg-gray-100 cursor-not-allowed': mode === 'edit' }"
              :readonly="mode === 'edit'"
              placeholder="P001"
            />
            <p v-if="mode === 'edit'" class="text-xs text-gray-500 mt-1">Kode tidak bisa diubah setelah dibuat.</p>
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
        <div>
          <KpiFormFieldLabel label="Petunjuk input manual" :hint="fieldHints.manual_input_hint.hint" :example="fieldHints.manual_input_hint.example" />
          <textarea v-model="form.manual_input_hint" rows="2" class="mt-1 w-full rounded-lg border-gray-300" placeholder="Petunjuk untuk kolom Manual/Override di evaluasi (opsional)" />
        </div>

        <div class="border rounded-xl p-4 bg-rose-50/40 space-y-4">
          <h4 class="font-semibold text-rose-800 text-sm">Konfigurasi KPI</h4>
          <p class="text-xs text-gray-600">Field ini dipakai otomatis saat parameter dipilih di Template KPI.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <KpiFormFieldLabel label="Target" :hint="fieldHints.target_value.hint" :example="fieldHints.target_value.example" />
              <input v-model="form.target_value" class="mt-1 w-full rounded-lg border-gray-300" placeholder="100%" />
            </div>
            <div>
              <KpiFormFieldLabel label="Direction" :hint="fieldHints.target_direction.hint" :example="fieldHints.target_direction.example" />
              <select v-model="form.target_direction" class="mt-1 w-full rounded-lg border-gray-300">
                <option v-for="opt in options.target_directions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <KpiFormFieldLabel label="Frequency" :hint="fieldHints.frequency.hint" :example="fieldHints.frequency.example" />
              <select v-model="form.frequency" class="mt-1 w-full rounded-lg border-gray-300">
                <option v-for="opt in options.frequencies" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <KpiFormFieldLabel label="Formula" :hint="fieldHints.formula.hint" :example="fieldHints.formula.example" />
              <input v-model="form.formula" class="mt-1 w-full rounded-lg border-gray-300 font-mono text-sm" placeholder="P001 / P002 * 100" />
            </div>
          </div>
        </div>

        <div v-if="showErpMapping" class="border rounded-xl p-4 bg-indigo-50/50 space-y-3">
          <KpiFormFieldLabel
            label="ERP Mapping"
            hint="Konfigurasi ini menghubungkan parameter ke modul ERP. Muncul hanya jika Source = ERP atau Hybrid."
          />
          <div>
            <KpiFormFieldLabel
              label="Resolver Key"
              :required="resolverRequired"
              :hint="fieldHints.resolver_key.hint"
              :example="fieldHints.resolver_key.example"
            />
            <select v-model="form.erp_mapping.resolver_key" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="">{{ resolverRequired ? '-- Pilih sumber data ERP --' : '-- Opsional (kosongkan jika manual) --' }}</option>
              <option v-for="opt in resolverOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p v-if="!resolverRequired" class="text-xs text-gray-500 mt-1">Hybrid: resolver opsional. Kosongkan jika nilai diisi manual saat penilaian.</p>
            <div v-if="form.errors['erp_mapping.resolver_key']" class="text-xs text-red-500 mt-1">{{ form.errors['erp_mapping.resolver_key'] }}</div>
          </div>
          <div>
            <KpiFormFieldLabel label="Aggregation" :hint="fieldHints.aggregation.hint" :example="fieldHints.aggregation.example" />
            <select v-model="form.erp_mapping.aggregation" class="mt-1 w-full rounded-lg border-gray-300">
              <option value="sum">sum — total nilai (revenue, amount)</option>
              <option value="count">count — jumlah baris/order</option>
              <option value="avg">avg — rata-rata</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Untuk jumlah order (D011), pilih resolver <strong>POS Orders — Jumlah Order</strong> atau aggregation <strong>count</strong>.</p>
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
