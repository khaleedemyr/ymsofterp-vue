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
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
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
          <table class="min-w-full text-xs border-collapse">
            <thead>
              <tr class="bg-gray-900 text-white">
                <th rowspan="2" class="px-3 py-2 border border-gray-700 text-left min-w-[160px]">Product</th>
                <th
                  v-for="param in parameterOptions"
                  :key="param.code"
                  colspan="2"
                  class="px-2 py-2 border border-gray-700 text-center"
                >
                  {{ param.label }}
                </th>
              </tr>
              <tr class="bg-gray-800 text-white">
                <template v-for="param in parameterOptions" :key="`${param.code}-sub`">
                  <th class="px-2 py-1 border border-gray-700 text-center w-10">C</th>
                  <th class="px-2 py-1 border border-gray-700 text-center w-10">NC</th>
                </template>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in record.products" :key="product.id" class="border-b">
                <td class="px-3 py-2 border border-gray-200">
                  <div class="font-medium">{{ product.item_name }}</div>
                  <div class="text-gray-500">{{ product.category_name }}</div>
                </td>
                <template v-for="param in parameterOptions" :key="`${product.id}-${param.code}`">
                  <td class="px-2 py-2 border border-gray-200 text-center">
                    <span v-if="getResult(participant.user_id, product.id, param.code) === 'C'" class="font-bold text-green-700">✓</span>
                  </td>
                  <td class="px-2 py-2 border border-gray-200 text-center">
                    <span v-if="getResult(participant.user_id, product.id, param.code) === 'NC'" class="font-bold text-red-700">✓</span>
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
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  record: { type: Object, required: true },
  parameterOptions: { type: Array, default: () => [] },
  canConduct: { type: Boolean, default: false },
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

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
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
</script>
