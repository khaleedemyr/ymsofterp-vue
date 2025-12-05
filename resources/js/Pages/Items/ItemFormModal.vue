<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 relative max-h-[80vh] overflow-y-auto">
      <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
        <i class="fa-solid fa-boxes-stacked text-blue-500"></i>
        {{ mode === 'create' ? 'Tambah Item Baru' : 'Edit Item' }}
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
      <form @submit.prevent="submit">
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
                    @click="toggleOption(option.id)">
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
        <div v-if="currentStep === 'bom'">
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
            <input type="number" v-model="bom.qty" min="0" step="0.01" placeholder="Qty" class="rounded border-gray-300 w-24" required />
            <span v-if="getSmallUnit(bom.item_id)" class="text-gray-700">{{ getSmallUnit(bom.item_id).name }}</span>
            <button type="button" @click="removeBomRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
          </div>
          <button type="button" @click="addBomRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded">
            <i class="fa-solid fa-plus"></i> Tambah Bahan
          </button>
        </div>
        <!-- Step 5/6: Item Price -->
        <div v-show="currentStep === 'price'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Harga per Region/Outlet</label>
            <div v-for="(price, idx) in form.prices" :key="idx" class="flex gap-2 mb-2 items-center">
              <select v-model="price.price_type" class="rounded border-gray-300" @change="handlePriceTypeChange(price)">
                <option value="all">All</option>
                <option value="specific">Specific Region/Outlet</option>
              </select>
              <template v-if="price.price_type === 'specific'">
                <select v-model="price.region_id" :disabled="!!price.outlet_id" class="rounded border-gray-300">
                  <option value="">Pilih Region</option>
                  <option v-for="region in regionsArray" :key="region.id" :value="region.id.toString()">{{ region.name }}</option>
                </select>
                <span class="text-gray-400">atau</span>
                <select v-model="price.outlet_id" :disabled="!!price.region_id" class="rounded border-gray-300">
                  <option value="">Pilih Outlet</option>
                  <option v-for="outlet in outletsArray" :key="outlet.id_outlet" :value="outlet.id_outlet.toString()">{{ outlet.nama_outlet }}</option>
                </select>
              </template>
              <input type="number" v-model="price.price" min="0" placeholder="Harga" class="rounded border-gray-300 w-32" required />
              <button type="button" @click="removePriceRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </div>
            <button type="button" @click="addPriceRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded"><i class="fa-solid fa-plus"></i> Tambah Harga</button>
          </div>
        </div>
        <!-- Step 4/5: Item Availability -->
        <div v-show="currentStep === 'availability'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Availability per Region/Outlet</label>
            <div v-for="(avail, idx) in form.availabilities" :key="idx" class="flex gap-2 mb-2 items-center">
              <select v-model="avail.region_id" :disabled="!!avail.outlet_id && avail.outlet_id !== 'all'" class="rounded border-gray-300">
                <option value="">Pilih Region</option>
                <option value="all">All</option>
                <option v-for="region in regionsArray" :key="region.id" :value="region.id.toString()">{{ region.name }}</option>
              </select>
              <span class="text-gray-400">atau</span>
              <select v-model="avail.outlet_id" :disabled="!!avail.region_id && avail.region_id !== 'all'" class="rounded border-gray-300">
                <option value="">Pilih Outlet</option>
                <option value="all">All</option>
                <option v-for="outlet in outletsArray" :key="outlet.id_outlet" :value="outlet.id_outlet.toString()">{{ outlet.nama_outlet }}</option>
              </select>
              <button type="button" @click="removeAvailabilityRow(idx)" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
            </div>
            <button type="button" @click="addAvailabilityRow" class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded"><i class="fa-solid fa-plus"></i> Tambah Availability</button>
          </div>
        </div>
        <!-- Step 5/6: Item SPS -->
        <div v-show="currentStep === 'sps'">
          <div class="space-y-4 mb-6">
            <div>
              <label class="block text-sm font-medium text-gray-700">Description</label>
              <textarea v-model="form.description" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Specification</label>
              <textarea v-model="form.specification" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
              <!-- Images -->
              <div>
                <label class="block text-sm font-medium text-gray-700">Upload Images</label>
                <input type="file" @change="handleImageUpload" multiple accept="image/*" class="mt-1 block w-full" />
              </div>
              <div v-if="form.images.length > 0" class="grid grid-cols-3 gap-4">
                <div v-for="(image, index) in form.images" :key="index" class="relative">
                  <img
                    v-if="image instanceof FileType"
                    :src="URLObject.createObjectURL(image)"
                    class="w-full h-32 object-cover rounded-lg"
                  />
                  <img
                    v-else
                    :src="typeof image === 'string' ? '/storage/' + image : ''"
                    class="w-full h-32 object-cover rounded-lg"
                  />
                  <button @click="removeImage(index)" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
              </div>
              <div v-if="mode === 'edit' && item && item.images && item.images.length > 0" class="grid grid-cols-3 gap-4">
                <div v-for="image in item.images" :key="image.id" class="relative">
                  <img :src="'/storage/' + image.path" class="w-full h-32 object-cover rounded-lg" />
                  <button @click="removeExistingImage(image.id)" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
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
          </div>
        </div>
      </form>
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
                <p class="font-medium">{{ categories.find(c => c.id === form.category_id)?.name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Sub Category</p>
                <p class="font-medium">{{ subCategories.find(sc => sc.id === form.sub_category_id)?.name }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Warehouse Division</p>
                <p class="font-medium">{{ form.warehouse_division_id }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">SKU</p>
                <p class="font-medium">{{ form.sku }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Type</p>
                <p class="font-medium">{{ form.type }}</p>
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
                <p class="font-medium">{{ units.find(u => u.id === form.small_unit_id)?.name }}</p>
              </div>
              <div v-if="form.medium_unit_id">
                <p class="text-sm text-gray-500">Medium Unit</p>
                <p class="font-medium">{{ units.find(u => u.id === form.medium_unit_id)?.name }}</p>
              </div>
              <div v-if="form.large_unit_id">
                <p class="text-sm text-gray-500">Large Unit</p>
                <p class="font-medium">{{ units.find(u => u.id === form.large_unit_id)?.name }}</p>
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
          <div v-if="form.modifier_enabled && form.modifier_option_ids && form.modifier_option_ids.length > 0" class="bg-gradient-to-r from-cyan-50 to-cyan-100 border-l-4 border-cyan-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-cyan-700 mb-4">
              <i class="fa-solid fa-sliders"></i> Modifier
            </h4>
            <div v-for="modifier in selectedModifiers" :key="modifier.id" class="mb-2">
              <div class="font-semibold text-cyan-800 mb-1 flex items-center gap-2">
                <i class="fa-solid fa-puzzle-piece"></i> {{ modifier.name }}
              </div>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="option in getSelectedOptions(modifier.options, form.modifier_option_ids)"
                  :key="option.id"
                  class="bg-cyan-500 text-white px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1"
                >
                  {{ option.name }}
                </span>
              </div>
            </div>
          </div>
          <!-- BOM Information -->
          <div v-if="form.composition_type === 'composed'" class="bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-yellow-700 mb-4">
              <i class="fa-solid fa-list-ul"></i> Bill of Materials
            </h4>
            <div class="space-y-4">
              <div v-for="(bom, index) in form.bom" :key="index" class="border-b pb-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <p class="text-sm text-gray-500">Material</p>
                    <p class="font-medium">{{ bomItems.find(i => i.id === bom.item_id)?.name }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Quantity</p>
                    <p class="font-medium">{{ bom.qty }} {{ units.find(u => u.id === getSmallUnit(bom.item_id)?.id)?.name }}</p>
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
          <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-l-4 border-gray-400 shadow rounded-lg p-6">
            <h4 class="flex items-center gap-2 text-md font-semibold text-gray-700 mb-4">
              <i class="fa-solid fa-file-alt"></i> Specification & Description
            </h4>
            <div class="space-y-4">
              <div>
                <p class="text-sm text-gray-500">Description</p>
                <p class="font-medium">{{ form.description }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-500">Specification</p>
                <p class="font-medium">{{ form.specification }}</p>
              </div>
              <div v-if="form.images.length > 0">
                <p class="text-sm text-gray-500">Images</p>
                <div class="grid grid-cols-4 gap-4 mt-2">
                  <div v-for="(image, index) in form.images" :key="index" class="relative">
                    <img
                      v-if="image instanceof FileType"
                      :src="URLObject.createObjectURL(image)"
                      class="w-full h-24 object-cover rounded shadow border"
                    />
                    <img
                      v-else
                      :src="typeof image === 'string' ? '/storage/' + image : ''"
                      class="w-full h-24 object-cover rounded shadow border"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-between pt-6">
            <button
              type="button"
              @click="prevStep"
              class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg flex items-center gap-2"
            >
              <i class="fa-solid fa-arrow-left"></i> Kembali
            </button>
          </div>
          <div class="flex justify-end pt-6">
            <button
              type="button"
              @click="handleSave"
              class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg flex items-center gap-2 shadow"
              :disabled="isSaving"
            >
              <i v-if="isSaving" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-save"></i>
              {{ mode === 'create' ? 'Simpan Item' : 'Update Item' }}
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const FileType = window.File;
const URLObject = window.URL || window.webkitURL;

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  item: Object,
  categories: {
    type: Array,
    default: () => []
  },
  subCategories: {
    type: Array,
    default: () => []
  },
  units: {
    type: Array,
    default: () => []
  },
  warehouseDivisions: {
    type: Array,
    default: () => []
  },
  menuTypes: {
    type: Array,
    default: () => []
  },
  regions: {
    type: [Array, Object],
    default: () => ({})
  },
  outlets: {
    type: [Array, Object],
    default: () => ({})
  },
  bomItems: {
    type: Array,
    default: () => []
  },
  modifiers: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  category_id: '',
  sub_category_id: '',
  warehouse_division_id: '',
  sku: '',
  type: '',
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
  modifier_option_ids: []
});

const previewImages = ref([]);

const showBomStep = computed(() => form.composition_type === 'composed');
const showModifierStep = computed(() => selectedCategory.value && selectedCategory.value.show_pos == 1 && form.modifier_enabled);
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

const filteredSubCategories = computed(() => {
  if (!form.category_id || !props.subCategories) return [];
  return props.subCategories.filter(
    sc => sc.category_id === Number(form.category_id) && sc.status === 'active'
  );
});

const hasSubCategories = computed(() => {
  return filteredSubCategories.value?.length > 0;
});

const selectedCategory = computed(() => {
  return props.categories?.find(cat => cat.id === Number(form.category_id)) || null;
});

const isShowWarehouseDivision = computed(() => {
  return selectedCategory.value && selectedCategory.value.show_pos == 0;
});

const addPriceRow = () => {
  form.prices.push({ 
    price_type: 'specific',
    region_id: '', 
    outlet_id: '', 
    price: 0 
  });
};

const removePriceRow = (idx) => {
  form.prices.splice(idx, 1);
};

const handlePriceTypeChange = (price) => {
  if (price.price_type === 'all') {
    price.region_id = '';
    price.outlet_id = '';
  }
};

const usedRegionIds = computed(() => form.prices.map(p => p.region_id ? p.region_id.toString() : '').filter(Boolean));
const usedOutletIds = computed(() => form.prices.map(p => p.outlet_id ? p.outlet_id.toString() : '').filter(Boolean));

const addAvailabilityRow = () => {
  form.availabilities.push({ region_id: '', outlet_id: '', status: 'available' });
};
const removeAvailabilityRow = (idx) => {
  form.availabilities.splice(idx, 1);
};

const usedAvailRegionIds = computed(() => form.availabilities.map(a => a.region_id).filter(Boolean));
const usedAvailOutletIds = computed(() => form.availabilities.map(a => a.outlet_id).filter(Boolean));

const addBomRow = () => {
  form.bom.push({ item_id: '', qty: '', unit_id: '', selectedItem: null });
};
const removeBomRow = (idx) => {
  form.bom.splice(idx, 1);
};

const getSmallUnit = (itemId) => {
  const item = props.bomItems.find(i => i.id == itemId);
  if (!item) return null;
  return props.units.find(u => u.id == item.small_unit_id) || null;
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

watch(
  () => form.bom.map(b => b.item_id),
  (newVal, oldVal) => {
    form.bom.forEach((bom, idx) => {
      const item = props.bomItems.find(i => i.id == bom.item_id);
      bom.unit_id = item ? item.small_unit_id : '';
    });
  },
  { deep: true }
);

watch(() => props.show, (val) => {
  if (val && props.mode === 'create') {
    form.reset();
    form.prices = [{ 
      price_type: 'specific',
      region_id: '', 
      outlet_id: '', 
      price: 0 
    }];
    form.availabilities = [];
    form.bom = [];
    form.images = [];
    form.modifier_option_ids = [];
    form.modifier_enabled = false;
    form.composition_type = 'single';
    form.status = 'active';
    currentStep.value = 'info';
  } else if (val && props.mode === 'edit' && props.item) {
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
      images: [],
      deleted_images: [],
      prices: props.item.prices ? props.item.prices.map(p => ({
        price_type: p.availability_price_type === 'all' ? 'all' : 'specific',
        region_id: p.region_id,
        outlet_id: p.outlet_id,
        price: p.price,
        label: p.label
      })) : [],
      availabilities: props.item.availabilities ? props.item.availabilities.map(a => ({
        region_id: a.region_id,
        outlet_id: a.outlet_id,
        availability_type: a.availability_type,
        label: a.label
      })) : [],
      bom: props.item.bom ? JSON.parse(JSON.stringify(props.item.bom)) : [],
      modifier_option_ids: props.item.modifier_option_ids ? [...props.item.modifier_option_ids] : [],
    });
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

const handleImageUpload = (event) => {
  const files = event.target.files;
  for (let i = 0; i < files.length; i++) {
    const file = files[i];
    form.images.push(file);
  }
  // Reset input agar bisa upload file yang sama lagi jika perlu
  event.target.value = '';
};

const removeImage = (index) => {
  form.images.splice(index, 1);
};

const removeExistingImage = (imageId) => {
  form.deleted_images.push(imageId);
};

const submit = () => {
  if (props.mode === 'create') {
    form.post(route('items.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Item berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => {
        Swal.fire('Gagal', 'Gagal menambah item.', 'error');
      }
    });
  } else {
    form.put(route('items.update', props.item.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Item berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => {
        Swal.fire('Gagal', 'Gagal update item.', 'error');
      }
    });
  }
};

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

const modifiers = props.modifiers || [];
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

const selectedModifiers = computed(() =>
  (modifiers || []).filter(
    m => Array.isArray(m.options) && m.options.some(opt => form.modifier_option_ids.includes(opt.id))
  )
);

const validModifiers = computed(() =>
  (modifiers || []).filter(m => m && typeof m.id !== 'undefined' && Array.isArray(m.options))
);

function getValidOptions(options) {
  return Array.isArray(options) ? options.filter(o => o && typeof o.id !== 'undefined') : [];
}

function getSelectedOptions(options, selectedIds) {
  return Array.isArray(options)
    ? options.filter(o => o && typeof o.id !== 'undefined' && selectedIds.includes(o.id))
    : [];
}

onMounted(() => {
  console.log('warehouseDivisions:', props.warehouseDivisions);
  console.log('modifiers:', modifiers);
  console.log('regions:', props.regions);
  console.log('outlets:', props.outlets);
});

const isSaving = ref(false);

const handleSave = async () => {
  isSaving.value = true;
  Swal.fire({
    title: 'Menyimpan...',
    text: 'Mohon tunggu, data sedang diproses',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  try {
    if (props.mode === 'create') {
      await form.post(route('items.store'), {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil', 'Item berhasil ditambahkan!', 'success');
          emit('success');
          emit('close');
        },
        onError: (errors) => {
          let msg = 'Gagal menambah item.';
          if (form.errors && Object.values(form.errors).length > 0) {
            msg = Object.values(form.errors).flat().join('<br>');
          }
          Swal.fire({ 
            title: 'Gagal', 
            html: msg, 
            icon: 'error',
            confirmButtonText: 'OK'
          });
        },
        onFinish: () => { isSaving.value = false; }
      });
    } else {
      await form.put(route('items.update', props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
          Swal.fire('Berhasil', 'Item berhasil diupdate!', 'success');
          emit('success');
          emit('close');
        },
        onError: () => {
          let msg = 'Gagal update item.';
          if (form.errors && Object.values(form.errors).length > 0) {
            msg = Object.values(form.errors).flat().join('<br>');
          }
          Swal.fire({ title: 'Gagal', html: msg, icon: 'error' });
        },
        onFinish: () => { isSaving.value = false; }
      });
    }
  } catch (e) {
    isSaving.value = false;
    Swal.fire('Error', 'Terjadi kesalahan saat menyimpan.', 'error');
  }
};

const regionsArray = computed(() => Array.isArray(props.regions) ? props.regions : Object.values(props.regions || {}));
const outletsArray = computed(() => Array.isArray(props.outlets) ? props.outlets : Object.values(props.outlets || {}));

const closeModal = () => {
  form.reset();
  form.clearErrors();
  currentStep.value = 'info';
  emit('close');
};
</script> 
