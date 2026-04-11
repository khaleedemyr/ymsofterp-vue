<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  forms: Object,
  filters: Object,
  outlets: { type: Array, default: () => [] },
  canChooseOutlet: { type: Boolean, default: true },
  lockedOutlet: { type: Object, default: null },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const idOutlet = ref(
  props.filters?.id_outlet != null && props.filters.id_outlet !== ''
    ? String(props.filters.id_outlet)
    : ''
);
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const paginationSummary = computed(() => {
  const p = props.forms;
  if (!p?.total) return '';
  const from = p.from ?? 0;
  const to = p.to ?? 0;
  let s = `Menampilkan ${from}–${to} dari ${p.total} entri`;
  if (p.last_page > 1) {
    s += ` · Halaman ${p.current_page} / ${p.last_page}`;
  }
  return s;
});

function applyFilters() {
  const q = {
    search: search.value,
    status: status.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  };
  if (props.canChooseOutlet && idOutlet.value) {
    q.id_outlet = idOutlet.value;
  }
  router.get('/guest-comment-forms', q, { preserveState: true, replace: true });
}

function statusLabel(s) {
  if (s === 'verified') return 'Terverifikasi';
  if (s === 'pending_verification') return 'Menunggu verifikasi';
  return s || '-';
}

function rowNumber(index) {
  const p = props.forms;
  const page = p?.current_page ?? 1;
  const perPage = p?.per_page ?? 15;
  return (page - 1) * perPage + index + 1;
}

/** Singkatan rating (Poor/Average/Good/Excellent) untuk tabel */
const R_ABBR = { poor: 'P', average: 'A', good: 'G', excellent: 'E' };

function ratingLetter(v) {
  if (!v) return '—';
  const s = String(v).toLowerCase();
  return R_ABBR[s] ?? String(v).charAt(0).toUpperCase();
}

/** Satu baris ringkas: Sv Fd Bv Cl St V */
function ratingsSummary(row) {
  const parts = [
    ['Sv', row.rating_service],
    ['Fd', row.rating_food],
    ['Bv', row.rating_beverage],
    ['Cl', row.rating_cleanliness],
    ['St', row.rating_staff],
    ['V', row.rating_value],
  ];
  return parts.map(([lab, val]) => `${lab} ${ratingLetter(val)}`).join(' · ');
}

async function confirmDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus guest comment?',
    text: 'Data dan foto formulir akan dihapus permanen.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;

  router.delete(route('guest-comment-forms.destroy', row.id), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({
        icon: 'success',
        title: 'Terhapus',
        timer: 1600,
        showConfirmButton: false,
      });
    },
    onError: () => {
      Swal.fire({ icon: 'error', title: 'Gagal menghapus', text: 'Coba lagi atau hubungi admin.' });
    },
  });
}
</script>

