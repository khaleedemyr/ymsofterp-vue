<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
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
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white rounded-xl font-semibold text-sm hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed"
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
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="lg:col-span-2">
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
          <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-sm font-semibold">Filter</button>
            <button type="button" @click="resetFilters" class="px-4 py-2 border rounded-lg text-sm">Reset</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
              <tr>
                <th class="px-3 py-3 w-10">
                  <input type="checkbox" :checked="allSelected" @change="toggleAll" :disabled="!rows.length" />
                </th>
                <th class="px-3 py-3">No. L&amp;B</th>
                <th class="px-3 py-3">Tanggal</th>
                <th class="px-3 py-3">Pemilik</th>
                <th class="px-3 py-3">Lokasi / Gudang</th>
                <th class="px-3 py-3">Item</th>
                <th class="px-3 py-3">Tipe</th>
                <th class="px-3 py-3 text-right">Qty</th>
                <th class="px-3 py-3 text-right">Diganti</th>
                <th class="px-3 py-3 text-right">Sisa</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="r in rows" :key="r.detail_id" class="hover:bg-orange-50/50" :class="selectedIds.includes(r.detail_id) ? 'bg-orange-50' : ''">
                <td class="px-3 py-2">
                  <input type="checkbox" :value="r.detail_id" v-model="selectedIds" />
                </td>
                <td class="px-3 py-2">
                  <button type="button" @click="router.visit(`/lost-breakage/${r.header_id}`)" class="text-orange-600 font-medium hover:underline">
                    {{ r.header_number || '#' + r.header_id }}
                  </button>
                </td>
                <td class="px-3 py-2 text-gray-600">{{ r.header_date }}</td>
                <td class="px-3 py-2">{{ r.owner_outlet_name }}</td>
                <td class="px-3 py-2 text-gray-600">
                  <div>{{ r.location_outlet_name }}</div>
                  <div class="text-xs text-gray-400">{{ r.warehouse_outlet_name || '-' }}</div>
                </td>
                <td class="px-3 py-2">
                  <div class="font-medium text-gray-800">{{ r.item_name }}</div>
                  <div class="text-xs text-gray-400">{{ r.sku || '-' }}</div>
                </td>
                <td class="px-3 py-2">
                  <span class="px-2 py-0.5 rounded text-xs font-medium" :class="r.type === 'lost' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">
                    {{ r.type === 'lost' ? 'Hilang' : 'Rusak' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right">{{ formatNum(r.qty) }} {{ r.unit_name }}</td>
                <td class="px-3 py-2 text-right text-gray-500">{{ formatNum(r.qty_replaced) }}</td>
                <td class="px-3 py-2 text-right font-semibold text-orange-700">{{ formatNum(r.qty_remaining) }}</td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="10" class="px-4 py-12 text-center text-gray-400">Tidak ada sisa penggantian.</td>
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
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

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
