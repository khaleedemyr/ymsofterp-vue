<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header Section -->
      <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
          <button 
            @click="goBack" 
            class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <i class="fa fa-arrow-left text-gray-600 text-xl"></i>
          </button>
          <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
              <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-industry text-white text-xl"></i>
              </div>
              <span>Buat MK Production</span>
            </h1>
            <p class="text-gray-600 ml-16">Pencatatan produksi Main Kitchen (sauce, seasoning, dll)</p>
          </div>
        </div>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8">
        <MKProductionForm :items="items" :warehouses="warehouses" @submitted="onSubmitted" @cancel="goBack" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import MKProductionForm from './Form.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items: Array,
  warehouses: Array,
})
function goBack() {
  router.visit(route('mk-production.index'))
}
function onSubmitted() {
  router.visit(route('mk-production.index'))
}

function onItemChange() {
  const item = props.items.find(i => i.id == form.value.item_id)
  form.value.unit_id = item?.small_unit_id || ''
  unitName.value = item?.small_unit_id ? (item.small_unit_name || 'Small') : ''
  fetchBom()
}
function fetchBom() {
  if (!form.value.item_id || !form.value.qty) {
    bom.value = []
    return
  }
  loading.value = true
  axios.post('/mk-production/bom', { item_id: form.value.item_id, qty: form.value.qty })
    .then(res => {
      bom.value = res.data
    })
    .finally(() => loading.value = false)
}
</script>

<style scoped>
.btn.btn-ghost {
  @apply bg-white border border-blue-100 text-blue-700 hover:bg-blue-50 transition;
}
</style> 