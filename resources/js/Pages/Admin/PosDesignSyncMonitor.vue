<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  summary: {
    type: Array,
    default: () => [],
  },
  logs: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ kode_outlet: '', status: '' }),
  },
});

const statusClass = (status) => {
  if (status === 'success') return 'bg-emerald-100 text-emerald-700';
  if (status === 'validation_failed') return 'bg-amber-100 text-amber-700';
  if (status === 'failed') return 'bg-rose-100 text-rose-700';
  return 'bg-slate-100 text-slate-700';
};
</script>

<template>
  <Head title="POS Design Sync Monitor" />

  <AppLayout>
    <div class="w-full min-h-screen bg-slate-50 px-4 py-8">
      <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold text-slate-800">POS Design Sync Monitor</h1>
            <p class="text-sm text-slate-500">Monitoring sinkronisasi 1 arah dari YMSoftPOS ke server pusat</p>
          </div>
          <a
            href="/admin/pos-design-sync-monitor"
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
          >
            Refresh
          </a>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
          <form method="get" action="/admin/pos-design-sync-monitor" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input
              type="text"
              name="kode_outlet"
              :value="filters.kode_outlet"
              placeholder="Filter kode outlet"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
            />
            <select
              name="status"
              :value="filters.status"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">Semua status</option>
              <option value="success">Success</option>
              <option value="validation_failed">Validation Failed</option>
              <option value="failed">Failed</option>
            </select>
            <button
              type="submit"
              class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
            >
              Terapkan Filter
            </button>
            <a
              href="/admin/pos-design-sync-monitor"
              class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100"
            >
              Reset
            </a>
          </form>
        </div>

        <div class="rounded-xl bg-white shadow-sm border border-slate-200 overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Ringkasan Snapshot per Outlet</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-100 text-slate-600 uppercase text-xs">
                <tr>
                  <th class="px-4 py-3 text-left">Kode Outlet</th>
                  <th class="px-4 py-3 text-left">Nama Outlet</th>
                  <th class="px-4 py-3 text-right">Sections</th>
                  <th class="px-4 py-3 text-right">Tables</th>
                  <th class="px-4 py-3 text-right">Total Seating Capacity</th>
                  <th class="px-4 py-3 text-right">Accessories</th>
                  <th class="px-4 py-3 text-left">Last Success Sync</th>
                  <th class="px-4 py-3 text-left">Last Status</th>
                  <th class="px-4 py-3 text-left">Last Message</th>
                  <th class="px-4 py-3 text-left">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="summary.length === 0">
                  <td colspan="10" class="px-4 py-8 text-center text-slate-400">Belum ada data sinkronisasi</td>
                </tr>
                <tr v-for="row in summary" :key="row.kode_outlet" class="border-t border-slate-100 hover:bg-slate-50">
                  <td class="px-4 py-3 font-semibold text-slate-800">{{ row.kode_outlet }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ row.nama_outlet || '-' }}</td>
                  <td class="px-4 py-3 text-right">{{ row.sections_count }}</td>
                  <td class="px-4 py-3 text-right">{{ row.tables_count }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-slate-800">{{ row.total_seating_capacity }}</td>
                  <td class="px-4 py-3 text-right">{{ row.accessories_count }}</td>
                  <td class="px-4 py-3">{{ row.last_synced_at || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(row.last_status)">
                      {{ row.last_status || '-' }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-slate-600">{{ row.last_message || '-' }}</td>
                  <td class="px-4 py-3">
                    <Link
                      :href="`/admin/pos-design-sync-layout?kode_outlet=${encodeURIComponent(row.kode_outlet)}`"
                      class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                      Lihat Layout
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm border border-slate-200 overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Histori Sync POS Design</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-100 text-slate-600 uppercase text-xs">
                <tr>
                  <th class="px-4 py-3 text-left">Waktu</th>
                  <th class="px-4 py-3 text-left">Kode Outlet</th>
                  <th class="px-4 py-3 text-left">Nama Outlet</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-right">Sections</th>
                  <th class="px-4 py-3 text-right">Tables</th>
                  <th class="px-4 py-3 text-right">Accessories</th>
                  <th class="px-4 py-3 text-left">Pesan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="logs.data.length === 0">
                  <td colspan="8" class="px-4 py-8 text-center text-slate-400">Belum ada histori sync</td>
                </tr>
                <tr v-for="log in logs.data" :key="log.id" class="border-t border-slate-100 hover:bg-slate-50">
                  <td class="px-4 py-3">{{ log.synced_at || log.created_at }}</td>
                  <td class="px-4 py-3 font-semibold text-slate-800">{{ log.kode_outlet }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ log.nama_outlet || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="statusClass(log.status)">
                      {{ log.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right">{{ log.sections_count }}</td>
                  <td class="px-4 py-3 text-right">{{ log.tables_count }}</td>
                  <td class="px-4 py-3 text-right">{{ log.accessories_count }}</td>
                  <td class="px-4 py-3 text-slate-600">{{ log.message || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="logs.links && logs.links.length" class="px-4 py-3 border-t border-slate-200 flex flex-wrap gap-2">
            <template v-for="link in logs.links" :key="link.label">
              <Link
                v-if="link.url"
                :href="link.url"
                v-html="link.label"
                class="px-3 py-1 rounded border text-sm"
                :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
              />
              <span
                v-else
                v-html="link.label"
                class="px-3 py-1 rounded border text-sm bg-slate-100 text-slate-400 border-slate-200"
              />
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
