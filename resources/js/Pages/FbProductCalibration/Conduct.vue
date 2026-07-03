<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-check text-violet-600"></i>
            Conduct Calibration
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            {{ record.outlet_name }} · {{ formatDate(record.scheduled_date) }} · Conducted by {{ record.conductor_name }}
          </p>
        </div>
        <Link :href="route('fb-product-calibration.show', record.id)" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6">
          <label class="block text-sm font-semibold text-gray-700 mb-2">User yang di-calibration test</label>
          <Multiselect
            v-model="participants"
            :options="participantOptions"
            :multiple="true"
            :searchable="true"
            :internal-search="false"
            :loading="participantLoading"
            placeholder="Cari user..."
            label="display_label"
            track-by="user_id"
            @search-change="searchParticipants"
          />
          <p class="text-xs text-gray-500 mt-2">
            Bisa pilih beberapa user sekaligus. Setiap user tidak wajib diisi semua product — cukup isi product yang di-calibration saja.
          </p>
        </div>

        <div v-if="participants.length" class="space-y-6">
          <div
            v-for="participant in participants"
            :key="participant.user_id"
            class="bg-white rounded-xl shadow overflow-x-auto"
          >
            <div class="px-6 py-4 border-b bg-gray-50">
              <h3 class="font-semibold text-gray-800">{{ participant.user_name || participant.nama_lengkap }}</h3>
              <p class="text-sm text-gray-500">{{ participant.jabatan_name }}</p>
            </div>

            <table class="fbc-calibration-table min-w-[1100px] w-full text-xs border-collapse">
              <thead>
                <tr>
                  <th rowspan="3" class="fbc-th-product">Product</th>
                  <th :colspan="parameterOptions.length * 2" class="fbc-th-group">
                    CALIBRATION PARAMETER
                  </th>
                </tr>
                <tr>
                  <th
                    v-for="param in parameterOptions"
                    :key="param.code"
                    colspan="2"
                    class="fbc-th-param"
                  >
                    {{ param.label }}
                  </th>
                </tr>
                <tr>
                  <template v-for="param in parameterOptions" :key="`${param.code}-cn`">
                    <th class="fbc-th-choice">C</th>
                    <th class="fbc-th-choice">NC</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in record.products" :key="product.id">
                  <td class="fbc-td-product">
                    <div class="font-semibold text-gray-900">{{ product.item_name }}</div>
                    <div class="text-[11px] text-gray-500 leading-snug">
                      {{ product.category_name }}
                      <span v-if="product.sub_category_name"> · {{ product.sub_category_name }}</span>
                    </div>
                  </td>
                  <template v-for="param in parameterOptions" :key="`${participant.user_id}-${product.id}-${param.code}`">
                    <td class="fbc-td-choice">
                      <input
                        type="radio"
                        :name="`r-${participant.user_id}-${product.id}-${param.code}`"
                        value="C"
                        :checked="getValue(participant.user_id, product.id, param.code) === 'C'"
                        class="fbc-radio"
                        @change="setValue(participant.user_id, product.id, param.code, 'C')"
                      />
                    </td>
                    <td class="fbc-td-choice">
                      <input
                        type="radio"
                        :name="`r-${participant.user_id}-${product.id}-${param.code}`"
                        value="NC"
                        :checked="getValue(participant.user_id, product.id, param.code) === 'NC'"
                        class="fbc-radio"
                        @change="setValue(participant.user_id, product.id, param.code, 'NC')"
                      />
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('fb-product-calibration.show', record.id)" class="px-6 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing || !participants.length"
            class="px-6 py-2.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50"
          >
            {{ form.processing ? 'Menyimpan...' : 'Simpan Hasil Calibration' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  parameterOptions: { type: Array, default: () => [] },
  initialParticipants: { type: Array, default: () => [] },
  initialResults: { type: Array, default: () => [] },
});

const form = useForm({
  participants: [],
  results: [],
});

const participants = ref([]);
const participantOptions = ref([]);
const participantLoading = ref(false);
let participantTimer = null;

const resultState = ref({});

function initResults() {
  const state = {};
  props.initialResults.forEach((row) => {
    const key = `${row.user_id}_${row.calibration_product_id}`;
    state[key] = { ...row };
  });
  resultState.value = state;
}

function initParticipants() {
  participants.value = (props.initialParticipants || []).map((p) => ({
    user_id: p.user_id,
    user_name: p.user_name,
    jabatan_name: p.jabatan_name,
    nama_lengkap: p.user_name,
    display_label: p.display_label || `${p.user_name} — ${p.jabatan_name || '-'}`,
  }));
  participantOptions.value = [...participants.value];
}

function resultKey(userId, productId) {
  return `${userId}_${productId}`;
}

