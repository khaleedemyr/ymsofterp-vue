<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  order: Object,
  user: Object,
  itemsBySupplier: Array,
  supplierHeaders: Array,
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

// Tambahkan subtotal per kategori
const categorySubtotals = computed(() => {
  const result = {};
  Object.entries(groupedItems.value).forEach(([cat, items]) => {
    result[cat] = items.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0);
  });
  return result;
});

const grandTotal = computed(() =>
  props.order.items?.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0) || 0
);

const grandTotalSupplier = computed(() => {
  if (!props.itemsBySupplier || !props.itemsBySupplier.length) return 0;
  return props.itemsBySupplier.reduce((sum, group) => {
    return sum + group.items.reduce((s, item) => s + (Number(item.subtotal) || 0), 0);
  }, 0);
});

const isSuperadmin = computed(() =>
  props.user?.id_role === '5af56935b011a' && props.user?.status === 'A'
);

// Function untuk mengecek apakah user bisa approve berdasarkan warehouse outlet
const canUserApproveByWarehouse = (user, warehouseOutletName) => {
  if (!user || !warehouseOutletName) return false;
  
  const userJabatan = user.id_jabatan;
  const userStatus = user.status;
  
  // Sekarang semua jabatan yang menerima notifikasi juga bisa approve
  switch (warehouseOutletName) {
    case 'Kitchen':
      // Jabatan yang bisa approve: semua yang menerima notifikasi
      return [163, 174, 180, 345, 346, 347, 348, 349].includes(userJabatan) && userStatus === 'A';
    case 'Bar':
      // Jabatan yang bisa approve: semua yang menerima notifikasi
      return [175, 182, 323].includes(userJabatan) && userStatus === 'A';
    case 'Service':
      // Jabatan yang bisa approve: semua yang menerima notifikasi
      return [176, 322, 164, 321].includes(userJabatan) && userStatus === 'A';
    default:
      return false;
  }
};

const canApproveFO = computed(() => {
  if (props.order.fo_mode === 'RO Supplier') {
    const isExecutiveChef = props.user?.id_jabatan === 163 && props.user?.status === 'A';
    return props.order.status === 'submitted' && (isExecutiveChef || isSuperadmin.value);
  }

  if (props.order.fo_mode === 'RO Khusus' && props.order.status === 'submitted') {
    const flows = props.order.approval_flows || [];
    if (flows.length > 0) {
      const pending = [...flows]
        .filter((f) => f.status === 'PENDING')
        .sort((a, b) => a.approval_level - b.approval_level);
      if (!pending.length) return false;
      const next = pending[0];
      if (isSuperadmin.value) return true;
      return Number(next.approver_id) === Number(props.user?.id);
    }

    const warehouseName = props.order.warehouse_outlet?.name;
    return isSuperadmin.value || canUserApproveByWarehouse(props.user, warehouseName);
  }

  return false;
});

function flowStatusClass(status) {
  switch (status) {
    case 'APPROVED': return 'border-green-300 bg-green-50';
    case 'REJECTED': return 'border-red-300 bg-red-50';
    case 'PENDING': return 'border-amber-300 bg-amber-50';
    default: return 'border-gray-200 bg-gray-50';
  }
}

function flowStatusBadgeClass(status) {
  switch (status) {
    case 'APPROVED': return 'bg-green-100 text-green-800';
    case 'REJECTED': return 'bg-red-100 text-red-800';
    case 'PENDING': return 'bg-amber-100 text-amber-800';
    default: return 'bg-gray-100 text-gray-800';
  }
}

