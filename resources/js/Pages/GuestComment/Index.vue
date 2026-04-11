<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import VueEasyLightbox from 'vue-easy-lightbox';
import GuestCommentUserAvatar from '@/Components/GuestCommentUserAvatar.vue';

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

const avatarLightboxVisible = ref(false);
const avatarLightboxImages = ref([]);
const avatarLightboxIndex = ref(0);

function openAvatarLightbox({ src }) {
  if (!src) return;
  avatarLightboxImages.value = [src];
  avatarLightboxIndex.value = 0;
  avatarLightboxVisible.value = true;
}

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

/** 1–4 bintang: Poor / Average / Good / Excellent */
function ratingStarCount(v) {
  if (!v) return 0;
  const s = String(v).toLowerCase();
  const map = { poor: 1, average: 2, good: 3, excellent: 4 };
  return map[s] ?? 0;
}

const RATING_LABELS = [
  { key: 'rating_service', label: 'Service', abbr: 'Svc' },
  { key: 'rating_food', label: 'Food', abbr: 'Fd' },
  { key: 'rating_beverage', label: 'Beverage', abbr: 'Bv' },
  { key: 'rating_cleanliness', label: 'Cleanliness', abbr: 'Cl' },
  { key: 'rating_staff', label: 'Staff', abbr: 'St' },
  { key: 'rating_value', label: 'Value', abbr: 'V' },
];

function ratingRowsForTable(row) {
  return RATING_LABELS.map(({ key, label, abbr }) => ({
    label,
    abbr,
    count: ratingStarCount(row[key]),
  }));
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
    <div class="guest-comment-index w-full max-w-[100vw] py-6 sm:py-8 px-3 sm:px-4 lg:px-6 xl:px-8 box-border">
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

      <div class="flex flex-wrap gap-3 mb-4 items-end w-full">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama / telepon / komentar..."
          class="w-full sm:w-64 min-w-0 flex-1 sm:flex-none px-4 py-2 rounded-xl border border-blue-200 shadow-sm focus:ring-2 focus:ring-blue-400"
          @keyup.enter="applyFilters"
        />
        <select v-if="canChooseOutlet" v-model="idOutlet" class="w-full sm:w-auto min-w-[200px] px-4 py-2 rounded-xl border border-blue-200 shadow-sm" @change="applyFilters">
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
        Rating: Svc Service, Fd Food, Bv Beverage, Cl Cleanliness, St Staff, V Value — ★ 1–4 = Poor → Excellent (kosong = belum diisi).
      </p>

      <div class="bg-white rounded-2xl shadow-xl overflow-x-auto w-full">
        <table class="w-full min-w-[1000px] divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-2 sm:px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase w-12">No.</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase">Tamu</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[200px] w-[220px]">Rating</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[180px]">Komentar</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[120px]">Outlet</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase w-[120px]">Status</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[150px]">Pencatat</th>
              <th class="px-3 py-3 text-left text-xs font-bold text-blue-700 uppercase min-w-[150px]">Verifikasi</th>
              <th class="px-2 py-3 text-center text-xs font-bold text-blue-700 uppercase w-[100px]">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!forms.data?.length">
              <td colspan="9" class="px-4 py-10 text-center text-gray-400">Belum ada data.</td>
            </tr>
            <tr v-for="(row, idx) in forms.data" :key="row.id" class="hover:bg-blue-50/50 align-top">
              <td class="px-2 sm:px-3 py-3 text-sm text-gray-700 font-medium tabular-nums">{{ rowNumber(idx) }}</td>
              <td class="px-3 py-3 min-w-0">
                <div class="font-medium text-gray-900 break-words">{{ row.guest_name || '—' }}</div>
                <div class="mt-1.5 text-xs text-gray-600 space-y-0.5">
                  <div v-if="row.guest_address" class="break-words">{{ row.guest_address }}</div>
                  <div v-if="row.guest_phone" class="text-gray-700">{{ row.guest_phone }}</div>
                  <div v-if="!row.guest_address && !row.guest_phone" class="text-gray-400">Alamat / HP belum diisi</div>
                </div>
              </td>
              <td class="px-3 py-3 min-w-0 align-top">
                <div
                  class="grid grid-cols-3 gap-x-2 gap-y-0.5 text-[10px] leading-tight text-gray-800 max-w-[14rem]"
                  role="list"
                  :aria-label="'Rating tamu'"
                >
                  <div
                    v-for="r in ratingRowsForTable(row)"
                    :key="r.label"
                    class="flex items-center gap-1 min-w-0"
                    role="listitem"
                  >
                    <span class="text-gray-500 shrink-0 font-medium" :title="r.label">{{ r.abbr }}</span>
                    <span v-if="r.count > 0" class="inline-flex items-center gap-px text-amber-500" :aria-label="`${r.label}: ${r.count} bintang`">
                      <i v-for="n in r.count" :key="n" class="fa-solid fa-star text-[8px]" aria-hidden="true"></i>
                    </span>
                    <span v-else class="text-gray-300">—</span>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 min-w-0">
                <p
                  class="text-sm text-gray-800 whitespace-pre-wrap break-words line-clamp-4"
                  :title="row.comment_text || ''"
                >
                  {{ row.comment_text || '—' }}
                </p>
              </td>
              <td class="px-3 py-3 text-sm break-words min-w-0">{{ row.outlet?.nama_outlet || '—' }}</td>
              <td class="px-3 py-3">
                <span
                  class="inline-block px-2 py-1 rounded-full text-xs font-semibold"
                  :class="row.status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ statusLabel(row.status) }}
                </span>
              </td>
              <td class="px-3 py-3 text-sm text-gray-800 min-w-0">
                <div class="flex gap-2 min-w-0">
                  <GuestCommentUserAvatar
                    v-if="row.creator"
                    :user="row.creator"
                    size-class="w-9 h-9 sm:w-10 sm:h-10"
                    @preview="openAvatarLightbox"
                  />
                  <div class="min-w-0 flex-1">
                    <div class="font-medium text-gray-900 break-words">{{ row.creator?.nama_lengkap || '—' }}</div>
                    <div class="mt-1 text-[11px] text-gray-500 leading-snug">
                      <span class="text-gray-400">Dibuat:</span>
                      {{ row.created_at ? new Date(row.created_at).toLocaleString('id-ID') : '—' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-3 py-3 text-sm text-gray-800 min-w-0">
                <template v-if="row.status === 'verified' && row.verifier">
                  <div class="flex gap-2 min-w-0">
                    <GuestCommentUserAvatar
                      :user="row.verifier"
                      size-class="w-9 h-9 sm:w-10 sm:h-10"
                      @preview="openAvatarLightbox"
                    />
                    <div class="min-w-0 flex-1">
                      <div class="font-medium text-gray-900 break-words">{{ row.verifier.nama_lengkap }}</div>
                      <div v-if="row.verified_at" class="mt-1 text-[11px] text-gray-500 leading-snug">
                        {{ new Date(row.verified_at).toLocaleString('id-ID') }}
                      </div>
                    </div>
                  </div>
                </template>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
              <td class="px-2 py-3">
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

      <VueEasyLightbox
        :visible="avatarLightboxVisible"
        :imgs="avatarLightboxImages"
        :index="avatarLightboxIndex"
        @hide="avatarLightboxVisible = false"
      />
    </div>
  </AppLayout>
</template>
