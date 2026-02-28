<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Outlet Rejection
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold">Create New Outlet Rejection</h3>
              <Link
                :href="route('outlet-rejections.index')"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
              >
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
              </Link>
            </div>

            <!-- Form -->
            <form @submit.prevent="submitForm">
              <!-- Header Information -->
              <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h4 class="text-md font-semibold mb-4">Header Information</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <!-- Rejection Date -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Rejection Date <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="form.rejection_date"
                      type="date"
                      required
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.rejection_date }"
                    />
                    <p v-if="errors.rejection_date" class="text-red-500 text-xs mt-1">
                      {{ errors.rejection_date }}
                    </p>
                  </div>

                  <!-- Outlet -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Outlet <span class="text-red-500">*</span>
                    </label>
                    <select
                      v-model="form.outlet_id"
                      required
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.outlet_id }"
                    >
                      <option value="">Select Outlet</option>
                      <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                        {{ outlet.nama_outlet }}
                      </option>
                    </select>
                    <p v-if="errors.outlet_id" class="text-red-500 text-xs mt-1">
                      {{ errors.outlet_id }}
                    </p>
                  </div>

                  <!-- Warehouse -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Warehouse <span class="text-red-500">*</span>
                    </label>
                    <select
                      v-model="form.warehouse_id"
                      required
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.warehouse_id }"
                    >
                      <option value="">Select Warehouse</option>
                      <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                        {{ warehouse.name }}
                      </option>
                    </select>
                    <p v-if="errors.warehouse_id" class="text-red-500 text-xs mt-1">
                      {{ errors.warehouse_id }}
                    </p>
                  </div>

                  <!-- Delivery Order (Optional) -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Delivery Order (Optional)
                    </label>
                    <input
                      v-model="deliveryOrderSearch"
                      type="text"
                      class="w-full mb-2 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      placeholder="Search delivery order..."
                      :disabled="!form.outlet_id || !form.warehouse_id"
                    />
                    <select
                      v-model="form.delivery_order_id"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      @change="onDeliveryOrderChange"
                      :disabled="!form.outlet_id || !form.warehouse_id"
                    >
                      <option value="">Select Delivery Order</option>
                      <option v-if="!form.outlet_id || !form.warehouse_id" value="" disabled>
                        Please select outlet and warehouse first
                      </option>
                      <option v-if="form.outlet_id && form.warehouse_id && filteredDeliveryOrders.length === 0" value="" disabled>
                        No delivery order found
                      </option>
                      <option v-for="deliveryOrder in filteredDeliveryOrders" :key="deliveryOrder.id" :value="deliveryOrder.id">
                          {{ deliveryOrder.display_text || deliveryOrder.number }}
                          (FO: {{ deliveryOrder.floor_order_number }} [{{ deliveryOrder.floor_order_mode }}], PL: {{ deliveryOrder.packing_number }}, GR: {{ deliveryOrder.good_receive_number }})
                      </option>
                    </select>
                    <p v-if="errors.delivery_order_id" class="text-red-500 text-xs mt-1">
                      {{ errors.delivery_order_id }}
                    </p>
                  </div>

                  <!-- Notes -->
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                      Notes
                    </label>
                    <textarea
                      v-model="form.notes"
                      rows="3"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                      placeholder="Enter general notes about this rejection..."
                    ></textarea>
                    <p v-if="errors.notes" class="text-red-500 text-xs mt-1">
                      {{ errors.notes }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Document Flow Information -->
              <div v-if="documentFlowInfo" class="bg-blue-50 p-6 rounded-lg mb-6">
                <h4 class="text-lg font-semibold mb-4">Document Flow Information</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                  <!-- Floor Order -->
                  <div v-if="documentFlowInfo.floor_order_number" class="bg-white p-4 rounded-lg border">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Floor Order</h5>
                    <div class="text-lg font-semibold text-indigo-600">{{ documentFlowInfo.floor_order_number }}</div>
                    <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.floor_order_created_at) }}</div>
                    <div class="text-sm text-gray-600">{{ documentFlowInfo.floor_order_creator }}</div>
                    <div v-if="documentFlowInfo.floor_order_mode" class="text-xs text-gray-400 mt-1">
                      Mode: {{ documentFlowInfo.floor_order_mode }}
                    </div>
                  </div>
                  
                  <!-- Packing List -->
                  <div v-if="documentFlowInfo.packing_number" class="bg-white p-4 rounded-lg border">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Packing List</h5>
                    <div class="text-lg font-semibold text-blue-600">{{ documentFlowInfo.packing_number }}</div>
                    <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.packing_list_created_at) }}</div>
                    <div class="text-sm text-gray-600">{{ documentFlowInfo.packing_list_creator }}</div>
                  </div>
                  
                  <!-- Delivery Order -->
                  <div v-if="documentFlowInfo.delivery_order_number" class="bg-white p-4 rounded-lg border">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Delivery Order</h5>
                    <div class="text-lg font-semibold text-orange-600">{{ documentFlowInfo.delivery_order_number }}</div>
                    <div class="text-sm text-gray-500">{{ formatDateTime(documentFlowInfo.delivery_order_created_at) }}</div>
                    <div class="text-sm text-gray-600">{{ documentFlowInfo.delivery_order_creator }}</div>
                  </div>
                  
                  <!-- Good Receive -->
                  <div v-if="documentFlowInfo.good_receive_number" class="bg-white p-4 rounded-lg border">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Good Receive</h5>
                    <div class="text-lg font-semibold text-green-600">{{ documentFlowInfo.good_receive_number }}</div>
                    <div class="text-sm text-gray-500">{{ formatDate(documentFlowInfo.good_receive_date) }}</div>
                    <div class="text-sm text-gray-600">{{ documentFlowInfo.good_receive_creator }}</div>
                  </div>
                </div>
              </div>

              <!-- Items Section -->
              <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <div class="flex justify-between items-center mb-4">
                  <h4 class="text-md font-semibold">Rejected Items</h4>
                  <div v-if="loading" class="flex items-center text-blue-600">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Loading items...
                  </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-4xl mb-4 text-blue-600"></i>
                  <p>Loading delivery order items...</p>
                </div>

                <!-- Items List -->
                <div v-else-if="form.items.length === 0" class="text-center py-8 text-gray-500">
                  <i class="fas fa-box text-4xl mb-4"></i>
                  <p>No items available for rejection. Please select a delivery order.</p>
                </div>

                <div v-else class="space-y-4">
                  <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="bg-white p-4 rounded-lg border border-gray-200"
                  >
                    <div class="flex justify-between items-start mb-4">
                      <h5 class="font-medium">Item #{{ index + 1 }}</h5>
                      <button
                        type="button"
                        @click="removeItem(index)"
                        class="text-red-500 hover:text-red-700"
                      >
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                      <!-- Item Selection -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Item <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                          <input
                            v-model="item.item_search"
                            type="text"
                            placeholder="Search item..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            @input="searchItems(index)"
                            @focus="showItemDropdown(index)"
                            @blur="hideItemDropdown(index)"
                          />
                          <div v-if="item.show_dropdown && item.search_results.length > 0" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                            <div
                              v-for="result in item.search_results"
                              :key="result.id"
                              @click="selectItem(index, result)"
                              class="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100"
                            >
                              <div class="font-medium">{{ result.name }}</div>
                              <div class="text-sm text-gray-500">{{ result.sku }}</div>
                            </div>
                          </div>
                        </div>
                        <p v-if="errors[`items.${index}.item_id`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.item_id`] }}
                        </p>
                      </div>

                      <!-- Unit Selection -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Unit <span class="text-red-500">*</span>
                        </label>
                        <select
                          v-model="item.unit_id"
                          required
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          :class="{ 'border-red-500': errors[`items.${index}.unit_id`] }"
                          :disabled="!item.item_id"
                        >
                          <option value="">Select Unit</option>
                          <option v-for="unit in item.unit_options" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                          </option>
                        </select>
                        <p v-if="errors[`items.${index}.unit_id`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.unit_id`] }}
                        </p>
                      </div>

                      <!-- Quantity Rejected -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Qty Rejected <span class="text-red-500">*</span>
                        </label>
                        <input
                          v-model="item.qty_rejected"
                          type="number"
                          step="0.01"
                          min="0.01"
                          required
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          :class="{ 'border-red-500': errors[`items.${index}.qty_rejected`] }"
                        />
                        <p v-if="errors[`items.${index}.qty_rejected`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.qty_rejected`] }}
                        </p>
                      </div>

                      <!-- Item Condition -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Condition <span class="text-red-500">*</span>
                        </label>
                        <select
                          v-model="item.item_condition"
                          required
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          :class="{ 'border-red-500': errors[`items.${index}.item_condition`] }"
                        >
                          <option value="">Select Condition</option>
                          <option value="good">Good</option>
                          <option value="damaged">Damaged</option>
                          <option value="expired">Expired</option>
                          <option value="other">Other</option>
                        </select>
                        <p v-if="errors[`items.${index}.item_condition`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.item_condition`] }}
                        </p>
                      </div>
                    </div>

                    <!-- Additional Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                      <!-- Rejection Reason -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Rejection Reason
                        </label>
                        <textarea
                          v-model="item.rejection_reason"
                          rows="2"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          placeholder="Enter rejection reason..."
                        ></textarea>
                        <p v-if="errors[`items.${index}.rejection_reason`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.rejection_reason`] }}
                        </p>
                      </div>

                      <!-- Condition Notes -->
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                          Condition Notes
                        </label>
                        <textarea
                          v-model="item.condition_notes"
                          rows="2"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                          placeholder="Enter condition details..."
                        ></textarea>
                        <p v-if="errors[`items.${index}.condition_notes`]" class="text-red-500 text-xs mt-1">
                          {{ errors[`items.${index}.condition_notes`] }}
                        </p>
                      </div>
                    </div>

                                         <!-- Selected Item Info -->
                     <div v-if="item.selected_item" class="mt-4 p-3 bg-blue-50 rounded-lg">
                       <!-- Basic Item Info -->
                       <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-3">
                         <div>
                           <span class="font-medium">Item:</span> {{ item.selected_item.name }}
                         </div>
                         <div>
                           <span class="font-medium">SKU:</span> {{ item.selected_item.sku }}
                         </div>
                         <div>
                           <span class="font-medium">Category:</span> {{ item.selected_item.category?.name || 'N/A' }}
                         </div>
                       </div>
                       
                                                                 <!-- Quantity Flow Information -->
                  <div v-if="itemsWithQuantityFlow && itemsWithQuantityFlow[item.item_id]" class="mt-3 pt-3 border-t border-blue-200">
                    <h6 class="font-medium text-sm mb-2 text-blue-800">Quantity Flow:</h6>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                      <div class="bg-blue-50 p-2 rounded border">
                        <div class="font-medium text-gray-700">Order Qty</div>
                        <div class="text-blue-600">{{ itemsWithQuantityFlow[item.item_id].qty_order || 0 }}</div>
                      </div>
                      <div class="bg-green-50 p-2 rounded border">
                        <div class="font-medium text-gray-700">PL Qty</div>
                        <div class="text-green-600">{{ itemsWithQuantityFlow[item.item_id].qty_packing_list || 0 }}</div>
                      </div>
                      <div class="bg-orange-50 p-2 rounded border">
                        <div class="font-medium text-gray-700">DO Qty</div>
                        <div class="text-orange-600">{{ itemsWithQuantityFlow[item.item_id].qty_do || 0 }}</div>
                      </div>
                      <div class="bg-purple-50 p-2 rounded border">
                        <div class="font-medium text-gray-700">GR Qty</div>
                        <div class="text-purple-600">{{ itemsWithQuantityFlow[item.item_id].qty_receive || 0 }}</div>
                      </div>
                      <div class="bg-red-50 p-2 rounded border">
                        <div class="font-medium text-gray-700">Remaining</div>
                        <div class="text-red-600 font-bold">{{ (itemsWithQuantityFlow[item.item_id].qty_do || 0) - (itemsWithQuantityFlow[item.item_id].qty_receive || 0) }}</div>
                      </div>
                    </div>
                  </div>
                       
                       <!-- Remaining Qty Information -->
                       <div v-if="item.remaining_qty !== undefined" class="mt-3 pt-3 border-t border-blue-200">
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                           <div class="bg-yellow-50 p-2 rounded border border-yellow-200">
                             <span class="font-medium text-yellow-800">Remaining Qty:</span> 
                             <span class="text-yellow-700 font-bold">{{ item.remaining_qty }}</span>
                           </div>
                           <div class="bg-green-50 p-2 rounded border border-green-200">
                             <span class="font-medium text-green-800">Received Qty:</span> 
                             <span class="text-green-700">{{ item.received_qty }}</span>
                           </div>
                         </div>
                       </div>
                       
                                         <!-- Document Information -->
                  <div v-if="item.floor_order_info || item.packing_list_info || item.good_receive_info" class="mt-3 pt-3 border-t border-blue-200">
                    <h6 class="font-medium text-sm mb-2 text-blue-800">Document Information:</h6>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
                      <div v-if="item.floor_order_info" class="bg-white p-2 rounded border">
                        <div class="font-medium text-gray-700">Floor Order</div>
                        <div class="text-indigo-600">{{ item.floor_order_info.number }}</div>
                        <div class="text-gray-500">{{ new Date(item.floor_order_info.created_at).toLocaleDateString() }}</div>
                        <div class="text-xs text-gray-400">{{ item.floor_order_info.mode }}</div>
                      </div>
                      <div v-if="item.packing_list_info" class="bg-white p-2 rounded border">
                        <div class="font-medium text-gray-700">Packing List</div>
                        <div class="text-blue-600">{{ item.packing_list_info.number }}</div>
                        <div class="text-gray-500">{{ new Date(item.packing_list_info.created_at).toLocaleDateString() }}</div>
                      </div>
                      <div v-if="item.good_receive_info" class="bg-white p-2 rounded border">
                        <div class="font-medium text-gray-700">Good Receive</div>
                        <div class="text-green-600">{{ item.good_receive_info.number }}</div>
                        <div class="text-gray-500">{{ new Date(item.good_receive_info.receive_date).toLocaleDateString() }}</div>
                      </div>
                      <div class="bg-white p-2 rounded border">
                        <div class="font-medium text-gray-700">Delivery Order</div>
                        <div class="text-orange-600">{{ form.delivery_order_id ? 'Selected' : 'N/A' }}</div>
                        <div class="text-gray-500">Current</div>
                      </div>
                    </div>
                  </div>
                     </div>
                  </div>
                </div>
              </div>

              <!-- Form Actions -->
              <div class="flex justify-end space-x-4">
                <Link
                  :href="route('outlet-rejections.index')"
                  class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  Cancel
                </Link>
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50"
                >
                  <i v-if="isSubmitting" class="fas fa-spinner fa-spin mr-2"></i>
                  <i v-else class="fas fa-save mr-2"></i>
                  {{ isSubmitting ? 'Saving...' : 'Save Rejection' }}
                </button>
              </div>
            </form>

          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  outlets: Array,
  warehouses: Array,
  deliveryOrders: Array,
  errors: Object,
  documentFlowInfo: Object,
  itemsWithQuantityFlow: Object
})

