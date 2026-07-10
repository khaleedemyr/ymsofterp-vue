<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import axios from 'axios';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  audits: Object,
  filters: Object,
  statistics: Object,
  outlets: Array,
  permissions: Object,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const outletId = ref(props.filters?.outlet_id || '');
const fromMonth = ref(props.filters?.from_month || '');
const toMonth = ref(props.filters?.to_month || '');
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);
const sharingAuditId = ref(null);

const debouncedFilter = debounce(() => {
  router.get(route('qa2-audits.index'), {
    search: search.value,
    status: status.value,
    outlet_id: outletId.value,
    from_month: fromMonth.value,
    to_month: toMonth.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 300);

watch([search, status, outletId, fromMonth, toMonth], debouncedFilter);

function clearFilters() {
  search.value = '';
  status.value = '';
  outletId.value = '';
  fromMonth.value = '';
  toMonth.value = '';
}

function applyQuickMonthRange(kind) {
  const end = new Date();
  const start = new Date(end);
  if (kind === '1m') {
    // bulan ini saja
  } else if (kind === '3m') {
    start.setMonth(start.getMonth() - 2);
  } else if (kind === '6m') {
    start.setMonth(start.getMonth() - 5);
  } else if (kind === 'ytd') {
    start.setMonth(0);
    start.setDate(1);
  }
  const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  fromMonth.value = fmt(start);
  toMonth.value = fmt(end);
}

function goCreate() {
  router.visit(route('qa2-audits.create'));
}

function goReportSummary() {
  router.visit(route('qa2-audits.report-summary'));
}

function goReportNcDetail() {
  router.visit(route('qa2-audits.report-nc-detail'));
}

function goReportNcDashboard() {
  router.visit(route('qa2-audits.report-nc-dashboard'));
}

function goEdit(id) {
  router.visit(route('qa2-audits.edit', id));
}

function goView(id) {
  router.visit(route('qa2-audits.edit', id));
}

function canCap(audit) {
  return audit.status === 'submitted' && Number(audit.count_nc || 0) > 0 && Number(audit.count_nc_pending_cap || 0) > 0;
}

function auditScore(audit) {
  const compliant = Number(audit.count_c || 0);
  const nonCompliant = Number(audit.count_nc || 0);
  const denominator = compliant + nonCompliant;
  if (denominator <= 0) {
    return 0;
  }
  return (compliant / denominator) * 100;
}

function formatScore(value) {
  return `${Number(value || 0).toFixed(0)}%`;
}

function auditResult(audit) {
  const score = auditScore(audit);
  if (score >= 91) {
    return {
      label: 'EXCELLENT',
      className: 'bg-emerald-100 text-emerald-700',
      score,
    };
  }
  if (score >= 85) {
    return {
      label: 'SATISFACTORY',
      className: 'bg-yellow-100 text-yellow-700',
      score,
    };
  }
  return {
    label: 'TO IMPROVE',
    className: 'bg-rose-100 text-rose-700',
    score,
  };
}

function openCap(id) {
  router.visit(route('qa2-audits.edit', id));
}

function resolveAvatarUrl(person) {
  if (!person) {
    return null;
  }
  const raw = String(person.avatar_url || person.avatar || '').trim();
  if (!raw) {
    return null;
  }
  if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
    return raw;
  }
  return `/storage/${raw}`;
}

function openAvatarLightbox(person) {
  const avatarUrl = resolveAvatarUrl(person);
  if (!avatarUrl) {
    return;
  }
  lightboxImages.value = [avatarUrl];
  lightboxIndex.value = 0;
  lightboxVisible.value = true;
}

async function removeAudit(id) {
  const result = await Swal.fire({
    title: 'Hapus QA Audit?',
    text: 'Data akan dihapus permanen.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
  });

  if (!result.isConfirmed) {
    return;
  }

  router.delete(route('qa2-audits.destroy', id));
}

async function shareToWhatsApp(audit) {
  if (sharingAuditId.value === audit.id) return;

  try {
    sharingAuditId.value = audit.id;
    const response = await axios.post(route('qa2-audits.share-link', audit.id));
    const url = response.data?.url;
    if (!url) {
      throw new Error('Link tidak tersedia');
    }

    const message = response.data?.message || `QA Audit ${audit.audit_number}\n${url}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || error.message || 'Gagal membuat link share', 'error');
  } finally {
    sharingAuditId.value = null;
  }
}
</script>

<template>
  <AppLayout title="QA Audit">
    <div class="space-y-6 p-4 sm:p-6">
      <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">QA Audit</h1>
            <p class="text-sm text-gray-500">Draft autosave, submit final, dan tracking NC/CAP.</p>
          </div>
          <button
            v-if="permissions?.can_manage"
            type="button"
            class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
            @click="goCreate"
          >
            Buat QA Audit
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-lg border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50"
            @click="goReportSummary"
          >
            Report Summary
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50"
            @click="goReportNcDetail"
          >
            Report NC Detail
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded-lg border border-fuchsia-300 px-4 py-2 text-sm font-medium text-fuchsia-700 hover:bg-fuchsia-50"
            @click="goReportNcDashboard"
          >
            NC Dashboard
          </button>
        </div>
      </div>

      <div class="grid gap-4" :class="permissions?.can_manage ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ statistics?.total || 0 }}</p>
        </div>
        <div v-if="permissions?.can_manage" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Draft</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600">{{ statistics?.draft || 0 }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Submitted</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ statistics?.submitted || 0 }}</p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-6">
          <input
            v-model="search"
            type="text"
            class="w-full rounded-lg border-gray-300 text-sm md:col-span-2"
            placeholder="Cari nomor audit, outlet, template, auditor, auditee..."
          >

          <select v-model="status" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">Semua Status</option>
            <option v-if="permissions?.can_manage" value="draft">Draft</option>
            <option value="submitted">Submitted</option>
          </select>

          <select v-model="outletId" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="String(outlet.id_outlet)">
              {{ outlet.nama_outlet }}
            </option>
          </select>

          <input v-model="fromMonth" type="month" class="w-full rounded-lg border-gray-300 text-sm" title="From Month">
          <input v-model="toMonth" type="month" class="w-full rounded-lg border-gray-300 text-sm" title="To Month">
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <button type="button" class="rounded-lg border border-indigo-300 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickMonthRange('1m')">
            Bulan Ini
          </button>
          <button type="button" class="rounded-lg border border-indigo-300 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickMonthRange('3m')">
            3 Bulan
          </button>
          <button type="button" class="rounded-lg border border-indigo-300 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickMonthRange('6m')">
            6 Bulan
          </button>
          <button type="button" class="rounded-lg border border-indigo-300 px-2 py-1.5 text-xs text-indigo-700 hover:bg-indigo-50" @click="applyQuickMonthRange('ytd')">
            YTD
          </button>
          <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50" @click="clearFilters">
            Reset Filter
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Audit</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Template</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Auditor</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Auditee</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">C / NC / NA</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Audit Result</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="audit in audits.data" :key="audit.id">
                <td class="px-4 py-3 text-sm text-gray-700">
                  <div class="font-medium text-gray-900">{{ audit.audit_number }}</div>
                  <div class="text-xs text-gray-500">{{ audit.audit_datetime }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ audit.outlet_name || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ audit.template_name || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <div v-if="audit.auditors?.length" class="space-y-1">
                    <div v-for="person in audit.auditors" :key="`auditor-${audit.id}-${person.id}`" class="flex items-start gap-2 text-xs leading-relaxed">
                      <button
                        v-if="resolveAvatarUrl(person)"
                        type="button"
                        class="mt-0.5 h-8 w-8 shrink-0 overflow-hidden rounded-full ring-1 ring-gray-200"
                        title="Lihat avatar"
                        @click="openAvatarLightbox(person)"
                      >
                        <img :src="resolveAvatarUrl(person)" :alt="person.name" class="h-full w-full object-cover">
                      </button>
                      <div class="min-w-0">
                        <span class="font-medium text-gray-900">{{ person.name }}</span>
                        <span v-if="person.jabatan" class="block text-[11px] text-indigo-600">{{ person.jabatan }}</span>
                      </div>
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">-</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <div v-if="audit.auditees?.length" class="space-y-1">
                    <div v-for="person in audit.auditees" :key="`auditee-${audit.id}-${person.id}`" class="flex items-start gap-2 text-xs leading-relaxed">
                      <button
                        v-if="resolveAvatarUrl(person)"
                        type="button"
                        class="mt-0.5 h-8 w-8 shrink-0 overflow-hidden rounded-full ring-1 ring-gray-200"
                        title="Lihat avatar"
                        @click="openAvatarLightbox(person)"
                      >
                        <img :src="resolveAvatarUrl(person)" :alt="person.name" class="h-full w-full object-cover">
                      </button>
                      <div class="min-w-0">
                        <span class="font-medium text-gray-900">{{ person.name }}</span>
                        <span v-if="person.jabatan" class="block text-[11px] text-indigo-600">{{ person.jabatan }}</span>
                      </div>
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">-</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <span class="font-semibold text-emerald-600">{{ audit.count_c || 0 }}</span>
                  /
                  <span class="font-semibold text-rose-600">{{ audit.count_nc || 0 }}</span>
                  /
                  <span class="font-semibold text-slate-600">{{ audit.count_na || 0 }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">
                  <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="auditResult(audit).className">
                    {{ auditResult(audit).label }}
                  </span>
                  <div class="mt-1 text-xs font-medium text-gray-500">{{ formatScore(auditResult(audit).score) }}</div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="audit.status === 'submitted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                  >
                    {{ audit.status }}
                  </span>
                  <div v-if="canCap(audit)" class="mt-1 text-xs font-medium text-rose-600">
                    NC belum CAP: {{ audit.count_nc_pending_cap || 0 }}
                  </div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs text-gray-700" @click="goView(audit.id)">
                      Lihat
                    </button>
                    <button
                      type="button"
                      class="rounded-md border border-green-300 px-2.5 py-1 text-xs text-green-700"
                      :disabled="sharingAuditId === audit.id"
                      @click="shareToWhatsApp(audit)"
                    >
                      <i :class="sharingAuditId === audit.id ? 'fas fa-spinner fa-spin mr-1' : 'fab fa-whatsapp mr-1'" />
                      Share WA
                    </button>
                    <button
                      v-if="permissions?.can_manage"
                      type="button"
                      class="rounded-md border border-indigo-300 px-2.5 py-1 text-xs text-indigo-700"
                      @click="goEdit(audit.id)"
                    >
                      Edit
                    </button>
                    <button
                      v-if="canCap(audit)"
                      type="button"
                      class="rounded-md border border-rose-300 px-2.5 py-1 text-xs text-rose-700"
                      @click="openCap(audit.id)"
                    >
                      Isi CAP
                    </button>
                    <button
                      v-if="permissions?.can_manage"
                      type="button"
                      class="rounded-md border border-red-300 px-2.5 py-1 text-xs text-red-700"
                      @click="removeAudit(audit.id)"
                    >
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!audits.data.length">
                <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">
                  Data QA Audit belum ada.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </AppLayout>
</template>
