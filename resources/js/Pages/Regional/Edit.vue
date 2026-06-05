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
          <label class="block text-sm font-semibold text-gray-700">Pilih Area (pilih salah satu)</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
        </div>

        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">Batal</button>
          <button type="submit" :disabled="!form.area" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all disabled:opacity-50">Update</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { REGIONAL_DEPARTMENTS } from './regionalOutletUtils'

const props = defineProps({
  user: Object,
  currentArea: String,
})

const form = ref({
  area: '',
})

onMounted(() => {
  form.value.area = props.currentArea || ''
})

function submitForm() {
  if (!form.value.area) {
    alert('Pilih area Bar, Kitchen, atau Service')
    return
  }

  router.put(`/regional/${props.user.id}`, {
    area: form.value.area,
  })
}

function goBack() {
  router.get('/regional')
}
</script>
