<template>
  <AppLayout title="Stock Cut">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          <i class="fa-solid fa-scissors text-blue-500"></i> Potong Stock
        </h2>
        <div class="flex gap-2">
          <Link :href="route('stock-cut.menu-cost')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
            <i class="fa-solid fa-calculator mr-1"></i> Report Cost Menu
          </Link>
          <Link :href="route('stock-cut.index')" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fa-solid fa-list mr-1"></i> Log Stock Cut
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- Form -->
          <form @submit.prevent="cekEngineering" class="space-y-4 bg-white rounded-xl shadow-xl p-8">
            <div>
              <label class="block text-sm font-medium text-gray-700">Tanggal</label>
              <input type="date" v-model="tanggal" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" :max="today" required />
            </div>

            <div v-if="user.id_outlet === 1">
              <label class="block text-sm font-medium text-gray-700">Outlet <span class="text-red-500">*</span></label>
              <select v-model="selectedOutlet" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                <option value="">Pilih Outlet</option>
                <option v-for="o in outlets" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div v-else>
              <label class="block text-sm font-medium text-gray-700">Outlet</label>
              <input type="text" :value="userOutletName" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed" readonly />
              <input type="hidden" v-model="selectedOutlet" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Type Item (Opsional)</label>
              <select v-model="selectedType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">Semua Type (Food + Beverages)</option>
                <option value="food">Food (Asian, Western, Food)</option>
                <option value="beverages">Beverages</option>
              </select>
            </div>

            <div class="flex gap-2 mt-4">
              <button type="submit" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <span v-if="loading" class="animate-spin mr-2">⏳</span>
                Cek Engineering
              </button>
            </div>
          </form>

          <!-- Stock Cut Status -->
            <div v-if="stockCutStatus && stockCutStatus.has_stock_cut && stockCutStatus.logs && stockCutStatus.logs.length > 0" class="mt-6">
            <!-- Tampilkan semua log yang berhasil -->
            <div v-for="log in stockCutStatus.logs.filter(l => l.status === 'success')" :key="log.id" class="mb-4">
              <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <strong class="font-bold">Stock Cut Sudah Dilakukan!</strong>
                <div class="mt-2 text-sm">
                  <p>Outlet ini sudah melakukan potong stock pada tanggal {{ tanggal }}.</p>
                  <p v-if="log.type_filter">
                    Type yang sudah dipotong: 
                    <span class="font-semibold">
                      {{ log.type_filter === 'food' ? 'Food' : (log.type_filter === 'beverages' ? 'Beverages' : 'Semua Type') }}
                    </span>
                  </p>
                  <p v-else class="font-semibold">Type: Semua Type (Food + Beverages)</p>
                  <p>Total item dipotong: {{ log.total_items_cut }}</p>
                  <p>Total modifier dipotong: {{ log.total_modifiers_cut }}</p>
                  <p>Dilakukan pada: {{ new Date(log.created_at).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>
            
            <!-- Tampilkan log yang gagal -->
            <div v-for="log in stockCutStatus.logs.filter(l => l.status !== 'success')" :key="log.id" class="mb-4">
              <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong class="font-bold">Stock Cut Gagal!</strong>
                <div class="mt-2 text-sm">
                  <p>Outlet ini pernah mencoba potong stock pada tanggal {{ tanggal }} tetapi gagal.</p>
                  <p v-if="log.error_message">Error: {{ log.error_message }}</p>
                  <p>Dilakukan pada: {{ new Date(log.created_at).toLocaleString('id-ID') }}</p>
                  <p class="mt-2 font-semibold">Anda dapat mencoba stock cut lagi.</p>
                </div>
              </div>
            </div>
            
            <!-- Warning jika tidak bisa potong lagi -->
            <div v-if="stockCutStatus.can_cut === false" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mt-4">
              <strong class="font-bold">⚠️ Perhatian!</strong>
              <p class="mt-2 text-sm">
                <span v-if="stockCutStatus.has_all_mode">
                  Stock cut "Semua Type" sudah pernah dilakukan. Tidak dapat melakukan stock cut lagi untuk tanggal ini.
                </span>
                <span v-else-if="stockCutStatus.has_food_mode && stockCutStatus.has_beverages_mode">
                  Stock cut "Food" dan "Beverages" sudah pernah dilakukan. Tidak dapat melakukan stock cut lagi untuk tanggal ini.
                </span>
                <span v-else-if="stockCutStatus.has_food_mode">
                  Stock cut "Food" sudah pernah dilakukan. Anda masih bisa melakukan stock cut "Beverages".
                </span>
                <span v-else-if="stockCutStatus.has_beverages_mode">
                  Stock cut "Beverages" sudah pernah dilakukan. Anda masih bisa melakukan stock cut "Food".
                </span>
                <span v-else>
                  Stock cut sudah pernah dilakukan untuk tanggal ini.
                </span>
              </p>
            </div>
          </div>

          <!-- No Data Message -->
          <div v-if="engineeringChecked && engineering.length === 0" class="mt-6">
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
              Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.
            </div>
          </div>

          <!-- Engineering Section -->
          <div v-if="engineeringChecked && Object.keys(engineering).length" class="mt-6">
            <h3 class="font-bold mb-2">Engineering (Item Terjual)</h3>
            <div v-for="(catObj, type) in engineering" :key="type" class="mb-4 border rounded-lg">
              <button @click="toggleType(type)" class="w-full text-left px-4 py-2 bg-blue-50 font-bold rounded-t-lg flex items-center justify-between">
                <span>{{ type }}</span>
                <span><i :class="expandedType[type] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
              </button>
              <div v-show="expandedType[type]">
                <div v-for="(subcatObj, cat) in catObj" :key="cat" class="ml-4 mb-2 border-l-2 border-blue-200">
                  <button @click="toggleCat(type, cat)" class="w-full text-left px-4 py-2 bg-blue-100 font-semibold flex items-center justify-between">
                    <span>{{ cat }}</span>
                    <span><i :class="expandedCat[type+cat] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
                  </button>
                  <div v-show="expandedCat[type+cat]">
                    <div v-for="(items, subcat) in subcatObj" :key="subcat" class="ml-4 mb-2 border-l-2 border-blue-100">
                      <button @click="toggleSubcat(type, cat, subcat)" class="w-full text-left px-4 py-2 bg-blue-200 font-medium flex items-center justify-between">
                        <span>{{ subcat }}</span>
                        <span><i :class="expandedSubcat[type+cat+subcat] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
                      </button>
                      <div v-show="expandedSubcat[type+cat+subcat]">
                        <table class="min-w-full divide-y divide-gray-200 bg-white rounded shadow mt-2">
                          <thead>
                            <tr>
                              <th class="px-4 py-2 text-left">Nama Item</th>
                              <th class="px-4 py-2 text-right">Qty Terjual</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="row in items" :key="row.item_name">
                              <td class="px-4 py-2">{{ row.item_name }}</td>
                              <td class="px-4 py-2 text-right">{{ row.total_qty }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Missing BOM Warnings -->
            <div v-if="missingBom.length" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">
              <strong class="font-bold">Perhatian!</strong> Ada item yang belum punya BOM:
              <ul class="mt-2 list-disc ml-6">
                <li v-for="item in missingBom" :key="item.item_id">
                  {{ item.item_name }} (ID: {{ item.item_id }})
                </li>
              </ul>
            </div>

            <!-- Modifier Engineering -->
            <div v-if="engineeringChecked && modifiers.length" class="mt-6">
              <h3 class="font-bold mb-2">Modifier Engineering</h3>
              <div v-for="(group, groupIdx) in modifiers" :key="group.group_id" class="mb-4 border rounded-lg">
                <button @click="toggleModifierGroup(group.group_id)" class="w-full text-left px-4 py-2 bg-purple-50 font-bold rounded-t-lg flex items-center justify-between">
                  <span>{{ group.group_name }} (Total: {{ getModifierGroupTotal(group) }})</span>
                  <span><i :class="expandedModifierGroup[group.group_id] ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i></span>
                </button>
                <div v-show="expandedModifierGroup[group.group_id]">
                  <div class="bg-white rounded-lg shadow border">
                    <table class="min-w-full divide-y divide-gray-200">
                      <thead>
                        <tr class="bg-purple-100">
                          <th class="px-4 py-2 text-left text-sm font-semibold text-purple-800">No</th>
                          <th class="px-4 py-2 text-left text-sm font-semibold text-purple-800">Nama Modifier</th>
                          <th class="px-4 py-2 text-right text-sm font-semibold text-purple-800">Qty Terjual</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(mod, idx) in group.modifiers" :key="mod.name" class="hover:bg-purple-50">
                          <td class="px-4 py-2 text-sm">{{ idx + 1 }}</td>
                          <td class="px-4 py-2 text-sm font-medium">{{ mod.name }}</td>
                          <td class="px-4 py-2 text-sm text-right font-semibold">{{ mod.qty }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div v-if="missingModifierBom.length" class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded mt-4">
                <strong class="font-bold">Perhatian!</strong> Ada modifier yang belum punya BOM:
                <ul class="mt-2 list-disc ml-6">
                  <li v-for="modifier in missingModifierBom" :key="modifier.name">
                    {{ modifier.name }} (Modifier ID: {{ modifier.modifier_id }})
                  </li>
                </ul>
              </div>
            </div>

            <!-- Check Stock Button -->
            <div class="mt-4">
              <button @click="cekKebutuhan" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <span v-if="loading" class="animate-spin mr-2">⏳</span>
                Cek Kebutuhan Stock Engineering
              </button>
              <button v-if="bolehPotong && !isAlreadyCut" @click="potongStockAsync" :disabled="loadingPotong" class="ml-2 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                <span v-if="loadingPotong" class="animate-spin mr-2">⏳</span>
                Potong Stock Sekarang (Queue)
              </button>
            </div>
          </div>

          <!-- Laporan Stock Table -->
          <div v-if="showLaporanStock" class="mt-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-lg">Laporan Kebutuhan vs Stock Tersedia</h3>
              <div class="flex gap-2">
                <button @click="expandAll" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition">
                  Expand All
                </button>
                <button @click="collapseAll" class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700 transition">
                  Collapse All
                </button>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
              <div class="animate-spin text-blue-500 text-4xl mb-4">⏳</div>
              <p class="text-blue-700 font-medium">Memuat data laporan stock...</p>
            </div>

            <!-- Table -->
            <div v-else-if="laporanStock.length > 0" class="bg-white rounded-lg shadow border overflow-hidden">
              <!-- Group by Warehouse -->
              <div v-for="(warehouseData, warehouseName) in groupedLaporanStock" :key="warehouseName" class="mb-6">
                <button @click="toggleWarehouse(warehouseName)" class="w-full bg-gray-100 px-4 py-3 border-b text-left hover:bg-gray-200 transition">
                  <div class="flex items-center justify-between">
                    <div>
                      <h4 class="font-bold text-lg text-gray-800">{{ warehouseName }} ({{ getWarehouseItemCount(warehouseData) }} items)</h4>
                      <div class="text-sm text-gray-600">
                        <span class="text-green-600">{{ getWarehouseCukupCount(warehouseData) }} cukup</span> | 
                        <span class="text-red-600">{{ getWarehouseKurangCount(warehouseData) }} kurang</span>
                      </div>
                    </div>
                    <i :class="{'fa-chevron-down': !expandedWarehouse[warehouseName], 'fa-chevron-up': expandedWarehouse[warehouseName]}" class="fa-solid text-gray-600"></i>
                  </div>
                </button>
                
                <div v-if="expandedWarehouse[warehouseName]">
                  <!-- Group by Category -->
                  <div v-for="(categoryData, categoryName) in warehouseData" :key="categoryName" class="mb-4">
                    <button @click="toggleCategory(warehouseName, categoryName)" class="w-full bg-blue-50 px-4 py-2 border-b text-left hover:bg-blue-100 transition">
                      <div class="flex items-center justify-between">
                        <div>
                          <h5 class="font-semibold text-blue-800">{{ categoryName }} ({{ getCategoryItemCount(categoryData) }} items)</h5>
                          <div class="text-xs text-blue-600">
                            <span class="text-green-600">{{ getCategoryCukupCount(categoryData) }} cukup</span> | 
                            <span class="text-red-600">{{ getCategoryKurangCount(categoryData) }} kurang</span>
                          </div>
                        </div>
                        <i :class="{'fa-chevron-down': !expandedCategory[warehouseName+categoryName], 'fa-chevron-up': expandedCategory[warehouseName+categoryName]}" class="fa-solid text-blue-600"></i>
                      </div>
                    </button>
                    
                    <div v-if="expandedCategory[warehouseName+categoryName]">
                      <!-- Group by Sub Category -->
                      <div v-for="(subCategoryData, subCategoryName) in categoryData" :key="subCategoryName" class="mb-3">
                        <button @click="toggleSubCategory(warehouseName, categoryName, subCategoryName)" class="w-full bg-blue-100 px-4 py-1 border-b text-left hover:bg-blue-200 transition">
                          <div class="flex items-center justify-between">
                            <div>
                              <h6 class="font-medium text-blue-700 text-sm">{{ subCategoryName }} ({{ subCategoryData.length }} items)</h6>
                              <div class="text-xs text-blue-600">
                                <span class="text-green-600">{{ subCategoryData.filter(item => item.status === 'cukup').length }} cukup</span> | 
                                <span class="text-red-600">{{ subCategoryData.filter(item => item.status === 'kurang').length }} kurang</span>
                              </div>
                            </div>
                            <i :class="{'fa-chevron-down': !expandedSubCategory[warehouseName+categoryName+subCategoryName], 'fa-chevron-up': expandedSubCategory[warehouseName+categoryName+subCategoryName]}" class="fa-solid text-blue-600"></i>
                          </div>
                        </button>
                        
                        <div v-if="expandedSubCategory[warehouseName+categoryName+subCategoryName]">
                          <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                              <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Kebutuhan</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Tersedia</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                              </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                              <template v-for="item in subCategoryData" :key="item.item_id + '-' + item.warehouse_id + '-' + item.unit_id">
                                <!-- Row Small Unit (always shown) -->
                                <tr class="hover:bg-gray-50">
                                  <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <div class="flex items-center">
                                      <button @click="toggleItem(item.item_id + '-' + item.warehouse_id + '-' + item.unit_id)" class="mr-2 text-blue-600 hover:text-blue-800">
                                        <i :class="{'fa-chevron-down': !expandedItems[item.item_id + '-' + item.warehouse_id + '-' + item.unit_id], 'fa-chevron-up': expandedItems[item.item_id + '-' + item.warehouse_id + '-' + item.unit_id]}" class="fa-solid text-sm"></i>
                                      </button>
                                      {{ item.item_name }}
                                    </div>
                                  </td>
                                  <td class="px-4 py-3 text-sm text-gray-500">
                                    <span class="font-semibold">{{ item.unit_small_name || item.unit_name }}</span>
                                    <span class="text-xs text-gray-400 ml-1">(Small)</span>
                                  </td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatQuantity(item.kebutuhan_small || item.kebutuhan) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatQuantity(item.stock_tersedia_small || item.stock_tersedia) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold" :class="(item.selisih_small || item.selisih) > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ (item.selisih_small || item.selisih) > 0 ? '+' + formatQuantity(item.selisih_small || item.selisih) : formatQuantity(item.selisih_small || item.selisih) }}
                                  </td>
                                  <td class="px-4 py-3 text-sm text-center">
                                    <span v-if="item.status === 'cukup'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                      Cukup
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                      Kurang
                                    </span>
                                  </td>
                                </tr>
                                
                                <!-- Row Medium Unit (if available) -->
                                <tr v-if="item.has_medium_unit && item.unit_medium_name" class="hover:bg-gray-50 bg-blue-50/30">
                                  <td class="px-4 py-3 text-sm text-gray-500"></td>
                                  <td class="px-4 py-3 text-sm text-gray-500">
                                    <span class="font-semibold">{{ item.unit_medium_name }}</span>
                                    <span class="text-xs text-gray-400 ml-1">(Medium)</span>
                                  </td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-700">{{ formatQuantity(item.kebutuhan_medium) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-700">{{ formatQuantity(item.stock_tersedia_medium) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold" :class="item.selisih_medium > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ item.selisih_medium > 0 ? '+' + formatQuantity(item.selisih_medium) : formatQuantity(item.selisih_medium) }}
                                  </td>
                                  <td class="px-4 py-3 text-sm text-center">
                                    <span class="text-xs text-gray-500">-</span>
                                  </td>
                                </tr>
                                
                                <!-- Row Large Unit (if available) -->
                                <tr v-if="item.has_large_unit && item.unit_large_name" class="hover:bg-gray-50 bg-purple-50/30">
                                  <td class="px-4 py-3 text-sm text-gray-500"></td>
                                  <td class="px-4 py-3 text-sm text-gray-500">
                                    <span class="font-semibold">{{ item.unit_large_name }}</span>
                                    <span class="text-xs text-gray-400 ml-1">(Large)</span>
                                  </td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-700">{{ formatQuantity(item.kebutuhan_large) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-700">{{ formatQuantity(item.stock_tersedia_large) }}</td>
                                  <td class="px-4 py-3 text-sm text-right font-semibold" :class="item.selisih_large > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ item.selisih_large > 0 ? '+' + formatQuantity(item.selisih_large) : formatQuantity(item.selisih_large) }}
                                  </td>
                                  <td class="px-4 py-3 text-sm text-center">
                                    <span class="text-xs text-gray-500">-</span>
                                  </td>
                                </tr>
                                
                                <!-- Contributing Menus Section -->
                                <tr v-if="expandedItems[item.item_id + '-' + item.warehouse_id + '-' + item.unit_id] && item.contributing_menus && item.contributing_menus.length > 0" class="bg-gray-50">
                                  <td colspan="6" class="px-4 py-3">
                                    <div class="bg-white border rounded-lg p-3">
                                      <h6 class="font-semibold text-gray-800 mb-2">Menu yang Mengurangi Stock:</h6>
                                      <p class="text-xs text-gray-500 mb-2">Angka di kanan = total pakai (gram/satuan). Untuk modifier: (qty pesanan × qty modifier × BOM/modifier) dijumlah per transaksi.</p>
                                      <div class="space-y-2">
                                        <div v-for="(menu, menuIdx) in item.contributing_menus" :key="menuIdx" class="flex justify-between items-center text-sm border-b pb-1">
                                          <div class="flex-1">
                                            <span class="font-medium text-gray-700">{{ menu.menu_name }}</span>
                                            <span v-if="menu.type === 'menu'" class="text-gray-500 ml-2" :title="'Qty pesanan × BOM per porsi = ' + formatQuantity(menu.total_contributed)">
                                              (Total: {{ formatQuantity(menu.menu_qty) }} × {{ formatQuantity(menu.bom_qty_per_menu) }})
                                            </span>
                                            <span v-else-if="menu.type === 'modifier'" class="text-gray-500 ml-2" :title="'Total pakai = Σ (qty pesanan × qty modifier × BOM/modifier) per transaksi. Bukan (32×32×30) karena tiap transaksi beda qty.'">
                                              (Qty pesanan total: {{ formatQuantity(menu.menu_qty) }}, Qty modifier total: {{ formatQuantity(menu.modifier_qty) }}, BOM/modifier: {{ formatQuantity(menu.bom_qty_per_modifier) }})
                                            </span>
                                          </div>
                                          <div class="text-right">
                                            <span class="font-semibold text-blue-600">{{ formatQuantity(menu.total_contributed) }}</span>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </td>
                                </tr>
                              </template>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- No Data Message -->
            <div v-else-if="!loading" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
              <strong class="font-bold">Tidak ada data laporan stock untuk ditampilkan.</strong>
              <p class="mt-2 text-sm">Silakan klik tombol "Cek Kebutuhan Stock Engineering" untuk memuat data laporan stock.</p>
            </div>

            <!-- Summary Cards -->
            <div v-if="laporanStock.length > 0" class="mt-4">
              <!-- Overall Summary -->
              <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                  <div class="text-sm font-medium text-blue-800">Total Item Dicek</div>
                  <div class="text-2xl font-bold text-blue-900">{{ laporanStock.length }}</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                  <div class="text-sm font-medium text-green-800">Stock Cukup</div>
                  <div class="text-2xl font-bold text-green-900">{{ laporanStock.filter(item => item.status === 'cukup').length }}</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                  <div class="text-sm font-medium text-red-800">Stock Kurang</div>
                  <div class="text-2xl font-bold text-red-900">{{ laporanStock.filter(item => item.status === 'kurang').length }}</div>
                </div>
              </div>
              
              <!-- Warehouse Summary -->
              <div class="grid grid-cols-2 gap-4">
                <div v-for="(warehouseData, warehouseName) in groupedLaporanStock" :key="warehouseName" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                  <h5 class="font-semibold text-gray-800 mb-3">{{ warehouseName }}</h5>
                  <div class="grid grid-cols-3 gap-2 text-sm mb-3">
                    <div class="text-center">
                      <div class="font-medium text-gray-600">Total</div>
                      <div class="font-bold text-gray-800">{{ getWarehouseItemCount(warehouseData) }}</div>
                    </div>
                    <div class="text-center">
                      <div class="font-medium text-green-600">Cukup</div>
                      <div class="font-bold text-green-800">{{ getWarehouseCukupCount(warehouseData) }}</div>
                    </div>
                    <div class="text-center">
                      <div class="font-medium text-red-600">Kurang</div>
                      <div class="font-bold text-red-800">{{ getWarehouseKurangCount(warehouseData) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Messages -->
          <div v-if="successMsg" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mt-4">{{ successMsg }}</div>
          <div v-if="errorMsg" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4">{{ errorMsg }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  outlets: Array,
  user: Object
})

// User data - get from auth
const user = ref({
  id_outlet: 1, // Default to admin
  outlet_name: ''
})

// Reactive data
const tanggal = ref(new Date().toISOString().slice(0, 10))
const today = new Date().toISOString().slice(0, 10)
const loading = ref(false)
const loadingPotong = ref(false)
const successMsg = ref('')
const errorMsg = ref('')
const bolehPotong = ref(false)
const itemMaster = ref({})
const warehouseMaster = ref({})
const outlets = ref(props.outlets && props.outlets.length ? props.outlets : [])
const selectedOutlet = ref('')
const selectedType = ref('')
const engineering = ref({})
const engineeringChecked = ref(false)
const expandedType = ref({})
const expandedCat = ref({})
const expandedSubcat = ref({})
const expandedModifierGroup = ref({})
const missingBom = ref([])
const missingModifierBom = ref([])
const modifiers = ref([])
const stockCutStatus = ref(null)
const laporanStock = ref([])
const showLaporanStock = ref(false)
const expandedWarehouse = ref({})
const expandedCategory = ref({})
const expandedSubCategory = ref({})
const expandedItems = ref({}) // New ref for item expansion

// Computed
const userOutletName = computed(() => user.value.outlet_name)
const isAlreadyCut = computed(() => {
  if (!stockCutStatus.value || !stockCutStatus.value.has_stock_cut) {
    return false
  }
  
  // Normalize selected type: empty string atau null berarti "Semua Type"
  const normalizedSelectedType = selectedType.value && selectedType.value !== '' ? selectedType.value : null
  
  // Cek apakah ada log dengan status success untuk type yang dipilih
  if (stockCutStatus.value.logs && stockCutStatus.value.logs.length > 0) {
    const successLogs = stockCutStatus.value.logs.filter(log => log.status === 'success')
    
    if (successLogs.length === 0) {
      return false
    }
    
    // Jika user pilih "Semua Type" (null), cek apakah sudah ada stock cut apapun
    if (normalizedSelectedType === null) {
      return successLogs.length > 0
    }
    
    // Jika user pilih type tertentu, cek apakah type tersebut sudah dipotong
    // Atau jika sudah ada stock cut "Semua Type" (type_filter = null)
    return successLogs.some(log => {
      const logType = log.type_filter || null
      // Jika log adalah "Semua Type", maka semua type sudah dipotong
      if (logType === null) {
        return true
      }
      // Jika log type sama dengan selected type, maka sudah dipotong
      return logType === normalizedSelectedType
    })
  }
  
  // Fallback untuk kompatibilitas dengan struktur lama
  if (stockCutStatus.value.log) {
    const log = stockCutStatus.value.log
    if (log.status !== 'success') {
      return false
    }
    
    const logType = log.type_filter || null
    
    // Jika log adalah "Semua Type", maka semua type sudah dipotong
    if (logType === null) {
      return true
    }
    
    // Jika user pilih "Semua Type", cek apakah sudah ada stock cut
    if (normalizedSelectedType === null) {
      return true
    }
    
    // Jika log type sama dengan selected type, maka sudah dipotong
    return logType === normalizedSelectedType
  }
  
  return false
})

// Group laporan stock by warehouse, category, and sub-category
const groupedLaporanStock = computed(() => {
  if (!laporanStock.value || laporanStock.value.length === 0) {
    return {}
  }

  const grouped = {}
  
  laporanStock.value.forEach(item => {
    const warehouseName = item.warehouse_name
    const categoryName = item.category_name
    const subCategoryName = item.sub_category_name
    
    if (!grouped[warehouseName]) {
      grouped[warehouseName] = {}
    }
    
    if (!grouped[warehouseName][categoryName]) {
      grouped[warehouseName][categoryName] = {}
    }
    
    if (!grouped[warehouseName][categoryName][subCategoryName]) {
      grouped[warehouseName][categoryName][subCategoryName] = []
    }
    
    grouped[warehouseName][categoryName][subCategoryName].push(item)
  })
  
  return grouped
})

// Lifecycle
onMounted(async () => {
  // Get user data from props or auth
  if (props.user) {
    user.value = props.user
  }
  
  // Jika id_outlet != 1, set selectedOutlet otomatis dan tidak bisa pilih outlet
  if (user.value.id_outlet && user.value.id_outlet != 1) {
    selectedOutlet.value = user.value.id_outlet
  }
  
  if (!outlets.value.length) {
    const res = await axios.get('/api/outlets')
    outlets.value = res.data
  }
  await fetchMasters()
  bolehPotong.value = false
})

// Methods
async function fetchMasters() {
  const [itemRes, whRes] = await Promise.all([
    axios.get('/api/items'),
    axios.get('/api/warehouse-outlets', { params: { outlet_id: selectedOutlet.value || user.value.id_outlet } })
  ])
  itemMaster.value = Object.fromEntries(itemRes.data.map(i => [i.id, i.name]))
  warehouseMaster.value = Object.fromEntries(whRes.data.map(w => [w.id, w.name]))
}

function getItemName(id) {
  return itemMaster.value[id] || id
}

function getWarehouseName(id) {
  return warehouseMaster.value[id] || id
}

// Watchers
watch(selectedOutlet, async (val) => {
  if (user.value.id_outlet === 1 && val) {
    const whRes = await axios.get('/api/warehouse-outlets', { params: { outlet_id: val } })
    warehouseMaster.value = Object.fromEntries(whRes.data.map(w => [w.id, w.name]))
  }
  // Reset stock cut status saat outlet berubah
  if (tanggal.value) {
    stockCutStatus.value = null
    await cekStockCutStatus()
  }
})

watch(tanggal, async (val) => {
  // Reset stock cut status saat tanggal berubah
  if (val && (selectedOutlet.value || user.value.id_outlet)) {
    stockCutStatus.value = null
    await cekStockCutStatus()
  }
})

watch(selectedType, async (val) => {
  // Reset stock cut status saat type berubah
  if (tanggal.value && (selectedOutlet.value || user.value.id_outlet)) {
    stockCutStatus.value = null
    await cekStockCutStatus()
  }
})

// API Functions
async function cekStockCutStatus() {
  try {
    const statusRes = await axios.post('/stock-cut/check-status', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet,
      type: selectedType.value || null
    })
    stockCutStatus.value = statusRes.data
  } catch (e) {
    console.error('Error checking stock cut status:', e)
  }
}

async function cekEngineering() {
  loading.value = true
  successMsg.value = ''
  errorMsg.value = ''
  bolehPotong.value = false
  engineering.value = {}
  engineeringChecked.value = false
  modifiers.value = []
  missingBom.value = []
  missingModifierBom.value = []
  laporanStock.value = []
  showLaporanStock.value = false
  expandedWarehouse.value = {}
  expandedCategory.value = {}
  expandedSubCategory.value = {}
  expandedItems.value = {} // Reset item expansion

  try {
    await cekStockCutStatus()

    // Validasi: Cek apakah type yang dipilih sudah pernah dipotong
    // API sudah memberikan informasi can_cut, tapi kita juga perlu validasi manual untuk pesan error yang lebih jelas
    const selectedTypeValue = selectedType.value || null
    
    if (stockCutStatus.value && stockCutStatus.value.has_stock_cut) {
      // Cek apakah masih bisa potong berdasarkan response API
      if (stockCutStatus.value.can_cut === false) {
        // Ambil log terakhir untuk menampilkan informasi
        const logs = stockCutStatus.value.logs || []
        const lastLog = logs.length > 0 ? logs[0] : null
        
        let errorMessage = ''
        const normalizedSelectedType = selectedTypeValue && selectedTypeValue !== '' ? selectedTypeValue : null
        const selectedTypeName = normalizedSelectedType === null ? 'Semua Type' : (normalizedSelectedType === 'food' ? 'Food' : 'Beverages')
        
        if (stockCutStatus.value.has_all_mode) {
          // Sudah potong "Semua Type"
          errorMessage = 'Stock cut "Semua Type" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut lagi, terlepas dari type yang dipilih.'
        } else if (normalizedSelectedType === null) {
          // User mau potong "Semua Type", tapi sudah ada stock cut sebelumnya
          if (stockCutStatus.value.has_food_mode && stockCutStatus.value.has_beverages_mode) {
            errorMessage = 'Stock cut "Food" dan "Beverages" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Semua Type" lagi.'
          } else if (stockCutStatus.value.has_food_mode) {
            errorMessage = 'Stock cut "Food" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Semua Type" jika sudah ada stock cut sebelumnya.'
          } else if (stockCutStatus.value.has_beverages_mode) {
            errorMessage = 'Stock cut "Beverages" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Semua Type" jika sudah ada stock cut sebelumnya.'
          } else {
            errorMessage = 'Stock cut sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Semua Type" lagi.'
          }
        } else if (normalizedSelectedType === 'food' && stockCutStatus.value.has_food_mode) {
          errorMessage = 'Stock cut "Food" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Food" lagi.'
        } else if (normalizedSelectedType === 'beverages' && stockCutStatus.value.has_beverages_mode) {
          errorMessage = 'Stock cut "Beverages" sudah pernah dilakukan untuk tanggal ini. Tidak dapat melakukan stock cut "Beverages" lagi.'
        } else {
          errorMessage = `Stock cut "${selectedTypeName}" tidak dapat dilakukan untuk tanggal ini karena sudah ada stock cut sebelumnya.`
        }
        
        errorMsg.value = errorMessage
        engineeringChecked.value = false
        loading.value = false
        return
      }
      
      // Jika ada log dengan status success, tampilkan informasi
      if (stockCutStatus.value.logs && stockCutStatus.value.logs.length > 0) {
        const successLog = stockCutStatus.value.logs.find(log => log.status === 'success')
        if (successLog && successLog.status === 'success') {
          // Log sudah ditampilkan di bagian stock cut status di atas
          // Tidak perlu validasi tambahan karena can_cut sudah dicek
        }
      }
    }

    const engRes = await axios.post('/stock-cut/engineering', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet,
      type: selectedType.value || null
    })

    engineering.value = engRes.data.engineering
    missingBom.value = engRes.data.missing_bom
    modifiers.value = engRes.data.modifiers
    missingModifierBom.value = engRes.data.missing_modifier_bom
    engineeringChecked.value = true

    // Jika tidak ada data engineering tapi ada stock cut untuk type lain, kemungkinan data salah
    if (Object.keys(engineering.value).length === 0 && modifiers.value.length === 0) {
      const normalizedSelectedType = selectedTypeValue && selectedTypeValue !== '' ? selectedTypeValue : null
      if (normalizedSelectedType && stockCutStatus.value && stockCutStatus.value.has_stock_cut) {
        // Cek apakah type yang dipilih belum dipotong
        const hasSelectedTypeCut = normalizedSelectedType === 'food' 
          ? stockCutStatus.value.has_food_mode 
          : (normalizedSelectedType === 'beverages' 
            ? stockCutStatus.value.has_beverages_mode 
            : false)
        
        // Jika type belum dipotong tapi tidak ada data, kemungkinan data stock_cut salah
        if (!hasSelectedTypeCut) {
          try {
            const fixRes = await axios.post('/stock-cut/fix-data', {
              tanggal: tanggal.value,
              id_outlet: selectedOutlet.value || user.value.id_outlet
            })
            
            if (fixRes.data.status === 'success' && fixRes.data.fixed_count > 0) {
              // Reload engineering setelah fix
              const engResAfterFix = await axios.post('/stock-cut/engineering', {
                tanggal: tanggal.value,
                id_outlet: selectedOutlet.value || user.value.id_outlet,
                type: selectedType.value || null
              })
              
              engineering.value = engResAfterFix.data.engineering
              modifiers.value = engResAfterFix.data.modifiers
              missingBom.value = engResAfterFix.data.missing_bom
              missingModifierBom.value = engResAfterFix.data.missing_modifier_bom
              
              if (Object.keys(engineering.value).length === 0 && modifiers.value.length === 0) {
                successMsg.value = 'Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.'
              }
            } else {
              successMsg.value = 'Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.'
            }
          } catch (fixError) {
            console.error('Error fixing data:', fixError)
            successMsg.value = 'Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.'
          }
        } else {
          successMsg.value = 'Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.'
        }
      } else {
        successMsg.value = 'Tidak ada transaksi/menu terjual pada tanggal dan outlet yang dipilih.'
      }
    }

  } catch (e) {
    console.error('Error fetching engineering data:', e)
    errorMsg.value = 'Gagal mengambil data engineering. Silakan coba lagi.'
  } finally {
    loading.value = false
  }
}

