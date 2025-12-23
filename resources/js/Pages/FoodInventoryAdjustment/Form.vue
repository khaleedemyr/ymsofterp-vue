<template>
  <AppLayout>
    <Head title="Tambah Stock Adjustment" />
    <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
          <Link
            :href="route('food-inventory-adjustment.index')"
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <i class="fa fa-arrow-left text-gray-600 text-xl"></i>
          </Link>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
              <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-boxes-stacked text-white text-xl"></i>
              </div>
              <span>Tambah Stock Adjustment</span>
            </h1>
            <p class="text-gray-600 ml-16">Buat penyesuaian stok inventory baru</p>
          </div>
        </div>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8">
        <form @submit.prevent="validateAndSubmit" @keydown.enter.prevent>
          <!-- Basic Information -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-5">
              <div class="p-2 bg-blue-100 rounded-lg">
                <i class="fa-solid fa-info-circle text-blue-600"></i>
              </div>
              <h3 class="text-lg font-bold text-gray-800">Informasi Dasar</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-calendar-day text-blue-500 mr-2"></i> Tanggal
                </label>
                <input
                  type="date"
                  v-model="form.date"
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-warehouse text-blue-500 mr-2"></i> Gudang
                </label>
                <select
                  v-model="form.warehouse_id"
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
                  required
                >
                  <option value="">Pilih Gudang</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  <i class="fa-solid fa-tag text-blue-500 mr-2"></i> Tipe Adjustment
                </label>
                <select
                  v-model="form.type"
                  class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
                  required
                >
                  <option value="">Pilih Tipe</option>
                  <option value="in">Stock In</option>
                  <option value="out">Stock Out</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Items Section -->
          <div class="mb-8">
            <div class="flex items-center justify-between mb-5">
              <div class="flex items-center gap-2">
                <div class="p-2 bg-indigo-100 rounded-lg">
                  <i class="fa-solid fa-list-check text-indigo-600"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Detail Items</h3>
              </div>
              <button
                type="button"
                @click="addItem"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl shadow-md hover:shadow-lg transition-all font-semibold"
              >
                <i class="fa-solid fa-plus"></i>
                Tambah Item
              </button>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-full">
                <thead class="bg-gradient-to-r from-indigo-600 to-indigo-700">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-box text-indigo-200"></i>
                        <span>Item</span>
                      </div>
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">
                      <div class="flex items-center justify-end gap-2">
                        <i class="fa-solid fa-calculator text-indigo-200"></i>
                        <span>Qty</span>
                      </div>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-ruler text-indigo-200"></i>
                        <span>Unit</span>
                      </div>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">
                      <div class="flex items-center gap-2">
                        <i class="fa-solid fa-note-sticky text-indigo-200"></i>
                        <span>Note</span>
                      </div>
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-white uppercase tracking-wider">
                      <div class="flex items-center justify-center gap-2">
                        <i class="fa-solid fa-trash text-indigo-200"></i>
                        <span>Action</span>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                  <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx" class="hover:bg-indigo-50 transition-colors">
                    <td class="px-4 py-3 min-w-[260px]">
                      <div class="relative">
                        <input
                          :id="`item-input-${idx}`"
                          type="text"
                          v-model="item.item_name"
                          @input="onItemInput(idx, $event)"
                          @focus="onItemInput(idx, $event)"
                          @blur="onItemBlur(idx)"
                          @keydown.down="onItemKeydown(idx, $event)"
                          @keydown.up="onItemKeydown(idx, $event)"
                          @keydown.enter="onItemKeydown(idx, $event)"
                          @keydown.esc="onItemKeydown(idx, $event)"
                          class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                          required
                          autocomplete="off"
                          placeholder="Cari nama item..."
                        />
                        <Teleport to="body">
                          <div
                            v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                            :style="getDropdownStyle(idx)"
                            :id="`autocomplete-dropdown-${idx}`"
                            class="fixed z-[9999] bg-white border-2 border-blue-200 rounded-xl shadow-2xl max-w-xs w-[260px] max-h-96 overflow-auto mt-1"
                          >
                            <div
                              v-for="(s, sidx) in item.suggestions"
                              :key="s.id"
                              :id="`autocomplete-item-${idx}-${sidx}`"
                              @mousedown.prevent="selectItem(idx, s)"
                              :class="[
                                'px-4 py-3 flex justify-between items-center cursor-pointer transition-colors',
                                item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50'
                              ]"
                            >
                              <div>
                                <div class="font-medium text-gray-900">{{ s.name }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ s.sku }}</div>
                              </div>
                              <div class="text-sm text-gray-600 font-medium">{{ s.unit }}</div>
                            </div>
                          </div>
                        </Teleport>
                        <div v-if="item.loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                          <i class="fa fa-spinner fa-spin text-blue-500"></i>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3 min-w-[100px]">
                      <input
                        type="number"
                        min="0.01"
                        step="0.01"
                        v-model="item.qty"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-right"
                        required
                      />
                    </td>
                    <td class="px-4 py-3 min-w-[100px]">
                      <template v-if="item.available_units && item.available_units.length">
                        <select
                          v-model="item.selected_unit"
                          @change="onUnitChange(idx, $event)"
                          class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white"
                        >
                          <option v-for="u in item.available_units" :key="u" :value="u">{{ u }}</option>
                        </select>
                      </template>
                      <template v-else>
                        <input
                          type="text"
                          v-model="item.unit"
                          class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                          required
                        />
                      </template>
                    </td>
                    <td class="px-4 py-3 min-w-[120px]">
                      <input
                        type="text"
                        v-model="item.note"
                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                        placeholder="Optional"
                      />
                    </td>
                    <td class="px-4 py-3 text-center">
                      <button
                        type="button"
                        @click="removeItem(idx)"
                        :disabled="form.items.length === 1"
                        class="inline-flex items-center justify-center w-10 h-10 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Reason Section -->
          <div class="mb-8">
            <div class="flex items-center gap-2 mb-5">
              <div class="p-2 bg-amber-100 rounded-lg">
                <i class="fa-solid fa-note-sticky text-amber-600"></i>
              </div>
              <h3 class="text-lg font-bold text-gray-800">Alasan / Catatan</h3>
            </div>
            <textarea
              v-model="form.reason"
              class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none"
              rows="3"
              placeholder="Masukkan alasan atau catatan untuk adjustment ini..."
              required
            ></textarea>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
            <Link
              :href="route('food-inventory-adjustment.index')"
              class="px-6 py-2.5 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all font-semibold border-2 border-gray-200 hover:border-gray-300"
            >
              <i class="fa-solid fa-times mr-2"></i> Batal
            </Link>
            <button
              type="submit"
              class="px-6 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold transform hover:-translate-y-0.5"
              :disabled="loading"
            >
              <span v-if="loading" class="animate-spin mr-2 inline-block">
                <i class="fa fa-spinner"></i>
              </span>
              <span v-else>
                <i class="fa-solid fa-save mr-2"></i>
              </span>
              {{ loading ? 'Menyimpan...' : 'Simpan Adjustment' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Loading Overlay -->
      <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-30">
        <div class="bg-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3">
          <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          <span class="font-semibold text-gray-700">Menyimpan...</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import axios from 'axios'

const props = defineProps({
  warehouses: Array,
  items: Array,
  editData: Object,
})

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    available_units: [],
    selected_unit: '',
    unit: '',
    note: '',
    suggestions: [],
    showDropdown: false,
    loading: false,
    highlightedIndex: -1,
    _rowKey: Date.now() + '-' + Math.random(),
  }
}

