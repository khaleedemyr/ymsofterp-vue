<template>
  <AppLayout title="Web Profile Security Monitoring">
    <div class="mx-auto max-w-7xl px-4 py-8">
      <div class="mb-6 flex items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Security Monitoring</h1>
          <p class="text-sm text-gray-500">Audit trail perubahan link/banner kritikal Web Profile.</p>
        </div>
        <Link href="/web-profile" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
          Kembali
        </Link>
      </div>

      <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-5">
        <input
          v-model="filterQ"
          type="text"
          placeholder="Cari deskripsi / IP / type"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          @keyup.enter="applyFilters"
        />
        <select
          v-model="filterType"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          @change="applyFilters"
        >
          <option value="">Semua activity type</option>
          <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
        </select>
        <input
          v-model="filterDateFrom"
          type="date"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          @change="applyFilters"
        />
        <input
          v-model="filterDateTo"
          type="date"
          class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          @change="applyFilters"
        />
        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="applyFilters">
          Terapkan Filter
        </button>
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-600">
              <tr>
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Deskripsi</th>
                <th class="px-4 py-3">IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs.data" :key="log.id" class="border-t border-gray-100 align-top">
                <td class="px-4 py-3 text-gray-700">{{ formatDate(log.created_at) }}</td>
                <td class="px-4 py-3 text-gray-700">{{ log.user_name }}</td>
                <td class="px-4 py-3">
                  <span class="rounded px-2 py-1 text-xs" :class="severityBadgeClass(log.activity_type)">
                    {{ log.activity_type }} ({{ severityLabel(log.activity_type) }})
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-700">
                  <p class="font-medium">{{ log.description }}</p>
                  <p v-if="log.old_data || log.new_data" class="mt-1 text-xs text-gray-500">
                    old/new tersedia untuk investigasi detail.
                  </p>
                  <button
                    v-if="log.old_data || log.new_data"
                    type="button"
                    class="mt-2 rounded border border-indigo-300 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50"
                    @click="openDetail(log)"
                  >
                    Lihat Detail
                  </button>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ log.ip_address || '-' }}</td>
              </tr>
              <tr v-if="!logs.data.length">
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Belum ada data monitoring.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <Link
          v-for="link in logs.links"
          :key="`${link.label}-${link.url}`"
          :href="link.url || '#'"
          preserve-state
          preserve-scroll
          class="rounded border px-3 py-1.5 text-sm"
          :class="[
            link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-700',
            !link.url ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50',
          ]"
          v-html="link.label"
        />
      </div>

      <div v-if="selectedLog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-auto rounded-xl bg-white p-5 shadow-xl">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Detail Audit</h3>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
              @click="closeDetail"
            >
              Tutup
            </button>
          </div>
          <p class="mb-3 text-sm text-gray-600">{{ selectedLog.description }}</p>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <h4 class="mb-1 text-sm font-semibold text-gray-700">Old Data</h4>
              <pre class="overflow-auto rounded bg-gray-50 p-3 text-xs text-gray-700">{{ prettyJson(selectedLog.old_data) }}</pre>
            </div>
            <div>
              <h4 class="mb-1 text-sm font-semibold text-gray-700">New Data</h4>
              <pre class="overflow-auto rounded bg-gray-50 p-3 text-xs text-gray-700">{{ prettyJson(selectedLog.new_data) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  activityTypes: { type: Array, default: () => [] },
});

const filterQ = ref(props.filters.q || '');
const filterType = ref(props.filters.activity_type || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const selectedLog = ref(null);

const applyFilters = () => {
  router.get('/web-profile/security-monitoring', {
    q: filterQ.value || undefined,
    activity_type: filterType.value || undefined,
    date_from: filterDateFrom.value || undefined,
    date_to: filterDateTo.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const severityLabel = (type) => {
  if (['banner_deleted'].includes(type)) return 'high';
  if (['banner_updated', 'home_service_landing_updated', 'justus_apps_settings_updated'].includes(type)) return 'medium';
  return 'low';
};

const severityBadgeClass = (type) => {
  const severity = severityLabel(type);
  if (severity === 'high') return 'bg-red-50 text-red-700';
  if (severity === 'medium') return 'bg-amber-50 text-amber-700';
  return 'bg-emerald-50 text-emerald-700';
};

const openDetail = (log) => {
  selectedLog.value = log;
};

const closeDetail = () => {
  selectedLog.value = null;
};

const prettyJson = (value) => {
  if (!value) return '-';
  try {
    return JSON.stringify(value, null, 2);
  } catch (error) {
    return String(value);
  }
};

const formatDate = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID');
};
</script>
