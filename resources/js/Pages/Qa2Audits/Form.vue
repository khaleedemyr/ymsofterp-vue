<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import AppLayout from '@/Layouts/AppLayout.vue';
import CameraModal from '@/Components/CameraModal.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  mode: String,
  audit: Object,
  outlets: Array,
  users: Array,
  templates: Array,
  tree: Array,
  permissions: Object,
});

const isCreate = computed(() => props.mode === 'create');
const canManage = computed(() => !!props.permissions?.can_manage);
const canFillCap = computed(() => !!props.permissions?.can_fill_cap);

const selectedOutlet = ref(null);
const selectedTemplate = ref(null);
const selectedAuditors = ref([]);
const selectedAuditees = ref([]);
const notes = ref('');
const search = ref('');
const saving = ref(false);
const lastSavedAt = ref('');
const capSaving = ref(false);
const capLastSavedAt = ref('');

const collapseCategory = ref({});
const collapseSubcategory = ref({});

const items = ref([]);

function mapAuditItem(row) {
  return {
    ...row,
    media: row.media || [],
    cap: row.cap || {
      id: null,
      action_plan: '',
      target_date: '',
      status: 'open',
      media: [],
    },
  };
}

function hydrateFromAudit() {
  if (isCreate.value || !props.audit) {
    return;
  }

  selectedOutlet.value = props.outlets.find((x) => Number(x.id_outlet) === Number(props.audit.outlet_id)) || null;
  selectedTemplate.value = props.templates.find((x) => Number(x.id) === Number(props.audit.template_id)) || null;
  selectedAuditors.value = props.users.filter((u) => (props.audit.auditor_ids || []).includes(u.id));
  selectedAuditees.value = props.users.filter((u) => (props.audit.auditee_ids || []).includes(u.id));
  notes.value = props.audit.notes || '';
  items.value = (props.audit.items || []).map((row) => {
    const item = mapAuditItem(row);
    if (item.result === 'NC') {
      ensureCap(item);
    }
    return item;
  });
}

hydrateFromAudit();

watch(() => [props.mode, props.audit?.id], () => {
  hydrateFromAudit();
});

const groupedItems = computed(() => buildGroupedItems(items.value));
const groupedCapItems = computed(() => buildGroupedItems(items.value.filter((item) => item.result === 'NC')));

function buildGroupedItems(sourceItems) {
  const keyword = (search.value || '').toLowerCase().trim();
  const map = new Map();

  for (const item of sourceItems) {
    const haystack = [
      item.parameter_code,
      item.parameter_text,
      item.category_name,
      item.subcategory_name,
    ].join(' ').toLowerCase();

    if (keyword && !haystack.includes(keyword)) {
      continue;
    }

    const catId = item.category_id || 0;
    const subId = item.subcategory_id || 0;
    const catKey = `c-${catId}`;
    const subKey = `${catKey}-s-${subId}`;

    if (!map.has(catKey)) {
      map.set(catKey, {
        key: catKey,
        name: item.category_name || 'Tanpa Kategori',
        subcategories: new Map(),
      });
    }

    const cat = map.get(catKey);
    if (!cat.subcategories.has(subKey)) {
      cat.subcategories.set(subKey, {
        key: subKey,
        name: item.subcategory_name || 'Tanpa Subcategory',
        items: [],
      });
    }

    cat.subcategories.get(subKey).items.push(item);
  }

  return Array.from(map.values()).map((cat) => ({
    ...cat,
    subcategories: Array.from(cat.subcategories.values()),
  }));
}

const categorySummaryRows = computed(() => {
  const map = new Map();

  for (const item of items.value) {
    const key = item.category_id || 0;
    if (!map.has(key)) {
      map.set(key, {
        id: key,
        name: item.category_name || 'Tanpa Kategori',
        compliant: 0,
        non_compliant: 0,
        non_applicable: 0,
      });
    }

    const row = map.get(key);
    if (item.result === 'C') {
      row.compliant += 1;
    } else if (item.result === 'NC') {
      row.non_compliant += 1;
    } else if (item.result === 'NA') {
      row.non_applicable += 1;
    }
  }

  return Array.from(map.values())
    .sort((a, b) => String(a.name).localeCompare(String(b.name)))
    .map((row, index) => {
      const denominator = row.compliant + row.non_compliant;
      const score = denominator > 0 ? (row.compliant / denominator) * 100 : 0;
      return {
        ...row,
        no: index + 1,
        score,
      };
    });
});

