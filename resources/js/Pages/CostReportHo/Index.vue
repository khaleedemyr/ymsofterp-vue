<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2 text-gray-800">
          <i class="fa-solid fa-building-columns text-amber-600"></i>
          Cost Report HO
        </h1>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <p class="text-sm text-gray-600 mb-4">
          Laporan cost pusat per <strong>gudang</strong>. Begin inventory bulan terpilih dihitung dari posisi
          <strong>akhir bulan sebelumnya</strong>: saldo qty kecil pada kartu stok terakhir × MAC pada histori biaya terakhir
          (masing-masing s/d tanggal cutoff yang sama).
        </p>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
            <input
              v-model="filters.bulan"
              type="month"
              class="w-full min-w-[200px] border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <button
            type="button"
            :disabled="clearingCache"
            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50"
            @click="loadReport"
          >
            <i class="fa-solid fa-download mr-2"></i>
            Muat data
          </button>
          <button
            type="button"
            :disabled="clearingCache || !filters.bulan"
            class="bg-amber-600 text-white px-4 py-2 rounded-md hover:bg-amber-700 disabled:opacity-50"
            @click="clearCacheAndReload"
          >
            <i :class="clearingCache ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-rotate'" class="mr-2"></i>
            Clear cache
          </button>
          <a
            v-if="filters.bulan"
            :href="traceBeginUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center text-sm text-slate-700 border border-slate-300 rounded-md px-3 py-2 hover:bg-slate-50"
          >
            <i class="fa-solid fa-magnifying-glass-chart mr-2 text-slate-500"></i>
            Trace JSON (baris begin terbesar)
          </a>
        </div>
      </div>

      <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-1 flex-wrap" aria-label="Tabs">
          <button
            type="button"
            class="px-4 py-3 border-b-2 text-sm font-medium transition"
            :class="activeTab === 'cost' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="switchTab('cost')"
          >
            Cost
          </button>
          <button
            type="button"
            class="px-4 py-3 border-b-2 text-sm font-medium transition"
            :class="activeTab === 'cogs_ideal' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="switchTab('cogs_ideal')"
          >
            Perbandingan COGS Ideal
          </button>
        </nav>
      </div>

      <div class="mb-4">
        <div
          v-if="activeTab === 'cogs_ideal' && loadingComparison"
          class="mb-3 text-sm text-gray-600 flex items-center gap-2"
        >
          <i class="fa-solid fa-spinner fa-spin"></i>
          Memuat struktur tab…
        </div>
        <div v-if="activeTab === 'cost'" class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama gudang</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Begin inventory</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="row in costRowsData"
                :key="'wh-' + row.warehouse_id"
                class="bg-white hover:bg-gray-50"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ row.warehouse_name }}
                  <span v-if="row.warehouse_code" class="text-gray-500 font-normal text-xs ml-1">({{ row.warehouse_code }})</span>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-900">
                  {{ formatNumber(row.begin_inventory) }}
                </td>
              </tr>
              <tr v-if="!costRowsData.length">
                <td colspan="2" class="px-4 py-10 text-center text-gray-500">
                  Pilih bulan lalu klik <strong>Muat data</strong>.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="bg-white rounded-xl shadow overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama gudang</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Perbandingan COGS Ideal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr
                v-for="row in comparisonRowsData"
                :key="'cmp-wh-' + row.warehouse_id"
                class="bg-white hover:bg-gray-50"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                  {{ row.warehouse_name }}
                  <span v-if="row.warehouse_code" class="text-gray-500 font-normal text-xs ml-1">({{ row.warehouse_code }})</span>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-400">—</td>
              </tr>
              <tr v-if="!comparisonRowsData.length">
                <td colspan="2" class="px-4 py-10 text-center text-gray-500">
                  Pilih bulan lalu klik <strong>Muat data</strong>, lalu buka tab ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  costRows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ bulan: '' }) }
})

const filters = ref({ ...props.filters })
const loadingComparison = ref(false)
const clearingCache = ref(false)
const activeTab = ref('cost')
const costRowsData = ref(props.costRows || [])
const comparisonRowsData = ref([])
const loadedTabs = ref({})

const traceBeginUrl = computed(() => {
  const b = filters.value.bulan
  if (!b) {
    return '#'
  }
  return route('cost-report-ho.trace-begin', { bulan: b, limit: 100 })
})

watch(
  () => props.filters,
  (v) => {
    filters.value = { ...v }
  },
  { deep: true }
)

watch(
  () => props.costRows,
  (v) => {
    costRowsData.value = v || []
  },
  { immediate: true }
)

function formatNumber(value) {
  if (value == null || value === '') return '0'
  const num = Number(value)
  if (Number.isNaN(num)) return '0'
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(num)
}

function loadReport() {
  const bulan = filters.value.bulan || ''
  if (!bulan) {
    window.alert('Pilih bulan terlebih dahulu.')
    return
  }
  loadedTabs.value = {}
  comparisonRowsData.value = []
  router.get(
    route('cost-report-ho.index'),
    { load: 1, bulan },
    { preserveState: true, preserveScroll: true }
  )
}

async function switchTab(tab) {
  activeTab.value = tab
  const bulan = filters.value.bulan || ''
  if (tab === 'cogs_ideal') {
    if (!bulan) {
      window.alert('Pilih bulan terlebih dahulu untuk membuka tab ini.')
      activeTab.value = 'cost'
      return
    }
    await fetchComparisonSkeleton(bulan)
  }
}

async function fetchComparisonSkeleton(bulan) {
  if (!bulan) return
  const key = `${bulan}:cogs_ideal`
  if (loadedTabs.value[key]) return
  loadingComparison.value = true
  try {
    const res = await axios.get(route('cost-report-ho.tab-data'), {
      params: { bulan, tab: 'cogs_ideal' }
    })
    if (res.data?.success) {
      comparisonRowsData.value = res.data.comparisonRows || []
      loadedTabs.value[key] = true
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingComparison.value = false
  }
}

async function clearCacheAndReload() {
  const bulan = filters.value.bulan || ''
  if (!bulan) return
  clearingCache.value = true
  try {
    await axios.post(route('cost-report-ho.clear-cache'), { bulan })
    loadedTabs.value = {}
    comparisonRowsData.value = []
    router.get(
      route('cost-report-ho.index'),
      { load: 1, bulan },
      { preserveState: true, preserveScroll: true }
    )
  } catch (e) {
    console.error(e)
  } finally {
    clearingCache.value = false
  }
}
</script>
