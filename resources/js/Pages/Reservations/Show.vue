<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-pink-50/30 to-slate-50 py-8 px-4 sm:px-6">
      <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
          <div class="flex items-center gap-3">
            <Link
              :href="route('reservations.index')"
              class="flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition"
            >
              <i class="fa-solid fa-arrow-left"></i>
            </Link>
            <div>
              <h1 class="text-2xl font-bold text-slate-800">Detail Reservasi</h1>
              <p class="text-sm text-slate-500 mt-0.5">{{ reservation.name }} · {{ formatDate(reservation.reservation_date) }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span :class="getStatusClass(reservation.status)" class="inline-flex items-center px-3 py-1.5 rounded-xl text-sm font-semibold">
              {{ getStatusText(reservation.status) }}
            </span>
            <Link
              :href="route('reservations.edit', { reservation: reservation.id })"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium text-white bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 shadow-lg shadow-pink-500/25 transition"
            >
              <i class="fa-solid fa-pen-to-square text-sm"></i>
              Edit
            </Link>
          </div>
        </div>

        <div class="space-y-6">
          <!-- Data Pemesan -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-rose-100 text-rose-600">
                <i class="fa-solid fa-user text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">Data Pemesan</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Nama Lengkap</p>
                <p class="text-slate-800 font-medium">{{ reservation.name || '–' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Telepon</p>
                <p class="text-slate-800">{{ reservation.phone || '–' }}</p>
              </div>
              <div class="sm:col-span-2">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Email</p>
                <p class="text-slate-800">{{ reservation.email || '–' }}</p>
              </div>
            </div>
          </section>

          <!-- Detail Reservasi -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-pink-100 text-pink-600">
                <i class="fa-solid fa-calendar-days text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">Detail Reservasi</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Outlet</p>
                <p class="text-slate-800 font-medium">{{ reservation.outlet?.nama_outlet || '–' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Tanggal</p>
                <p class="text-slate-800">{{ formatDate(reservation.reservation_date) }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Waktu</p>
                <p class="text-slate-800">{{ formatTime(reservation.reservation_time) }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Jumlah Tamu</p>
                <p class="text-slate-800">{{ reservation.number_of_guests ?? '–' }} orang</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Area</p>
                <span :class="reservation.smoking_preference === 'smoking' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'" class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium">
                  {{ reservation.smoking_preference === 'smoking' ? 'Smoking Area' : 'Non-Smoking Area' }}
                </span>
              </div>
              <div class="sm:col-span-2">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Catatan Khusus</p>
                <p class="text-slate-800 whitespace-pre-wrap">{{ reservation.special_requests || '–' }}</p>
              </div>
            </div>
          </section>

          <!-- DP & Sales -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600">
                <i class="fa-solid fa-money-bill-wave text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">DP & Sales</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">DP (Down Payment)</p>
                <p class="text-slate-800 font-semibold">{{ formatDp(reservation.dp) }}</p>
              </div>
              <div v-if="reservation.dp != null && reservation.dp > 0 && (reservation.dp_code)">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Kode DP (untuk POS)</p>
                <p class="font-mono text-lg font-bold text-emerald-700">{{ reservation.dp_code }}</p>
              </div>
              <div v-if="reservation.dp != null && reservation.dp > 0">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Jenis Pembayaran</p>
                <p class="text-slate-800">{{ (reservation.payment_type || reservation.paymentType)?.name || '–' }}</p>
              </div>
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Sumber</p>
                <span :class="reservation.from_sales ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-600'" class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium">
                  {{ reservation.from_sales ? 'Dari Sales' : 'Bukan' }}
                </span>
                <p v-if="reservation.from_sales && salesName" class="text-slate-700 text-sm mt-2">{{ salesName }}</p>
              </div>
            </div>
          </section>

          <!-- Menu -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 text-amber-600">
                <i class="fa-solid fa-utensils text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">Menu</h2>
            </div>
            <div class="p-6 space-y-4">
              <p class="text-slate-800 whitespace-pre-wrap">{{ reservation.menu || '–' }}</p>
              <div v-if="reservation.menu_file || reservation.menu_file_url" class="pt-2 border-t border-slate-100">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1.5">File menu</p>
                <a
                  :href="menuFileDownloadUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  download
                  class="inline-flex items-center gap-2 text-rose-600 hover:text-rose-700 font-medium text-sm"
                >
                  <i class="fa-solid fa-file-arrow-down"></i>
                  {{ menuFileName }}
                  <i class="fa-solid fa-external-link-alt text-xs"></i>
                </a>
              </div>
            </div>
          </section>

          <!-- Info sistem -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-200 text-slate-600">
                <i class="fa-solid fa-info-circle text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">Informasi</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Dibuat oleh</p>
                <p class="text-slate-800">{{ creatorName }}</p>
              </div>
              <div v-if="reservation.created_at">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Tanggal dibuat</p>
                <p class="text-slate-800">{{ formatDateTime(reservation.created_at) }}</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Footer actions -->
        <div class="flex flex-col-reverse sm:flex-row gap-3 mt-8">
          <Link
            :href="route('reservations.index')"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition"
          >
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Daftar
          </Link>
          <Link
            :href="route('reservations.edit', { reservation: reservation.id })"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 shadow-lg shadow-pink-500/25 transition"
          >
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Reservasi
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  reservation: Object
});

const reservation = computed(() => props.reservation || {});

const menuFileName = computed(() => {
  const path = reservation.value.menu_file;
  if (!path || typeof path !== 'string') return 'Unduh file';
  return path.split(/[/\\]/).pop() || 'Unduh file';
});

/** URL untuk buka/unduh file menu via endpoint (lebih andal daripada direct storage link). */
const menuFileDownloadUrl = computed(() => {
  if (!reservation.value.id || !reservation.value.menu_file) return null;
  return route('reservations.menu-file', { reservation: reservation.value.id });
});

const creatorName = computed(() => {
  const c = reservation.value.creator;
  if (!c) return '–';
  return c.nama_lengkap || c.name || '–';
});

const salesName = computed(() => {
  const s = reservation.value.sales_user || reservation.value.salesUser;
  if (!s) return '';
  return s.nama_lengkap || s.name || '';
});

function formatDate(date) {
  if (!date) return '–';
  return new Date(date).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
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
    case 'cancelled': return 'bg-rose-100 text-rose-800';
    default: return 'bg-amber-100 text-amber-800';
  }
}

function getStatusText(status) {
  switch (status) {
    case 'confirmed': return 'Dikonfirmasi';
    case 'cancelled': return 'Dibatalkan';
    default: return 'Pending';
  }
}
</script>