const summaryTotal = computed(() => {
  const total = {
    compliant: 0,
    non_compliant: 0,
    non_applicable: 0,
  };

  for (const row of categorySummaryRows.value) {
    total.compliant += row.compliant;
    total.non_compliant += row.non_compliant;
    total.non_applicable += row.non_applicable;
  }

  const denominator = total.compliant + total.non_compliant;
  const score = denominator > 0 ? (total.compliant / denominator) * 100 : 0;

  return {
    ...total,
    score,
  };
});

function formatScore(value) {
  return `${Number(value || 0).toFixed(0)}%`;
}

function resolveAuditResult(score) {
  const numeric = Number(score || 0);
  if (numeric >= 91) {
    return {
      label: 'EXCELLENT',
      range: '91 - 100%',
      className: 'bg-emerald-500 text-white',
    };
  }
  if (numeric >= 85) {
    return {
      label: 'SATISFACTORY',
      range: '85 - 90%',
      className: 'bg-yellow-300 text-gray-900',
    };
  }
  return {
    label: 'TO IMPROVE',
    range: '<85%',
    className: 'bg-red-600 text-white',
  };
}

const overallAuditResult = computed(() => resolveAuditResult(summaryTotal.value.score));

const draftPayload = computed(() => ({
  outlet_id: selectedOutlet.value?.id_outlet || null,
  auditor_ids: selectedAuditors.value.map((u) => u.id),
  auditee_ids: selectedAuditees.value.map((u) => u.id),
  notes: notes.value,
  items: items.value.map((item) => ({
    id: item.id,
    result: item.result || null,
    comment: item.comment || null,
    due_date: item.due_date || null,
  })),
}));

const autoSave = debounce(async () => {
  if (isCreate.value || !canManage.value) {
    return;
  }

  saving.value = true;
  try {
    await axios.post(route('qa2-audits.save-draft', props.audit.id), draftPayload.value);
    lastSavedAt.value = new Date().toLocaleTimeString();
  } catch (error) {
    console.error(error);
  } finally {
    saving.value = false;
  }
}, 1200);

watch(draftPayload, () => {
  autoSave();
}, { deep: true });

const capPayload = computed(() => ({
  caps: items.value
    .filter((item) => item.result === 'NC')
    .map((item) => ({
      audit_item_id: item.id,
      action_plan: item.cap?.action_plan || '',
      target_date: item.cap?.target_date || null,
      status: item.cap?.status || 'open',
    })),
}));

const autoSaveCap = debounce(async () => {
  if (isCreate.value || !canFillCap.value) {
    return;
  }

  if (!capPayload.value.caps.length) {
    return;
  }

  capSaving.value = true;
  try {
    const { data } = await axios.post(route('qa2-audits.save-cap', props.audit.id), capPayload.value);
    applyCapIds(data.caps || []);
    capLastSavedAt.value = new Date().toLocaleTimeString();
  } catch (error) {
    console.error(error);
  } finally {
    capSaving.value = false;
  }
}, 1200);

watch(capPayload, () => {
  autoSaveCap();
}, { deep: true });

function toggleCategory(key) {
  collapseCategory.value[key] = !collapseCategory.value[key];
}

function toggleSubcategory(key) {
  collapseSubcategory.value[key] = !collapseSubcategory.value[key];
}

function isCategoryCollapsed(key) {
  return !!collapseCategory.value[key];
}

function isSubcategoryCollapsed(key) {
  return !!collapseSubcategory.value[key];
}

async function createDraft() {
  if (!selectedOutlet.value || !selectedTemplate.value) {
    await Swal.fire('Validasi', 'Outlet dan template wajib dipilih.', 'warning');
    return;
  }

  router.post(route('qa2-audits.store'), {
    outlet_id: selectedOutlet.value.id_outlet,
    template_id: selectedTemplate.value.id,
    auditor_ids: selectedAuditors.value.map((x) => x.id),
    auditee_ids: selectedAuditees.value.map((x) => x.id),
    notes: notes.value,
  });
}

