<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-pink-50/30 to-slate-50">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
              <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 text-white shadow-lg shadow-pink-500/25">
                <i class="fa-solid fa-calendar-check text-xl"></i>
              </span>
              Reservasi
            </h1>
            <p class="mt-2 text-slate-500 text-sm">Kelola booking dan reservasi pelanggan</p>
          </div>
          <Link
            :href="route('reservations.create')"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 shadow-lg shadow-pink-500/25 hover:shadow-pink-500/40 transition-all duration-200"
          >
            <i class="fa-solid fa-plus text-sm"></i>
            Buat Reservasi
          </Link>
        </div>

        <!-- Filter Card -->
        <div class="bg-white/80 backdrop-blur rounded-2xl border border-slate-200/60 shadow-sm p-5 mb-6">
          <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-filter text-rose-500"></i>
            <span class="font-semibold text-slate-700">Filter</span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="lg:col-span-2">
              <label class="block text-xs font-medium text-slate-500 mb-1">Cari nama</label>
              <div class="relative">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                  v-model="search"
                  type="text"
                  placeholder="Cari nama reservasi..."
                  class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm"
                  @keyup.enter="applyFilters"
                />
              </div>
            </div>
            <div v-if="canChooseOutlet">
              <label class="block text-xs font-medium text-slate-500 mb-1">Outlet</label>
              <select
                v-model="outletId"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm bg-white"
              >
                <option value="">Semua Outlet</option>
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div v-else-if="outlets.length === 1" class="flex flex-col justify-end">
              <label class="block text-xs font-medium text-slate-500 mb-1">Outlet</label>
              <span class="py-2.5 text-sm text-slate-700">{{ outlets[0].name }}</span>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal dari</label>
              <input
                type="date"
                v-model="dateFrom"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal sampai</label>
              <input
                type="date"
                v-model="dateTo"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
              <select
                v-model="status"
                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm bg-white"
              >
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="arrived">Datang</option>
                <option value="cancelled">Cancelled</option>
                <option value="no_show">No Show</option>
              </select>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-4">
            <button
              @click="applyFilters"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500 text-white text-sm font-medium hover:bg-rose-600 transition"
            >
              <i class="fa-solid fa-magnifying-glass"></i>
              Terapkan
            </button>
            <button
              @click="resetFilters"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 transition"
            >
              <i class="fa-solid fa-rotate-left"></i>
              Reset
            </button>
          </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">No</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Nama</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Outlet</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Tanggal & Waktu</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Tamu</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">DP</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Kode DP</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Jenis Pembayaran</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Sales</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Area</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                  <th class="px-5 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Dibuat oleh</th>
                  <th class="px-5 py-4 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="!reservations.length">
                  <td colspan="13" class="px-5 py-16 text-center">
                    <div class="flex flex-col items-center gap-4">
                      <span class="flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 text-slate-400">
                        <i class="fa-solid fa-calendar-xmark text-2xl"></i>
                      </span>
                      <div>
                        <p class="font-medium text-slate-600">Belum ada reservasi</p>
                        <p class="text-sm text-slate-400 mt-1">Gunakan filter atau buat reservasi baru</p>
                      </div>
                      <Link
                        :href="route('reservations.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500 text-white text-sm font-medium hover:bg-rose-600 transition"
                      >
                        <i class="fa-solid fa-plus"></i>
                        Buat Reservasi
                      </Link>
                    </div>
                  </td>
                </tr>
                <tr
                  v-for="(reservation, idx) in reservations"
                  :key="reservation.id"
                  class="hover:bg-rose-50/50 transition group"
                >
                  <td class="px-5 py-4 text-sm text-slate-500">{{ idx + 1 }}</td>
                  <td class="px-5 py-4">
                    <span class="font-medium text-slate-800">{{ reservation.name }}</span>
                  </td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ reservation.outlet }}</td>
                  <td class="px-5 py-4">
                    <div class="text-sm text-slate-700">{{ formatDate(reservation.reservation_date) }}</div>
                    <div class="text-xs text-slate-500">{{ formatTime(reservation.reservation_time) }}</div>
                  </td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ reservation.number_of_guests }} orang</td>
                  <td class="px-5 py-4 text-sm font-medium text-slate-700">{{ reservation.dp != null ? formatDp(reservation.dp) : '–' }}</td>
                  <td class="px-5 py-4 font-mono text-sm font-semibold text-emerald-700">{{ reservation.dp_code || '–' }}</td>
                  <td class="px-5 py-4 text-sm text-slate-600">{{ reservation.payment_type_name || '–' }}</td>
                  <td class="px-5 py-4">
                    <span :class="reservation.from_sales ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-500'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium">
                      {{ reservation.from_sales ? 'Dari Sales' : 'Bukan' }}
                    </span>
                    <p v-if="reservation.from_sales && reservation.sales_user_name" class="text-xs text-slate-500 mt-1 truncate max-w-[120px]" :title="reservation.sales_user_name">{{ reservation.sales_user_name }}</p>
                  </td>
                  <td class="px-5 py-4">
                    <span :class="reservation.smoking_preference === 'smoking' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'" class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium">
                      {{ reservation.smoking_preference === 'smoking' ? 'Smoking' : 'Non-Smoking' }}
                    </span>
                  </td>
                  <td class="px-5 py-4">
                    <span :class="getStatusClass(reservation.status)" class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold">
                      {{ getStatusText(reservation.status) }}
                    </span>
                  </td>
                  <td class="px-5 py-4">
                    <div class="text-sm text-slate-700">{{ reservation.created_by || '–' }}</div>
                    <div class="text-xs text-slate-500">{{ formatDateTime(reservation.created_at) }}</div>
                  </td>
                  <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition">
                      <Link
                        :href="route('reservations.show', { reservation: reservation.id })"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition"
                        title="Detail"
                      >
                        <i class="fa-solid fa-eye text-sm"></i>
                      </Link>
                      <Link
                        :href="route('reservations.edit', { reservation: reservation.id })"
                        class="p-2 rounded-lg text-amber-600 hover:bg-amber-50 transition"
                        title="Edit"
                      >
                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                      </Link>
                      <button
                        @click="handleDelete(reservation.id)"
                        :disabled="loadingDeleteId === reservation.id"
                        class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 transition disabled:opacity-50"
                        title="Hapus"
                      >
                        <i v-if="loadingDeleteId === reservation.id" class="fa-solid fa-spinner fa-spin text-sm"></i>
                        <i v-else class="fa-solid fa-trash text-sm"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref, computed } from 'vue';