async function cekKebutuhan() {
  loading.value = true
  successMsg.value = ''
  errorMsg.value = ''
  bolehPotong.value = false
  laporanStock.value = []
  showLaporanStock.value = false
  expandedWarehouse.value = {}
  expandedCategory.value = {}
  expandedSubCategory.value = {}
  expandedItems.value = {} // Reset item expansion

  try {
    const res = await axios.post('/stock-cut/cek-kebutuhan', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet,
      type: selectedType.value || null
    })

    if (res.data.status === 'success') {
      laporanStock.value = res.data.laporan_stock || []
      
      // Hanya tampilkan laporan stock jika ada data
      showLaporanStock.value = laporanStock.value.length > 0

      const adaYangKurang = res.data.total_kurang > 0

      if (laporanStock.value.length === 0) {
        // Tidak ada laporan stock
        bolehPotong.value = false
        
        // Cek apakah ada engineering data
        const hasEngineeringData = engineeringChecked.value && (
          Object.keys(engineering.value).length > 0 || 
          modifiers.value.length > 0
        )
        
        if (hasEngineeringData) {
          // Ada engineering data tapi tidak ada laporan stock
          // Kemungkinan semua order_items sudah dipotong (stock_cut = 1) atau tidak ada BOM
          if (missingBom.value.length > 0 || missingModifierBom.value.length > 0) {
            errorMsg.value = 'Tidak ada kebutuhan stock karena ada item/modifier yang belum punya BOM. Silakan lengkapi BOM terlebih dahulu sebelum melakukan stock cut.'
          } else {
            errorMsg.value = 'Tidak ada order_items yang perlu dipotong stock. Kemungkinan semua item untuk type yang dipilih sudah dipotong sebelumnya. Silakan cek log stock cut atau coba dengan type yang berbeda.'
          }
        } else {
          // Tidak ada engineering data
          errorMsg.value = res.data.message || 'Tidak ada kebutuhan stock untuk tanggal dan outlet yang dipilih. Silakan klik "Cek Engineering" terlebih dahulu untuk melihat data transaksi.'
        }
      } else if (!adaYangKurang) {
        // Ada laporan stock dan stock cukup
        bolehPotong.value = true
        successMsg.value = 'Stock cukup, siap untuk potong stock!'
      } else {
        // Ada laporan stock tapi stock kurang
        bolehPotong.value = false
        errorMsg.value = 'Stock kurang, tidak bisa potong stock!'
      }
    } else {
      errorMsg.value = res.data.message || 'Terjadi kesalahan saat memeriksa kebutuhan stock.'
    }
  } catch (e) {
    console.error('Error checking stock needs:', e)
    errorMsg.value = 'Gagal memeriksa kebutuhan stock. Silakan coba lagi.'
  } finally {
    loading.value = false
  }
}