const form = useForm({
  date: props.editData?.date || new Date().toISOString().slice(0, 10),
  warehouse_id: props.editData?.warehouse_id || '',
  type: props.editData?.type || '',
  items: props.editData?.items?.length ? JSON.parse(JSON.stringify(props.editData.items)) : [newItem()],
  reason: props.editData?.reason || '',
})

const loading = ref(false)

function addItem() {
  form.items.push(newItem())
}

function removeItem(idx) {
  if (form.items.length === 1) return
  form.items.splice(idx, 1)
}

async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2 || !form.warehouse_id) {
    form.items[idx].suggestions = []
    form.items[idx].highlightedIndex = -1
    return
  }
  form.items[idx].loading = true
  try {
    const res = await axios.get('/items/search-for-warehouse-transfer', {
      params: { q, warehouse_id: form.warehouse_id }
    })
    let items = Array.isArray(res.data) ? res.data : []
    form.items[idx].suggestions = items.map(item => ({
      ...item,
      available_units: [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean),
      unit_small: item.unit_small,
      unit_medium: item.unit_medium,
      unit_large: item.unit_large,
      small_unit_id: item.small_unit_id,
      medium_unit_id: item.medium_unit_id,
      large_unit_id: item.large_unit_id,
    }))
    form.items[idx].showDropdown = true
    form.items[idx].highlightedIndex = 0
  } catch (error) {
    form.items[idx].suggestions = []
    form.items[idx].highlightedIndex = -1
  } finally {
    form.items[idx].loading = false
  }
}

