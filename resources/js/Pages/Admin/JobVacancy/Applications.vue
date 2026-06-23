<template>
  <AppLayout title="Data Pelamar Job Vacancy">
    <div class="max-w-[1800px] w-full mx-auto py-8 px-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-file-circle-check"></i> Data Pelamar Job Vacancy
        </h2>
        <div class="flex flex-wrap gap-2">
          <a
            href="/admin/job-vacancy/recruitment-dashboard"
            class="bg-gradient-to-r from-orange-500 to-orange-700 text-white px-4 py-2 rounded shadow hover:from-orange-600 hover:to-orange-800 transition"
          >
            Dashboard Rekrutmen
          </a>
          <a
            href="/admin/job-vacancy"
            class="bg-gray-600 text-white px-4 py-2 rounded shadow hover:bg-gray-700 transition"
          >
            Kembali ke Lowongan
          </a>
        </div>
      </div>

      <p class="mb-4 text-sm text-gray-600">
        Setiap tahap rekrutmen diinput terpisah: <strong>Screening CV</strong>, <strong>HR Interview</strong>, <strong>User Interview</strong>, dan <strong>LOI</strong>.
        Sourcing di dashboard = total pelamar posisi tersebut (otomatis).
      </p>

      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="filterScope" class="rounded border px-2 py-1.5 text-sm">
          <option value="">Semua Kategori</option>
          <option value="outlet">Outlet</option>
          <option value="head_office">Head Office</option>
        </select>
        <input
          v-model="search"
          placeholder="Cari nama, email, domisili, pendidikan, posisi..."
          class="rounded border px-2 py-1.5 text-sm w-72"
        />
        <button @click="fetchApplications(1)" class="bg-blue-500 text-white px-3 py-1.5 rounded text-sm">
          Cari
        </button>
      </div>

      <div class="overflow-x-auto rounded-lg shadow border border-gray-200">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-indigo-700 text-white text-left">
              <th class="px-3 py-2.5 w-10">#</th>
              <th class="px-3 py-2.5 w-16">Foto</th>
              <th class="px-3 py-2.5">Nama</th>
              <th class="px-3 py-2.5">Domisili</th>
              <th class="px-3 py-2.5">Pendidikan</th>
              <th class="px-3 py-2.5">Tgl Lahir</th>
              <th class="px-3 py-2.5">Posisi / Lokasi</th>
              <th class="px-3 py-2.5">Kontak</th>
              <th class="px-3 py-2.5">CV</th>
              <th class="px-2 py-2.5">Screening CV</th>
              <th class="px-2 py-2.5">HR Interview</th>
              <th class="px-2 py-2.5">User Interview</th>
              <th class="px-2 py-2.5">LOI</th>
              <th class="px-2 py-2.5">Tgl Join</th>
              <th class="px-3 py-2.5">Dilamar</th>
              <th class="px-3 py-2.5 w-24"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in rows.data"
              :key="row.id"
              class="bg-white border-b border-gray-100 hover:bg-indigo-50/40 align-top"
            >
              <td class="px-3 py-3 text-gray-500">
                {{ idx + 1 + (rows.current_page - 1) * rows.per_page }}
              </td>
              <td class="px-3 py-3">
                <button
                  v-if="row.photo_file"
                  type="button"
                  class="block h-12 w-12 overflow-hidden rounded-lg border border-gray-200 bg-gray-50"
                  @click="openDetail(row)"
                >
                  <img
                    :src="fileUrl(row.photo_file, row.photo_url)"
                    :alt="row.full_name"
                    class="h-full w-full object-cover"
                  />
                </button>
                <div
                  v-else
                  class="flex h-12 w-12 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-[10px] text-gray-400"
                >
                  N/A
                </div>
              </td>
              <td class="px-3 py-3">
                <div class="font-semibold text-gray-900">{{ row.full_name }}</div>
                <span
                  class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                  :class="scopeBadgeClass(row.job_vacancy?.job_scope)"
                >
                  {{ scopeLabel(row.job_vacancy?.job_scope) }}
                </span>
              </td>
              <td class="px-3 py-3 text-gray-700">{{ row.domicile || '-' }}</td>
              <td class="px-3 py-3 text-gray-700">{{ row.last_education || '-' }}</td>
              <td class="px-3 py-3 text-gray-700 whitespace-nowrap">{{ formatBirthDate(row.birth_date) }}</td>
              <td class="px-3 py-3">
                <div class="font-medium text-gray-900">{{ row.job_vacancy?.position || '-' }}</div>
                <div class="text-xs text-gray-500">{{ row.job_vacancy?.location || '-' }}</div>
              </td>
              <td class="px-3 py-3">
                <div class="text-gray-900">{{ row.email || '-' }}</div>
                <div class="text-gray-600">{{ row.phone || '-' }}</div>
              </td>
              <td class="px-3 py-3">
                <a
                  v-if="row.cv_file"
                  :href="fileUrl(row.cv_file, row.cv_url)"
                  target="_blank"
                  class="text-blue-600 hover:underline"
                >
                  Download
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-2 py-2">
                <StepSelect :model-value="row.screening_status" @update:model-value="(v) => updateStep(row.id, 'screening_status', v)" />
              </td>
              <td class="px-2 py-2">
                <StepSelect :model-value="row.hr_interview_status" @update:model-value="(v) => updateStep(row.id, 'hr_interview_status', v)" />
              </td>
              <td class="px-2 py-2">
                <StepSelect :model-value="row.user_interview_status" @update:model-value="(v) => updateStep(row.id, 'user_interview_status', v)" />
              </td>
              <td class="px-2 py-2">
                <StepSelect :model-value="row.loi_status" @update:model-value="(v) => updateStep(row.id, 'loi_status', v)" />
              </td>
              <td class="px-2 py-2">
                <input
                  type="date"
                  :value="toDateInput(row.joined_at)"
                  class="rounded border px-1 py-1 text-xs w-[118px]"
                  @change="(e) => updateStep(row.id, 'joined_at', e.target.value || null)"
                />
              </td>
              <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ formatDate(row.created_at) }}</td>
              <td class="px-3 py-3">
                <button
                  type="button"
                  class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700"
                  @click="openDetail(row)"
                >
                  Detail
                </button>
              </td>
            </tr>
            <tr v-if="rows.data.length === 0">
              <td colspan="16" class="text-center py-10 text-gray-400">Belum ada data pelamar</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex justify-end">
        <nav v-if="rows.last_page > 1" class="inline-flex -space-x-px">
          <button
            v-for="page in rows.last_page"
            :key="page"
            @click="fetchApplications(page)"
            :class="[
              'px-3 py-1 border border-gray-300 text-sm',
              page === rows.current_page
                ? 'bg-indigo-600 text-white'
                : 'bg-white text-indigo-700 hover:bg-indigo-50',
            ]"
          >
            {{ page }}
          </button>
        </nav>
      </div>

      <!-- Modal detail pelamar -->
      <div
        v-if="selected"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4"
        @click="closeDetail"
      >
        <div
          class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-2xl"
          @click.stop
        >
          <div class="flex items-start justify-between gap-4 border-b px-5 py-4">
            <div>
              <h3 class="text-lg font-bold text-gray-900">{{ selected.full_name }}</h3>
              <p class="text-sm text-gray-500">{{ selected.job_vacancy?.position || '-' }}</p>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700 text-xl leading-none" @click="closeDetail">
              &times;
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-[140px_1fr] gap-5 p-5">
            <div class="flex flex-col items-center gap-2">
              <div
                v-if="selected.photo_file"
                class="h-36 w-36 overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
              >
                <img
                  :src="fileUrl(selected.photo_file, selected.photo_url)"
                  :alt="selected.full_name"
                  class="h-full w-full object-cover"
                />
              </div>
              <div
                v-else
                class="flex h-36 w-36 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-400"
              >
                Tidak ada foto
              </div>
              <a
                v-if="selected.photo_file"
                :href="fileUrl(selected.photo_file, selected.photo_url)"
                target="_blank"
                class="text-xs text-blue-600 hover:underline"
              >
                Buka foto ukuran penuh
              </a>
            </div>

            <div class="space-y-3 text-sm">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <DetailItem label="Email" :value="selected.email" />
                <DetailItem label="Nomor HP" :value="selected.phone" />
                <DetailItem label="Domisili" :value="selected.domicile" />
                <DetailItem label="Pendidikan Terakhir" :value="selected.last_education" />
                <DetailItem label="Tanggal Lahir" :value="formatBirthDate(selected.birth_date)" />
                <DetailItem label="Tanggal Lamar" :value="formatDateTime(selected.created_at)" />
                <DetailItem label="Kategori" :value="scopeLabel(selected.job_vacancy?.job_scope)" />
                <DetailItem label="Lokasi Lowongan" :value="selected.job_vacancy?.location" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Screening CV</div>
                  <StepSelect
                    :model-value="selected.screening_status"
                    full-width
                    @update:model-value="(v) => updateStep(selected.id, 'screening_status', v, true)"
                  />
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">HR Interview</div>
                  <StepSelect
                    :model-value="selected.hr_interview_status"
                    full-width
                    @update:model-value="(v) => updateStep(selected.id, 'hr_interview_status', v, true)"
                  />
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">User Interview</div>
                  <StepSelect
                    :model-value="selected.user_interview_status"
                    full-width
                    @update:model-value="(v) => updateStep(selected.id, 'user_interview_status', v, true)"
                  />
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">LOI</div>
                  <StepSelect
                    :model-value="selected.loi_status"
                    full-width
                    @update:model-value="(v) => updateStep(selected.id, 'loi_status', v, true)"
                  />
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Tanggal Join</div>
                  <input
                    type="date"
                    :value="toDateInput(selected.joined_at)"
                    class="rounded border px-3 py-1.5 text-sm w-full"
                    @change="(e) => updateStep(selected.id, 'joined_at', e.target.value || null, true)"
                  />
                </div>
              </div>

              <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Catatan Progress</div>
                <textarea
                  :value="selected.stage_notes || ''"
                  rows="2"
                  class="w-full rounded border px-3 py-1.5 text-sm"
                  placeholder="Catatan untuk pelamar ini..."
                  @blur="(e) => saveStageNotes(selected.id, e.target.value)"
                />
              </div>

              <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Cover Letter</div>
                <p class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-gray-700 whitespace-pre-line">
                  {{ selected.cover_letter || '-' }}
                </p>
              </div>

              <div class="flex flex-wrap gap-3 pt-1">
                <a
                  v-if="selected.cv_file"
                  :href="fileUrl(selected.cv_file, selected.cv_url)"
                  target="_blank"
                  class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                  <i class="fa-solid fa-file-pdf"></i> Download CV
                </a>
                <a
                  v-if="selected.photo_file"
                  :href="fileUrl(selected.photo_file, selected.photo_url)"
                  target="_blank"
                  class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                  <i class="fa-solid fa-image"></i> Lihat Foto
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { defineComponent, h, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useJustAcademyFlash } from '@/Composables/useJustAcademyUi';