let pollTimer = null
async function potongStock() {
  loadingPotong.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    const res = await axios.post('/stock-cut/order-items', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet,
      type: selectedType.value || null
    })
    if (res.data.status === 'success') {
      successMsg.value = res.data.message
      bolehPotong.value = false
    } else if (res.data.kurang) {
      errorMsg.value = 'Stock kurang, tidak bisa potong stock!'
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message
  } finally {
    loadingPotong.value = false
  }
}

async function potongStockAsync() {
  loadingPotong.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    // Process stock cut directly (synchronous, no queue needed)
    const res = await axios.post('/stock-cut/dispatch', {
      tanggal: tanggal.value,
      id_outlet: selectedOutlet.value || user.value.id_outlet,
      type: selectedType.value || null
    })
    if (res.data.status === 'success') {
      successMsg.value = res.data.message || 'Potong stock berhasil.'
      bolehPotong.value = false
      // Refresh stock cut status setelah berhasil
      await cekStockCutStatus()
    } else if (res.data.kurang) {
      errorMsg.value = 'Stock kurang, tidak bisa potong stock!'
    } else if (res.data.already_cut) {
      errorMsg.value = res.data.message || 'Stock cut sudah pernah dilakukan untuk tanggal ini.'
      // Refresh stock cut status
      await cekStockCutStatus()
    } else {
      errorMsg.value = res.data.message || 'Terjadi kesalahan saat memproses stock cut.'
    }
  } catch (e) {
    const errorData = e.response?.data
    if (errorData?.already_cut) {
      errorMsg.value = errorData.message || 'Stock cut sudah pernah dilakukan untuk tanggal ini.'
      // Refresh stock cut status
      await cekStockCutStatus()
    } else {
      errorMsg.value = errorData?.message || e.message || 'Terjadi kesalahan saat memproses stock cut.'
    }
  } finally {
    loadingPotong.value = false
  }
}

