<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  filters: Object,
  outlets: Array,
  rows: Array,
});

const outletId = ref(props.filters?.outlet_id || '');
const fromDate = ref(props.filters?.from_date || '');
const toDate = ref(props.filters?.to_date || '');
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const debouncedFilter = debounce(() => {
  router.get(route('qa2-audits.report-nc-detail'), {
    outlet_id: outletId.value,
    from_date: fromDate.value,
    to_date: toDate.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 250);

watch([outletId, fromDate, toDate], debouncedFilter);

function backToIndex() {
  router.visit(route('qa2-audits.index'));
}

function resetFilter() {
  outletId.value = '';
  const today = new Date().toISOString().slice(0, 10);
  fromDate.value = `${today.slice(0, 7)}-01`;
  toDate.value = today;
}

const exportUrl = computed(() => route('qa2-audits.report-nc-detail.export', {
  outlet_id: outletId.value || undefined,
  from_date: fromDate.value || undefined,
  to_date: toDate.value || undefined,
}));

function photoMediaList(mediaList = []) {
  return (mediaList || []).filter((m) => String(m?.media_type || '').toLowerCase() === 'photo' && m?.url);
}

function openPhotoLightbox(mediaList, currentMedia) {
  const photos = photoMediaList(mediaList);
  if (!photos.length) return;
  const urls = photos.map((m) => m.url);
  const currentUrl = currentMedia?.url;
  const currentIndex = Math.max(0, urls.findIndex((url) => url === currentUrl));
  lightboxImages.value = urls;
  lightboxIndex.value = currentIndex;
  lightboxVisible.value = true;
}
</script>

<template>
  <AppLayout title="QA2 NC Detail Report">
    <div class="space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">QA2 NC Detail Report</h1>
            <p class="text-sm text-gray-500">Detail semua parameter NC per audit, outlet, auditor/auditee, komentar, dan attachment.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="backToIndex"
            >
              Back to QA2 Audits
            </button>
            <a
              :href="exportUrl"
              class="rounded-lg border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50"
            >
              Export Excel
            </a>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Outlet</label>
            <select v-model="outletId" class="w-full rounded-lg border-gray-300 text-sm">
              <option value="">Semua Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="String(outlet.id_outlet)">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Date From</label>
            <input v-model="fromDate" type="date" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-600">Date To</label>
            <input v-model="toDate" type="date" class="w-full rounded-lg border-gray-300 text-sm">
          </div>
          <div class="flex items-end">
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="resetFilter"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Audit</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Auditor</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Auditee</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Kategori</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Sub Kategori</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Parameter</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Komentar</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Attachment Item</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">CAP</th>
                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500">Attachment CAP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="(row, index) in (rows || [])" :key="`${row.audit_id}-${index}-${row.parameter_code}`" class="align-top">
                <td class="px-3 py-2 text-xs text-gray-700">
                  <div class="font-semibold text-gray-900">{{ row.audit_number }}</div>
                  <div>{{ row.audit_datetime || '-' }}</div>
                  <div class="mt-1 text-[11px] text-gray-500">{{ row.template_name || '-' }}</div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-700">{{ row.outlet_name || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700 whitespace-pre-line">{{ row.auditors || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700 whitespace-pre-line">{{ row.auditees || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700">{{ row.category_name || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700">{{ row.subcategory_name || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700">
                  <div class="font-semibold text-gray-900">{{ row.parameter_code || '-' }}</div>
                  <div class="mt-1 whitespace-pre-line">{{ row.parameter_text || '-' }}</div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-700 whitespace-pre-line">{{ row.comment || '-' }}</td>
                <td class="px-3 py-2 text-xs text-gray-700">
                  <div v-if="photoMediaList(row.item_media).length" class="grid grid-cols-2 gap-1">
                    <img
                      v-for="media in photoMediaList(row.item_media)"
                      :key="`item-photo-${media.id}`"
                      :src="media.url"
                      class="h-14 w-14 cursor-pointer rounded border border-gray-200 object-cover"
                      @click="openPhotoLightbox(row.item_media, media)"
                    >
                  </div>
                  <div v-else>-</div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-700">
                  <div class="font-semibold">Status: {{ row.cap_status || '-' }}</div>
                  <div class="mt-1">Target: {{ row.cap_target_date || '-' }}</div>
                  <div class="mt-1 whitespace-pre-line">{{ row.cap_action_plan || '-' }}</div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-700">
                  <div v-if="photoMediaList(row.cap_media).length" class="grid grid-cols-2 gap-1">
                    <img
                      v-for="media in photoMediaList(row.cap_media)"
                      :key="`cap-photo-${media.id}`"
                      :src="media.url"
                      class="h-14 w-14 cursor-pointer rounded border border-gray-200 object-cover"
                      @click="openPhotoLightbox(row.cap_media, media)"
                    >
                  </div>
                  <div v-else>-</div>
                </td>
              </tr>
              <tr v-if="!(rows || []).length">
                <td colspan="11" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada data NC pada filter ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
      />
    </div>
  </AppLayout>
</template>
