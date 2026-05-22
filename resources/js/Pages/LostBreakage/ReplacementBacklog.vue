<template>
  <AppLayout>
    <div class="w-full py-8 px-4 lg:px-6">
      <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
          <button @click="router.visit('/lost-breakage')" class="text-orange-600 text-sm mb-2 hover:underline">
            <i class="fa fa-arrow-left mr-1"></i> Lost &amp; Breakage
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-orange-500"></i>
            Sisa Penggantian L&amp;B
          </h1>
          <p class="text-sm text-gray-500 mt-1">Centang baris item (boleh dari dokumen berbeda), lalu buat PR Asset.</p>
        </div>
        <button
          type="button"
          :disabled="!selectedIds.length || !prIntegrationReady || preparing"
          @click="createPr"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
        >
          <i :class="preparing ? 'fa fa-spinner fa-spin' : 'fa fa-file-invoice'"></i>
          Buat PR Asset ({{ selectedIds.length }})
        </button>
      </div>

      <div v-if="!prIntegrationReady" class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
        <i class="fa fa-triangle-exclamation mr-1"></i>
        Jalankan SQL <code class="text-xs bg-white px-1 rounded">database/sql/lost_breakage_pr_integration.sql</code>
        (dan <code class="text-xs bg-white px-1 rounded">lost_breakage_replacements.sql</code> jika belum).
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
          <div class="sm:col-span-2 xl:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input v-model="filters.search" type="text" placeholder="No. dokumen, item, SKU..." class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>
          <div v-if="isAdmin">
            <label class="block text-sm font-medium text-gray-700 mb-1">Pemilik</label>
            <select v-model="filters.owner_outlet_id" class="w-full px-3 py-2 border rounded-lg text-sm">
              <option value="">Semua</option>
              <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
            <select v-model="filters.outlet_id" class="w-full px-3 py-2 border rounded-lg text-sm">
              <option value="">Semua</option>
              <option v-for="o in outlets" :key="'loc-' + o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
            <select v-model="filters.type" class="w-full px-3 py-2 border rounded-lg text-sm">
              <option value="">Semua</option>
              <option value="lost">Hilang</option>
              <option value="breakage">Rusak</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dari</label>
            <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sampai</label>
            <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 border rounded-lg text-sm" />
          </div>
          <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-2">
            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-semibold">Filter</button>
            <button type="button" @click="resetFilters" class="px-4 py-2 border rounded-lg text-sm">Reset</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm table-fixed min-w-[1200px]">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
              <tr>
                <th class="w-10 px-4 py-3">
                  <input type="checkbox" :checked="allSelected" @change="toggleAll" :disabled="!rows.length" />
                </th>
                <th class="w-[130px] px-4 py-3">No. L&amp;B</th>
                <th class="w-[100px] px-4 py-3">Tanggal</th>
                <th class="w-[140px] px-4 py-3">Pemilik</th>
                <th class="w-[150px] px-4 py-3">Lokasi / Gudang</th>
                <th class="w-[180px] px-4 py-3">Item</th>
                <th class="w-[72px] px-4 py-3">Tipe</th>
                <th class="w-[80px] px-4 py-3 text-right">Qty</th>
                <th class="w-[72px] px-4 py-3 text-right">Diganti</th>
                <th class="w-[72px] px-4 py-3 text-right">Sisa</th>
                <th class="w-[150px] px-4 py-3">PR Asset</th>
                <th class="w-[150px] px-4 py-3">PO</th>
                <th class="w-[150px] px-4 py-3">NFP</th>
                <th class="w-[150px] px-4 py-3">GR Asset</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="r in rows" :key="r.detail_id" class="hover:bg-orange-50/50 align-top" :class="selectedIds.includes(r.detail_id) ? 'bg-orange-50' : ''">
                <td class="px-4 py-3">
                  <input type="checkbox" :value="r.detail_id" v-model="selectedIds" />
                </td>
                <td class="px-4 py-3">
                  <button type="button" @click="router.visit(`/lost-breakage/${r.header_id}`)" class="text-orange-600 font-medium hover:underline text-left break-all">
                    {{ r.header_number || '#' + r.header_id }}
                  </button>
                </td>
                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ r.header_date }}</td>
                <td class="px-4 py-3 text-gray-800">{{ r.owner_outlet_name }}</td>
                <td class="px-4 py-3 text-gray-600">
                  <div>{{ r.location_outlet_name }}</div>
                  <div class="text-xs text-gray-400 mt-0.5">{{ r.warehouse_outlet_name || '—' }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="font-medium text-gray-800">{{ r.item_name }}</div>
                  <div class="text-xs text-gray-400 mt-0.5">{{ r.sku || '—' }}</div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap" :class="r.type === 'lost' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">
                    {{ r.type === 'lost' ? 'Hilang' : 'Rusak' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">{{ formatNum(r.qty) }} {{ r.unit_name }}</td>
                <td class="px-4 py-3 text-right text-gray-500 whitespace-nowrap">{{ formatNum(r.qty_replaced) }}</td>
                <td class="px-4 py-3 text-right font-semibold text-orange-700 whitespace-nowrap">{{ formatNum(r.qty_remaining) }}</td>
                <td class="px-4 py-3">
                  <PipelineDocList :docs="pipelineDocs(r, 'pipeline_prs')" />
                </td>
                <td class="px-4 py-3">
                  <PipelineDocList :docs="pipelineDocs(r, 'pipeline_pos')" />
                </td>
                <td class="px-4 py-3">
                  <PipelineDocList :docs="pipelineDocs(r, 'pipeline_nfps')" />
                </td>
                <td class="px-4 py-3">
                  <PipelineDocList :docs="pipelineDocs(r, 'pipeline_grs')" />
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="14" class="px-4 py-12 text-center text-gray-400">Tidak ada sisa penggantian.</td>
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
import { ref, computed, h } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const PipelineDocList = {
  props: {
    docs: { type: Array, default: () => [] },
  },
  setup(props) {
    const statusClass = (status) => {
      const s = (status || '').toLowerCase()
      if (['approved', 'completed', 'paid', 'done'].includes(s)) return 'text-green-700 bg-green-50'
      if (['rejected', 'cancelled', 'canceled'].includes(s)) return 'text-red-700 bg-red-50'
      if (['submitted', 'waiting', 'pending', 'draft'].includes(s)) return 'text-amber-800 bg-amber-50'
      return 'text-slate-600 bg-slate-50'
    }
    return () => {
      if (!props.docs?.length) {
        return h('span', { class: 'text-xs text-gray-400' }, '—')
      }
      return h(
        'div',
        { class: 'space-y-2' },
        props.docs.map((doc) =>
          h('div', { key: doc.id + '-' + (doc.number || ''), class: 'leading-tight' }, [
            h(
              'a',
              {
                href: doc.url || '#',
                class: 'block text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline break-all',
                onClick: (e) => {
                  if (doc.url) {
                    e.preventDefault()
                    router.visit(doc.url)
                  }
                },
              },
              doc.number || `#${doc.id}`
            ),
            h(
              'span',
              {
                class: `inline-block mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium capitalize ${statusClass(doc.status)}`,
              },
              doc.status || '-'
            ),
          ])
        )
      )
    }
  },
}

function pipelineDocs(row, key) {
  const v = row?.[key]
  return Array.isArray(v) ? v : []
}

const props = defineProps({
  rows: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  isAdmin: Boolean,
  prIntegrationReady: Boolean,
})

const filters = ref({ ...props.filters })
const selectedIds = ref([])
const preparing = ref(false)

const allSelected = computed(() => props.rows.length > 0 && selectedIds.value.length === props.rows.length)

function formatNum(n) {
  const x = Number(n)
  if (Number.isNaN(x)) return '-'
  return x % 1 === 0 ? String(x) : x.toFixed(2)
}

function toggleAll(e) {
  if (e.target.checked) {
    selectedIds.value = props.rows.map((r) => r.detail_id)
  } else {
    selectedIds.value = []
  }
}

function applyFilters() {
  router.get('/lost-breakage/replacement-backlog', filters.value, { preserveState: true })
}

function resetFilters() {
  filters.value = { search: '', owner_outlet_id: '', outlet_id: '', type: '', date_from: '', date_to: '' }
  applyFilters()
}

async function createPr() {
  if (!selectedIds.value.length) return
  preparing.value = true
  try {
    const { data } = await axios.post('/lost-breakage/replacement-backlog/prepare-pr', {
      detail_ids: selectedIds.value,
    })
    if (data.success && data.redirect_url) {
      window.location.href = data.redirect_url
      return
    }
    Swal.fire('Error', data.message || 'Gagal menyiapkan PR', 'error')
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || e.message, 'error')
  } finally {
    preparing.value = false
  }
}
</script>