function reset() {
  tanggal.value = new Date().toISOString().slice(0, 10)
  selectedOutlet.value = user.value.id_outlet === 1 ? '' : user.value.id_outlet
  selectedType.value = ''
  loading.value = false
  loadingPotong.value = false
  successMsg.value = ''
  errorMsg.value = ''
  bolehPotong.value = false
  engineering.value = {}
  engineeringChecked.value = false
  expandedType.value = {}
  expandedCat.value = {}
  expandedSubcat.value = {}
  expandedModifierGroup.value = {}
  missingBom.value = []
  missingModifierBom.value = []
  modifiers.value = []
  stockCutStatus.value = null
  laporanStock.value = []
  showLaporanStock.value = false
  expandedWarehouse.value = {}
  expandedCategory.value = {}
  expandedSubCategory.value = {}
  expandedItems.value = {}
}

// Toggle functions
function toggleType(type) {
  expandedType.value[type] = !expandedType.value[type]
}

function toggleCat(type, cat) {
  expandedCat.value[type+cat] = !expandedCat.value[type+cat]
}

function toggleSubcat(type, cat, subcat) {
  expandedSubcat.value[type+cat+subcat] = !expandedSubcat.value[type+cat+subcat]
}

function toggleModifierGroup(groupId) {
  expandedModifierGroup.value[groupId] = !expandedModifierGroup.value[groupId]
}