async function submitAudit() {
  if (!canManage.value) {
    return;
  }

  const missing = items.value.filter((x) => !x.result).length;
  if (missing > 0) {
    await Swal.fire('Validasi', `Masih ada ${missing} parameter belum diisi C/NC/NA.`, 'warning');
    return;
  }

  const result = await Swal.fire({
    title: 'Submit QA Audit?',
    text: 'Status akan berubah menjadi submitted dan Audit Time End akan terisi otomatis.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Submit',
    cancelButtonText: 'Batal',
  });

  if (!result.isConfirmed) {
    return;
  }

  router.post(route('qa2-audits.submit', props.audit.id));
}

const showCameraModal = ref(false);
const cameraMode = ref('photo');
const cameraContext = ref(null);
const itemFileInputs = ref({});
const capFileInputs = ref({});
const uploadingItemMedia = ref({});
const uploadingCapMedia = ref({});
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

function mapMediaForLightbox(media) {
  if (media.media_type === 'video') {
    return { src: media.url, type: 'video' };
  }
  return media.url;
}

function openMediaLightbox(mediaList, index) {
  if (!mediaList?.length) {
    return;
  }

  lightboxImages.value = mediaList.map(mapMediaForLightbox);
  lightboxIndex.value = index;
  lightboxVisible.value = true;
}

function setItemFileInput(itemId, el) {
  if (el) {
    itemFileInputs.value[itemId] = el;
  }
}

function setCapFileInput(itemId, el) {
  if (el) {
    capFileInputs.value[itemId] = el;
  }
}

function triggerItemFilePicker(item) {
  itemFileInputs.value[item.id]?.click();
}

function triggerCapFilePicker(item) {
  capFileInputs.value[item.id]?.click();
}

function openItemCamera(item, mode) {
  cameraContext.value = { type: 'item', item };
  cameraMode.value = mode;
  showCameraModal.value = true;
}

function openCapCamera(item, mode) {
  cameraContext.value = { type: 'cap', item };
  cameraMode.value = mode;
  showCameraModal.value = true;
}

function closeCamera() {
  showCameraModal.value = false;
  cameraContext.value = null;
}

function dataURLtoFile(dataUrl, filename) {
  const [header, data] = dataUrl.split(',');
  const mime = header.match(/:(.*?);/)[1];
  const binary = atob(data);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i);
  }
  return new File([bytes], filename, { type: mime });
}

