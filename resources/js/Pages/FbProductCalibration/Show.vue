<template>
  <AppLayout>
    <div class="max-w-6xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-utensils text-violet-600"></i>
            Detail F&B Product Calibration
          </h1>
          <p class="text-sm text-gray-500 mt-1">{{ record.outlet_name }} · {{ formatDate(record.scheduled_date) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('fb-product-calibration.index')" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kalender
          </Link>
          <Link
            v-if="record.status !== 'completed'"
            :href="route('fb-product-calibration.edit', record.id)"
            class="px-4 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600"
          >
            <i class="fa-solid fa-pen mr-1"></i> Edit
          </Link>
          <Link
            v-if="canConduct"
            :href="route('fb-product-calibration.conduct', record.id)"
            class="px-4 py-2 rounded-lg bg-violet-600 text-white hover:bg-violet-700"
          >
            <i class="fa-solid fa-clipboard-check mr-1"></i>
            {{ record.status === 'completed' ? 'Edit Conduct' : 'Conduct Calibration' }}
          </Link>
          <button
            v-if="canDelete"
            type="button"
            class="px-4 py-2 rounded-lg bg-red-100 text-red-700 hover:bg-red-200"
            @click="confirmDelete"
          >
            <i class="fa-solid fa-trash mr-1"></i> Hapus
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
        <div>
          <div class="text-gray-500">Mode</div>
          <div class="font-semibold">{{ modeLabel(record.mode) }}</div>
        </div>
        <div>
          <div class="text-gray-500">Outlet</div>
          <div class="font-semibold">{{ record.outlet_name }}</div>
        </div>
        <div>
          <div class="text-gray-500">Tanggal</div>
          <div class="font-semibold">{{ formatDate(record.scheduled_date) }}</div>
        </div>
        <div>
          <div class="text-gray-500">Conducted By</div>
          <div class="font-semibold">{{ record.conductor_name }}</div>
        </div>
        <div>
          <div class="text-gray-500">Status</div>
          <span :class="statusClass(record.status)" class="inline-flex px-2 py-1 rounded text-xs font-semibold">
            {{ statusLabel(record.status) }}
          </span>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Products</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div v-for="product in record.products" :key="product.id" class="rounded-lg border border-gray-200 p-3">
            <div class="font-semibold text-gray-800">{{ product.item_name }}</div>
            <div class="text-sm text-gray-500">
              {{ product.category_name || '-' }}
              <span v-if="product.sub_category_name"> · {{ product.sub_category_name }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="record.status === 'completed' && conductPayload" class="space-y-6">
        <div
          v-for="participant in conductPayload.participants"
          :key="participant.user_id"
          class="bg-white rounded-xl shadow overflow-x-auto"
        >
          <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-800">{{ participant.user_name }}</h3>
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
                <template v-for="param in parameterOptions" :key="`${param.code}-sub`">
                  <th class="fbc-th-choice">C</th>
                  <th class="fbc-th-choice">NC</th>
                </template>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in productsForParticipant(participant.user_id)" :key="product.id">
                <td class="fbc-td-product">
                  <div class="font-semibold text-gray-900">{{ product.item_name }}</div>
                  <div class="text-[11px] text-gray-500 leading-snug">
                    {{ product.category_name }}
                    <span v-if="product.sub_category_name"> · {{ product.sub_category_name }}</span>
                  </div>
                </td>
                <template v-for="param in parameterOptions" :key="`${product.id}-${param.code}`">
                  <td class="fbc-td-choice">
                    <span v-if="getResult(participant.user_id, product.id, param.code) === 'C'" class="font-bold text-green-700 text-sm">✓</span>
                  </td>
                  <td class="fbc-td-choice">
                    <span v-if="getResult(participant.user_id, product.id, param.code) === 'NC'" class="font-bold text-red-700 text-sm">✓</span>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="canConduct" class="bg-violet-50 border border-violet-200 rounded-xl p-6 text-sm text-violet-900">
        Calibration belum dilakukan. Klik <strong>Conduct Calibration</strong> untuk mulai input hasil test.
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, required: true },
  parameterOptions: { type: Array, default: () => [] },
  canConduct: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  conductPayload: { type: Object, default: null },
});

const resultMap = {};
(props.conductPayload?.results || []).forEach((row) => {
  resultMap[`${row.user_id}_${row.calibration_product_id}`] = row;
});

function getResult(userId, productId, paramCode) {
  const row = resultMap[`${userId}_${productId}`];
  return row?.[paramCode] || null;
}

function productsForParticipant(userId) {
  return (props.record.products || []).filter((product) => resultMap[`${userId}_${product.id}`]);
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

function modeLabel(mode) {
  return mode === 'bar' ? 'Bar' : 'Kitchen';
}

function statusLabel(status) {
  const map = {
    scheduled: 'Scheduled',
    in_progress: 'In Progress',
    completed: 'Completed',
    cancelled: 'Cancelled',
  };
  return map[status] || status;
}

function statusClass(status) {
  const map = {
    scheduled: 'bg-violet-100 text-violet-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-100 text-gray-700',
  };
  return map[status] || 'bg-gray-100 text-gray-700';
}

function confirmDelete() {
  Swal.fire({
    title: 'Hapus jadwal?',
    text: `Jadwal ${props.record.outlet_name} akan dihapus dari kalender. Data tetap tersimpan di sistem.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('fb-product-calibration.destroy', props.record.id));
    }
  });
}
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
</style>
