<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Purchase Order Foods
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <form @submit.prevent="handleSubmit">
              <!-- Header Info -->
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                  <h3 class="text-lg font-semibold mb-2">Informasi PO</h3>
                  <div class="space-y-2">
                    <p><span class="font-medium">Nomor PO:</span> {{ po.number }}</p>
                    <p><span class="font-medium">Tanggal:</span> {{ formatDate(po.date) }}</p>
                    <p><span class="font-medium">Status:</span> 
                      <span :class="getStatusClass(po.status)">{{ po.status }}</span>
                    </p>
                    <p><span class="font-medium">Supplier:</span> {{ po.supplier?.name }}</p>
                  </div>
                </div>
                <div>
                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Catatan</label>
                    <textarea
                      v-model="form.notes"
                      rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- Items Table -->
              <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Daftar Item</h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(item, index) in form.items" :key="index">
                        <td class="px-6 py-4 whitespace-nowrap">{{ item.item?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ item.quantity }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ item.unit }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <input
                            type="number"
                            v-model="item.price"
                            @input="updateItemTotal(index)"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ formatRupiah(item.total) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <button
                            type="button"
                            @click="removeItem(index)"
                            class="text-red-600 hover:text-red-900"
                          >
                            Hapus
                          </button>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="bg-gray-50">
                        <td colspan="4" class="px-6 py-4 text-right font-medium">Total:</td>
                        <td class="px-6 py-4 font-medium">{{ formatRupiah(calculateTotal()) }}</td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex justify-end space-x-4">
                <Link
                  :href="route('po-foods.show', po.id)"
                  class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Batal
                </Link>
                <button
                  type="submit"
                  class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Simpan
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  po: {
    type: Object,
    required: true
  }
})

const form = ref({
  notes: props.po.notes || '',
  items: props.po.items.map(item => ({
    ...item,
    price: item.price,
    total: item.total
  }))
})

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const getStatusClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    pending_gm_finance: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800'
  }
  return `px-2 py-1 rounded-full text-xs font-medium ${classes[status] || classes.draft}`
}

const formatRupiah = (value) => {
  if (typeof value !== 'number') value = Number(value) || 0;
  return 'Rp ' + value.toLocaleString('id-ID');
}

const updateItemTotal = (index) => {
  const item = form.value.items[index]
  item.total = item.quantity * item.price
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

const calculateTotal = () => {
  return form.value.items.reduce((sum, item) => sum + item.total, 0)
}

const handleSubmit = async () => {
  try {
    const response = await axios.put(`/po-foods/${props.po.id}`, form.value)
    
    if (response.data.success) {
      router.visit(route('po-foods.show', props.po.id))
    }
  } catch (error) {
    console.error('Update failed:', error)
  }
}
</script> 