<script setup>
import { ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ search: '' }) },
  justusKunestWebUrl: { type: String, default: '' },
  previewKey: { type: String, default: '' },
});

const page = usePage();
const search = ref(props.filters.search || '');

watch(
  () => page.props.flash?.success,
  (message) => {
    if (message) {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: message, confirmButtonText: 'OK' });
    }
  },
  { immediate: true },
);

function handleSearch() {
  router.get('/web-profile/outlet-landings', { search: search.value }, {
    preserveState: true,
    replace: true,
  });
}

function openPreviewUrl(url) {
  if (!url) {
    Swal.fire({
      icon: 'info',
      title: 'Preview tidak tersedia',
      text: !props.justusKunestWebUrl
        ? 'Set env JUSTUS_KUNEST_WEB_URL di ymsofterp.'
        : 'Landing belum siap atau slug belum diisi.',
    });
    return;
  }
  window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
  <AppLayout title="Outlet Landing Pages">
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link href="/web-profile" class="text-sm text-gray-500 hover:text-gray-700">&larr; Web Profile</Link>
          <h1 class="text-2xl font-bold text-gray-800 mt-1">Outlet Landing Pages</h1>
          <p class="text-sm text-gray-500 mt-1">Kelola landing page per outlet untuk Justus Kunest web</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="handleSearch" class="flex gap-3">
          <TextInput v-model="search" class="flex-1" placeholder="Cari outlet..." />
          <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Cari</button>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Outlet</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Slug</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aktif</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Siap Tampil</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.outlet_id" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900">{{ row.outlet_name }}</div>
                <div v-if="row.outlet_address" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ row.outlet_address }}</div>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ row.slug || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex px-2 py-0.5 rounded text-xs font-semibold"
                  :class="row.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                >
                  {{ row.is_active ? 'Ya' : 'Tidak' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex px-2 py-0.5 rounded text-xs font-semibold"
                  :class="row.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                >
                  {{ row.is_published ? 'Ya' : 'Belum' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="inline-flex flex-wrap items-center justify-center gap-2">
                  <button
                    v-if="row.preview_draft_url"
                    type="button"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-gray-800 text-white hover:bg-gray-900 text-xs"
                    title="Preview draft"
                    @click="openPreviewUrl(row.preview_draft_url)"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </button>
                  <button
                    v-if="row.preview_live_url"
                    type="button"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-xs"
                    title="Preview live"
                    @click="openPreviewUrl(row.preview_live_url)"
                  >
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </button>
                  <Link
                    :href="`/web-profile/outlet-landings/${row.outlet_id}/edit`"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700"
                  >
                    <i class="fa-solid fa-pen text-xs"></i> Edit
                  </Link>
                </div>
              </td>
            </tr>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-gray-500">Tidak ada outlet ditemukan.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
