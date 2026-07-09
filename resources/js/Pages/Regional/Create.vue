<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-globe"></i> Assign Regional
        </h1>
        <Link :href="route('regional.index')" class="bg-gradient-to-r from-gray-500 to-gray-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6">
          <form @submit.prevent="submitForm">
            <div class="space-y-2 max-w-xl">
              <label class="block text-sm font-semibold text-gray-700">
                <i class="fa-solid fa-user mr-2"></i>Pilih Nama Karyawan
              </label>
              <Multiselect
                v-model="form.user_id"
                :options="userOptions"
                :searchable="true"
                :close-on-select="true"
                :clear-on-select="false"
                :preserve-search="true"
                placeholder="Ketik nama atau email user..."
                track-by="id"
                label="name"
                :preselect-first="false"
                class="w-full regional-multiselect"
              />
              <p v-if="form.errors?.user_id" class="text-red-500 text-xs mt-1">{{ form.errors.user_id }}</p>
            </div>

            <div class="mt-6 space-y-3">
              <label class="block text-sm font-semibold text-gray-700">
                <i class="fa-solid fa-layer-group mr-2"></i>Pilih Area (pilih salah satu)
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-3xl">
                <label
                  v-for="dept in REGIONAL_DEPARTMENTS"
                  :key="dept.key"
                  class="relative flex items-center gap-3 rounded-xl border p-4 cursor-pointer transition-all"
                  :class="form.area === dept.key
                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-200'
                    : 'border-gray-200 bg-white hover:border-indigo-300 hover:bg-indigo-50/40'"
                >
                  <input v-model="form.area" type="radio" :value="dept.key" class="sr-only" />
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    :class="form.area === dept.key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600'"
                  >
                    <i :class="['fas', dept.icon]"></i>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-900">{{ dept.label }}</p>
                    <p class="text-xs text-gray-500">Regional {{ dept.label }}</p>
                  </div>
                </label>
              </div>
              <p v-if="form.errors?.area" class="text-red-500 text-xs">{{ form.errors.area }}</p>
            </div>

            <div class="mt-6 space-y-3 max-w-4xl">
              <label class="block text-sm font-semibold text-gray-700">
                <i class="fa-solid fa-user-tie mr-2"></i>Atasan (Jabatan)
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
              <p v-if="form.errors?.supervisor_position_id" class="text-red-500 text-xs">{{ form.errors.supervisor_position_id }}</p>
            </div>

            <div class="mt-6 space-y-3 max-w-4xl">
              <label class="block text-sm font-semibold text-gray-700">
                <i class="fa-solid fa-store mr-2"></i>Target Kunjungan per Outlet / Bulan
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
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
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
              <p class="text-xs text-gray-500">Total target akan dihitung otomatis dari semua outlet.</p>
              <p v-if="form.errors?.outlet_visit_targets" class="text-red-500 text-xs">{{ form.errors.outlet_visit_targets }}</p>
            </div>

            <div v-if="form.user_id && form.area" class="mt-6 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 shadow-sm max-w-3xl">
              <div class="flex items-center mb-4">
                <div class="bg-blue-100 p-2 rounded-lg mr-3">
                  <i class="fa-solid fa-check-circle text-blue-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-blue-800">Preview Assignment</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-lg border border-blue-100">
                  <p class="text-sm text-gray-600">Karyawan</p>
                  <p class="font-semibold text-gray-800">{{ getUserName(form.user_id) }}</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-blue-100">
                  <p class="text-sm text-gray-600">Area</p>
                  <p class="font-semibold text-gray-800">{{ getAreaLabel(form.area) }}</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-blue-100">
                  <p class="text-sm text-gray-600">Atasan (Jabatan)</p>
                  <p class="font-semibold text-gray-800">{{ form.supervisor_position?.name || '—' }}</p>
                </div>
                <div class="bg-white p-4 rounded-lg border border-blue-100 md:col-span-2">
                  <p class="text-sm text-gray-600">Total Target Kunjungan / Bulan</p>
                  <p class="font-semibold text-gray-800">{{ totalTargetVisits }}</p>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-200">
              <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm transition-all">
                <i class="fa fa-times mr-2"></i>Batal
              </button>
              <button
                type="submit"
                :disabled="!canSubmit || isSubmitting"
                class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-bold shadow-lg hover:shadow-xl transition-all disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed"
              >
                <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa fa-save mr-2"></i>
                {{ isSubmitting ? 'Menyimpan...' : 'Simpan Assignment' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import { ref, computed, onMounted } from 'vue'
import { router, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { REGIONAL_DEPARTMENTS, getAreaLabel } from './regionalOutletUtils'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const form = useForm({
  user_id: null,
  area: '',
  target_outlet_visits: null,
  supervisor_position: null,
  outlet_visit_targets: [],
})

const userOptions = ref([])
const outletOptions = ref([])
const supervisorPositionOptions = ref([])
const isSubmitting = ref(false)

const canSubmit = computed(() =>
  form.user_id
  && form.area
  && form.supervisor_position?.id
  && form.outlet_visit_targets.some((row) => row?.outlet?.id),
)

const getUserName = (userId) => {
  if (!userId) return ''
  if (typeof userId === 'object' && userId.name) return userId.name
  const user = userOptions.value.find((u) => u.id === userId)
  return user ? user.name : ''
}

onMounted(() => {
  loadUsers()
  loadOutlets()
  loadSupervisorPositions()
  if (!form.outlet_visit_targets.length) {
    addOutletRow()
  }
})

const loadUsers = async () => {
  try {
    const response = await fetch('/api/regional/search-users?search=')
    userOptions.value = await response.json()
  } catch (error) {
    console.error('Error loading users:', error)
  }
}

const loadOutlets = async () => {
  try {
    const response = await fetch('/api/regional/search-outlets?search=')
    outletOptions.value = await response.json()
  } catch (error) {
    console.error('Error loading outlets:', error)
  }
}

const loadSupervisorPositions = async () => {
  try {
    const response = await fetch('/api/regional/search-supervisor-positions?search=')
    supervisorPositionOptions.value = await response.json()
  } catch (error) {
    console.error('Error loading supervisor positions:', error)
  }
}

const addOutletRow = () => {
  form.outlet_visit_targets.push({
    outlet: null,
    target_visits: 0,
  })
}

const removeOutletRow = (index) => {
  form.outlet_visit_targets.splice(index, 1)
}

const totalTargetVisits = computed(() => form.outlet_visit_targets.reduce((sum, row) => {
  const value = Number(row.target_visits) || 0
  return sum + value
}, 0))

function submitForm() {
  if (!form.user_id) {
    Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih karyawan terlebih dahulu!' })
    return
  }
  if (!form.area) {
    Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih area Bar, Kitchen, atau Service!' })
    return
  }
  if (!form.outlet_visit_targets.some((row) => row?.outlet?.id)) {
    Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Tambahkan minimal satu outlet target kunjungan.' })
    return
  }
  if (!form.supervisor_position?.id) {
    Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Pilih jabatan atasan terlebih dahulu.' })
    return
  }

  Swal.fire({
    title: 'Konfirmasi Assignment',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>Karyawan:</strong> ${getUserName(form.user_id)}</p>
        <p class="mb-2"><strong>Area:</strong> ${getAreaLabel(form.area)}</p>
        <p class="mb-2"><strong>Atasan:</strong> ${form.supervisor_position?.name || '—'}</p>
        <p class="mb-2"><strong>Total Target Kunjungan:</strong> ${totalTargetVisits.value}</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3b82f6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Ya, Simpan!',
    cancelButtonText: 'Batal',
    showLoaderOnConfirm: true,
    preConfirm: () => new Promise((resolve) => {
      isSubmitting.value = true
      form.transform((data) => ({
        user_id: typeof data.user_id === 'object' ? data.user_id.id : data.user_id,
        area: data.area,
        target_outlet_visits: totalTargetVisits.value,
        supervisor_position_id: data.supervisor_position?.id || null,
        outlet_visit_targets: data.outlet_visit_targets
          .filter((row) => row?.outlet?.id)
          .map((row) => ({
            outlet_id: row.outlet.id,
            target_visits: Number(row.target_visits) || 0,
          })),
      })).post('/regional', {
        onSuccess: () => {
          isSubmitting.value = false
          Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Regional assignment berhasil disimpan!' })
          resolve()
        },
        onError: () => {
          isSubmitting.value = false
          Swal.fire({ icon: 'error', title: 'Error!', text: 'Gagal menyimpan assignment.' })
          resolve()
        },
      })
    }),
    allowOutsideClick: () => !isSubmitting.value,
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