function selectItem(idx, item) {
  form.items[idx].item_id = item.id
  form.items[idx].item_name = item.name
  form.items[idx].unit = item.unit_small || item.unit || ''
  form.items[idx].selected_unit = item.unit_small || item.unit || ''
  form.items[idx].available_units = [item.unit_small, item.unit_medium, item.unit_large].filter(Boolean)
  form.items[idx].suggestions = []
  form.items[idx].showDropdown = false
  form.items[idx].highlightedIndex = -1
}

function onItemInput(idx, e) {
  const value = e.target.value
  form.items[idx].item_id = ''
  form.items[idx].item_name = value
  form.items[idx].showDropdown = true
  fetchItemSuggestions(idx, value)
}

function onItemBlur(idx) {
  setTimeout(() => {
    form.items[idx].showDropdown = false
  }, 200)
}

function onItemKeydown(idx, e) {
  const item = form.items[idx]
  if (!item.showDropdown || !item.suggestions.length) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex])
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false
  }
}

function onUnitChange(idx, e) {
  form.items[idx].selected_unit = e.target.value
  form.items[idx].unit = e.target.value
}

function getDropdownStyle(idx) {
  const inputs = document.querySelectorAll('input[placeholder="Cari nama item..."]')
  const input = inputs[idx]
  if (!input) return {}
  const rect = input.getBoundingClientRect()
  return {
    left: rect.left + 'px',
    top: rect.bottom + 'px',
    width: rect.width + 'px',
    position: 'fixed',
    zIndex: 9999
  }
}

function validateAndSubmit() {
  if (loading.value) return
  if (!form.date || !form.warehouse_id || !form.type || !form.reason) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Semua field wajib diisi.',
      confirmButtonColor: '#3085d6'
    })
    return
  }
  if (!form.items.length || form.items.some(item => !item.item_id || !item.qty || !item.selected_unit)) {
    Swal.fire({
      icon: 'error',
      title: 'Validasi Gagal',
      text: 'Setiap item wajib dipilih, qty dan unit diisi.',
      confirmButtonColor: '#3085d6'
    })
    return
  }
  Swal.fire({
    title: 'Konfirmasi Simpan',
    text: 'Apakah Anda yakin ingin menyimpan stock adjustment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, simpan!',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6b7280'
  }).then((result) => {
    if (result.isConfirmed) {
      loading.value = true
      form.post(route('food-inventory-adjustment.store'), {
        onFinish: () => loading.value = false
      })
    }
  })
}
</script>
