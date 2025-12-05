<template>
  <Modal :show="open" @close="onClose" maxWidth="3xl">
    <div class="p-6 mx-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
        Detail Item
      </h2>

      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="currentTab = tab.id"
            :class="[
              currentTab === tab.id
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2'
            ]"
          >
            <i :class="tab.icon"></i>
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div class="mt-4">
        <!-- Item Information Tab -->
        <div v-show="currentTab === 'info'" class="space-y-6">
          <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-blue-700 mb-4">
              <i class="fa-solid fa-info-circle"></i> Item Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-500">Composition Type</p>
                <p class="font-medium">
                  {{
                    item?.composition_type === 'single' ? 'Single' :
                    item?.composition_type === 'composed' ? 'Composed' :
                    '-' 
                  }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Category</p>
                <p class="font-medium">{{ item?.category?.name || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Sub Category</p>
                <p class="font-medium">{{ item?.sub_category?.name || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Warehouse Division</p>
                <p class="font-medium">{{ item?.warehouse_division?.name || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">SKU</p>
                <p class="font-medium">{{ item?.sku || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Type</p>
                <p class="font-medium">{{ item?.type || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Name</p>
                <p class="font-bold text-blue-800">{{ item?.name || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Min Stock</p>
                <p class="font-medium">{{ item?.min_stock || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Expiry Days</p>
                <p class="font-medium">{{ item?.exp || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Status</p>
                <span :class="item?.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded text-xs font-bold">
                  <i :class="item?.status === 'active' ? 'fa-solid fa-check-circle' : 'fa-solid fa-times-circle'"></i>
                  {{ item?.status === 'active' ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div v-if="item?.modifier_enabled !== undefined">
                <p class="text-sm text-gray-500">Modifier Enabled</p>
                <span :class="item?.modifier_enabled ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded text-xs font-bold">
                  <i :class="item?.modifier_enabled ? 'fa-solid fa-check-circle' : 'fa-solid fa-times-circle'"></i>
                  {{ item?.modifier_enabled ? 'Yes' : 'No' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- UoM Tab -->
        <div v-show="currentTab === 'uom'" class="space-y-6">
          <div class="bg-gradient-to-r from-purple-50 to-purple-100 border-l-4 border-purple-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-purple-700 mb-4">
              <i class="fa-solid fa-ruler-combined"></i> Unit of Measurement
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-500">Small Unit</p>
                <p class="font-medium">{{ item?.small_unit?.name || '-' }}</p>
              </div>
              <div v-if="item?.medium_unit">
                <p class="text-sm text-gray-500">Medium Unit</p>
                <p class="font-medium">{{ item?.medium_unit?.name || '-' }}</p>
              </div>
              <div v-if="item?.large_unit">
                <p class="text-sm text-gray-500">Large Unit</p>
                <p class="font-medium">{{ item?.large_unit?.name || '-' }}</p>
              </div>
              <div v-if="item?.medium_conversion_qty">
                <p class="text-sm text-gray-500">Medium Conversion Quantity</p>
                <p class="font-medium">{{ item?.medium_conversion_qty || '-' }}</p>
              </div>
              <div v-if="item?.small_conversion_qty">
                <p class="text-sm text-gray-500">Small Conversion Quantity</p>
                <p class="font-medium">{{ item?.small_conversion_qty || '-' }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Modifier Tab -->
        <div v-show="currentTab === 'modifier'" class="space-y-6">
          <div class="bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-green-700 mb-4">
              <i class="fa-solid fa-sliders"></i> Modifiers
            </h4>
            <div v-if="selectedModifiers.length" class="space-y-4">
              <div v-for="modifier in selectedModifiers" :key="modifier.id" class="border rounded-lg bg-white p-4">
                <h5 class="font-semibold text-gray-700 mb-2">{{ modifier.name }}</h5>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="option in modifier.options"
                    :key="option.id"
                    class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm"
                  >
                    {{ option.name }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 italic">No modifiers assigned.</div>
          </div>
        </div>

        <!-- BOM Tab -->
        <div v-show="currentTab === 'bom'" class="space-y-6">
          <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-yellow-700 mb-4">
              <i class="fa-solid fa-list-check"></i> Bill of Materials
            </h4>
            <div v-if="item?.bom?.length" class="space-y-4">
              <div v-for="(bom, index) in item.bom" :key="index" class="flex justify-between items-center gap-4 p-3 bg-white rounded-lg">
                <span class="font-medium text-gray-800">{{ bom.item_name || '-' }}</span>
                <span class="text-gray-500 text-right min-w-[120px]">{{ formatNumber(bom.qty, 2) }} {{ bom.unit_name || '' }}</span>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 italic">No BOM items assigned.</div>
          </div>
        </div>

        <!-- Price Tab -->
        <div v-show="currentTab === 'price'" class="space-y-6">
          <div class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-l-4 border-indigo-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-indigo-700 mb-4">
              <i class="fa-solid fa-tag"></i> Prices
            </h4>
            <div v-if="item?.prices?.length" class="space-y-4">
              <div v-for="(price, index) in item.prices" :key="index" class="flex justify-between items-center gap-4 p-3 bg-white rounded-lg">
                <span style="display:none">{{ console.log('DEBUG price', JSON.parse(JSON.stringify(price))) }}</span>
                <span class="font-medium text-gray-800 min-w-[120px]">
                  {{ price.region_id ? getRegionLabel(price.region_id, price.region_name || price.label) : price.outlet_id ? getOutletLabel(price.outlet_id, price.outlet_name || price.label) : price.label || '-' }}
                </span>
                <span class="text-gray-500 text-right min-w-[120px]">Rp {{ formatNumber(price.price, 0) }}</span>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 italic">No prices assigned.</div>
          </div>
        </div>

        <!-- Availability Tab -->
        <div v-show="currentTab === 'availability'" class="space-y-6">
          <div class="bg-gradient-to-r from-pink-50 to-pink-100 border-l-4 border-pink-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-pink-700 mb-4">
              <i class="fa-solid fa-store"></i> Availability
            </h4>
            <div v-if="item?.availabilities?.length" class="space-y-4">
              <div v-for="(avail, index) in item.availabilities" :key="index" class="flex items-center gap-4 p-3 bg-white rounded-lg">
                <span style="display:none">{{ console.log('DEBUG avail', JSON.parse(JSON.stringify(avail))) }}</span>
                <span class="font-medium">
                  {{ avail.region_id ? getRegionLabel(avail.region_id, avail.region_name || avail.label) : avail.outlet_id ? getOutletLabel(avail.outlet_id, avail.outlet_name || avail.label) : avail.label || '-' }}
                </span>
                <span class="text-gray-500 text-xs font-bold capitalize">{{ avail.availability_type || '-' }}</span>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 italic">No availability settings assigned.</div>
          </div>
        </div>

        <!-- SPS Tab -->
        <div v-show="currentTab === 'sps'" class="space-y-6">
          <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-l-4 border-gray-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-gray-700 mb-4">
              <i class="fa-solid fa-file-alt"></i> Specification & Images
            </h4>
            <div class="space-y-4">
              <div>
                <p class="text-sm text-gray-500">Description</p>
                <p class="font-medium">{{ item?.description || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Specification</p>
                <p class="font-medium">{{ item?.specification || '-' }}</p>
              </div>
            </div>

            <div v-if="item?.images?.length" class="mt-6">
              <p class="text-sm text-gray-500 mb-4">Images</p>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="(img, idx) in item.images" :key="idx" class="relative group">
                  <img 
                    v-if="getImageUrl(img)"
                    :src="getImageUrl(img)" 
                    class="w-full h-32 object-cover rounded-lg shadow border transition-transform duration-200 group-hover:scale-105"
                    @error="(e) => e.target.style.display='none'"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2" @click="onClose">
          <i class="fa-solid fa-xmark"></i>
          Tutup
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  open: {
    type: Boolean,
    required: true
  },
  item: {
    type: Object,
    required: true,
    default: () => ({})
  },
  modifiers: {
    type: Array,
    required: false,
    default: () => []
  },
  regions: {
    type: [Array, Object],
    required: false,
    default: () => ({})
  },
  outlets: {
    type: [Array, Object],
    required: false,
    default: () => ({})
  }
})

const emit = defineEmits(['close'])

const currentTab = ref('info')

const tabs = [
  { id: 'info', name: 'Information', icon: 'fa-solid fa-info-circle' },
  { id: 'uom', name: 'UoM', icon: 'fa-solid fa-ruler-combined' },
  { id: 'modifier', name: 'Modifier', icon: 'fa-solid fa-sliders' },
  { id: 'bom', name: 'BOM', icon: 'fa-solid fa-list-check' },
  { id: 'price', name: 'Price', icon: 'fa-solid fa-tag' },
  { id: 'availability', name: 'Availability', icon: 'fa-solid fa-store' },
  { id: 'sps', name: 'SPS', icon: 'fa-solid fa-file-alt' }
]

function onClose() { 
  emit('close') 
}

const getImageUrl = (image) => {
  if (!image || !image.path) return null;
  try {
    return `/storage/${image.path}`;
  } catch (error) {
    console.error('Error processing image:', error);
    return null;
  }
}

const formatNumber = (number, digits = 0) => {
  if (typeof number === 'number' || !isNaN(Number(number))) {
    return Number(number).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
  }
  return number;
}

// --- MODIFIER PATCH ---
const selectedModifierOptionIds = computed(() => {
  // Fallback: jika modifier_option_ids tidak ada, ambil dari item_modifier_options
  if (props.item?.modifier_option_ids && props.item.modifier_option_ids.length > 0) {
    return props.item.modifier_option_ids;
  } else if (props.item?.item_modifier_options && props.item.item_modifier_options.length > 0) {
    return props.item.item_modifier_options.map(opt => opt.modifier_option_id);
  }
  return [];
});

console.log('DEBUG item.modifier_option_ids', props.item?.modifier_option_ids);
console.log('DEBUG item.item_modifier_options', props.item?.item_modifier_options);
console.log('DEBUG modifiers', props.modifiers);

const selectedModifiers = computed(() => {
  // Ambil semua modifier yang punya option terpilih
  return (props.modifiers || []).map(mod => {
    const selectedOptions = (mod.options || []).filter(opt => selectedModifierOptionIds.value.includes(opt.id));
    return selectedOptions.length > 0 ? { ...mod, options: selectedOptions } : null;
  }).filter(Boolean);
});

console.log('DEBUG item.prices', props.item?.prices)

const getRegionLabel = (id, fallback) => {
  if (!id) return fallback || '-';
  if (Array.isArray(props.regions)) {
    const found = props.regions.find(r => r.id == id);
    return found ? found.name : (fallback || '-');
  } else if (props.regions && typeof props.regions === 'object') {
    return props.regions[id]?.name || fallback || '-';
  }
  return fallback || '-';
};
const getOutletLabel = (id, fallback) => {
  if (!id) return fallback || '-';
  if (Array.isArray(props.outlets)) {
    const found = props.outlets.find(o => o.id_outlet == id);
    return found ? found.nama_outlet : (fallback || '-');
  } else if (props.outlets && typeof props.outlets === 'object') {
    return props.outlets[id]?.nama_outlet || fallback || '-';
  }
  return fallback || '-';
};

// DEBUG LOGS
console.log('DEBUG item.prices', props.item?.prices);
console.log('DEBUG item.availabilities', props.item?.availabilities);
console.log('DEBUG regions', props.regions);
console.log('DEBUG outlets', props.outlets);
</script> 