const deliveryOrders = ref(props.deliveryOrders || [])
const deliveryOrderSearch = ref('')
const documentFlowInfo = ref(props.documentFlowInfo || null)
const itemsWithQuantityFlow = ref(props.itemsWithQuantityFlow || {})

const isSubmitting = ref(false)
const loading = ref(false)

const filteredDeliveryOrders = computed(() => {
  const keyword = deliveryOrderSearch.value?.trim().toLowerCase()

  if (!keyword) {
    return deliveryOrders.value
  }

  return deliveryOrders.value.filter((deliveryOrder) => {
    const haystack = [
      deliveryOrder.display_text,
      deliveryOrder.number,
      deliveryOrder.floor_order_number,
      deliveryOrder.floor_order_mode,
      deliveryOrder.packing_number,
      deliveryOrder.good_receive_number
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(keyword)
  })
})

const form = useForm({
  rejection_date: new Date().toISOString().split('T')[0],
  outlet_id: '',
  warehouse_id: '',
  delivery_order_id: '',
  notes: '',
  items: []
})



const removeItem = async (index) => {
  const result = await Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus item ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    form.items.splice(index, 1)
    Swal.fire(
      'Terhapus!',
      'Item berhasil dihapus.',
      'success'
    )
  }
}

// Search items
const searchItems = async (index) => {
  const item = form.items[index]
  if (item.item_search.length < 2) {
    item.search_results = []
    return
  }

  try {
    const response = await fetch(`/outlet-rejections/api/items?search=${item.item_search}`)
    const data = await response.json()
    item.search_results = data
  } catch (error) {
    console.error('Error searching items:', error)
  }
}