useJustAcademyFlash();

const DetailItem = defineComponent({
  name: 'DetailItem',
  props: {
    label: { type: String, required: true },
    value: { type: String, default: '' },
  },
  setup(props) {
    return () =>
      h('div', [
        h('div', { class: 'text-xs font-semibold uppercase tracking-wide text-gray-500 mb-0.5' }, props.label),
        h('div', { class: 'text-gray-900 font-medium' }, props.value || '-'),
      ]);
  },
});

const STEP_OPTIONS = ['pending', 'ok', 'nok'];
const STEP_LABELS = { pending: 'Pending', ok: 'Lolos', nok: 'Tidak Lolos' };

const StepSelect = defineComponent({
  name: 'StepSelect',
  props: {
    modelValue: { type: String, default: 'pending' },
    fullWidth: { type: Boolean, default: false },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () =>
      h(
        'select',
        {
          class: [
            'rounded border px-1 py-1 text-xs',
            props.fullWidth ? 'w-full text-sm px-3 py-1.5' : 'min-w-[88px]',
            stepClass(props.modelValue),
          ],
          value: props.modelValue || 'pending',
          onChange: (e) => emit('update:modelValue', e.target.value),
        },
        STEP_OPTIONS.map((opt) => h('option', { value: opt }, STEP_LABELS[opt] || opt)),
      );
  },
});

