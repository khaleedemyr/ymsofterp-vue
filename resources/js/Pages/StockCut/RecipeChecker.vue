<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-3">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <i class="fa-solid fa-utensils text-blue-600"></i>
          Cek Resep BOM (Menu & Modifier)
        </h1>
        <p class="text-sm text-gray-600 mt-1">
          Cari berdasarkan bahan baku atau berdasarkan menu/modifier.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-xl shadow p-5 border border-gray-100">
          <h2 class="text-lg font-semibold text-gray-800 mb-3">1) Cari dari Bahan Baku</h2>
          <div class="space-y-3">
            <Multiselect
              v-model="selectedMaterial"
              :options="materialOptions"
              :searchable="true"
              :close-on-select="true"
              :clear-on-select="false"
              :preserve-search="true"
              :loading="loadingMaterials"
              :preselect-first="false"
              placeholder="Ketik nama bahan baku..."
              label="label"
              track-by="value"
              @search-change="onSearchMaterials"
            />
            <button
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!selectedMaterialId || loadingMaterialResult"
              @click="loadByMaterial"
            >
              {{ loadingMaterialResult ? 'Loading...' : 'Load Penggunaan Bahan' }}
            </button>
          </div>

          <div v-if="materialResult" class="mt-4 space-y-4">
            <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-sm">
              <span class="font-semibold">Bahan baku:</span> {{ materialResult.material.name }}
            </div>

            <div>
              <h3 class="font-semibold text-gray-800 mb-2">Dipakai di Menu</h3>
              <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-3 py-2 text-left">Menu</th>
                      <th class="px-3 py-2 text-left">Qty</th>
                      <th class="px-3 py-2 text-left">Unit</th>
                      <th class="px-3 py-2 text-left">Stock Cut</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in materialResult.menus" :key="`m-${idx}`" class="border-t hover:bg-slate-50/70">
                      <td class="px-3 py-2 font-medium text-gray-800">{{ row.menu_name }}</td>
                      <td class="px-3 py-2 font-mono">{{ formatQty(row.qty) }}</td>
                      <td class="px-3 py-2">{{ row.unit_name || '-' }}</td>
                      <td class="px-3 py-2">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="stockCutLabel(row.stock_cut) === 'Ya' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                          {{ stockCutLabel(row.stock_cut) }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="materialResult.menus.length === 0">
                      <td class="px-3 py-3 text-gray-500" colspan="4">Tidak dipakai di BOM menu.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div>
              <h3 class="font-semibold text-gray-800 mb-2">Dipakai di Modifier</h3>
              <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50">
                    <tr>
                      <th class="px-3 py-2 text-left">Modifier</th>
                      <th class="px-3 py-2 text-left">Option</th>
                      <th class="px-3 py-2 text-left">Qty</th>
                      <th class="px-3 py-2 text-left">Unit</th>
                      <th class="px-3 py-2 text-left">Stock Cut</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, idx) in materialResult.modifiers" :key="`x-${idx}`" class="border-t hover:bg-slate-50/70">
                      <td class="px-3 py-2">{{ row.modifier_name || '-' }}</td>
                      <td class="px-3 py-2 font-medium text-gray-800">{{ row.modifier_option_name }}</td>
                      <td class="px-3 py-2 font-mono">{{ formatQty(row.qty) }}</td>
                      <td class="px-3 py-2">{{ row.unit_name || '-' }}</td>
                      <td class="px-3 py-2">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="stockCutLabel(row.stock_cut) === 'Ya' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                          {{ stockCutLabel(row.stock_cut) }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="materialResult.modifiers.length === 0">
                      <td class="px-3 py-3 text-gray-500" colspan="5">Tidak dipakai di BOM modifier.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl shadow p-5 border border-gray-100">
          <h2 class="text-lg font-semibold text-gray-800 mb-3">2) Cari Resep Menu/Modifier</h2>
          <div class="space-y-3">
            <Multiselect
              v-model="selectedTarget"
              :options="targetOptions"
              :searchable="true"
              :close-on-select="true"
              :clear-on-select="false"
              :preserve-search="true"
              :loading="loadingTargets"
              :preselect-first="false"
              placeholder="Ketik nama menu atau modifier..."
              label="label"
              track-by="value"
              @search-change="onSearchTargets"
            />
            <button
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="!selectedTargetType || !selectedTargetId || loadingTargetResult"
              @click="loadByTarget"
            >
              {{ loadingTargetResult ? 'Loading...' : 'Load Resep Target' }}
            </button>
          </div>

          <div v-if="targetResult" class="mt-4">
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2 text-sm mb-3">
              <span class="font-semibold">Target:</span>
              {{ targetResult.target.name }}
              <span class="ml-2 text-xs uppercase text-emerald-700">({{ targetResult.target.type }})</span>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
              <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-3 py-2 text-left">Bahan Baku</th>
                    <th class="px-3 py-2 text-left">Qty</th>
                    <th class="px-3 py-2 text-left">Unit</th>
                    <th class="px-3 py-2 text-left">Stock Cut</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in targetResult.recipe" :key="`r-${idx}`" class="border-t hover:bg-slate-50/70">
                    <td class="px-3 py-2 font-medium text-gray-800">{{ row.material_name }}</td>
                    <td class="px-3 py-2 font-mono">{{ formatQty(row.qty) }}</td>
                    <td class="px-3 py-2">{{ row.unit_name || '-' }}</td>
                    <td class="px-3 py-2">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="stockCutLabel(row.stock_cut) === 'Ya' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        {{ stockCutLabel(row.stock_cut) }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="targetResult.recipe.length === 0">
                    <td class="px-3 py-3 text-gray-500" colspan="4">Target ini belum punya BOM.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const selectedMaterial = ref(null)