const showItemDropdown = (index) => {
  form.items[index].show_dropdown = true
}

const hideItemDropdown = (index) => {
  setTimeout(() => {
    form.items[index].show_dropdown = false
  }, 200)
}

const selectItem = (index, selectedItem) => {
  const item = form.items[index]
  item.item_id = selectedItem.id
  item.selected_item = selectedItem
  item.item_search = selectedItem.name
  item.show_dropdown = false
  item.search_results = []

  // Generate unit options based on selected item
  item.unit_options = []
  const addedUnits = new Set()

  if (selectedItem.small_unit_id && selectedItem.small_unit) {
    item.unit_options.push({
      id: selectedItem.small_unit_id,
      name: selectedItem.small_unit.name
    })
    addedUnits.add(selectedItem.small_unit_id)
  }

  if (selectedItem.medium_unit_id && selectedItem.medium_unit && !addedUnits.has(selectedItem.medium_unit_id)) {
    item.unit_options.push({
      id: selectedItem.medium_unit_id,
      name: selectedItem.medium_unit.name
    })
    addedUnits.add(selectedItem.medium_unit_id)
  }

  if (selectedItem.large_unit_id && selectedItem.large_unit && !addedUnits.has(selectedItem.large_unit_id)) {
    item.unit_options.push({
      id: selectedItem.large_unit_id,
      name: selectedItem.large_unit.name
    })
  }

  // Auto-select unit if only one option
  if (item.unit_options.length === 1) {
    item.unit_id = item.unit_options[0].id
  } else {
    item.unit_id = ''
  }
}