function stepClass(value) {
  if (value === 'ok') return 'border-green-300 bg-green-50';
  if (value === 'nok') return 'border-red-300 bg-red-50';
  return 'border-gray-300 bg-white';
}

const props = defineProps({
  applications: Object,
  filters: Object,
  stepOptions: { type: Array, default: () => ['pending', 'ok', 'nok'] },
  stepLabels: { type: Object, default: () => ({ pending: 'Pending', ok: 'Lolos', nok: 'Tidak Lolos' }) },
});

const rows = ref(props.applications);
const search = ref(props.filters?.search || '');
const filterScope = ref(props.filters?.scope || '');
const filterVacancyId = ref(props.filters?.job_vacancy_id || '');
const stepOptions = ref(props.stepOptions);
const stepLabels = ref(props.stepLabels);
const selected = ref(null);

watch(
  () => props.applications,
  (value) => {
    rows.value = value;
    if (selected.value) {
      const updated = value?.data?.find((r) => r.id === selected.value.id);
      if (updated) selected.value = updated;
    }
  },
);

function fetchApplications(page = 1) {
  router.get(
    '/admin/job-vacancy/applications',
    {
      search: search.value,
      scope: filterScope.value,
      job_vacancy_id: filterVacancyId.value,
      page,
    },
    { preserveState: true, replace: true },
  );
}

