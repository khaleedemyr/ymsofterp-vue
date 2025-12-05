<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto py-10 px-2">
      <div class="flex items-center gap-2 mb-4">
        <button @click="goBack" class="btn btn-ghost !px-3 !py-2 rounded-full shadow hover:bg-blue-50">
          <i class="fa fa-arrow-left text-lg"></i>
        </button>
        <div>
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-industry text-blue-500"></i> Buat Outlet WIP Production
          </h1>
          <div class="text-gray-500 text-sm mt-1">Pencatatan produksi bahan setengah jadi di outlet</div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-8 mt-2">
        <OutletWIPForm 
          :items="items" 
          :warehouse_outlets="warehouse_outlets" 
          :outlets="outlets"
          :user_outlet_id="user_outlet_id"
          :headerData="headerData"
          :detailData="detailData"
          @submitted="onSubmitted" 
          @cancel="goBack" 
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import OutletWIPForm from './Form.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  items: Array,
  warehouse_outlets: Array,
  outlets: Array,
  user_outlet_id: Number,
  headerData: Object,
  detailData: Array,
})

function goBack() {
  router.visit(route('outlet-wip.index'))
}

function onSubmitted() {
  router.visit(route('outlet-wip.index'))
}
</script>

<style scoped>
.btn.btn-ghost {
  background: transparent;
  border: none;
  color: #6b7280;
}

.btn.btn-ghost:hover {
  background: #f3f4f6;
}
</style>
