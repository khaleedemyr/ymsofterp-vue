<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  rows: Object,
  users: Array,
  summary: Object,
  filters: Object,
})

function normalizeFilters(f) {
  if (!f) {
    return {
      user_id: '',
      type: 'all',
      date_from: '',
      date_to: '',
      search: '',
      per_page: 25,
    }
  }

  return {
    user_id: f.user_id != null ? String(f.user_id) : '',
    type: f.type ?? 'all',
    date_from: f.date_from ?? '',
    date_to: f.date_to ?? '',
    search: f.search ?? '',
    per_page: Number(f.per_page) > 0 ? Number(f.per_page) : 25,
  }
}

const filters = ref(normalizeFilters(props.filters))
const loading = ref(false)
const employeeSearch = ref('')

watch(
  () => props.filters,
  (newFilters) => {
    filters.value = normalizeFilters(newFilters)
  },
  { deep: true },
)

function applyFilters() {
  router.get('/users/leave-transaction-report', filters.value, {
    preserveState: false,
    replace: true,
    preserveScroll: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
    onCancel: () => (loading.value = false),
    onError: () => (loading.value = false),
  })
}

function resetFilters() {
  filters.value = {
    user_id: '',
    type: 'all',
    date_from: '',
    date_to: '',
    search: '',
    per_page: 25,
  }
  applyFilters()
}

function goToPage(url) {
  if (!url) return

  router.visit(url, {
    preserveState: false,
    replace: true,
    preserveScroll: true,
    onStart: () => (loading.value = true),
    onFinish: () => (loading.value = false),
    onCancel: () => (loading.value = false),
    onError: () => (loading.value = false),
  })
}

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID')
}

function formatType(type) {
  const map = {
    monthly_credit: 'Kredit Bulanan',
    initial_balance: 'Saldo Awal',
    leave_usage: 'Pemakaian Cuti',
    manual_adjustment: 'Penyesuaian',
    burning: 'Burning',
  }

  return map[type] ?? type
}

function amountClass(amount) {
  if (Number(amount) > 0) return 'text-green-600'
  if (Number(amount) < 0) return 'text-red-600'
  return 'text-gray-600'
}

const filteredUsers = computed(() => {
  const keyword = employeeSearch.value.trim().toLowerCase()
  if (!keyword) return props.users

  return (props.users || []).filter((u) => {
    const name = (u.nama_lengkap || '').toLowerCase()
    const nik = (u.nik || '').toLowerCase()
    return name.includes(keyword) || nik.includes(keyword)
  })
})
</script>

<template>
  <AppLayout title="Report Transaksi Cuti">
    <div class="w-full py-8 px-4">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
            <i class="fa-solid fa-calendar-check text-indigo-500"></i>
            Report Transaksi Cuti
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Tracking kapan cuti didapat (kredit) dan kapan cuti digunakan.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">Total Transaksi</div>
          <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">
            {{ Number(summary?.total_transactions || 0) }}
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">Total Cuti Didapat</div>
          <div class="text-2xl font-bold text-green-600">
            +{{ Number(summary?.total_credit_days || 0) }} hari
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">Total Cuti Digunakan</div>
          <div class="text-2xl font-bold text-red-600">
            -{{ Number(summary?.total_usage_days || 0) }} hari
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="text-sm text-gray-500 dark:text-gray-400">Jumlah Karyawan</div>
          <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">
            {{ Number(summary?.total_users || 0) }}
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Karyawan</label>
            <input
              v-model="employeeSearch"
              type="text"
              placeholder="Cari cepat nama/NIK..."
              class="w-full mb-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            />
            <select
              v-model="filters.user_id"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option value="">Semua karyawan</option>
              <option v-for="u in filteredUsers" :key="u.id" :value="String(u.id)">
                {{ u.nama_lengkap }} ({{ u.nik || '-' }})
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipe</label>
            <select
              v-model="filters.type"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option value="all">Semua</option>
              <option value="credit">Dapat Cuti</option>
              <option value="usage">Pakai Cuti</option>
              <option value="adjustment">Adjustment</option>
              <option value="burning">Burning</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Dari</label>
            <input
              v-model="filters.date_from"
              type="date"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Sampai</label>
            <input
              v-model="filters.date_to"
              type="date"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cari</label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Nama, NIK, deskripsi..."
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
              @keyup.enter="applyFilters"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Per halaman</label>
            <select
              v-model.number="filters.per_page"
              class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm"
            >
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button
            type="button"
            class="px-4 py-2 rounded-md bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 font-medium"
            @click="resetFilters"
          >
            Reset
          </button>
          <button
            type="button"
            class="px-4 py-2 rounded-md bg-indigo-600 text-white font-medium disabled:opacity-60"
            :disabled="loading"
            @click="applyFilters"
          >
            {{ loading ? 'Memuat...' : 'Terapkan' }}
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tanggal</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">NIK</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nama</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipe</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Jumlah</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Saldo Setelah</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Deskripsi</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Dibuat Oleh</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="row in rows.data" :key="row.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ row.nik || '-' }}</td>
                <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ row.nama_lengkap }}</td>
                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ formatType(row.transaction_type) }}</td>
                <td class="px-3 py-2 text-sm text-right font-semibold whitespace-nowrap" :class="amountClass(row.amount)">
                  {{ Number(row.amount) > 0 ? '+' : '' }}{{ Number(row.amount) }} hari
                </td>
                <td class="px-3 py-2 text-sm text-right text-gray-700 dark:text-gray-200 whitespace-nowrap">
                  {{ Number(row.balance_after || 0) }} hari
                </td>
                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">{{ row.description || '-' }}</td>
                <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-200">{{ row.created_by_name || 'Sistem' }}</td>
              </tr>
              <tr v-if="!rows.data || rows.data.length === 0">
                <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                  Tidak ada data transaksi cuti
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="rows.links && rows.links.length > 3"
          class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-1 justify-center"
        >
          <button
            v-for="(link, i) in rows.links"
            :key="i"
            type="button"
            class="px-3 py-1 rounded text-sm"
            :class="[
              link.active
                ? 'bg-indigo-600 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200',
              !link.url ? 'opacity-50 cursor-not-allowed' : '',
            ]"
            :disabled="!link.url"
            @click="link.url && goToPage(link.url)"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

