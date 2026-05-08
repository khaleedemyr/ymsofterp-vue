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
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Mode Pemesanan</p>
                <span
                  :class="reservation.order_mode === 'self_order' ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-700'"
                  class="inline-flex px-2.5 py-1 rounded-lg text-xs font-medium"
                >
                  {{ reservation.order_mode === 'self_order' ? 'Self Order' : 'Manual WhatsApp' }}
                </span>
              </div>
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

          <!-- Transaksi POS terkait -->
          <section v-if="linkedOrders && linkedOrders.length" class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-violet-100 text-violet-600">
                <i class="fa-solid fa-receipt text-lg"></i>
              </span>
              <h2 class="font-semibold text-slate-800">Transaksi POS Terkait</h2>
            </div>
            <div class="p-6">
              <p class="text-sm text-slate-500 mb-4">Order yang di-link ke reservasi ini (dari POS, dengan atau tanpa DP).</p>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500 font-medium">
                      <th class="py-2 pr-4">No. Bayar</th>
                      <th class="py-2 pr-4">Meja</th>
                      <th class="py-2 pr-4">Total</th>
                      <th class="py-2 pr-4">Waktu</th>
                      <th class="py-2 pr-4">Outlet</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="ord in linkedOrders" :key="ord.id" class="border-b border-slate-100 hover:bg-slate-50/50">
                      <td class="py-3 pr-4 font-medium text-slate-800">{{ ord.paid_number || '–' }}</td>
                      <td class="py-3 pr-4 text-slate-700">{{ ord.table || '–' }}</td>
                      <td class="py-3 pr-4 font-medium text-slate-800">{{ formatCurrency(ord.grand_total) }}</td>
                      <td class="py-3 pr-4 text-slate-600">{{ formatDateTime(ord.created_at) }}</td>
                      <td class="py-3 pr-4 text-slate-600">{{ ord.kode_outlet || '–' }}</td>
                    </tr>
                  </tbody>
                </table>
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
              <template v-if="selfOrders.length">
                <div
                  v-for="selfOrder in selfOrders"
                  :key="selfOrder.id"
                  class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-4"
                >
                  <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                      <p class="font-mono text-sm font-semibold text-violet-700">{{ selfOrder.order_no || '-' }}</p>
                      <p class="mt-1 text-xs text-slate-500">{{ formatDateTime(selfOrder.created_at) }}</p>
                      <p v-if="selfOrder.notes" class="mt-1 text-xs italic text-slate-600">Catatan order: {{ selfOrder.notes }}</p>
                    </div>
                    <div class="text-right">
                      <p class="text-xs uppercase tracking-wider text-slate-500">Total</p>
                      <p class="font-semibold text-slate-800">{{ formatCurrency(selfOrder.grand_total) }}</p>
                    </div>
                  </div>

                  <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                          <th class="py-2 pr-3">Item</th>
                          <th class="py-2 pr-3">Qty</th>
                          <th class="py-2 pr-3">Harga</th>
                          <th class="py-2 pr-3">Subtotal</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="item in selfOrder.items" :key="item.id" class="border-b border-slate-100 align-top">
                          <td class="py-2 pr-3">
                            <p class="font-medium text-slate-800">{{ item.item_name }}</p>
                            <p v-if="item.notes" class="mt-1 text-xs italic text-slate-500">Notes: {{ item.notes }}</p>
                            <div v-if="formatModifiers(item.modifiers).length" class="mt-1 flex flex-wrap gap-1.5">
                              <span
                                v-for="(modifierLabel, idx) in formatModifiers(item.modifiers)"
                                :key="`${item.id}-mod-${idx}`"
                                class="inline-flex items-center rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-medium text-violet-700"
                              >
                                {{ modifierLabel }}
                              </span>
                            </div>
                          </td>
                          <td class="py-2 pr-3 text-slate-700">{{ item.qty }}</td>
                          <td class="py-2 pr-3 text-slate-700">{{ formatCurrency(item.price) }}</td>
                          <td class="py-2 pr-3 font-medium text-slate-800">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>

              <p v-else class="text-slate-800 whitespace-pre-wrap">{{ reservation.menu || '–' }}</p>

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
  reservation: Object,
  linked_orders: Array,
  self_orders: Array,
});

const reservation = computed(() => props.reservation || {});
const linkedOrders = computed(() => props.linked_orders || []);
const selfOrders = computed(() => props.self_orders || []);

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

function formatCurrency(val) {
  if (val == null || val === '') return '–';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(val));
}

function formatModifiers(modifiers) {
  if (!modifiers) return [];

  let parsed = modifiers;
  if (typeof parsed === 'string') {
    try {
      parsed = JSON.parse(parsed);
    } catch {
      return [String(modifiers)];
    }
  }

  if (Array.isArray(parsed)) {
    return parsed
      .map((entry) => {
        if (entry == null) return null;
        if (typeof entry === 'string') return entry;
        if (typeof entry === 'object') {
          const name = entry.name || entry.label || entry.option_name || entry.modifier_name;
          const qty = Number(entry.qty || entry.quantity || 0);
          if (!name) return null;
          return qty > 1 ? `${name} x${qty}` : String(name);
        }
        return String(entry);
      })
      .filter(Boolean);
  }

  if (typeof parsed === 'object') {
    const labels = [];
    Object.entries(parsed).forEach(([groupKey, options]) => {
      if (options && typeof options === 'object' && !Array.isArray(options)) {
        Object.entries(options).forEach(([optionKey, qtyRaw]) => {
          const qty = Number(qtyRaw || 0);
          if (qty <= 0) return;
          labels.push(qty > 1 ? `${optionKey} x${qty}` : optionKey);
        });
        return;
      }

      if (Array.isArray(options)) {
        options.forEach((opt) => {
          if (opt == null) return;
          labels.push(String(opt));
        });
        return;
      }

      if (options) {
        labels.push(`${groupKey}: ${String(options)}`);
      }
    });
    return labels;
  }

  return [String(modifiers)];
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
    case 'confirmed': return 'Dikonfirmasi';
    case 'arrived': return 'Datang';
    case 'cancelled': return 'Dibatalkan';
    case 'no_show': return 'No Show';
    default: return 'Pending';
  }
}
</script>