async function uploadItemMediaFiles(item, files) {
  if (!canManage.value || !files.length) {
    return;
  }

  uploadingItemMedia.value[item.id] = true;
  const formData = new FormData();
  files.forEach((file) => formData.append('files[]', file));

  try {
    const { data } = await axios.post(route('qa2-audits.items.upload-media', [props.audit.id, item.id]), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    item.media = [...(item.media || []), ...(data.items || [])];
  } catch (error) {
    await Swal.fire('Error', 'Upload media gagal.', 'error');
  } finally {
    uploadingItemMedia.value[item.id] = false;
  }
}

async function uploadItemMedia(item, event) {
  const files = Array.from(event.target.files || []);
  if (!files.length) {
    return;
  }

  await uploadItemMediaFiles(item, files);
  event.target.value = '';
}

async function onCameraCapture(payload) {
  const ctx = cameraContext.value;
  closeCamera();

  if (!ctx) {
    return;
  }

  let file = null;
  if (typeof payload === 'string') {
    file = dataURLtoFile(payload, `capture-${Date.now()}.jpg`);
  } else if (payload instanceof Blob) {
    file = new File([payload], `video-${Date.now()}.webm`, { type: payload.type || 'video/webm' });
  }

  if (!file) {
    return;
  }

  if (ctx.type === 'item') {
    await uploadItemMediaFiles(ctx.item, [file]);
  } else if (ctx.type === 'cap') {
    await uploadCapMediaFiles(ctx.item, [file]);
  }
}

async function removeItemMedia(item, media) {
  if (!canManage.value) {
    return;
  }

  await axios.delete(route('qa2-audits.items.delete-media', [props.audit.id, item.id, media.id]));
  item.media = (item.media || []).filter((x) => x.id !== media.id);
}

function ensureCap(item) {
  if (!item.cap) {
    item.cap = {
      id: null,
      action_plan: '',
      target_date: '',
      status: 'open',
      media: [],
    };
  }
}

function applyCapIds(capRows = []) {
  for (const row of capRows) {
    const item = items.value.find((x) => Number(x.id) === Number(row.audit_item_id));
    if (item) {
      ensureCap(item);
      item.cap.id = row.cap_id;
    }
  }
}

async function ensureCapRecord(item) {
  ensureCap(item);
  if (item.cap.id) {
    return;
  }

  const { data } = await axios.post(route('qa2-audits.save-cap', props.audit.id), {
    caps: [{
      audit_item_id: item.id,
      action_plan: item.cap.action_plan || '',
      target_date: item.cap.target_date || null,
      status: item.cap.status || 'open',
    }],
  });

  applyCapIds(data.caps || []);
}

async function uploadCapMediaFiles(item, files) {
  if (!canFillCap.value || !files.length) {
    return;
  }

  try {
    await ensureCapRecord(item);
  } catch (error) {
    await Swal.fire('Error', 'Gagal menyiapkan CAP sebelum upload media.', 'error');
    return;
  }

  if (!item.cap?.id) {
    return;
  }

  uploadingCapMedia.value[item.id] = true;
  const formData = new FormData();
  files.forEach((file) => formData.append('files[]', file));

  try {
    const { data } = await axios.post(route('qa2-audits.cap.upload-media', [props.audit.id, item.cap.id]), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    item.cap.media = [...(item.cap.media || []), ...(data.items || [])];
  } catch (error) {
    await Swal.fire('Error', 'Upload CAP media gagal.', 'error');
  } finally {
    uploadingCapMedia.value[item.id] = false;
  }
}

async function uploadCapMedia(item, event) {
  const files = Array.from(event.target.files || []);
  if (!files.length) {
    return;
  }

  await uploadCapMediaFiles(item, files);
  event.target.value = '';
}

function goBackToIndex() {
  router.visit(route('qa2-audits.index'));
}

function formatUserLabel(user) {
  if (!user) {
    return '';
  }
  return user.jabatan ? `${user.nama_lengkap} (${user.jabatan})` : user.nama_lengkap;
}
</script>

<template>
  <AppLayout title="QA Audit Form">
    <div class="space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">
              {{ isCreate ? 'Buat QA Audit' : `QA Audit ${audit.audit_number}` }}
            </h1>
            <p class="text-sm text-gray-500">
              {{ isCreate ? 'Buat draft audit baru berdasarkan template QA2.' : `Status: ${audit.status}` }}
            </p>
          </div>
          <div class="flex items-start gap-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="goBackToIndex"
            >
              Back to Index
            </button>

            <div v-if="!isCreate" class="text-xs text-gray-500">
            <div>Audit Date: {{ audit.audit_datetime }}</div>
            <div>Time Start: {{ audit.audit_time_start || '-' }}</div>
            <div>Time End: {{ audit.audit_time_end || '-' }}</div>
            <div v-if="saving" class="font-medium text-amber-600">Autosave draft...</div>
            <div v-else-if="lastSavedAt" class="font-medium text-emerald-600">Tersimpan {{ lastSavedAt }}</div>
            <div v-if="canFillCap && capSaving" class="font-medium text-rose-600">Autosave CAP...</div>
            <div v-else-if="canFillCap && capLastSavedAt" class="font-medium text-emerald-600">CAP tersimpan {{ capLastSavedAt }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Outlet Name</label>
            <Multiselect
              v-model="selectedOutlet"
              :options="outlets"
              :disabled="!isCreate && !canManage"
              track-by="id_outlet"
              label="nama_outlet"
              placeholder="Pilih outlet"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Template</label>
            <Multiselect
              v-model="selectedTemplate"
              :options="templates"
              :disabled="!isCreate"
              track-by="id"
              label="name"
              placeholder="Pilih template"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Auditor Name</label>
            <Multiselect
              v-model="selectedAuditors"
              :options="users"
              :multiple="true"
              :disabled="!isCreate && !canManage"
              track-by="id"
              :custom-label="formatUserLabel"
              placeholder="Pilih auditor"
            >
              <template #option="{ option }">
                <div>
                  <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                  <div v-if="option.jabatan" class="text-xs text-indigo-600">{{ option.jabatan }}</div>
                </div>
              </template>
              <template #tag="{ option }">
                <span>{{ formatUserLabel(option) }}</span>
              </template>
            </Multiselect>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Auditee Name</label>
            <Multiselect
              v-model="selectedAuditees"
              :options="users"
              :multiple="true"
              :disabled="!isCreate && !canManage"
              track-by="id"
              :custom-label="formatUserLabel"
              placeholder="Pilih auditee"
            >
              <template #option="{ option }">
                <div>
                  <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                  <div v-if="option.jabatan" class="text-xs text-indigo-600">{{ option.jabatan }}</div>
                </div>
              </template>
              <template #tag="{ option }">
                <span>{{ formatUserLabel(option) }}</span>
              </template>
            </Multiselect>
          </div>

          <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium text-gray-700">Catatan Umum</label>
            <textarea
              v-model="notes"
              :disabled="!isCreate && !canManage"
              rows="2"
              class="w-full rounded-lg border-gray-300 text-sm"
              placeholder="Catatan tambahan..."
            />
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
          <button
            v-if="isCreate"
            type="button"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            @click="createDraft"
          >
            Buat Draft
          </button>

          <button
            v-if="!isCreate && canManage && audit.status === 'draft'"
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
            @click="submitAudit"
          >
            Submit Audit
          </button>
        </div>
      </div>

      <div v-if="!isCreate && canFillCap" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Pengisian CAP (NC)</h2>
            <p class="text-sm text-gray-500">Hanya parameter Non-Compliant. Perubahan disimpan otomatis.</p>
          </div>
          <input
            v-model="search"
            type="text"
            class="w-full rounded-lg border-gray-300 text-sm md:w-96"
            placeholder="Cari parameter NC..."
          >
        </div>

        <div class="space-y-4">
          <div
            v-for="cat in groupedCapItems"
            :key="cat.key"
            class="overflow-hidden rounded-xl border border-rose-200"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between bg-rose-50 px-4 py-3 text-left"
              @click="toggleCategory(cat.key)"
            >
              <span class="font-semibold text-rose-900">{{ cat.name }}</span>
              <span class="text-xs text-rose-600">{{ isCategoryCollapsed(cat.key) ? 'Show' : 'Hide' }}</span>
            </button>

            <div v-show="!isCategoryCollapsed(cat.key)" class="space-y-3 p-3">
              <div
                v-for="sub in cat.subcategories"
                :key="sub.key"
                class="overflow-hidden rounded-lg border border-rose-100"
              >
                <button
                  type="button"
                  class="flex w-full items-center justify-between bg-white px-3 py-2 text-left"
                  @click="toggleSubcategory(sub.key)"
                >
                  <span class="font-medium text-gray-800">{{ sub.name }}</span>
                  <span class="text-xs text-gray-500">{{ isSubcategoryCollapsed(sub.key) ? 'Show' : 'Hide' }}</span>
                </button>

                <div v-show="!isSubcategoryCollapsed(sub.key)" class="space-y-3 border-t border-rose-100 p-3">
                  <div
                    v-for="item in sub.items"
                    :key="item.id"
                    class="rounded-lg border border-rose-200 bg-rose-50/40 p-3"
                  >
                    <div class="mb-3 flex items-start justify-between gap-3">
                      <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.parameter_code || '-' }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ item.parameter_text }}</p>
                      </div>
                      <span class="rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white">NC</span>
                    </div>

                    <div v-if="item.comment" class="mb-3 rounded-lg border border-gray-200 bg-white p-3">
                      <p class="mb-1 text-xs font-semibold text-gray-500">Komentar Auditor</p>
                      <p class="text-sm text-gray-700">{{ item.comment }}</p>
                    </div>

                    <div v-if="item.media?.length" class="mb-3">
                      <p class="mb-2 text-xs font-semibold text-gray-500">Bukti dari Auditor ({{ item.media.length }})</p>
                      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(media, mediaIndex) in item.media" :key="`auditor-${media.id}`" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                          <img
                            v-if="media.media_type === 'photo'"
                            :src="media.url"
                            class="h-28 w-full cursor-pointer object-cover transition hover:opacity-90"
                            @click="openMediaLightbox(item.media, mediaIndex)"
                          >
                          <div
                            v-else
                            class="relative h-28 w-full cursor-pointer bg-gray-900"
                            @click="openMediaLightbox(item.media, mediaIndex)"
                          >
                            <video :src="media.url" class="pointer-events-none h-28 w-full object-cover" muted />
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                              <i class="fas fa-play-circle text-3xl text-white" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="rounded-lg border border-rose-200 bg-white p-3">
                      <p class="mb-2 text-sm font-semibold text-rose-700">Corrective Action Plan</p>

                      <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                          <label class="mb-1 block text-xs font-semibold text-rose-700">Action Plan</label>
                          <textarea
                            v-model="item.cap.action_plan"
                            rows="2"
                            class="w-full rounded-lg border-rose-300 text-sm"
                            placeholder="Tindakan perbaikan..."
                          />
                        </div>

                        <div>
                          <label class="mb-1 block text-xs font-semibold text-rose-700">Target Date</label>
                          <input v-model="item.cap.target_date" type="date" class="w-full rounded-lg border-rose-300 text-sm">
                        </div>

                        <div>
                          <label class="mb-1 block text-xs font-semibold text-rose-700">Status CAP</label>
                          <select v-model="item.cap.status" class="w-full rounded-lg border-rose-300 text-sm">
                            <option value="open">Open</option>
                            <option value="progress">Progress</option>
                            <option value="done">Done</option>
                          </select>
                        </div>

                        <div class="md:col-span-2">
                          <label class="mb-1 block text-xs font-semibold text-rose-700">Media CAP (per parameter)</label>
                          <div class="flex flex-wrap gap-2">
                            <button
                              type="button"
                              class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                              :disabled="uploadingCapMedia[item.id]"
                              @click="openCapCamera(item, 'photo')"
                            >
                              <i class="fas fa-camera" />
                              Ambil Foto
                            </button>
                            <button
                              type="button"
                              class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                              :disabled="uploadingCapMedia[item.id]"
                              @click="openCapCamera(item, 'video')"
                            >
                              <i class="fas fa-video" />
                              Rekam Video
                            </button>
                            <button
                              type="button"
                              class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-100 px-2.5 py-1.5 text-xs font-medium text-rose-800 hover:bg-rose-200 disabled:opacity-50"
                              :disabled="uploadingCapMedia[item.id]"
                              @click="triggerCapFilePicker(item)"
                            >
                              <i class="fas fa-images" />
                              Pilih File
                            </button>
                            <input
                              :ref="(el) => setCapFileInput(item.id, el)"
                              type="file"
                              multiple
                              accept="image/*,video/*"
                              class="hidden"
                              @change="uploadCapMedia(item, $event)"
                            >
                          </div>
                          <p v-if="uploadingCapMedia[item.id]" class="mt-1 text-xs text-rose-600">Mengunggah media CAP...</p>
                        </div>
                      </div>

                      <div v-if="item.cap?.media?.length" class="mt-3">
                        <p class="mb-2 text-xs font-semibold text-rose-700">Media CAP ({{ item.cap.media.length }})</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                          <div
                            v-for="(media, mediaIndex) in item.cap.media"
                            :key="media.id"
                            class="overflow-hidden rounded-lg border border-rose-200 bg-white"
                          >
                            <img
                              v-if="media.media_type === 'photo'"
                              :src="media.url"
                              class="h-24 w-full cursor-pointer object-cover transition hover:opacity-90"
                              @click="openMediaLightbox(item.cap.media, mediaIndex)"
                            >
                            <div
                              v-else
                              class="relative h-24 w-full cursor-pointer bg-gray-900"
                              @click="openMediaLightbox(item.cap.media, mediaIndex)"
                            >
                              <video :src="media.url" class="pointer-events-none h-24 w-full object-cover" muted />
                              <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                <i class="fas fa-play-circle text-2xl text-white" />
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!groupedCapItems.length" class="rounded-lg border border-dashed border-rose-200 p-8 text-center text-sm text-gray-500">
            {{ search.trim() ? 'Tidak ada parameter NC yang cocok dengan pencarian.' : 'Tidak ada parameter NC untuk diisi CAP.' }}
          </div>
        </div>
      </div>

      <div v-if="!isCreate && !canFillCap" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Parameter Audit</h2>
          <input
            v-model="search"
            type="text"
            class="w-full rounded-lg border-gray-300 text-sm md:w-96"
            placeholder="Cari parameter, kategori, subcategory..."
          >
        </div>

        <div class="space-y-4">
          <div
            v-for="cat in groupedItems"
            :key="cat.key"
            class="overflow-hidden rounded-xl border border-gray-200"
          >
            <button
              type="button"
              class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left"
              @click="toggleCategory(cat.key)"
            >
              <span class="font-semibold text-gray-900">{{ cat.name }}</span>
              <span class="text-xs text-gray-500">{{ isCategoryCollapsed(cat.key) ? 'Show' : 'Hide' }}</span>
            </button>

            <div v-show="!isCategoryCollapsed(cat.key)" class="space-y-3 p-3">
              <div
                v-for="sub in cat.subcategories"
                :key="sub.key"
                class="overflow-hidden rounded-lg border border-gray-200"
              >
                <button
                  type="button"
                  class="flex w-full items-center justify-between bg-white px-3 py-2 text-left"
                  @click="toggleSubcategory(sub.key)"
                >
                  <span class="font-medium text-gray-800">{{ sub.name }}</span>
                  <span class="text-xs text-gray-500">{{ isSubcategoryCollapsed(sub.key) ? 'Show' : 'Hide' }}</span>
                </button>

                <div v-show="!isSubcategoryCollapsed(sub.key)" class="space-y-3 border-t border-gray-100 p-3">
                  <div
                    v-for="item in sub.items"
                    :key="item.id"
                    class="rounded-lg border border-gray-200 p-3"
                  >
                    <div class="mb-2 flex items-start justify-between gap-3">
                      <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.parameter_code || '-' }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ item.parameter_text }}</p>
                      </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Result</label>
                        <div class="flex flex-wrap gap-2">
                          <label class="inline-flex items-center gap-2 rounded border px-2 py-1 text-sm">
                            <input v-model="item.result" type="radio" value="C" :disabled="!canManage || audit.status !== 'draft'">
                            C
                          </label>
                          <label class="inline-flex items-center gap-2 rounded border px-2 py-1 text-sm">
                            <input v-model="item.result" type="radio" value="NC" :disabled="!canManage || audit.status !== 'draft'">
                            NC
                          </label>
                          <label class="inline-flex items-center gap-2 rounded border px-2 py-1 text-sm">
                            <input v-model="item.result" type="radio" value="NA" :disabled="!canManage || audit.status !== 'draft'">
                            NA
                          </label>
                        </div>
                      </div>

                      <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Due Date (optional)</label>
                        <input v-model="item.due_date" type="date" class="w-full rounded-lg border-gray-300 text-sm" :disabled="!canManage || audit.status !== 'draft'">
                      </div>

                      <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Upload Foto / Video</label>
                        <div v-if="canManage && audit.status === 'draft'" class="flex flex-wrap gap-2">
                          <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="uploadingItemMedia[item.id]"
                            @click="openItemCamera(item, 'photo')"
                          >
                            <i class="fas fa-camera" />
                            Ambil Foto
                          </button>
                          <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="uploadingItemMedia[item.id]"
                            @click="openItemCamera(item, 'video')"
                          >
                            <i class="fas fa-video" />
                            Rekam Video
                          </button>
                          <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                            :disabled="uploadingItemMedia[item.id]"
                            @click="triggerItemFilePicker(item)"
                          >
                            <i class="fas fa-images" />
                            Pilih File
                          </button>
                          <input
                            :ref="(el) => setItemFileInput(item.id, el)"
                            type="file"
                            multiple
                            accept="image/*,video/*"
                            class="hidden"
                            @change="uploadItemMedia(item, $event)"
                          >
                        </div>
                        <p v-if="uploadingItemMedia[item.id]" class="mt-1 text-xs text-indigo-600">Mengunggah media...</p>
                      </div>
                    </div>

                    <div v-if="item.media?.length" class="mt-3">
                      <p class="mb-2 text-xs font-semibold text-gray-500">Media terlampir ({{ item.media.length }})</p>
                      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(media, mediaIndex) in item.media" :key="media.id" class="group relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                          <img
                            v-if="media.media_type === 'photo'"
                            :src="media.url"
                            class="h-28 w-full cursor-pointer object-cover transition hover:opacity-90"
                            @click="openMediaLightbox(item.media, mediaIndex)"
                          >
                          <div
                            v-else
                            class="relative h-28 w-full cursor-pointer bg-gray-900"
                            @click="openMediaLightbox(item.media, mediaIndex)"
                          >
                            <video :src="media.url" class="pointer-events-none h-28 w-full object-cover" muted />
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                              <i class="fas fa-play-circle text-3xl text-white" />
                            </div>
                          </div>
                          <button
                            v-if="canManage && audit.status === 'draft'"
                            type="button"
                            class="absolute right-1 top-1 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700"
                            title="Hapus"
                            @click.stop="removeItemMedia(item, media)"
                          >
                            <i class="fas fa-times text-xs" />
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="mt-3">
                      <label class="mb-1 block text-xs font-semibold text-gray-500">Comment (optional)</label>
                      <textarea v-model="item.comment" rows="2" class="w-full rounded-lg border-gray-300 text-sm" :disabled="!canManage || audit.status !== 'draft'" />
                    </div>

                    <div v-if="item.result === 'NC' && !canFillCap && (item.cap?.action_plan || item.cap?.media?.length)" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3">
                      <p class="mb-2 text-sm font-semibold text-rose-700">Corrective Action Plan</p>
                      <p v-if="item.cap?.action_plan" class="mb-2 whitespace-pre-wrap text-sm text-gray-800">{{ item.cap.action_plan }}</p>
                      <div class="grid gap-2 text-xs text-gray-600 md:grid-cols-2">
                        <div v-if="item.cap?.target_date">Target: {{ item.cap.target_date }}</div>
                        <div v-if="item.cap?.status">Status: {{ item.cap.status }}</div>
                      </div>
                      <div v-if="item.cap?.media?.length" class="mt-3">
                        <p class="mb-2 text-xs font-semibold text-rose-700">Media CAP ({{ item.cap.media.length }})</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                          <div
                            v-for="(media, mediaIndex) in item.cap.media"
                            :key="media.id"
                            class="overflow-hidden rounded-lg border border-rose-200 bg-white"
                          >
                            <img
                              v-if="media.media_type === 'photo'"
                              :src="media.url"
                              class="h-24 w-full cursor-pointer object-cover transition hover:opacity-90"
                              @click="openMediaLightbox(item.cap.media, mediaIndex)"
                            >
                            <div
                              v-else
                              class="relative h-24 w-full cursor-pointer bg-gray-900"
                              @click="openMediaLightbox(item.cap.media, mediaIndex)"
                            >
                              <video :src="media.url" class="pointer-events-none h-24 w-full object-cover" muted />
                              <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                <i class="fas fa-play-circle text-2xl text-white" />
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!groupedItems.length" class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
            {{ search.trim() ? 'Tidak ada parameter yang cocok dengan pencarian.' : 'Belum ada parameter audit dari template.' }}
          </div>
        </div>
      </div>

      <div v-if="!isCreate" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">QA Audit Detail Summary</h2>

        <div class="overflow-hidden rounded-lg border border-gray-200">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-amber-900 text-white">
                <tr>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">No</th>
                  <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Category</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Compliant</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Non-Compliant</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Non-Applicable</th>
                  <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">Score</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="row in categorySummaryRows" :key="row.id">
                  <td class="px-3 py-2 text-center text-sm font-semibold text-gray-900">{{ row.no }}</td>
                  <td class="px-3 py-2 text-sm font-semibold uppercase text-gray-900">{{ row.name }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.non_compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ row.non_applicable }}</td>
                  <td class="px-3 py-2 text-center text-sm text-gray-900">{{ formatScore(row.score) }}</td>
                </tr>

                <tr v-if="!categorySummaryRows.length">
                  <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">
                    Belum ada data parameter untuk dirangkum.
                  </td>
                </tr>
              </tbody>
              <tfoot class="bg-amber-900 text-white">
                <tr>
                  <td class="px-3 py-2 text-center text-sm font-semibold" colspan="2">TOTAL</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ summaryTotal.compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ summaryTotal.non_compliant }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ summaryTotal.non_applicable }}</td>
                  <td class="px-3 py-2 text-center text-sm font-semibold">{{ formatScore(summaryTotal.score) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
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

        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
          <div class="text-sm font-semibold text-gray-500">Overall Audit Result</div>
          <div class="mt-2 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold" :class="overallAuditResult.className">
            <span>{{ overallAuditResult.label }}</span>
            <span>({{ formatScore(summaryTotal.score) }})</span>
          </div>
        </div>
      </div>
    </div>

    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />

    <CameraModal
      v-if="showCameraModal"
      :mode="cameraMode"
      @close="closeCamera"
      @capture="onCameraCapture"
    />
  </AppLayout>
</template>
