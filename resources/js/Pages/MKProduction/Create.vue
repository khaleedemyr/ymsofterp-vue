<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-10 px-2">
      <div class="flex items-center gap-2 mb-4">
        <button @click="goBack" class="btn btn-ghost !px-3 !py-2 rounded-full shadow hover:bg-blue-50">
          <i class="fa fa-arrow-left text-lg"></i>
        </button>
        <div>
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-industry text-blue-500"></i> Buat MK Production
          </h1>
          <div class="text-gray-500 text-sm mt-1">Pencatatan produksi Main Kitchen (sauce, seasoning, dll)</div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-8 mt-2">
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