<template>
  <AppLayout>
    <div class="w-full py-8 px-4 max-w-7xl mx-auto">
      <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-800 rounded-xl">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded-xl">
        {{ $page.props.flash.error }}
      </div>

      <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-comment-dots text-blue-500"></i>
          Guest Comment (OCR)
        </h1>
        <Link
          :href="route('guest-comment-forms.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold"
        >
          + Unggah formulir
        </Link>
      </div>

      <div v-if="!canChooseOutlet && lockedOutlet" class="mb-3 inline-flex items-center gap-2 bg-blue-50 text-blue-900 px-4 py-2 rounded-xl text-sm font-semibold">
        <i class="fa-solid fa-store"></i>
        Hanya data outlet: <span class="font-bold">{{ lockedOutlet.nama_outlet }}</span>
      </div>
      <div v-else-if="!canChooseOutlet && !lockedOutlet" class="mb-3 inline-flex items-center gap-2 bg-amber-50 text-amber-900 px-4 py-2 rounded-xl text-sm font-semibold">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Akun tidak memiliki outlet — daftar kosong.
      </div>

      <div class="flex flex-wrap gap-3 mb-4 items-end">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama / telepon / komentar..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow-sm focus:ring-2 focus:ring-blue-400"
          @keyup.enter="applyFilters"
        />
        <select v-if="canChooseOutlet" v-model="idOutlet" class="min-w-[200px] px-4 py-2 rounded-xl border border-blue-200 shadow-sm" @change="applyFilters">
          <option value="">Semua outlet</option>
          <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
        </select>
        <div class="flex flex-wrap items-center gap-2">
          <label class="text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">Dari</label>
          <input
            v-model="dateFrom"
            type="date"
            class="px-3 py-2 rounded-xl border border-blue-200 shadow-sm text-sm"
            @change="applyFilters"
          />
          <label class="text-xs font-semibold text-gray-500 uppercase whitespace-nowrap">Sampai</label>
          <input
            v-model="dateTo"
            type="date"
            class="px-3 py-2 rounded-xl border border-blue-200 shadow-sm text-sm"
            @change="applyFilters"
          />
        </div>
        <select v-model="status" class="px-4 py-2 rounded-xl border border-blue-200 shadow-sm" @change="applyFilters">
          <option value="">Semua status</option>
          <option value="pending_verification">Menunggu verifikasi</option>
          <option value="verified">Terverifikasi</option>
        </select>
        <button type="button" class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 font-semibold" @click="applyFilters">
          Terapkan
        </button>
      </div>

      <p class="text-xs text-gray-500 mb-2 px-1">
        Kolom rating: Sv Service, Fd Food, Bv Beverage, Cl Cleanliness, St Staff, V Value — huruf
        <span class="font-mono">P/A/G/E</span> = Poor / Average / Good / Excellent.
      </p>

      <div class="bg-white rounded-2xl shadow-xl overflow-x-auto">
        <table class="w-full min-w-[1180px] divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase w-14">No.</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[200px]">Tamu</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[200px]">Rating</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[200px]">Komentar</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Pencatat</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Diverifikasi</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Dibuat</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!forms.data?.length">
              <td colspan="10" class="px-4 py-10 text-center text-gray-400">Belum ada data.</td>
            </tr>
            <tr v-for="(row, idx) in forms.data" :key="row.id" class="hover:bg-blue-50/50 align-top">
              <td class="px-4 py-3 text-sm text-gray-700 font-medium tabular-nums">{{ rowNumber(idx) }}</td>
              <td class="px-4 py-3 max-w-[240px]">
                <div class="font-medium text-gray-900">{{ row.guest_name || '—' }}</div>
                <div class="mt-1.5 text-xs text-gray-600 space-y-0.5">
                  <div v-if="row.guest_address" class="break-words">{{ row.guest_address }}</div>
                  <div v-if="row.guest_phone" class="text-gray-700">{{ row.guest_phone }}</div>
                  <div v-if="!row.guest_address && !row.guest_phone" class="text-gray-400">Alamat / HP belum diisi</div>
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-gray-800 max-w-[220px]">
                <div class="font-mono tracking-tight leading-relaxed">{{ ratingsSummary(row) }}</div>
              </td>
              <td class="px-4 py-3 max-w-[260px]">
                <p
                  class="text-sm text-gray-800 whitespace-pre-wrap break-words line-clamp-4"
                  :title="row.comment_text || ''"
                >
                  {{ row.comment_text || '—' }}
                </p>
              </td>
              <td class="px-4 py-3">{{ row.outlet?.nama_outlet || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="row.status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ statusLabel(row.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-800 max-w-[140px]">
                {{ row.creator?.nama_lengkap || '—' }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-800 max-w-[160px]">
                <template v-if="row.status === 'verified' && row.verifier">
                  <span class="font-medium">{{ row.verifier.nama_lengkap }}</span>
                  <span v-if="row.verified_at" class="block text-xs text-gray-500 mt-0.5">
                    {{ new Date(row.verified_at).toLocaleString('id-ID') }}
                  </span>
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                {{ row.created_at ? new Date(row.created_at).toLocaleString('id-ID') : '—' }}
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1 items-center">
                  <Link
                    v-if="row.status !== 'verified'"
                    :href="route('guest-comment-forms.verify', row.id)"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                    title="Verifikasi"
                    aria-label="Verifikasi"
                  >
                    <i class="fa-solid fa-clipboard-check text-base" aria-hidden="true"></i>
                  </Link>
                  <Link
                    :href="route('guest-comment-forms.show', row.id)"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 transition-colors"
                    title="Detail"
                    aria-label="Detail"
                  >
                    <i class="fa-solid fa-eye text-base" aria-hidden="true"></i>
                  </Link>
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 hover:bg-red-50 transition-colors"
                    title="Hapus"
                    aria-label="Hapus"
                    @click="confirmDelete(row)"
                  >
                    <i class="fa-solid fa-trash text-base" aria-hidden="true"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="forms.total > 0" class="mt-4 text-center text-sm text-gray-600">
        {{ paginationSummary }}
      </div>

      <div v-if="forms.last_page > 1" class="mt-3 flex flex-wrap gap-2 justify-center items-center">
        <template v-for="(link, i) in forms.links" :key="i">
          <Link
            v-if="link.url"
            :href="link.url"
            class="px-3 py-1.5 rounded-lg text-sm border min-w-[2.25rem] text-center"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50 border-gray-200'"
            v-html="link.label"
          />
          <span
            v-else
            class="px-3 py-1.5 rounded-lg text-sm border text-gray-400 border-gray-200 cursor-default min-w-[2.25rem] text-center"
            v-html="link.label"
          />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