const props = defineProps({
  reservations: { type: Array, required: true, default: () => [] },
  outlets: { type: Array, default: () => [] },
  can_choose_outlet: { type: Boolean, default: true },
  search: String,
  outlet_id: [String, Number],
  status: String,
  dateFrom: String,
  dateTo: String
});

const canChooseOutlet = computed(() => props.can_choose_outlet !== false);

const search = ref(props.search || '');
const outletId = ref(props.outlet_id ?? '');
const status = ref(props.status || '');
const dateFrom = ref(props.dateFrom || '');
const dateTo = ref(props.dateTo || '');
const outlets = ref(props.outlets || []);
const loadingDeleteId = ref(null);

function formatDate(date) {
  if (!date) return '–';
  return new Date(date).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

function formatTime(time) {
  if (!time) return '–';
  return new Date(time).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTime(val) {
  if (!val) return '–';
  return new Date(val).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatDp(val) {
  if (val == null || val === '') return '–';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));
}

function getStatusClass(status) {
  switch (status) {
    case 'confirmed': return 'bg-emerald-100 text-emerald-800';
    case 'arrived': return 'bg-blue-100 text-blue-800';
    case 'cancelled': return 'bg-rose-100 text-rose-800';
    case 'no_show': return 'bg-slate-100 text-slate-700';
    default: return 'bg-amber-100 text-amber-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'confirmed': return 'Confirmed';
    case 'arrived': return 'Datang';
    case 'cancelled': return 'Cancelled';
    case 'no_show': return 'No Show';
    default: return 'Pending';
  }
}

async function handleDelete(id) {
  const confirm = await Swal.fire({
    title: 'Hapus Reservasi?',
    text: 'Data reservasi akan dihapus. Lanjutkan?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#e11d48',
  });
  if (!confirm.isConfirmed) return;
  loadingDeleteId.value = id;
  router.delete(route('reservations.destroy', { reservation: id }), {
    onSuccess: () => {
      loadingDeleteId.value = null;
      Swal.fire({ title: 'Berhasil', text: 'Reservasi dihapus.', icon: 'success' });
    },
    onError: () => {
      loadingDeleteId.value = null;
      Swal.fire({ title: 'Error', text: 'Gagal menghapus reservasi', icon: 'error' });
    }
  });
}

function applyFilters() {
  router.get(route('reservations.index'), {
    search: search.value,
    outlet_id: outletId.value || undefined,
    status: status.value,
    dateFrom: dateFrom.value,
    dateTo: dateTo.value
  }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
  search.value = '';
  outletId.value = canChooseOutlet.value ? '' : (props.outlet_id ?? '');
  status.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  applyFilters();
}
</script>