// Watch for changes in outlet and warehouse to load filtered delivery orders
const loadFilteredDeliveryOrders = async () => {
  if (!form.outlet_id || !form.warehouse_id) {
    deliveryOrders.value = []
    return
  }

  try {
    const response = await fetch(`/outlet-rejections/api/filtered-delivery-orders?outlet_id=${form.outlet_id}&warehouse_id=${form.warehouse_id}`)
    const data = await response.json()
    deliveryOrders.value = data
  } catch (error) {
    console.error('Error loading filtered delivery orders:', error)
    deliveryOrders.value = []
  }
}

// Watch for changes in outlet and warehouse
watch([() => form.outlet_id, () => form.warehouse_id], () => {
  form.delivery_order_id = '' // Reset delivery order selection
  deliveryOrderSearch.value = ''
  loadFilteredDeliveryOrders()
})

// Handle delivery order selection
const onDeliveryOrderChange = async () => {
  if (!form.delivery_order_id) {
    loading.value = false
    form.items = []
    documentFlowInfo.value = null
    itemsWithQuantityFlow.value = {}
    return
  }

  loading.value = true

  try {
    const response = await fetch(`/outlet-rejections/api/delivery-order-items?delivery_order_id=${form.delivery_order_id}`)
    const data = await response.json()
    
    // Store document flow and quantity flow data
    documentFlowInfo.value = data.documentFlowInfo || null
    itemsWithQuantityFlow.value = data.itemsWithQuantityFlow || {}
    
    // Clear existing items
    form.items = []
    
    // Add items from delivery order
    data.items.forEach(doItem => {
      // Generate unit options based on item's unit configuration
      const unitOptions = []
      const addedUnits = new Set()
      
      if (doItem.item.small_unit_id && doItem.item.small_unit_name) {
        unitOptions.push({
          id: doItem.item.small_unit_id,
          name: doItem.item.small_unit_name
        })
        addedUnits.add(doItem.item.small_unit_id)
      }
      
      if (doItem.item.medium_unit_id && doItem.item.medium_unit_name && !addedUnits.has(doItem.item.medium_unit_id)) {
        unitOptions.push({
          id: doItem.item.medium_unit_id,
          name: doItem.item.medium_unit_name
        })
        addedUnits.add(doItem.item.medium_unit_id)
      }
      
      if (doItem.item.large_unit_id && doItem.item.large_unit_name && !addedUnits.has(doItem.item.large_unit_id)) {
        unitOptions.push({
          id: doItem.item.large_unit_id,
          name: doItem.item.large_unit_name
        })
      }
      
      form.items.push({
        item_id: doItem.item_id,
        item_search: doItem.item.name || '',
        selected_item: doItem.item,
        search_results: [],
        show_dropdown: false,
        unit_id: doItem.unit_id || '',
        unit_options: unitOptions,
        qty_rejected: doItem.qty || '',
        rejection_reason: '',
        item_condition: 'good',
        condition_notes: ''
      })
    })
  } catch (error) {
    console.error('Error loading delivery order items:', error)
  } finally {
    loading.value = false
  }
}