watch([search, filterScope], () => {
  fetchApplications(1);
});

function fileUrl(path, url) {
  if (url) return url;
  if (!path) return '';
  return `/storage/${path}`;
}

function scopeLabel(scope) {
  return scope === 'head_office' ? 'Head Office' : 'Outlet';
}

function scopeBadgeClass(scope) {
  return scope === 'head_office'
    ? 'bg-indigo-100 text-indigo-700'
    : 'bg-emerald-100 text-emerald-700';
}

function stepLabel(step) {
  return stepLabels.value[step] || step;
}

function toDateInput(value) {
  if (!value) return '';
  if (typeof value === 'string' && value.length >= 10) return value.slice(0, 10);
  return '';
}

function updateStep(id, field, value, fromModal = false) {
  router.patch(
    `/admin/job-vacancy/applications/${id}/progress`,
    { [field]: value },
    {
      preserveScroll: true,
      onSuccess: () => {
        fetchApplications(rows.value.current_page || 1);
        if (fromModal && selected.value?.id === id) {
          selected.value = { ...selected.value, [field]: value };
        }
      },
    },
  );
}

function saveStageNotes(id, stageNotes) {
  const row = rows.value?.data?.find((r) => r.id === id);
  if (row && (row.stage_notes || '') === (stageNotes || '')) return;
  router.patch(
    `/admin/job-vacancy/applications/${id}/progress`,
    { stage_notes: stageNotes },
    { preserveScroll: true, onSuccess: () => fetchApplications(rows.value.current_page || 1) },
  );
}

function openDetail(row) {
  selected.value = row;
}

function closeDetail() {
  selected.value = null;
}

function formatDate(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatBirthDate(value) {
  if (!value) return '-';
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
    const [y, m, d] = value.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  }
  return formatDate(value);
}
</script>
