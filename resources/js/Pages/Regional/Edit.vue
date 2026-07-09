<template>
  <AppLayout>
    <div class="w-full px-4 py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-globe"></i>
        Edit Regional - {{ user.nama_lengkap || user.name }}
      </h1>

      <form @submit.prevent="submitForm">
        <div class="mb-6 max-w-xl">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Karyawan</label>
          <div class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-600">
            {{ user.nama_lengkap || user.name }} ({{ user.email }})
          </div>
        </div>

        <div class="mb-6 space-y-3 max-w-3xl">
          <label class="block text-sm font-semibold text-gray-700">Pilih Area (bisa lebih dari satu)</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label
              v-for="dept in REGIONAL_DEPARTMENTS"
              :key="dept.key"
              class="relative flex items-center gap-3 rounded-xl border p-4 cursor-pointer transition-all"
              :class="form.areas.includes(dept.key)
                ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                : 'border-gray-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/40'"
            >
              <input
                type="checkbox"
                class="sr-only"
                :checked="form.areas.includes(dept.key)"
                @change="toggleArea(dept.key)"
              />
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center"
                :class="form.areas.includes(dept.key) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
              >
                <i :class="['fas', dept.icon]"></i>
              </div>
              <div>
                <p class="font-semibold text-gray-900">{{ dept.label }}</p>
                <p class="text-xs text-gray-500">Regional {{ dept.label }}</p>
              </div>
            </label>
          </div>
        </div>

        <div class="mb-6 max-w-4xl">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-user-tie mr-1"></i>
            Atasan (Jabatan)
          </label>
          <Multiselect
            v-model="form.supervisor_position"
            :options="supervisorPositionOptions"
            :searchable="true"
            :close-on-select="true"
            :clear-on-select="false"
            :preserve-search="true"
            placeholder="Pilih jabatan atasan..."
            track-by="id"
            label="name"
            :preselect-first="false"
            class="w-full regional-multiselect"
          />
          <p v-if="form.errors?.supervisor_position_id" class="text-red-500 text-xs mt-1">{{ form.errors.supervisor_position_id }}</p>
        </div>

        <div class="mb-6 max-w-4xl">
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-bullseye mr-1"></i>
            Target Kunjungan per Outlet / Bulan
          </label>
          <div class="space-y-3">
            <div v-for="(row, idx) in form.outlet_visit_targets" :key="idx" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
              <div class="md:col-span-7">
                <Multiselect
                  v-model="row.outlet"
                  :options="outletOptions"
                  :searchable="true"
                  :close-on-select="true"
                  :clear-on-select="false"
                  :preserve-search="true"
                  placeholder="Pilih outlet..."
                  track-by="id"
                  label="name"
                  :preselect-first="false"
                  class="w-full regional-multiselect"
                />
              </div>
              <div class="md:col-span-3">
                <input
                  v-model.number="row.target_visits"
                  type="number"
                  min="0"
                  max="9999"
                  placeholder="Kunjungan"
                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                />
              </div>
              <div class="md:col-span-2">
                <button type="button" class="w-full px-3 py-3 border border-red-200 text-red-600 rounded-lg hover:bg-red-50" @click="removeOutletRow(idx)">
                  Hapus
                </button>
              </div>
            </div>
            <button type="button" class="px-4 py-2 border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50" @click="addOutletRow">
              <i class="fa fa-plus mr-1"></i> Tambah Outlet
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-1">Total target kunjungan (untuk KPI D022): {{ totalTargetVisits }}</p>
        </div>

        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">Batal</button>
          <button type="submit" :disabled="!canSubmit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all disabled:opacity-50">Update</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { REGIONAL_DEPARTMENTS } from './regionalOutletUtils'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  user: Object,
  currentArea: String,
  currentAreas: { type: Array, default: () => [] },
  targetOutletVisits: [Number, String, null],
  outletVisitTargets: { type: Array, default: () => [] },
  supervisorPositionId: [Number, String, null],
})

const form = ref({
  areas: [],
  target_outlet_visits: null,
  supervisor_position: null,
  outlet_visit_targets: [],
})
const outletOptions = ref([])
const supervisorPositionOptions = ref([])
const canSubmit = computed(() =>
  form.value.areas.length > 0
  && form.value.supervisor_position?.id
  && form.value.outlet_visit_targets.some((row) => row?.outlet?.id),
)

const toggleArea = (areaKey) => {
  if (form.value.areas.includes(areaKey)) {
    form.value.areas = form.value.areas.filter((area) => area !== areaKey)
    return
  }

  form.value.areas = [...form.value.areas, areaKey]
}

const totalTargetVisits = computed(() => form.value.outlet_visit_targets.reduce((sum, row) => {
  const value = Number(row.target_visits) || 0
  return sum + value
}, 0))

onMounted(() => {
  form.value.areas = props.currentAreas?.length
    ? [...props.currentAreas]
    : (props.currentArea ? [props.currentArea] : [])
  form.value.target_outlet_visits = props.targetOutletVisits ?? null
  loadOutlets()
  loadSupervisorPositions()
})

const loadOutlets = async () => {
  try {
    const response = await fetch('/api/regional/search-outlets?search=')
    outletOptions.value = await response.json()

    form.value.outlet_visit_targets = (props.outletVisitTargets || []).map((row) => {
      const selectedOutlet = outletOptions.value.find((outlet) => Number(outlet.id) === Number(row.outlet_id))

      return {
        outlet: selectedOutlet || null,
        target_visits: Number(row.target_visits) || 0,
      }
    })

    if (!form.value.outlet_visit_targets.length) {
      addOutletRow()
    }
  } catch (error) {
    console.error('Error loading outlets:', error)
  }
}

const loadSupervisorPositions = async () => {
  try {
    const response = await fetch('/api/regional/search-supervisor-positions?search=')
    supervisorPositionOptions.value = await response.json()
    const selected = supervisorPositionOptions.value.find((row) => Number(row.id) === Number(props.supervisorPositionId))
    form.value.supervisor_position = selected || null
  } catch (error) {
    console.error('Error loading supervisor positions:', error)
  }
}

function addOutletRow() {
  form.value.outlet_visit_targets.push({
    outlet: null,
    target_visits: 0,
  })
}

function removeOutletRow(index) {
  form.value.outlet_visit_targets.splice(index, 1)
}

function submitForm() {
  if (!form.value.areas.length) {
    alert('Pilih minimal satu area (Bar, Kitchen, atau Service)')
    return
  }
  if (!form.value.outlet_visit_targets.some((row) => row?.outlet?.id)) {
    alert('Tambahkan minimal satu outlet target kunjungan')
    return
  }
  if (!form.value.supervisor_position?.id) {
    alert('Pilih jabatan atasan terlebih dahulu')
    return
  }

  router.put(`/regional/${props.user.id}`, {
    areas: form.value.areas,
    area: form.value.areas[0] || null,
    target_outlet_visits: totalTargetVisits.value,
    supervisor_position_id: form.value.supervisor_position?.id || null,
    outlet_visit_targets: form.value.outlet_visit_targets
      .filter((row) => row?.outlet?.id)
      .map((row) => ({
        outlet_id: row.outlet.id,
        target_visits: Number(row.target_visits) || 0,
      })),
  })
}

function goBack() {
  router.get('/regional')
}
</script>

<style scoped>
:deep(.regional-multiselect .multiselect__option--highlight) {
  background: #4f46e5 !important;
  color: #ffffff !important;
}

:deep(.regional-multiselect .multiselect__option--selected.multiselect__option--highlight) {
  background: #4f46e5 !important;
  color: #ffffff !important;
}
</style>