const submitForm = async () => {
  // Show confirmation dialog
  const result = await Swal.fire({
    title: 'Konfirmasi Simpan',
    text: 'Apakah Anda yakin ingin menyimpan data Outlet Rejection ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Simpan!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  })

  if (!result.isConfirmed) {
    return
  }

  isSubmitting.value = true
  
  // Prepare items data for submission
  const itemsData = form.items.map(item => ({
    item_id: item.item_id,
    unit_id: item.unit_id,
    qty_rejected: item.qty_rejected,
    rejection_reason: item.rejection_reason,
    item_condition: item.item_condition,
    condition_notes: item.condition_notes
  }))

  form.items = itemsData

  form.post(route('outlet-rejections.store'), {
    onSuccess: () => {
      isSubmitting.value = false
      Swal.fire({
        title: 'Berhasil!',
        text: 'Data Outlet Rejection berhasil disimpan. Anda akan diarahkan ke halaman detail untuk complete rejection.',
        icon: 'success',
        confirmButtonText: 'OK'
      })
    },
    onError: (errors) => {
      isSubmitting.value = false
      let errorMessage = 'Terjadi kesalahan saat menyimpan data.'
      
      // Get first error message
      if (errors && Object.keys(errors).length > 0) {
        const firstError = Object.values(errors)[0]
        if (Array.isArray(firstError)) {
          errorMessage = firstError[0]
        } else {
          errorMessage = firstError
        }
      }
      
      Swal.fire({
        title: 'Error!',
        text: errorMessage,
        icon: 'error',
        confirmButtonText: 'OK'
      })
    }
  })
}

// Format functions
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}


</script>
