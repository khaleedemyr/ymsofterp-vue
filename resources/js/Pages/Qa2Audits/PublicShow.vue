<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  audit: Object,
});

const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

const groupedItems = computed(() => {
  const map = new Map();
  for (const item of (props.audit?.items || []).filter((x) => x.result === 'NC')) {
    const catName = item.category_name || 'Tanpa Kategori';
    const subName = item.subcategory_name || 'Tanpa Subcategory';
    const key = `${catName}__${subName}`;
    if (!map.has(key)) {
      map.set(key, {
        key,
        category: catName,
        subcategory: subName,
        items: [],
      });
    }
    map.get(key).items.push(item);
  }
  return Array.from(map.values());
});

function formatScore(value) {
  return `${Number(value || 0).toFixed(0)}%`;
}

function resolveAuditResult(score) {
  const numeric = Number(score || 0);
  if (numeric >= 91) {
    return {
      label: 'EXCELLENT',
      className: 'bg-emerald-500 text-white',
    };
  }
  if (numeric >= 85) {
    return {
      label: 'SATISFACTORY',
      className: 'bg-yellow-300 text-gray-900',
    };
  }
  return {
    label: 'TO IMPROVE',
    className: 'bg-red-600 text-white',
  };
}

const overallAuditResult = computed(() => resolveAuditResult(props.audit?.summary_total?.score || 0));

function resultBadgeClass(result) {
  if (result === 'C') return 'bg-emerald-100 text-emerald-700';
  if (result === 'NC') return 'bg-rose-100 text-rose-700';
  if (result === 'NA') return 'bg-slate-200 text-slate-700';
  return 'bg-amber-100 text-amber-700';
}

function resolveMediaUrls(mediaList = []) {
  return mediaList
    .filter((media) => media?.media_type === 'photo' && media?.url)
    .map((media) => media.url);
}

function openPhotoLightbox(mediaList, media) {
  const urls = resolveMediaUrls(mediaList);
  if (!urls.length) return;
  const currentIndex = Math.max(0, urls.findIndex((url) => url === media.url));
  lightboxImages.value = urls;
  lightboxIndex.value = currentIndex;
  lightboxVisible.value = true;
}
</script>