function toggleWarehouse(warehouseName) {
  expandedWarehouse.value[warehouseName] = !expandedWarehouse.value[warehouseName]
}

function toggleCategory(warehouseName, categoryName) {
  expandedCategory.value[warehouseName+categoryName] = !expandedCategory.value[warehouseName+categoryName]
}

function toggleSubCategory(warehouseName, categoryName, subCategoryName) {
  expandedSubCategory.value[warehouseName+categoryName+subCategoryName] = !expandedSubCategory.value[warehouseName+categoryName+subCategoryName]
}

function toggleItem(itemKey) {
  expandedItems.value[itemKey] = !expandedItems.value[itemKey]
}

function formatQuantity(value) {
  if (value === null || value === undefined) return '0.00';
  return parseFloat(value).toLocaleString('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function getModifierGroupTotal(group) {
  return group.modifiers.reduce((total, mod) => total + mod.qty, 0)
}

function getWarehouseItemCount(warehouseData) {
  return Object.values(warehouseData).reduce((sum, category) => sum + Object.values(category).reduce((catSum, subCat) => catSum + subCat.length, 0), 0);
}

function getCategoryItemCount(categoryData) {
  return Object.values(categoryData).reduce((sum, subCat) => sum + subCat.length, 0);
}

function getWarehouseCukupCount(warehouseData) {
  return Object.values(warehouseData).reduce((sum, category) => sum + Object.values(category).reduce((catSum, subCat) => catSum + subCat.filter(item => item.status === 'cukup').length, 0), 0);
}

function getWarehouseKurangCount(warehouseData) {
  return Object.values(warehouseData).reduce((sum, category) => sum + Object.values(category).reduce((catSum, subCat) => catSum + subCat.filter(item => item.status === 'kurang').length, 0), 0);
}

function getCategoryCukupCount(categoryData) {
  return Object.values(categoryData).reduce((sum, subCat) => sum + subCat.filter(item => item.status === 'cukup').length, 0);
}

function getCategoryKurangCount(categoryData) {
  return Object.values(categoryData).reduce((sum, subCat) => sum + subCat.filter(item => item.status === 'kurang').length, 0);
}

function expandAll() {
  // Initialize and expand all warehouse sections
  Object.keys(groupedLaporanStock.value).forEach(warehouseName => {
    expandedWarehouse.value[warehouseName] = true;
    
    // Initialize and expand all category sections
    Object.keys(groupedLaporanStock.value[warehouseName]).forEach(categoryName => {
      const categoryKey = warehouseName + categoryName;
      expandedCategory.value[categoryKey] = true;
      
      // Initialize and expand all sub-category sections
      Object.keys(groupedLaporanStock.value[warehouseName][categoryName]).forEach(subCategoryName => {
        const subCategoryKey = warehouseName + categoryName + subCategoryName;
        expandedSubCategory.value[subCategoryKey] = true;
        
        // Initialize and expand all items
        groupedLaporanStock.value[warehouseName][categoryName][subCategoryName].forEach(item => {
          const itemKey = item.item_id + '-' + item.warehouse_id + '-' + item.unit_id;
          expandedItems.value[itemKey] = true;
        });
      });
    });
  });
}

function collapseAll() {
  // Collapse all warehouse sections
  Object.keys(groupedLaporanStock.value).forEach(warehouseName => {
    expandedWarehouse.value[warehouseName] = false;
    
    // Collapse all category sections
    Object.keys(groupedLaporanStock.value[warehouseName]).forEach(categoryName => {
      const categoryKey = warehouseName + categoryName;
      expandedCategory.value[categoryKey] = false;
      
      // Collapse all sub-category sections
      Object.keys(groupedLaporanStock.value[warehouseName][categoryName]).forEach(subCategoryName => {
        const subCategoryKey = warehouseName + categoryName + subCategoryName;
        expandedSubCategory.value[subCategoryKey] = false;
        
        // Collapse all items
        groupedLaporanStock.value[warehouseName][categoryName][subCategoryName].forEach(item => {
          const itemKey = item.item_id + '-' + item.warehouse_id + '-' + item.unit_id;
          expandedItems.value[itemKey] = false;
        });
      });
    });
  });
}
</script> 