async function approveFO() {
  const { value: note } = await Swal.fire({
    title: 'Approve Request Order (RO)?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('floor-order.approve', props.order.id), {
      notes: note,
    }, {
      onError: (errors) => {
        // Handle budget error specifically
        if (errors.budget) {
          Swal.fire({
            icon: 'error',
            title: 'Budget Terlampaui',
            html: errors.budget,
            confirmButtonText: 'OK'
          });
        } else {
          // Handle other errors
          const errorMessage = Object.values(errors).join('<br>');
          Swal.fire({
            icon: 'error',
            title: 'Gagal Approve',
            html: errorMessage,
            confirmButtonText: 'OK'
          });
        }
      }
    });
  }
}
</script>
<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <button @click="$inertia.visit('/floor-order')" class="mb-6 text-blue-500 hover:underline flex items-center gap-2"><i class="fa fa-arrow-left"></i> Kembali ke Daftar</button>
      <div class="bg-white rounded-2xl shadow-2xl p-6 mb-8">
        <div class="flex flex-wrap gap-6 mb-4">
          <div>
            <div class="text-xs text-gray-500">No. Request Order (RO)</div>
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
            <div v-if="props.order.status === 'approved'" class="mt-1 text-xs text-green-700">
              Disetujui oleh: <b>{{ props.order.approver?.nama_lengkap || props.order.approver?.name || props.order.approval_by }}</b>
              <span v-if="props.order.approval_at">pada {{ new Date(props.order.approval_at).toLocaleString('id-ID') }}</span>
              <span v-if="props.order.approval_notes">- Note: {{ props.order.approval_notes }}</span>
            </div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Jadwal FO</div>
            <div v-if="props.order.fo_schedule">
              {{ props.order.fo_schedule.fo_mode }} - {{ translateDay(props.order.fo_schedule.day) }}<br>
              <span class="text-xs text-gray-500">{{ props.order.fo_schedule.open_time }} - {{ props.order.fo_schedule.close_time }}</span>
            </div>
            <div v-else class="text-gray-400 italic">-</div>
          </div>
          <div>
            <div class="text-xs text-gray-500">Warehouse Outlet</div>
            <div class="font-semibold">{{ props.order.warehouse_outlet?.name || '-' }}</div>
          </div>
        </div>
        <div class="mb-2">
          <div class="text-xs text-gray-500">Keterangan</div>
          <div class="font-semibold">{{ props.order.description || '-' }}</div>
        </div>
        <div
          v-if="props.order.fo_mode === 'RO Khusus' && props.order.approval_flows?.length"
          class="mt-4 border border-teal-200 rounded-xl p-4 bg-teal-50/40"
        >
          <h3 class="text-sm font-semibold text-teal-800 mb-3 flex items-center gap-2">
            <i class="fa fa-users"></i> Approval Flow
          </h3>
          <div class="space-y-2">
            <div
              v-for="flow in props.order.approval_flows"
              :key="flow.id"
              class="flex items-center justify-between p-3 rounded-lg border"
              :class="flowStatusClass(flow.status)"
            >
              <div>
                <div class="text-xs font-semibold text-teal-700">Level {{ flow.approval_level }}</div>
                <div class="font-medium">{{ flow.approver?.nama_lengkap || '-' }}</div>
                <div v-if="flow.comments" class="text-xs text-gray-600 mt-1">{{ flow.comments }}</div>
              </div>
              <div class="text-right">
                <span class="text-xs font-semibold px-2 py-1 rounded-full" :class="flowStatusBadgeClass(flow.status)">
                  {{ flow.status }}
                </span>
                <div v-if="flow.approved_at" class="text-xs text-gray-500 mt-1">
                  {{ new Date(flow.approved_at).toLocaleString('id-ID') }}
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <button
            v-if="canApproveFO"
            @click="approveFO"
            class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700"
          >
            Approve
          </button>
        </div>
      </div>
      <div class="space-y-8">
        <template v-if="itemsBySupplier && itemsBySupplier.length">
          <div v-for="group in itemsBySupplier" :key="group.header.id" class="bg-blue-50 rounded-xl shadow p-4 mb-8">
            <h3 class="font-bold text-blue-700 text-lg mb-2 flex items-center gap-2">
              <i class="fa fa-truck"></i>
              Supplier ID: {{ group.header.supplier_id }} | No. RO Supplier: {{ group.header.supplier_fo_number }}
            </h3>
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
                  <tr v-for="item in group.items" :key="item.id">
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
        </template>
        <template v-else>
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
                  <tr class="bg-blue-100 font-bold">
                    <td colspan="4" class="text-right">Total {{ cat }}</td>
                    <td>{{ formatRupiah(categorySubtotals[cat]) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>
      <div class="text-right font-bold text-xl mt-8">
        Grand Total: {{ formatRupiah(itemsBySupplier && itemsBySupplier.length ? grandTotalSupplier : grandTotal) }}
      </div>
    </div>
  </AppLayout>
</template> 