<template>
  <Head :title="`QA Audit ${audit?.audit_number || ''}`" />

  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white shadow-sm">
      <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <div class="min-w-0">
          <p class="text-xs uppercase tracking-wide text-gray-500">QA Audit</p>
          <h1 class="truncate text-lg font-semibold text-gray-900 sm:text-xl">{{ audit?.audit_number || '-' }}</h1>
        </div>
        <span class="hidden flex-shrink-0 text-xs text-gray-400 sm:inline">YMSoft ERP</span>
      </div>
    </header>

    <main class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Outlet Name</p>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ audit?.outlet_name || '-' }}</p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Template</p>
            <p class="mt-1 text-sm font-medium text-gray-900">{{ audit?.template_name || '-' }}</p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
            <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="audit?.status === 'submitted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
              {{ audit?.status || '-' }}
            </span>
          </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
          Audit Date: {{ audit?.audit_datetime || '-' }} |
          Time Start: {{ audit?.audit_time_start || '-' }} |
          Time End: {{ audit?.audit_time_end || '-' }}
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
          <h2 class="mb-3 text-sm font-semibold text-gray-900">Auditor</h2>
          <div v-if="audit?.auditors?.length" class="space-y-2">
            <div v-for="person in audit.auditors" :key="`auditor-${person.id}`" class="rounded-lg border border-gray-200 p-2">
              <div class="text-sm font-medium text-gray-900">{{ person.name }}</div>
              <div v-if="person.jabatan" class="text-xs text-indigo-600">{{ person.jabatan }}</div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">-</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
          <h2 class="mb-3 text-sm font-semibold text-gray-900">Auditee</h2>
          <div v-if="audit?.auditees?.length" class="space-y-2">
            <div v-for="person in audit.auditees" :key="`auditee-${person.id}`" class="rounded-lg border border-gray-200 p-2">
              <div class="text-sm font-medium text-gray-900">{{ person.name }}</div>
              <div v-if="person.jabatan" class="text-xs text-indigo-600">{{ person.jabatan }}</div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">-</p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">QA Audit Detail Summary</h2>
        <div class="overflow-hidden rounded-lg border border-gray-200">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-amber-900 text-white">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase">Category</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Compliant</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Non-Compliant</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Non-Applicable</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Score</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="row in audit?.summary_rows || []" :key="row.id">
                  <td class="px-3 py-2 text-sm font-semibold uppercase text-gray-900">{{ row.name }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.non_compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.non_applicable }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ formatScore(row.score) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-amber-900 text-white">
                <tr>
                  <td class="px-3 py-2 text-sm font-semibold">TOTAL</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ audit?.summary_total?.compliant || 0 }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ audit?.summary_total?.non_compliant || 0 }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ audit?.summary_total?.non_applicable || 0 }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ formatScore(audit?.summary_total?.score || 0) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <div class="bg-gray-100 px-4 py-2 text-lg font-bold text-gray-900">AUDIT RESULT % :</div>
        <div class="divide-y divide-gray-200">
          <div class="grid grid-cols-12 items-center">
            <div class="col-span-8 bg-gray-200 px-4 py-2 text-2xl font-bold text-gray-900">
              EXCELLENT
              <span class="float-right">:</span>
            </div>
            <div class="col-span-4 bg-emerald-500 px-4 py-2 text-right text-2xl font-bold text-white">91 - 100%</div>
          </div>

          <div class="grid grid-cols-12 items-center">
            <div class="col-span-8 bg-gray-200 px-4 py-2 text-2xl font-bold text-gray-900">
              SATISFACTORY
              <span class="float-right">:</span>
            </div>
            <div class="col-span-4 bg-yellow-300 px-4 py-2 text-right text-2xl font-bold text-gray-900">85-90%</div>
          </div>

          <div class="grid grid-cols-12 items-center">
            <div class="col-span-8 bg-gray-200 px-4 py-2 text-2xl font-bold text-gray-900">
              TO IMPROVE
              <span class="float-right">:</span>
            </div>
            <div class="col-span-4 bg-red-600 px-4 py-2 text-right text-2xl font-bold text-white">&lt;85%</div>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-sm font-semibold text-gray-500">Overall Audit Result</div>
        <div class="mt-2 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold" :class="overallAuditResult.className">
          <span>{{ overallAuditResult.label }}</span>
          <span>({{ formatScore(audit?.summary_total?.score || 0) }})</span>
        </div>
      </div>

      <div class="space-y-4">
        <div v-for="group in groupedItems" :key="group.key" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="mb-3">
            <div class="bg-gray-50 px-4 py-3">
              <h3 class="text-sm font-semibold text-gray-900">{{ group.category }}</h3>
              <p class="text-xs text-gray-500">{{ group.subcategory }}</p>
            </div>
          </div>

          <div class="space-y-3 p-3">
            <div v-for="item in group.items" :key="item.id" class="rounded-lg border border-gray-200 p-3">
              <div class="mb-2 flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.parameter_code || '-' }}</p>
                  <p class="text-sm font-medium text-gray-900">{{ item.parameter_text }}</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="resultBadgeClass(item.result)">
                  {{ item.result || 'Belum diisi' }}
                </span>
              </div>

              <div class="rounded-lg border border-slate-200 bg-white p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Temuan Auditor</p>
                <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap">{{ item.comment || '-' }}</p>
                <p class="mt-1 text-xs text-gray-500">Due date: {{ item.due_date || '-' }}</p>
              </div>

              <div v-if="item.media?.length" class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                <div v-for="media in item.media" :key="`item-media-${media.id}`" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                  <img
                    v-if="media.media_type === 'photo'"
                    :src="media.url"
                    class="h-24 w-full cursor-pointer object-cover"
                    @click="openPhotoLightbox(item.media, media)"
                  >
                  <div v-else class="relative h-24 w-full bg-gray-900">
                    <video :src="media.url" class="h-24 w-full object-cover" controls />
                  </div>
                </div>
              </div>

              <div v-if="item.cap?.action_plan || item.cap?.media?.length" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3">
                <p class="mb-2 text-sm font-semibold text-rose-700">Corrective Action Plan</p>
                <p v-if="item.cap?.action_plan" class="mb-2 whitespace-pre-wrap text-sm text-gray-800">{{ item.cap.action_plan }}</p>
                <div class="grid gap-2 text-xs text-gray-600 md:grid-cols-2">
                  <div v-if="item.cap?.target_date">Target: {{ item.cap.target_date }}</div>
                  <div v-if="item.cap?.status">Status: {{ item.cap.status }}</div>
                </div>
                <div v-if="item.cap?.media?.length" class="mt-3">
                  <p class="mb-2 text-xs font-semibold text-rose-700">Media CAP ({{ item.cap.media.length }})</p>
                  <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    <div v-for="media in item.cap.media" :key="`cap-media-${media.id}`" class="overflow-hidden rounded-lg border border-rose-200 bg-white">
                      <img
                        v-if="media.media_type === 'photo'"
                        :src="media.url"
                        class="h-24 w-full cursor-pointer object-cover"
                        @click="openPhotoLightbox(item.cap.media, media)"
                      >
                      <div v-else class="relative h-24 w-full bg-gray-900">
                        <video :src="media.url" class="h-24 w-full object-cover" controls />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!groupedItems.length" class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
          Tidak ada parameter NC pada audit ini.
        </div>
      </div>
    </main>

    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </div>
</template>