const materialOptions = ref([])
const loadingMaterials = ref(false)
const loadingMaterialResult = ref(false)
const materialResult = ref(null)

const selectedTarget = ref(null)
const targetOptions = ref([])
const loadingTargets = ref(false)
const loadingTargetResult = ref(false)
const targetResult = ref(null)

const selectedMaterialId = computed(() => Number(selectedMaterial.value?.value || 0))
const selectedTargetId = computed(() => Number(selectedTarget.value?.target_id || 0))
const selectedTargetType = computed(() => String(selectedTarget.value?.target_type || ''))

let materialSearchTimer = null
let targetSearchTimer = null

async function loadMaterialOptions() {
  loadingMaterials.value = true
  try {
    const res = await axios.get('/api/stock-cut/recipe-checker/search-materials', { params: { q: '' } })
    materialOptions.value = res.data?.items || []
  } finally {
    loadingMaterials.value = false
  }
}

async function loadTargetOptions() {
  loadingTargets.value = true
  try {
    const res = await axios.get('/api/stock-cut/recipe-checker/search-targets', { params: { q: '' } })
    targetOptions.value = res.data?.items || []
  } finally {
    loadingTargets.value = false
  }
}

function onSearchMaterials(query) {
  if (materialSearchTimer) clearTimeout(materialSearchTimer)
  materialSearchTimer = setTimeout(async () => {
    loadingMaterials.value = true
    try {
      const res = await axios.get('/api/stock-cut/recipe-checker/search-materials', {
        params: { q: query || '' },
      })
      materialOptions.value = res.data?.items || []
    } finally {
      loadingMaterials.value = false
    }
  }, 180)
}

function onSearchTargets(query) {
  if (targetSearchTimer) clearTimeout(targetSearchTimer)
  targetSearchTimer = setTimeout(async () => {
    loadingTargets.value = true
    try {
      const res = await axios.get('/api/stock-cut/recipe-checker/search-targets', {
        params: { q: query || '' },
      })
      targetOptions.value = res.data?.items || []
    } finally {
      loadingTargets.value = false
    }
  }, 180)
}

function formatQty(value) {
  const num = Number(value || 0)
  return num.toLocaleString('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  })
}

function stockCutLabel(value) {
  // Sesuai request: 0 = Ya, 1 = Tidak
  return Number(value) === 0 ? 'Ya' : 'Tidak'
}

async function loadByMaterial() {
  materialResult.value = null
  if (!selectedMaterialId.value) return
  loadingMaterialResult.value = true
  try {
    const res = await axios.get('/api/stock-cut/recipe-checker/by-material', {
      params: { material_item_id: selectedMaterialId.value },
    })
    materialResult.value = res.data
  } finally {
    loadingMaterialResult.value = false
  }
}

async function loadByTarget() {
  targetResult.value = null
  if (!selectedTargetType.value || !selectedTargetId.value) return
  loadingTargetResult.value = true
  try {
    const res = await axios.get('/api/stock-cut/recipe-checker/by-target', {
      params: {
        target_type: selectedTargetType.value,
        target_id: selectedTargetId.value,
      },
    })
    targetResult.value = res.data
  } finally {
    loadingTargetResult.value = false
  }
}

loadMaterialOptions()
loadTargetOptions()
</script>

<style scoped>
:deep(.multiselect) {
  min-height: 42px;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
}

:deep(.multiselect:focus-within) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

:deep(.multiselect__content-wrapper) {
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

:deep(.multiselect__option--highlight) {
  background: #2563eb;
  color: #fff;
}
</style>

