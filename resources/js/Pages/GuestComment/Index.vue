<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

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

      <div class="bg-white rounded-2xl shadow-xl overflow-x-auto">
        <table class="w-full min-w-[800px] divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">ID</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Tamu</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Outlet</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Dibuat</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!forms.data?.length">
              <td colspan="6" class="px-4 py-10 text-center text-gray-400">Belum ada data.</td>
            </tr>
            <tr v-for="row in forms.data" :key="row.id" class="hover:bg-blue-50/50">
              <td class="px-4 py-3 font-mono text-sm">#{{ row.id }}</td>
              <td class="px-4 py-3">{{ row.guest_name || '—' }}</td>
              <td class="px-4 py-3">{{ row.outlet?.nama_outlet || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="row.status === 'verified' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ statusLabel(row.status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ row.created_at ? new Date(row.created_at).toLocaleString('id-ID') : '—' }}
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <Link
                    v-if="row.status !== 'verified'"
                    :href="route('guest-comment-forms.verify', row.id)"
                    class="text-sm font-semibold text-blue-600 hover:underline"
                  >Verifikasi</Link>
                  <Link
                    :href="route('guest-comment-forms.show', row.id)"
                    class="text-sm font-semibold text-gray-600 hover:underline"
                  >Detail</Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="forms.links?.length > 3" class="mt-4 flex flex-wrap gap-2 justify-center">
        <template v-for="(link, i) in forms.links" :key="i">
          <Link
            v-if="link.url"
            :href="link.url"
            class="px-3 py-1 rounded-lg text-sm border"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white hover:bg-gray-50 border-gray-200'"
            v-html="link.label"
          />
          <span
            v-else
            class="px-3 py-1 rounded-lg text-sm border text-gray-400 border-gray-200"
            v-html="link.label"
          />
        </template>
      </div>
    </div>
  </AppLayout>
</template>
