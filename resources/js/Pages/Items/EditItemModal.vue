<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 relative max-h-[80vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
        Edit Item
      </h2>
      <button @click="closeModal" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
        <i class="fa-solid fa-xmark text-2xl"></i>
      </button>
      <!-- Stepper Navigation -->
      <div class="flex justify-between mb-6">
        <div v-for="(label, idx) in stepLabels" :key="label" class="flex-1 flex flex-col items-center">
          <div :class="['w-8 h-8 rounded-full flex items-center justify-center font-bold', currentStep === steps[idx] ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-500']">
            {{ idx+1 }}
          </div>
          <div class="text-xs mt-1 text-center" style="min-width: 70px">
            {{ label }}
          </div>
        </div>
      </div>
      <form @submit.prevent="saveItem">
        <!-- Step 1: Item Information -->
        <div v-show="currentStep === 'info'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Kolom Kiri -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Composition Type</label>
                <select v-model="form.composition_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option value="single">Single</option>
                  <option value="composed">Composed</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option value="">Select Category</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
              </div>
              <div v-if="hasSubCategories">
                <label class="block text-sm font-medium text-gray-700">Sub Category</label>
                <select v-model="form.sub_category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="">Select Sub Category</option>
                  <option v-for="subCategory in filteredSubCategories" :key="subCategory.id" :value="subCategory.id">{{ subCategory.name }}</option>
                </select>
              </div>
              <div v-if="isShowWarehouseDivision">
                <label class="block text-sm font-medium text-gray-700">Warehouse Division</label>
                <select v-model="form.warehouse_division_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option value="">Select Warehouse Division</option>
                  <option v-for="wd in warehouseDivisions" :key="wd.id" :value="wd.id">{{ wd.name }}</option>
                </select>
              </div>
            </div>
            <!-- Kolom Kanan -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">SKU</label>
                <input type="text" v-model="form.sku" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" readonly required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Type</label>
                <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option v-for="type in menuTypes" :key="type.id" :value="type.type">{{ type.type }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" v-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Minimum Stock</label>
                <input type="number" v-model="form.min_stock" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Expiry Days</label>
                <input type="number" v-model="form.exp" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter expiry days" />
              </div>
            </div>
          </div>
          <div v-if="selectedCategory && selectedCategory.show_pos == 1" class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Aktifkan Modifier</label>
            <button
              type="button"
              @click="form.modifier_enabled = !form.modifier_enabled"
              :class="form.modifier_enabled ? 'bg-blue-600' : 'bg-gray-300'"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
            >
              <span
                :class="form.modifier_enabled ? 'translate-x-6' : 'translate-x-1'"
                class="inline-block h-4 w-4 transform rounded-full bg-white transition"
              />
            </button>
            <span class="ml-2 text-gray-700">Item ini menggunakan modifier</span>
          </div>
        </div>
        <!-- Step 2: Item UoM -->
        <div v-show="currentStep === 'uom'">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Small Unit</label>
                <select v-model="form.small_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                  <option value="">Select Small Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Medium Unit</label>
                <select v-model="form.medium_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="">Select Medium Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Large Unit</label>
                <select v-model="form.large_unit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                  <option value="">Select Large Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </div>
            </div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Medium Conversion Quantity</label>
                <input type="number" v-model="form.medium_conversion_qty" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Small Conversion Quantity</label>
                <input type="number" v-model="form.small_conversion_qty" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
              </div>
            </div>
          </div>
        </div>
        <!-- Step 3: Item Modifier -->
        <div v-show="currentStep === 'modifier'">
          <div v-if="modifiers && modifiers.length > 0">
            <div v-for="modifier in validModifiers" :key="modifier.id" class="mb-4 border rounded-lg bg-gray-50">
              <button type="button" @click="toggleAccordion(modifier.id)" class="w-full flex justify-between items-center px-4 py-2 focus:outline-none">
                <span class="font-semibold text-blue-700 flex items-center gap-2"><i class="fa-solid fa-sliders"></i> {{ modifier.name }}</span>
                <i :class="accordionOpen[modifier.id] ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
              </button>
              <div v-show="accordionOpen[modifier.id]" class="px-4 pb-3">
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="option in getValidOptions(modifier.options)"
                    :key="option.id"
                    :class="isOptionSelected(option.id) ? 'bg-blue-500 text-white' : 'bg-white text-blue-700 border-blue-500 border'"
                    class="px-3 py-1 rounded-full cursor-pointer transition select-none flex items-center gap-1"
                    @click="toggleOption(option.id)"
                  >
                    {{ option.name }}
                    <i v-if="isOptionSelected(option.id)" class="fa-solid fa-xmark ml-1 text-xs"></i>
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 italic px-2 py-4">Tidak ada data modifier.</div>
        </div>
        <!-- Step 4: Item BOM -->
        <div v-show="currentStep === 'bom'">
          <label class="block text-sm font-medium text-gray-700 mb-2">Bill of Material (BOM)</label>
          <div v-for="(bom, idx) in form.bom" :key="idx" class="flex gap-2 mb-2 items-center">
            <div class="flex-1">
              <Multiselect
                v-model="bom.selectedItem"
                :options="bomItems"
                :searchable="true"
                :clear-on-select="false"
                :close-on-select="true"
                :show-labels="false"
                track-by="id"
                label="name"
                placeholder="Pilih atau cari bahan..."
                class="w-full"
                @select="(selectedItem) => onBomItemSelect(selectedItem, bom)"
                @clear="() => onBomItemClear(bom)"
              />
            </div>
            <input
              type="number"
              :value="formatQty(bom.qty)"
              @input="onQtyInput($event, bom)"
              min="0"
              step="0.01"
              placeholder="Qty"
              class="rounded border-gray-300 w-24"
              required
            />
            <span v-if="getSmallUnit(bom.item_id)" class="text-gray-700">{{ getSmallUnit(bom.item_id).name }}</span>
            <button type="button" @click="removeBomRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
          </div>
          <button type="button" @click="addBomRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded">
            <i class="fa-solid fa-plus"></i> Tambah Bahan
          </button>
        </div>
        <!-- Step 5: Item Price -->
        <div v-show="currentStep === 'price'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Harga per Region/Outlet</label>
            <div v-for="(price, idx) in form.prices" :key="idx" class="flex gap-2 mb-2 items-center">
              <select v-model="price.region_id" :disabled="!!price.outlet_id" class="rounded border-gray-300">
                <option value="">Pilih Region</option>
                <option value="all">All</option>
                <option v-for="region in regionsArray" :key="region?.id" :value="region?.id?.toString()">{{ region?.name }}</option>
              </select>
              <span class="text-gray-400">atau</span>
              <select v-model="price.outlet_id" :disabled="!!price.region_id" class="rounded border-gray-300">
                <option value="">Pilih Outlet</option>
                <option value="all">All</option>
                <option v-for="outlet in outletsArray" :key="outlet?.id_outlet" :value="outlet?.id_outlet?.toString()">{{ outlet?.nama_outlet }}</option>
              </select>
              <input type="number" v-model="price.price" min="0" placeholder="Harga" class="rounded border-gray-300 w-32" required />
              <button type="button" @click="removePriceRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </div>
            <button type="button" @click="addPriceRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded"><i class="fa-solid fa-plus"></i> Tambah Harga</button>
          </div>
        </div>
        <!-- Step 6: Item Availability -->
        <div v-show="currentStep === 'availability'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Availability per Region/Outlet</label>
            <div v-for="(avail, idx) in form.availabilities" :key="idx" class="flex gap-2 mb-2 items-center">
              <select v-model="avail.region_id" :disabled="!!avail.outlet_id && avail.outlet_id !== 'all'" class="rounded border-gray-300">
                <option value="">Pilih Region</option>
                <option value="all">All</option>
                <option v-for="region in (regionsArray || []).filter(r => !usedAvailRegionIds.includes(r?.id?.toString()) || avail.region_id == r?.id?.toString())" :key="region?.id" :value="region?.id">{{ region?.name }}</option>
              </select>
              <span class="text-gray-400">atau</span>
              <select v-model="avail.outlet_id" :disabled="!!avail.region_id && avail.region_id !== 'all'" class="rounded border-gray-300">
                <option value="">Pilih Outlet</option>
                <option value="all">All</option>
                <option v-for="outlet in (outletsArray || []).filter(o => !usedAvailOutletIds.includes(o?.id_outlet?.toString()) || avail.outlet_id == o?.id_outlet?.toString())" :key="outlet?.id_outlet" :value="outlet?.id_outlet">{{ outlet?.nama_outlet }}</option>
              </select>
              <button type="button" @click="removeAvailabilityRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </div>
            <button type="button" @click="addAvailabilityRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded"><i class="fa-solid fa-plus"></i> Tambah Availability</button>
          </div>
        </div>
        <!-- Step 7: Item SPS -->
        <div v-show="currentStep === 'sps'">
          <!-- SPS tab content -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Specification</label>
              <textarea v-model="form.specification" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Images</label>
              <input type="file" @change="handleImageUpload" multiple accept="image/*" class="mt-1 block w-full">
              <div class="mt-2 grid grid-cols-3 gap-4">
                <div v-for="(image, index) in form.images" :key="index" class="relative">
                  <img
                    v-if="image"
                    :src="getImagePreviewSrc(image)"
                    class="w-full h-32 object-cover rounded"
                  />
                  <button @click="removeImage(index)" class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-full">×</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Step 8: Preview -->
        <template v-if="currentStep === 'preview'">
          <div class="space-y-6">
            <div class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-2">
              <i class="fa-solid fa-eye text-blue-500 text-2xl"></i>
              <h3 class="text-lg font-bold text-blue-700">Preview Data Item</h3>
            </div>
            <!-- Item Information -->
            <div class="bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-blue-700 mb-4">
                <i class="fa-solid fa-info-circle"></i> Item Information
              </h4>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-gray-500">Category</p>
                  <p class="font-medium">{{ getCategoryLabel(form.category_id) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Sub Category</p>
                  <p class="font-medium">{{ getSubCategoryLabel(form.sub_category_id) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Warehouse Division</p>
                  <p class="font-medium">{{ getWarehouseDivisionLabel(form.warehouse_division_id) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">SKU</p>
                  <p class="font-medium">{{ form.sku }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Type</p>
                  <p class="font-medium">{{ getMenuTypeLabel(form.type) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Name</p>
                  <p class="font-bold text-blue-800">{{ form.name }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Min Stock</p>
                  <p class="font-medium">{{ form.min_stock }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Status</p>
                  <span :class="form.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded text-xs font-bold">
                    <i :class="form.status === 'active' ? 'fa-solid fa-check-circle' : 'fa-solid fa-times-circle'"></i>
                    {{ form.status }}
                  </span>
                </div>
              </div>
            </div>
            <!-- UoM Information -->
            <div class="bg-gradient-to-r from-purple-50 to-purple-100 border-l-4 border-purple-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-purple-700 mb-4">
                <i class="fa-solid fa-ruler-combined"></i> Unit of Measurement
              </h4>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-gray-500">Small Unit</p>
                  <p class="font-medium">{{ getUnitLabel(form.small_unit_id) }}</p>
                </div>
                <div v-if="form.medium_unit_id">
                  <p class="text-sm text-gray-500">Medium Unit</p>
                  <p class="font-medium">{{ getUnitLabel(form.medium_unit_id) }}</p>
                </div>
                <div v-if="form.large_unit_id">
                  <p class="text-sm text-gray-500">Large Unit</p>
                  <p class="font-medium">{{ getUnitLabel(form.large_unit_id) }}</p>
                </div>
                <div v-if="form.medium_conversion_qty">
                  <p class="text-sm text-gray-500">Medium Conversion Qty</p>
                  <p class="font-medium">{{ form.medium_conversion_qty }}</p>
                </div>
                <div v-if="form.small_conversion_qty">
                  <p class="text-sm text-gray-500">Small Conversion Qty</p>
                  <p class="font-medium">{{ form.small_conversion_qty }}</p>
                </div>
              </div>
            </div>
            <!-- Modifier Information -->
            <div v-if="form.modifier_enabled && validModifiers.length > 0 && form.modifier_option_ids && form.modifier_option_ids.length > 0" class="bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-yellow-700 mb-4">
                <i class="fa-solid fa-sliders"></i> Modifiers
              </h4>
              <div class="space-y-4">
                <div v-for="modifier in validModifiers" :key="modifier.id" class="border-b pb-4">
                  <p class="font-medium text-yellow-800 mb-2">{{ modifier.name }}</p>
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="option in getValidOptions(modifier.options)"
                      :key="option.id"
                      :class="isOptionSelected(option.id) ? 'bg-blue-500 text-white' : 'bg-white text-blue-700 border-blue-500 border'"
                      class="px-3 py-1 rounded-full cursor-pointer transition select-none flex items-center gap-1"
                      @click="toggleOption(option.id)"
                    >
                      {{ option.name }}
                      <i v-if="isOptionSelected(option.id)" class="fa-solid fa-xmark ml-1 text-xs"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <!-- BOM Information -->
            <div v-if="form.composition_type === 'composed'" class="bg-gradient-to-r from-indigo-50 to-indigo-100 border-l-4 border-indigo-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-indigo-700 mb-4">
                <i class="fa-solid fa-boxes-stacked"></i> Bill of Materials
              </h4>
              <div class="space-y-4">
                <div v-for="(bom, index) in form.bom" :key="index" class="border-b pb-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-sm text-gray-500">Item</p>
                      <p class="font-medium">{{ getBomItemLabel(bom.item_id) }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Quantity</p>
                      <p class="font-medium">{{ bom.qty }} {{ getUnitLabel(bom.unit_id) }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Price Information -->
            <div class="bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-green-700 mb-4">
                <i class="fa-solid fa-money-bill-wave"></i> Item Prices
              </h4>
              <div class="space-y-4">
                <div v-for="(price, index) in form.prices" :key="index" class="border-b pb-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-sm text-gray-500">Region/Outlet</p>
                      <p class="font-medium">{{ price.label || '-' }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Price</p>
                      <span class="bg-green-200 text-green-800 px-2 py-1 rounded font-bold">Rp {{ price.price }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Availability Information -->
            <div class="bg-gradient-to-r from-pink-50 to-pink-100 border-l-4 border-pink-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-pink-700 mb-4">
                <i class="fa-solid fa-store"></i> Item Availability
              </h4>
              <div class="space-y-4">
                <div v-for="(availability, index) in form.availabilities" :key="index" class="border-b pb-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <p class="text-sm text-gray-500">Region/Outlet</p>
                      <p class="font-medium">{{ availability.label || '-' }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Type</p>
                      <p class="font-medium">{{ availability.availability_type || '-' }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- SPS Information -->
            <div class="bg-gradient-to-r from-teal-50 to-teal-100 border-l-4 border-teal-400 shadow rounded-lg p-6">
              <h4 class="flex items-center gap-2 text-md font-semibold text-teal-700 mb-4">
                <i class="fa-solid fa-file-lines"></i> Specifications & Images
              </h4>
              <div class="space-y-4">
                <div>
                  <p class="text-sm text-gray-500">Description</p>
                  <p class="font-medium">{{ form.description || '-' }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Specification</p>
                  <p class="font-medium">{{ form.specification || '-' }}</p>
                </div>
                <div v-if="form.images && form.images.length > 0">
                  <p class="text-sm text-gray-500 mb-2">Images</p>
                  <div class="grid grid-cols-3 gap-4">
                    <div v-for="(image, index) in form.images" :key="index" class="relative">
                      <img
                        v-if="image"
                        :src="getImagePreviewSrc(image)"
                        class="w-full h-32 object-cover rounded"
                      />
                      <button @click="removeImage(index)" class="absolute top-0 right-0 bg-red-500 text-white p-1 rounded-full">×</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
        <!-- Stepper Navigation -->
        <div class="mt-6 flex justify-between">
          <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-2 rounded-lg flex items-center gap-2" @click="prevStep" :disabled="currentStep === steps[0]">
            <i class="fa-solid fa-arrow-left"></i>
            Sebelumnya
          </button>
          <div>
            <button v-if="currentStep !== 'preview'" type="button" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" @click="nextStep">
              Selanjutnya
              <i class="fa-solid fa-arrow-right"></i>
            </button>
            <button v-else type="button" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg flex items-center gap-2" @click="saveItem" :disabled="isSaving">
              <i v-if="isSaving" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              <span v-if="isSaving">Menyimpan...</span>
              <span v-else>Simpan</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  show: Boolean,
  item: Object,
  categories: { type: Array, default: () => [] },
  subCategories: { type: Array, default: () => [] },
  units: { type: Array, default: () => [] },
  warehouseDivisions: { type: Array, default: () => [] },
  menuTypes: { type: Array, default: () => [] },
  regions: { type: [Array, Object], default: () => ({}) },
  outlets: { type: [Array, Object], default: () => ({}) },
  bomItems: { type: Array, default: () => [] },
  modifiers: { type: Array, default: () => [] }
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  category_id: '',
  sub_category_id: '',
  warehouse_division_id: '',
  sku: '',
  type: 'product',
  name: '',
  description: '',
  specification: '',
  small_unit_id: '',
  medium_unit_id: '',
  large_unit_id: '',
  medium_conversion_qty: '',
  small_conversion_qty: '',
  min_stock: 0,
  exp: 0,
  status: 'active',
  images: [],
  deleted_images: [],
  prices: [],
  availabilities: [],
  composition_type: 'single',
  bom: [],
  modifier_enabled: false,
  modifier_option_ids: [],
});

const previewImages = ref([]);

const selectedCategory = computed(() => props.categories.find(cat => cat.id === Number(form.category_id)));
const filteredSubCategories = computed(() => {
  if (!form.category_id || !props.subCategories) return [];
  return props.subCategories.filter(
    sc => sc.category_id === Number(form.category_id) && sc.status === 'active'
  );
});
const hasSubCategories = computed(() => filteredSubCategories.value.length > 0);
const isShowWarehouseDivision = computed(() => selectedCategory.value && selectedCategory.value.show_pos == 0);

const showModifierStep = computed(() => selectedCategory.value && selectedCategory.value.show_pos == 1 && form.modifier_enabled);
const showBomStep = computed(() => form.composition_type === 'composed');

const steps = computed(() => {
  const arr = ['info', 'uom'];
  if (showModifierStep.value) arr.push('modifier');
  if (showBomStep.value) arr.push('bom');
  arr.push('price', 'availability', 'sps', 'preview');
  return arr;
});
const stepLabels = computed(() => {
  const arr = ['Item Info', 'UoM'];
  if (showModifierStep.value) arr.push('Modifier');
  if (showBomStep.value) arr.push('BOM');
  arr.push('Price', 'Availability', 'SPS', 'Preview');
  return arr;
});
const currentStep = ref('info');
const nextStep = () => {
  const idx = steps.value.indexOf(currentStep.value);
  if (idx < steps.value.length - 1) currentStep.value = steps.value[idx + 1];
};
const prevStep = () => {
  const idx = steps.value.indexOf(currentStep.value);
  if (idx > 0) currentStep.value = steps.value[idx - 1];
};

watch(() => props.show, (val) => {
  if (val && props.item) {
    console.log('DEBUG [EditItemModal] props.item (watch):', JSON.parse(JSON.stringify(props.item)));
    console.log('DEBUG [EditItemModal] modifier_enabled value:', props.item.modifier_enabled, 'type:', typeof props.item.modifier_enabled);
    
    // Convert modifier_enabled to boolean - handle both integer (1/0), boolean (true/false), and string ('1'/'0')
    const modifierEnabledValue = props.item.modifier_enabled;
    let isModifierEnabled = false;
    
    if (modifierEnabledValue === true || modifierEnabledValue === 1) {
      isModifierEnabled = true;
    } else if (modifierEnabledValue === '1' || String(modifierEnabledValue).trim() === '1') {
      isModifierEnabled = true;
    } else if (modifierEnabledValue === false || modifierEnabledValue === 0 || modifierEnabledValue === '0' || modifierEnabledValue === null || modifierEnabledValue === undefined) {
      isModifierEnabled = false;
    }
    
    console.log('DEBUG [EditItemModal] isModifierEnabled:', isModifierEnabled, 'from value:', modifierEnabledValue);
    
    Object.assign(form, {
      category_id: props.item.category_id,
      sub_category_id: props.item.sub_category_id,
      warehouse_division_id: props.item.warehouse_division_id,
      sku: props.item.sku,
      type: props.item.type,
      name: props.item.name,
      description: props.item.description,
      specification: props.item.specification,
      small_unit_id: props.item.small_unit_id,
      medium_unit_id: props.item.medium_unit_id,
      large_unit_id: props.item.large_unit_id,
      medium_conversion_qty: props.item.medium_conversion_qty,
      small_conversion_qty: props.item.small_conversion_qty,
      min_stock: props.item.min_stock,
      exp: props.item.exp,
      status: props.item.status,
      deleted_images: [],
      prices: props.item.prices ? props.item.prices.map(p => ({
        region_id: p.region_id === null ? 'all' : p.region_id?.toString(),
        outlet_id: p.outlet_id === null ? 'all' : p.outlet_id?.toString(),
        price: p.price,
        label: p.label
      })) : [],
      availabilities: props.item.availabilities ? props.item.availabilities.map(a => ({
        region_id: a.region_id === null ? 'all' : a.region_id?.toString(),
        outlet_id: a.outlet_id === null ? 'all' : a.outlet_id?.toString(),
        availability_type: a.availability_type,
        label: a.label
      })) : [],
      bom: props.item.bom ? JSON.parse(JSON.stringify(props.item.bom)) : [],
      modifier_option_ids: Array.isArray(props.item.modifier_option_ids) ? [...props.item.modifier_option_ids] : [],
      modifier_enabled: isModifierEnabled,
      composition_type: props.item.composition_type || 'single',
    });
    const images = props.item.images
      ? props.item.images
          .filter(img => !!img)
          .map(img => typeof img === 'string' ? { path: img } : img)
      : [];
    form.images = images;
    previewImages.value = [];
    console.log('DEBUG [EditItemModal] form.prices:', form.prices);
    console.log('DEBUG [EditItemModal] form.availabilities:', form.availabilities);
    console.log('DEBUG [EditItemModal] form.modifier_option_ids:', form.modifier_option_ids);
    console.log('DEBUG [EditItemModal] props.modifiers:', props.modifiers);
  } else if (val) {
    form.reset();
    previewImages.value = [];
  }
});

watch(() => form.category_id, (val) => {
  const cat = props.categories.find(c => c.id === Number(val));
  if (cat) {
    const date = new Date();
    const ymd = `${date.getFullYear()}${(date.getMonth()+1).toString().padStart(2,'0')}${date.getDate().toString().padStart(2,'0')}`;
    const rand = Math.floor(1000 + Math.random() * 9000);
    form.sku = `${cat.code}-${ymd}-${rand}`;
  } else {
    form.sku = '';
  }
});

watch(currentStep, (val) => {
  if (val === 'bom' && form.bom.length === 0) {
    form.bom.push({ item_id: '', qty: '', unit_id: '', selectedItem: null });
  }
});

// Initialize selectedItem for existing BOM data
watch(() => form.bom, (newBom) => {
  if (Array.isArray(newBom)) {
    newBom.forEach(bom => {
      if (bom.item_id && !bom.selectedItem) {
        bom.selectedItem = getBomItemObject(bom.item_id);
      }
    });
  }
}, { deep: true });

const regionsArray = computed(() => {
  if (!props.regions) return [];
  return Array.isArray(props.regions) ? props.regions : Object.values(props.regions);
});

const outletsArray = computed(() => {
  if (!props.outlets) return [];
  return Array.isArray(props.outlets) ? props.outlets : Object.values(props.outlets);
});

// Helper label relasi mirip ModalDetailItem.vue
const enumLabel = (arr, id, key = 'id', label = 'name') => {
  const found = arr.find(x => x[key] == id);
  return found ? found[label] : '-';
};
const getCategoryLabel = (id) => {
  const found = props.categories.find(x => x.id == id);
  return found ? found.name : '-';
};
const getSubCategoryLabel = (id) => {
  const found = props.subCategories.find(x => x.id == id);
  return found ? found.name : '-';
};
const getWarehouseDivisionLabel = (id) => {
  const found = props.warehouseDivisions.find(x => x.id == id);
  return found ? found.name : '-';
};
const getMenuTypeLabel = (type) => {
  const found = props.menuTypes.find(x => x.type == type || x.id == type);
  return found ? (found.name || found.type) : '-';
};
const getUnitLabel = (id) => enumLabel(props.units, id);

const closeModal = () => emit('close');

const isSaving = ref(false);

const saveItem = () => {
  console.log('DEBUG: Starting saveItem');
  console.log('DEBUG: form.prices before check:', form.prices);
  console.log('DEBUG: form.availabilities before check:', form.availabilities);

  // Ensure prices and availabilities are arrays
  if (!Array.isArray(form.prices)) {
    console.log('DEBUG: form.prices was not an array, initializing as empty array');
    form.prices = [];
  }
  if (!Array.isArray(form.availabilities)) {
    console.log('DEBUG: form.availabilities was not an array, initializing as empty array');
    form.availabilities = [];
  }

  // Ensure all required fields are present
  const requiredFields = {
    category_id: form.category_id,
    // sub_category_id: form.sub_category_id, // Tidak wajib
    // warehouse_division_id: form.warehouse_division_id, // Tidak wajib
    sku: form.sku,
    type: form.type,
    name: form.name,
    small_unit_id: form.small_unit_id,
    min_stock: form.min_stock,
    status: form.status
  };

  // Check for missing required fields
  const missingFields = Object.entries(requiredFields)
    .filter(([_, value]) => !value)
    .map(([key]) => key);

  if (missingFields.length > 0) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: `Field berikut harus diisi: ${missingFields.join(', ')}`
    });
    return;
  }

  // Validasi sub_category_id hanya jika ada sub kategori aktif
  if (hasSubCategories.value && (!form.sub_category_id || !props.subCategories.find(sc => sc.id == form.sub_category_id))) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Sub Category harus dipilih dan valid.' });
    return;
  }

  // Hapus field modifier jika tidak relevan
  if (!form.modifier_enabled && (!form.modifier_option_ids || form.modifier_option_ids.length === 0)) {
    delete form.modifier_enabled;
    delete form.modifier_option_ids;
  }
  // Hapus field bom jika bukan composed
  if (form.composition_type !== 'composed') {
    delete form.bom;
  }

  try {
    // Konversi region_id dan outlet_id di prices & availabilities ke number/null (bukan 0)
    form.prices = (form.prices || []).map(p => ({
      ...p,
      region_id: p.region_id === 'all' ? null : (p.region_id === '' || typeof p.region_id === 'undefined' || p.region_id === null ? null : Number(p.region_id)),
      outlet_id: p.outlet_id === 'all' ? null : (p.outlet_id === '' || typeof p.outlet_id === 'undefined' || p.outlet_id === null ? null : Number(p.outlet_id)),
      price: p.price === '' || p.price === null || typeof p.price === 'undefined' ? '' : Number(p.price)
    }));
    form.availabilities = (form.availabilities || []).map(a => ({
      ...a,
      region_id: a.region_id === 'all' ? null : (a.region_id === '' || typeof a.region_id === 'undefined' || a.region_id === null ? null : Number(a.region_id)),
      outlet_id: a.outlet_id === 'all' ? null : (a.outlet_id === '' || typeof a.outlet_id === 'undefined' || a.outlet_id === null ? null : Number(a.outlet_id))
    }));
  } catch (error) {
    console.error('DEBUG: Error processing prices/availabilities:', error);
    form.prices = [];
    form.availabilities = [];
  }

  // Filter prices dan availabilities agar hanya data valid yang dikirim
  form.prices = (form.prices || []).filter(p => (p.price !== '' && !isNaN(p.price)));
  form.availabilities = (form.availabilities || []).filter(a => true);

  console.log('DEBUG: Processed form data:', {
    prices: form.prices,
    availabilities: form.availabilities
  });

  const newImages = (form.images || []).filter(img => img instanceof File);
  if (newImages.length) {
    // Kirim pakai FormData jika ada file baru
    const formData = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (key === 'images') {
        (newImages || []).forEach((img) => {
          formData.append('images[]', img);
        });
      } else if (Array.isArray(value) && value.length && typeof value[0] === 'object') {
        (value || []).forEach((obj, idx) => {
          Object.entries(obj || {}).forEach(([k, v]) => {
            let val = v;
            if ((k === 'region_id' || k === 'outlet_id') && (v === '' || typeof v === 'undefined')) {
              val = null;
            }
            if ((k === 'region_id' || k === 'outlet_id') && v !== null && v !== '' && typeof v !== 'undefined') {
              val = Number(v);
              if (isNaN(val)) val = null;
            }
            if (val !== null && val !== '' && typeof val !== 'undefined') {
              formData.append(`${key}[${idx}][${k}]`, val);
            }
          });
        });
      } else if (Array.isArray(value)) {
        (value || []).forEach((v, idx) => formData.append(`${key}[${idx}]`, v));
      } else {
        if (key === 'sub_category_id' && (!value || value === '')) {
          // skip
        } else {
          // Pastikan boolean dikirim sebagai 1/0
          if (typeof value === 'boolean') {
            formData.append(key, value ? 1 : 0);
          } else {
            formData.append(key, value);
          }
        }
      }
    });

    // Tambahkan log FormData sebelum submit
    console.log('FORM DATA DEBUG:');
    for (let pair of formData.entries()) {
      console.log(pair[0]+ ': ' + pair[1]);
    }

    isSaving.value = true;
    window.axios.post(route('items.update', props.item.id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      method: 'POST',
      params: { _method: 'PUT' },
    })
      .then((response) => {
        console.log('DEBUG: Response data:', response.data);
        isSaving.value = false;
        // Ensure we have valid data before emitting success
        if (response.data && response.data.item) {
          const item = response.data.item;
          // Ensure prices and availabilities are arrays
          if (!Array.isArray(item.prices)) {
            item.prices = [];
          }
          if (!Array.isArray(item.availabilities)) {
            item.availabilities = [];
          }
          // Ensure all required arrays are initialized
          item.prices = item.prices.map(p => ({
            region_id: p.region_id === null ? 'all' : p.region_id?.toString(),
            outlet_id: p.outlet_id === null ? 'all' : p.outlet_id?.toString(),
            price: p.price,
            label: p.label
          }));
          item.availabilities = item.availabilities.map(a => ({
            region_id: a.region_id === null ? 'all' : a.region_id?.toString(),
            outlet_id: a.outlet_id === null ? 'all' : a.outlet_id?.toString(),
            availability_type: a.availability_type,
            label: a.label
          }));
          emit('success', item);
        } else {
          emit('success');
        }
        Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data item berhasil diperbarui!' });
        emit('close');
      })
      .catch((error) => {
        console.error('DEBUG: Error response:', error.response);
        isSaving.value = false;
        if (error.response && error.response.data && error.response.data.errors) {
          console.error('Validation errors:', error.response.data.errors);
          Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(error.response.data.errors).flat().join('\n') });
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan saat memperbarui data.' });
        }
        console.error('Error updating item:', error);
      });
  } else {
    // Tidak ada file baru, hapus field images sebelum submit
    const { images, ...formWithoutImages } = form;
    isSaving.value = true;
    window.axios.post(route('items.update', props.item.id), {
      ...formWithoutImages,
      _method: 'PUT',
    })
      .then((response) => {
        console.log('DEBUG: Response data:', response.data);
        isSaving.value = false;
        // Ensure we have valid data before emitting success
        if (response.data && response.data.item) {
          const item = response.data.item;
          // Ensure prices and availabilities are arrays
          if (!Array.isArray(item.prices)) {
            item.prices = [];
          }
          if (!Array.isArray(item.availabilities)) {
            item.availabilities = [];
          }
          // Ensure all required arrays are initialized
          item.prices = item.prices.map(p => ({
            region_id: p.region_id === null ? 'all' : p.region_id?.toString(),
            outlet_id: p.outlet_id === null ? 'all' : p.outlet_id?.toString(),
            price: p.price,
            label: p.label
          }));
          item.availabilities = item.availabilities.map(a => ({
            region_id: a.region_id === null ? 'all' : a.region_id?.toString(),
            outlet_id: a.outlet_id === null ? 'all' : a.outlet_id?.toString(),
            availability_type: a.availability_type,
            label: a.label
          }));
          emit('success', item);
        } else {
          emit('success');
        }
        Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data item berhasil diperbarui!' });
        emit('close');
      })
      .catch((error) => {
        console.error('DEBUG: Error response:', error.response);
        isSaving.value = false;
        if (error.response && error.response.data && error.response.data.errors) {
          console.error('Validation errors:', error.response.data.errors);
          Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(error.response.data.errors).flat().join('\n') });
        } else {
          Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan saat memperbarui data.' });
        }
        console.error('Error updating item:', error);
      });
  }
};

const getSmallUnit = (itemId) => {
  const item = props.bomItems.find(i => i.id == itemId);
  if (!item) return null;
  return props.units.find(u => u.id == item.small_unit_id) || null;
};

const modifiers = props.modifiers || [];
console.log('DEBUG EditItemModal - modifiers from props:', modifiers);
const accordionOpen = ref({});
const toggleAccordion = (id) => {
  accordionOpen.value[id] = !accordionOpen.value[id];
};
const isOptionSelected = (optionId) => form.modifier_option_ids && form.modifier_option_ids.includes(optionId);
const toggleOption = (optionId) => {
  if (!form.modifier_option_ids) form.modifier_option_ids = [];
  const idx = form.modifier_option_ids.indexOf(optionId);
  if (idx === -1) {
    form.modifier_option_ids.push(optionId);
  } else {
    form.modifier_option_ids.splice(idx, 1);
  }
};
const validModifiers = computed(() =>
  (modifiers || []).filter(m => m && typeof m.id !== 'undefined' && Array.isArray(m.options))
);
console.log('DEBUG EditItemModal - validModifiers computed:', validModifiers.value);
console.log('DEBUG EditItemModal - first modifier options:', validModifiers.value[0]?.options);
function getValidOptions(options) {
  if (!Array.isArray(options)) {
    return [];
  }
  return options.filter(o => o && typeof o === 'object' && typeof o.id !== 'undefined' && o.id !== null);
}

const addBomRow = () => {
  form.bom.push({
    item_id: '',
    qty: 1,
    selectedItem: null
  });
};

const onBomItemSelect = (selectedItem, bom) => {
  if (selectedItem) {
    bom.item_id = selectedItem.id;
    bom.selectedItem = selectedItem;
    // Set unit_id ke small_unit_id dari item yang dipilih
    bom.unit_id = selectedItem.small_unit_id || '';
  }
};

const onBomItemClear = (bom) => {
  bom.item_id = '';
  bom.selectedItem = null;
  bom.unit_id = '';
};

const getBomItemObject = (itemId) => {
  if (!itemId) return null;
  return props.bomItems.find(item => item.id == itemId) || null;
};

const removeBomRow = (index) => {
  form.bom.splice(index, 1);
};

const usedRegionIds = computed(() => form.prices.map(p => p.region_id ? p.region_id.toString() : '').filter(Boolean));
const usedOutletIds = computed(() => form.prices.map(p => p.outlet_id ? p.outlet_id.toString() : '').filter(Boolean));
const usedAvailRegionIds = computed(() => form.availabilities.map(a => a.region_id).filter(Boolean));
const usedAvailOutletIds = computed(() => form.availabilities.map(a => a.outlet_id).filter(Boolean));

const addPriceRow = () => {
  form.prices.push({ region_id: '', outlet_id: '', price: '' });
};

const removePriceRow = (idx) => {
  form.prices.splice(idx, 1);
};

const addAvailabilityRow = () => {
  form.availabilities.push({ region_id: '', outlet_id: '', availability_type: 'available' });
};

const removeAvailabilityRow = (idx) => {
  form.availabilities.splice(idx, 1);
};

const handleImageUpload = (event) => {
  const files = Array.from(event.target.files);
  form.images.push(...files);
};

const removeImage = (index) => {
  const img = form.images[index];
  // If image is a string (path), push as is
  if (typeof img === 'string') {
    form.deleted_images.push(img);
  } else if (img && typeof img === 'object') {
    // If image is an object, try to push its path or id
    if (img.path) {
      form.deleted_images.push(img.path);
    } else if (img.id) {
      form.deleted_images.push(img.id);
    }
  }
  form.images.splice(index, 1);
};

watch(
  () => Array.isArray(form.bom) ? form.bom.map(b => b.item_id) : [],
  (newVal, oldVal) => {
    if (!Array.isArray(form.bom)) return;
    form.bom.forEach((bom, idx) => {
      const item = props.bomItems.find(i => i.id == bom.item_id);
      bom.unit_id = item ? item.small_unit_id : '';
    });
  },
  { deep: true }
);

const getBomItemLabel = (id) => {
  const found = props.bomItems.find(x => x.id == id);
  return found ? found.name : '-';
};

const getImageUrl = (image) => {
  if (!image) return null;
  if (typeof image === 'string') {
    return image.startsWith('/') ? image : '/storage/' + image;
  }
  if (image.path) {
    return image.path.startsWith('/') ? '/storage' + image.path : '/storage/' + image.path;
  }
  if (image.url) {
    return image.url;
  }
  return null;
};

function formatQty(val) {
  if (val === null || val === undefined || val === '') return '';
  return parseFloat(val).toFixed(2).replace(/\.00$/, '').replace(/(\.[1-9]*)0+$/, '$1');
}

function onQtyInput(e, bom) {
  let val = e.target.value;
  if (val === '') {
    bom.qty = '';
  } else {
    bom.qty = parseFloat(val);
  }
}

function getImagePreviewSrc(image) {
  if (!image) return '';
  if (typeof image === 'string') return getImageUrl(image);
  if (typeof File !== 'undefined' && image instanceof File) return URL.createObjectURL(image);
  if (image && typeof image === 'object' && image.type && image.size) return URL.createObjectURL(image);
  return getImageUrl(image);
}

onMounted(() => {
  console.log('DEBUG props.item (onMounted)', props.item);
});

watch(() => props.item, (val) => {
  console.log('DEBUG [EditItemModal] props.item (watch):', JSON.parse(JSON.stringify(val)));
});
</script> 



