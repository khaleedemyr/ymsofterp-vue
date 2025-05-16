<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  order: Object,
  user: Object,
});

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function translateDay(day) {
  const map = {
    'Monday': 'Senin',
    'Tuesday': 'Selasa',
    'Wednesday': 'Rabu',
    'Thursday': 'Kamis',
    'Friday': 'Jumat',
    'Saturday': 'Sabtu',
    'Sunday': 'Minggu',
  };
  return map[day] || day;
}

// Group items by category name
const groupedItems = computed(() => {
  if (!props.order.items) return {};
  const group = {};
  props.order.items.forEach(item => {
    const cat = item.category?.name || '-';
    if (!group[cat]) group[cat] = [];
    group[cat].push(item);
  });
  return group;
});

const grandTotal = computed(() =>
  props.order.items?.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0) || 0
);
</script>
<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <button @click="$inertia.visit('/floor-order')" class="mb-6 text-blue-500 hover:underline flex items-center gap-2"><i class="fa fa-arrow-left"></i> Kembali ke Daftar</button>
      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-8">
        <div class="flex flex-wrap gap-6 mb-4">
          <div>
            <div class="text-xs text-gray-500">No. Floor Order</div>
            <div class="font-mono font-bold text-blue-700 text-lg">{{ props.order.order_number }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Tanggal</div>
            <div class="font-semibold">{{ new Date(props.order.tanggal).toLocaleDateString('id-ID') }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Outlet</div>
            <div class="font-semibold">{{ props.order.outlet?.nama_outlet }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Requester</div>
            <div class="font-semibold">{{ props.order.requester?.nama_lengkap }}</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Status</div>
            <span :class="{
              'bg-gray-100 text-gray-700': props.order.status === 'draft',
              'bg-green-100 text-green-700': props.order.status === 'approved',
            }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
              {{ props.order.status }}
            </span>
          </div>
          <div>
            <div class="text-xs text-gray-500">Jadwal FO</div>
            <div v-if="props.order.fo_schedule">
              {{ props.order.fo_schedule.fo_mode }} - {{ translateDay(props.order.fo_schedule.day) }}<br>
              <span class="text-xs text-gray-500">{{ props.order.fo_schedule.open_time }} - {{ props.order.fo_schedule.close_time }}</span>
            </div>
            <div v-else class="text-gray-400 italic">-</div>
          </div>
        </div>
        <div class="mb-2">
          <div class="text-xs text-gray-500">Keterangan</div>
          <div class="font-semibold">{{ props.order.description || '-' }}</div>
        </div>
      </div>
      <div class="space-y-8">
        <div v-for="(items, cat) in groupedItems" :key="cat" class="bg-blue-50 rounded-xl shadow p-4">
          <h3 class="font-bold text-blue-700 text-lg mb-2 flex items-center gap-2"><i class="fa fa-layer-group"></i> {{ cat }}</h3>
          <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-100 to-blue-200">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                  <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                  <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                  <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga</th>
                  <th class="px-4 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td class="px-4 py-2">{{ item.item_name }}</td>
                  <td class="px-4 py-2">{{ item.qty }}</td>
                  <td class="px-4 py-2">{{ item.unit }}</td>
                  <td class="px-4 py-2">{{ formatRupiah(item.price) }}</td>
                  <td class="px-4 py-2 font-semibold">{{ formatRupiah(item.subtotal) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="text-right font-bold text-xl mt-8">
        Grand Total: {{ formatRupiah(grandTotal) }}
      </div>
    </div>
  </AppLayout>
</template> 