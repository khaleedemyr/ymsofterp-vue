<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const debouncedFilter = debounce(() => {
  router.get(route('qa2-audits.index'), {
    search: search.value,
    status: status.value,
    outlet_id: outletId.value,
  }, {
    preserveState: true,
    replace: true,
  });
}, 300);

watch([search, status, outletId], debouncedFilter);

function clearFilters() {
  search.value = '';
  status.value = '';
  outletId.value = '';
}

function goCreate() {
  router.visit(route('qa2-audits.create'));
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
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Total</p>
          <p class="mt-2 text-2xl font-semibold text-gray-900">{{ statistics?.total || 0 }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Draft</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600">{{ statistics?.draft || 0 }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <p class="text-xs uppercase tracking-wide text-gray-500">Submitted</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ statistics?.submitted || 0 }}</p>
        </div>
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
        <div class="grid gap-3 md:grid-cols-4">
          <input
            v-model="search"
            type="text"
            class="w-full rounded-lg border-gray-300 text-sm"
            placeholder="Cari nomor audit, outlet, template..."
          >

          <select v-model="status" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
          </select>

          <select v-model="outletId" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="String(outlet.id_outlet)">
              {{ outlet.nama_outlet }}
            </option>
          </select>

          <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="clearFilters">
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
                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                  Data QA Audit belum ada.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
