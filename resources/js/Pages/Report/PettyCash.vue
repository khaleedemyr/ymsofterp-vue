<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-wallet"></i> Petty Cash Report
      </h1>

      <div class="bg-gray-50 rounded-xl p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select
              v-model="filters.outlet"
              :disabled="!can_select_outlet"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
            >
              <option v-if="can_select_outlet" value="">Pilih Outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal</label>
            <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal</label>
            <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
          </div>
          <div class="flex items-end">
            <button
              type="button"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium"
              @click="loadReport"
            >
              <i class="fa-solid fa-search mr-2"></i> Tampilkan
            </button>
          </div>
        </div>
      </div>

      <div v-if="!filters.outlet" class="text-center py-16 text-gray-400">
        <i class="fa-solid fa-store text-4xl mb-3"></i>
        <p>Pilih outlet terlebih dahulu</p>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
            <div class="text-xs font-semibold uppercase text-amber-700">Retail Non Food</div>
            <div class="text-xl font-bold text-amber-900 mt-1">{{ formatCurrency(totals.retail_non_food) }}</div>
          </div>
          <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
            <div class="text-xs font-semibold uppercase text-emerald-700">Retail Food</div>
            <div class="text-xl font-bold text-emerald-900 mt-1">{{ formatCurrency(totals.retail_food) }}</div>
          </div>
          <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4">
            <div class="text-xs font-semibold uppercase text-indigo-700">Grand Total</div>
            <div class="text-xl font-bold text-indigo-900 mt-1">{{ formatCurrency(totals.grand_total) }}</div>
          </div>
          <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
            <div class="text-xs font-semibold uppercase text-slate-600">Petty Cash Lock Budget</div>
            <div class="text-xl font-bold text-slate-900 mt-1">
              {{ petty_cash_budget?.lock_budget != null ? formatCurrency(petty_cash_budget.lock_budget) : '—' }}
            </div>
            <p v-if="petty_cash_budget" class="text-[11px] text-slate-500 mt-1">
              dari Revenue Target bulan {{ filters.date_from?.slice(0, 7) }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
          <!-- Retail Non Food by Category -->
          <div class="rounded-2xl border border-amber-200 overflow-hidden shadow-sm">
            <div class="bg-amber-600 px-4 py-3 text-white font-bold flex items-center justify-between">
              <span>Retail Non Food — per Category</span>
              <span class="text-sm font-semibold">{{ formatCurrency(totals.retail_non_food) }}</span>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="bg-amber-50 text-amber-900">
                    <th class="px-3 py-2 text-left w-12">No</th>
                    <th class="px-3 py-2 text-left">Category</th>
                    <th class="px-3 py-2 text-center">Txn</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, idx) in retail_non_food_by_category"
                    :key="'cat-' + (row.category_id ?? 'null')"
                    class="border-t border-amber-100 hover:bg-amber-50/70"
                  >
                    <td class="px-3 py-2 text-slate-600">{{ idx + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-slate-800">{{ row.category_name }}</td>
                    <td class="px-3 py-2 text-center text-slate-600">{{ row.txn_count }}</td>
                    <td class="px-3 py-2 text-right">
                      <button
                        type="button"
                        class="font-semibold text-amber-800 underline decoration-dotted underline-offset-2 hover:text-amber-950"
                        @click="openDetail('category', row.category_id, row.category_name, row.total)"
                      >
                        {{ formatCurrency(row.total) }}
                      </button>
                    </td>
                  </tr>
                  <tr v-if="retail_non_food_by_category.length === 0">
                    <td colspan="4" class="px-3 py-8 text-center text-slate-400">Tidak ada data</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-amber-700 text-white font-bold border-t-2 border-amber-800">
                    <td class="px-3 py-3" colspan="3">Subtotal Retail Non Food</td>
                    <td class="px-3 py-3 text-right">{{ formatCurrency(totals.retail_non_food) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Retail Food by Supplier -->
          <div class="rounded-2xl border border-emerald-200 overflow-hidden shadow-sm">
            <div class="bg-emerald-600 px-4 py-3 text-white font-bold flex items-center justify-between">
              <span>Retail Food — per Supplier</span>
              <span class="text-sm font-semibold">{{ formatCurrency(totals.retail_food) }}</span>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="bg-emerald-50 text-emerald-900">
                    <th class="px-3 py-2 text-left w-12">No</th>
                    <th class="px-3 py-2 text-left">Supplier</th>
                    <th class="px-3 py-2 text-center">Txn</th>
                    <th class="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, idx) in retail_food_by_supplier"
                    :key="'sup-' + (row.supplier_id ?? 'null')"
                    class="border-t border-emerald-100 hover:bg-emerald-50/70"
                  >
                    <td class="px-3 py-2 text-slate-600">{{ idx + 1 }}</td>
                    <td class="px-3 py-2 font-medium text-slate-800">{{ row.supplier_name }}</td>
                    <td class="px-3 py-2 text-center text-slate-600">{{ row.txn_count }}</td>
                    <td class="px-3 py-2 text-right">
                      <button
                        type="button"
                        class="font-semibold text-emerald-800 underline decoration-dotted underline-offset-2 hover:text-emerald-950"
                        @click="openDetail('supplier', row.supplier_id, row.supplier_name, row.total)"
                      >
                        {{ formatCurrency(row.total) }}
                      </button>
                    </td>
                  </tr>
                  <tr v-if="retail_food_by_supplier.length === 0">
                    <td colspan="4" class="px-3 py-8 text-center text-slate-400">Tidak ada data</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-emerald-700 text-white font-bold border-t-2 border-emerald-800">
                    <td class="px-3 py-3" colspan="3">Subtotal Retail Food</td>
                    <td class="px-3 py-3 text-right">{{ formatCurrency(totals.retail_food) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <div class="rounded-2xl bg-indigo-800 text-white px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 shadow-lg">
          <div>
            <div class="text-sm uppercase tracking-wide text-indigo-200 font-semibold">Grand Total Petty Cash</div>
            <div class="text-xs text-indigo-300 mt-0.5">{{ outlet_name }} · Retail Food + Retail Non Food (non contra bon)</div>
          </div>
          <div class="text-2xl font-bold">{{ formatCurrency(totals.grand_total) }}</div>
        </div>
      </template>
    </div>

    <!-- Detail Modal -->
    <div
      v-if="detailOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closeDetail"
    >
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b flex items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ detailMeta.title }}</h2>
            <p class="text-sm text-gray-500 mt-1">
              {{ filters.date_from }} s/d {{ filters.date_to }}
              <span v-if="detailMeta.amount != null"> · {{ formatCurrency(detailMeta.amount) }}</span>
            </p>
          </div>
          <button type="button" class="text-gray-400 hover:text-gray-700 text-xl" @click="closeDetail">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1">
          <div v-if="detailLoading" class="py-12 text-center text-gray-500">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Memuat detail...
          </div>
          <div v-else-if="detailError" class="py-8 text-center text-red-600">{{ detailError }}</div>
          <div v-else-if="!detailData?.transactions?.length" class="py-8 text-center text-gray-500">Tidak ada transaksi.</div>
          <div v-else class="space-y-5">
            <div
              v-for="txn in detailData.transactions"
              :key="txn.id"
              class="border border-gray-200 rounded-xl overflow-hidden"
            >
              <div class="bg-gray-50 px-4 py-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div><span class="text-gray-500">No:</span> <strong>{{ txn.number || '-' }}</strong></div>
                <div><span class="text-gray-500">Tanggal:</span> <strong>{{ formatDate(txn.date) }}</strong></div>
                <div><span class="text-gray-500">User:</span> <strong>{{ txn.created_by || '-' }}</strong></div>
                <div><span class="text-gray-500">Payment:</span> <strong>{{ txn.payment_method || '-' }}</strong></div>
                <div class="ml-auto"><span class="text-gray-500">Total:</span> <strong>{{ formatCurrency(txn.total) }}</strong></div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead>
                    <tr class="bg-white border-b text-gray-600">
                      <th class="px-4 py-2 text-left">Item</th>
                      <th class="px-4 py-2 text-right">Qty</th>
                      <th class="px-4 py-2 text-left">Unit</th>
                      <th class="px-4 py-2 text-right">Harga</th>
                      <th class="px-4 py-2 text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, i) in txn.items" :key="i" class="border-b last:border-b-0">
                      <td class="px-4 py-2">{{ item.name }}</td>
                      <td class="px-4 py-2 text-right">{{ item.qty }}</td>
                      <td class="px-4 py-2">{{ item.unit }}</td>
                      <td class="px-4 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                      <td class="px-4 py-2 text-right font-medium">{{ formatCurrency(item.subtotal) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t bg-gray-50 flex justify-between items-center">
          <div class="text-sm text-gray-600">
            Grand total: <strong>{{ formatCurrency(detailData?.grand_total || 0) }}</strong>
          </div>
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-black" @click="closeDetail">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
defineOptions({ layout: AppLayout })
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  user: { type: Object, default: () => ({}) },
  can_select_outlet: { type: Boolean, default: false },
  retail_non_food_by_category: { type: Array, default: () => [] },
  retail_food_by_supplier: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({ retail_non_food: 0, retail_food: 0, grand_total: 0 }) },
  petty_cash_budget: { type: Object, default: null },
  outlet_name: { type: String, default: null },
})

const filters = ref({
  outlet: props.filters.outlet || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
})

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detailData = ref(null)
const detailMeta = ref({ title: '', amount: null })

const loadReport = () => {
  if (!filters.value.outlet) {
    alert('Pilih outlet terlebih dahulu')
    return
  }
  router.get('/report-petty-cash', filters.value, {
    preserveState: true,
    preserveScroll: true,
  })
}

const openDetail = async (type, id, label, amount) => {
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = ''
  detailData.value = null
  detailMeta.value = {
    title: label,
    amount,
  }

  try {
    const res = await axios.get('/api/report/petty-cash-detail', {
      params: {
        type,
        key: id == null ? 'null' : String(id),
        outlet: filters.value.outlet,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
      },
    })
    detailData.value = res.data
    if (res.data?.title) detailMeta.value.title = res.data.title
  } catch (e) {
    detailError.value = e?.response?.data?.error || e?.message || 'Gagal memuat detail'
  } finally {
    detailLoading.value = false
  }
}

const closeDetail = () => {
  detailOpen.value = false
  detailLoading.value = false
  detailError.value = ''
  detailData.value = null
}

const formatCurrency = (value) => {
  const num = Number(value) || 0
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(num)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString + 'T12:00:00').toLocaleDateString('id-ID', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>