function ensureResultRow(userId, productId) {
  const key = resultKey(userId, productId);
  if (!resultState.value[key]) {
    resultState.value[key] = {
      user_id: userId,
      calibration_product_id: productId,
    };
    props.parameterOptions.forEach((param) => {
      resultState.value[key][param.code] = null;
    });
  }
  return resultState.value[key];
}

function getValue(userId, productId, paramCode) {
  const key = resultKey(userId, productId);
  return resultState.value[key]?.[paramCode] || null;
}

function setValue(userId, productId, paramCode, value) {
  const row = ensureResultRow(userId, productId);
  row[paramCode] = value;
}

function searchParticipants(query) {
  clearTimeout(participantTimer);
  participantTimer = setTimeout(async () => {
    participantLoading.value = true;
    try {
      const res = await axios.get('/api/fb-product-calibration/search-participants', { params: { q: query || '' } });
      const fetched = (res.data.users || []).map((u) => ({
        user_id: u.id,
        user_name: u.nama_lengkap,
        jabatan_name: u.jabatan_name,
        nama_lengkap: u.nama_lengkap,
        display_label: u.display_label,
      }));
      const selectedIds = new Set(participants.value.map((p) => p.user_id));
      participantOptions.value = [
        ...participants.value,
        ...fetched.filter((u) => !selectedIds.has(u.user_id)),
      ];
    } finally {
      participantLoading.value = false;
    }
  }, 300);
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

function productRowValues(userId, productId) {
  return props.parameterOptions.map((param) => getValue(userId, productId, param.code));
}

function isProductRowStarted(userId, productId) {
  return productRowValues(userId, productId).some((val) => val);
}

function isProductRowComplete(userId, productId) {
  return productRowValues(userId, productId).every((val) => val);
}

function buildPayload() {
  form.participants = participants.value.map((p) => ({ user_id: p.user_id }));

  const results = [];
  participants.value.forEach((participant) => {
    props.record.products.forEach((product) => {
      if (!isProductRowStarted(participant.user_id, product.id)) {
        return;
      }

      const key = resultKey(participant.user_id, product.id);
      const row = resultState.value[key] || {};
      const entry = {
        user_id: participant.user_id,
        calibration_product_id: product.id,
      };
      props.parameterOptions.forEach((param) => {
        entry[param.code] = row[param.code] || null;
      });
      results.push(entry);
    });
  });
  form.results = results;
}

function validateClient() {
  if (!participants.value.length) {
    Swal.fire({ icon: 'warning', title: 'Tambahkan minimal satu user' });
    return false;
  }

  for (const participant of participants.value) {
    let calibratedProductCount = 0;

    for (const product of props.record.products) {
      const started = isProductRowStarted(participant.user_id, product.id);
      if (!started) {
        continue;
      }

      if (!isProductRowComplete(participant.user_id, product.id)) {
        const missingParam = props.parameterOptions.find(
          (param) => !getValue(participant.user_id, product.id, param.code),
        );
        Swal.fire({
          icon: 'warning',
          title: 'Lengkapi semua parameter product',
          text: `User ${participant.user_name || participant.nama_lengkap}, product ${product.item_name}${missingParam ? `, parameter ${missingParam.label}` : ''}`,
        });
        return false;
      }

      calibratedProductCount += 1;
    }

    if (calibratedProductCount === 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Minimal satu product',
        text: `User ${participant.user_name || participant.nama_lengkap} belum memiliki product yang di-calibration.`,
      });
      return false;
    }
  }

  return true;
}

function submit() {
  if (!validateClient()) return;
  buildPayload();
  form.post(route('fb-product-calibration.conduct.store', props.record.id));
}

onMounted(() => {
  initParticipants();
  initResults();
  searchParticipants('');
});
</script>

<style scoped>
.fbc-calibration-table {
  table-layout: fixed;
}
.fbc-calibration-table th,
.fbc-calibration-table td {
  border: 1px solid #d1d5db;
}
.fbc-th-product {
  width: 200px;
  min-width: 200px;
  background: #111827;
  color: #fff;
  font-weight: 700;
  text-align: left;
  vertical-align: middle;
  padding: 10px 12px;
}
.fbc-th-group {
  background: #111827;
  color: #fff;
  font-weight: 700;
  text-align: center;
  letter-spacing: 0.04em;
  padding: 8px 6px;
}
.fbc-th-param {
  background: #1f2937;
  color: #fff;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
  font-size: 10px;
  letter-spacing: 0.02em;
  padding: 8px 4px;
  white-space: nowrap;
}
.fbc-th-choice {
  background: #374151;
  color: #fff;
  font-weight: 700;
  text-align: center;
  width: 44px;
  min-width: 44px;
  padding: 6px 4px;
}
.fbc-td-product {
  background: #fff;
  vertical-align: middle;
  padding: 10px 12px;
}
.fbc-td-choice {
  background: #fff;
  text-align: center;
  vertical-align: middle;
  padding: 8px 4px;
}
.fbc-radio {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #4f46e5;
}
</style>
