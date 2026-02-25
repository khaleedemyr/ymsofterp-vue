<template>
  <AppLayout title="Edit Payment">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Payment
        </h1>
        <Link
          :href="'/purchase-requisitions'"
          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
        >
          <i class="fas fa-arrow-left mr-2"></i>
          Back to List
        </Link>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- Mode Switch -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
          <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
            <select v-model="form.mode" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="pr_ops">Purchase Requisition</option>
              <option value="purchase_payment">Payment Application</option>
              <option value="travel_application">Travel Application</option>
              <option value="kasbon">Kasbon</option>
            </select>
          </div>
          <div class="md:col-span-2 flex justify-end">
            <div class="flex flex-col items-end">
              <p class="text-xs text-gray-600 mb-1">
                <span class="text-red-600 font-semibold">Klik disini untuk tutorial</span>
              </p>
              <button
                type="button"
                @click="showTutorial = true"
                class="inline-flex items-center px-4 py-2 border-2 border-red-500 text-sm font-semibold rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 shadow-md transition-all duration-200 hover:shadow-lg"
              >
                <i class="fa fa-question-circle mr-2 text-lg"></i>
                Cara Menggunakan Menu Payment
              </button>
            </div>
          </div>
        </div>
        
        <!-- Out of Period Warning for Kasbon -->
        <div v-if="kasbonOutOfPeriod" class="mb-6 p-6 bg-red-50 border-2 border-red-300 rounded-lg">
          <div class="text-center">
            <i class="fa fa-exclamation-triangle text-red-500 text-5xl mb-4"></i>
            <h3 class="text-xl font-bold text-red-800 mb-2">Tidak Dapat Input Kasbon</h3>
            <p class="text-sm text-red-700 mb-4">
              Anda tidak dapat menginput kasbon di luar periode yang ditentukan.
            </p>
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-4 inline-block">
              <p class="text-sm font-semibold text-blue-800 mb-2">Periode Kasbon Aktif:</p>
            <p class="text-sm text-blue-700">{{ getKasbonPeriodText() }}</p>
            </div>
            <p class="text-xs text-gray-600">
              Periode kasbon: Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
            </p>
            <button
              type="button"
              @click="kasbonOutOfPeriod = false; form.mode = 'pr_ops'"
              class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Kembali ke Form
            </button>
          </div>
        </div>

        <form v-if="!kasbonOutOfPeriod" @submit.prevent="submitForm">
          <!-- Basic Information -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Title -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
              <input
                v-model="form.title"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter payment title"
              />
            </div>

            <!-- Division -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Division *</label>
              
              <!-- For kasbon: Show auto-selected division (disabled) -->
              <div v-if="form.mode === 'kasbon'">
                <input
                  type="text"
                  :value="getKasbonDivisionName()"
                  disabled
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-500">
                  Division otomatis dipilih sesuai division user yang login
                </p>
              </div>
              
              <!-- For other modes: Show division dropdown -->
              <select
                v-else
                v-model="form.division_id"
                required
                @change="loadBudgetInfo"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Division</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">
                  {{ division.nama_divisi }}
                </option>
              </select>
            </div>

            <!-- Category (Hidden for pr_ops and purchase_payment mode) -->
            <div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
              
              <!-- For travel_application: Show auto-selected transport category (disabled) -->
              <div v-if="form.mode === 'travel_application'">
                <input
                  type="text"
                  :value="getTravelCategoryName()"
                  disabled
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-500">
                  Category otomatis dipilih: Transport (sesuai dengan mode Travel Application)
                </p>
              </div>
              
              <!-- For kasbon: Show auto-selected kasbon category (disabled) -->
              <div v-else-if="form.mode === 'kasbon'">
                <input
                  type="text"
                  :value="getKasbonCategoryName()"
                  disabled
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-500">
                  Category otomatis dipilih: Kasbon (sesuai dengan mode Kasbon)
                </p>
              </div>
              
              <!-- For other modes: Show category dropdown with integrated search -->
              <div v-else class="relative category-dropdown-container">
                <div
                  @click="showCategoryDropdown = !showCategoryDropdown"
                  class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer bg-white flex items-center justify-between"
                  :class="{ 'ring-2 ring-blue-500': showCategoryDropdown }"
                >
                  <span v-if="form.category_id" class="text-gray-700">
                    {{ getCategoryDisplayName(form.category_id) }}
                  </span>
                  <span v-else class="text-gray-400">Select Category</span>
                  <i class="fa fa-chevron-down text-gray-400" :class="{ 'transform rotate-180': showCategoryDropdown }"></i>
                </div>
                <div
                  v-if="showCategoryDropdown"
                  class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
                  @click.stop
                >
                  <input
                    v-model="categorySearch"
                    type="text"
                    placeholder="Cari category..."
                    class="w-full px-3 py-2 border-b border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    @click.stop
                  />
                  <div class="max-h-60 overflow-auto">
                    <div
                      v-if="filteredCategories.length === 0"
                      class="px-3 py-2 text-sm text-gray-500"
                    >
                      Tidak ada category yang ditemukan
                    </div>
                    <div
                      v-for="category in filteredCategories"
                      :key="category.id"
                      @click="form.category_id = category.id; onCategoryChange(); showCategoryDropdown = false; categorySearch = ''"
                      class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm"
                      :class="{ 'bg-blue-100': form.category_id == category.id }"
                    >
                      [{{ category.division }}] {{ category.name }}
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Category Details (Show for non-travel_application and non-kasbon modes) -->
              <div v-if="form.mode !== 'travel_application' && form.mode !== 'kasbon' && selectedCategoryDetails" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="space-y-2">
                  <div>
                    <span class="text-sm font-medium text-blue-800">Division:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ selectedCategoryDetails.division }}</span>
                  </div>
                  <div>
                    <span class="text-sm font-medium text-blue-800">Subcategory:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ selectedCategoryDetails.subcategory }}</span>
                  </div>
                  <div>
                    <span class="text-sm font-medium text-blue-800">Budget Limit:</span>
                    <span class="text-sm text-blue-700 ml-2">{{ formatCurrency(selectedCategoryDetails.budget_limit) }}</span>
                  </div>
                  <div v-if="selectedCategoryDetails.description">
                    <span class="text-sm font-medium text-blue-800">Description:</span>
                    <p class="text-sm text-blue-700 mt-1">{{ selectedCategoryDetails.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Category Details & Budget Info for travel_application -->
              <div v-if="form.mode === 'travel_application' && selectedCategoryDetails" class="mt-3">
                <!-- Category Details -->
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg mb-3">
                  <div class="space-y-2 text-sm">
                    <div>
                      <span class="font-medium text-blue-800">Division:</span>
                      <span class="text-blue-700 ml-2">{{ selectedCategoryDetails.division }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-blue-800">Subcategory:</span>
                      <span class="text-blue-700 ml-2">{{ selectedCategoryDetails.subcategory }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-blue-800">Budget Limit:</span>
                      <span class="text-blue-700 ml-2">{{ formatCurrency(selectedCategoryDetails.budget_limit) }}</span>
                    </div>
                    <div v-if="selectedCategoryDetails.description">
                      <span class="font-medium text-blue-800">Description:</span>
                      <p class="text-blue-700 mt-1">{{ selectedCategoryDetails.description }}</p>
                    </div>
                  </div>
                </div>

                <!-- Budget Info -->
                <div v-if="budgetInfo" class="p-3 bg-green-50 border border-green-200 rounded-lg">
                  <h4 class="text-sm font-semibold text-green-800 mb-2">
                    Budget Information
                    <span class="text-xs font-normal text-green-600 ml-2">
                      ({{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
                    </span>
                  </h4>
                  
                  <!-- Global Budget Info -->
                  <div v-if="budgetInfo.budget_type === 'GLOBAL'" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <div>
                      <p class="text-green-600">Category Budget</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.category_budget) }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Used This Month</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.category_used_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Current Input</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Total After Input</p>
                      <p class="font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-green-800'">
                        {{ formatCurrency(budgetInfo.total_with_current) }}
                      </p>
                    </div>
                    <div class="md:col-span-4 mt-2">
                      <p class="text-green-600">Remaining Budget</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.remaining_after_current) }}</p>
                    </div>
                  </div>

                  <!-- Per Outlet Budget Info -->
                  <div v-else-if="budgetInfo.budget_type === 'PER_OUTLET'" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <div>
                      <p class="text-green-600">Outlet Budget</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.outlet_budget) }}</p>
                      <p class="text-xs text-gray-500">{{ budgetInfo.outlet_info?.name || 'Unknown' }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Used This Month</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.outlet_used_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Current Input</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                    </div>
                    <div>
                      <p class="text-green-600">Total After Input</p>
                      <p class="font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-green-800'">
                        {{ formatCurrency(budgetInfo.total_with_current) }}
                      </p>
                    </div>
                    <div class="md:col-span-4 mt-2">
                      <p class="text-green-600">Remaining Budget</p>
                      <p class="font-semibold text-green-800">{{ formatCurrency(budgetInfo.remaining_after_current) }}</p>
                    </div>
                  </div>

                  <!-- Warning if exceeds budget -->
                  <div v-if="budgetInfo.exceeds_budget" class="mt-2 p-2 bg-red-50 border border-red-300 rounded">
                    <p class="text-xs text-red-800 font-semibold">
                      <i class="fa fa-exclamation-triangle mr-1"></i>
                      Budget Exceeded! Total melebihi budget yang tersedia.
                    </p>
                  </div>

                  <!-- Warning if budget low -->
                  <div v-else-if="budgetInfo.remaining_after_current < ((budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_budget : budgetInfo.category_budget) * 0.1)" class="mt-2 p-2 bg-yellow-50 border border-yellow-300 rounded">
                    <p class="text-xs text-yellow-800 font-semibold">
                      <i class="fa fa-exclamation-circle mr-1"></i>
                      Budget Warning! Hanya tersisa {{ formatCurrency(budgetInfo.remaining_after_current) }} setelah input ini.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Category Details for kasbon (without budget info) -->
              <div v-if="form.mode === 'kasbon' && selectedCategoryDetails" class="mt-3">
                <!-- Category Details -->
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                  <div class="space-y-2 text-sm">
                    <div>
                      <span class="font-medium text-blue-800">Division:</span>
                      <span class="text-blue-700 ml-2">{{ selectedCategoryDetails.division }}</span>
                    </div>
                    <div>
                      <span class="font-medium text-blue-800">Subcategory:</span>
                      <span class="text-blue-700 ml-2">{{ selectedCategoryDetails.subcategory }}</span>
                    </div>
                    <div v-if="selectedCategoryDetails.description">
                      <span class="font-medium text-blue-800">Description:</span>
                      <p class="text-blue-700 mt-1">{{ selectedCategoryDetails.description }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Outlet (Hidden for pr_ops, purchase_payment, travel_application, and kasbon mode) -->
            <div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment' && form.mode !== 'travel_application' && form.mode !== 'kasbon'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <select
                v-model="form.outlet_id"
                @change="onOutletChange"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET'" class="mt-1 text-xs text-gray-500">
                Outlet selection is required for per-outlet budget categories
              </p>
            </div>

            <!-- Outlet Display for Kasbon (Read-only) -->
            <div v-if="form.mode === 'kasbon'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
              <input
                type="text"
                :value="getKasbonOutletName()"
                disabled
                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600 cursor-not-allowed"
              />
              <p class="mt-1 text-xs text-gray-500">
                Outlet otomatis dipilih sesuai outlet user yang login
              </p>
            </div>

            <!-- Ticket (Hidden for pr_ops, purchase_payment, travel_application, and kasbon mode) -->
            <div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment' && form.mode !== 'travel_application' && form.mode !== 'kasbon'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Related Ticket</label>
              <select
                v-model="form.ticket_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select Ticket</option>
                <option v-for="ticket in tickets" :key="ticket.id" :value="ticket.id">
                  {{ ticket.ticket_number }} - {{ ticket.title }} ({{ ticket.outlet?.nama_outlet }})
                </option>
              </select>
            </div>

            <!-- Priority -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
              <select
                v-model="form.priority"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="LOW">Low</option>
                <option value="MEDIUM">Medium</option>
                <option value="HIGH">High</option>
                <option value="URGENT">Urgent</option>
              </select>
            </div>

            <!-- Currency (Hidden for pr_ops, purchase_payment, travel_application, and kasbon mode) -->
            <div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment' && form.mode !== 'travel_application' && form.mode !== 'kasbon'">
              <label class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
              <select
                v-model="form.currency"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
              </select>
            </div>
          </div>

          <!-- Items Section (Hidden for kasbon mode) -->
          <div v-if="form.mode !== 'kasbon'" class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Items *</label>
            
            <!-- Multi-Outlet Structure for PR Ops and Payment Application Mode -->
            <div v-if="form.mode === 'pr_ops' || form.mode === 'purchase_payment'" class="space-y-6">
              <!-- Outlet Cards -->
              <div v-for="(outlet, outletIdx) in form.outlets" :key="outletIdx" class="border-2 border-blue-200 rounded-lg p-4 bg-blue-50">
                <!-- Outlet Header -->
                <div class="flex items-center justify-between mb-4">
                  <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Outlet *</label>
                    <select
                      v-model="outlet.outlet_id"
                      required
                      class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="">Select Outlet</option>
                      <option v-for="outletOption in outlets" :key="outletOption.id_outlet" :value="outletOption.id_outlet">
                        {{ outletOption.nama_outlet }}
                      </option>
                    </select>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-blue-800">
                      Total: {{ formatCurrency(getOutletTotal(outletIdx)) }}
                    </span>
                    <button
                      type="button"
                      @click="removeOutlet(outletIdx)"
                      :disabled="form.outlets.length === 1"
                      class="text-red-500 hover:text-red-700 disabled:opacity-50"
                      title="Remove Outlet"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>

                <!-- Outlet Attachments Section -->
                <div class="mb-4 ml-4 p-3 bg-white border border-blue-300 rounded-lg">
                  <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700">Attachments</label>
                    <span class="text-xs text-gray-500">
                      {{ getOutletAttachments(outlet.outlet_id).length }} file(s)
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <input
                      type="file"
                      :ref="el => { if (el) fileInputs[`outlet-${outletIdx}`] = el }"
                      @change="(e) => handleOutletFileUpload(e, outlet.outlet_id)"
                      multiple
                      class="hidden"
                      :id="`file-upload-outlet-${outletIdx}`"
                      accept="*/*"
                      :disabled="!outlet.outlet_id"
                    />
                    <button
                      type="button"
                      @click="outlet.outlet_id ? fileInputs[`outlet-${outletIdx}`]?.click() : alert('Please select an outlet first')"
                      :disabled="!outlet.outlet_id"
                      :class="[
                        'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-1',
                        outlet.outlet_id 
                          ? 'text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500' 
                          : 'text-gray-500 bg-gray-300 cursor-not-allowed'
                      ]"
                    >
                      <i class="fa fa-upload mr-1 text-xs"></i>
                      Upload Files
                    </button>
                    <p v-if="!outlet.outlet_id" class="text-xs text-red-600">
                      Select outlet first
                    </p>
                  </div>
                  
                  <!-- Uploaded Files List for this Outlet -->
                  <div v-if="getOutletAttachments(outlet.outlet_id).length > 0" class="mt-2 space-y-1">
                    <div
                      v-for="(attachment, index) in getOutletAttachments(outlet.outlet_id)"
                      :key="attachment.temp_id || index"
                      class="flex items-center justify-between p-1.5 bg-gray-50 border border-gray-200 rounded text-xs"
                    >
                      <div class="flex items-center space-x-1.5 flex-1 min-w-0">
                        <i :class="getFileIcon(attachment.file_name)" class="text-xs flex-shrink-0"></i>
                        <span class="text-xs font-medium text-gray-900 truncate">{{ attachment.file_name }}</span>
                        <span class="text-xs text-gray-500 flex-shrink-0">({{ formatFileSize(attachment.file_size) }})</span>
                      </div>
                      <button
                        type="button"
                        @click="removeOutletAttachment(outlet.outlet_id, index)"
                        class="text-red-600 hover:text-red-800 ml-2 flex-shrink-0"
                        title="Remove"
                      >
                        <i class="fa fa-trash text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Categories per Outlet -->
                <div class="space-y-4 ml-4">
                  <div v-for="(category, categoryIdx) in outlet.categories" :key="categoryIdx" class="border border-green-200 rounded-lg p-4 bg-green-50">
                    <!-- Category Header -->
                    <div class="flex items-center justify-between mb-3">
                      <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <div class="relative category-dropdown-container">
                          <div
                            @click="showCategoryDropdown = !showCategoryDropdown"
                            class="w-full md:w-64 px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer bg-white flex items-center justify-between"
                            :class="{ 'ring-2 ring-blue-500': showCategoryDropdown }"
                          >
                            <span v-if="category.category_id" class="text-gray-700">
                              {{ getCategoryDisplayName(category.category_id) }}
                            </span>
                            <span v-else class="text-gray-400">Select Category</span>
                            <i class="fa fa-chevron-down text-gray-400" :class="{ 'transform rotate-180': showCategoryDropdown }"></i>
                          </div>
                          <div
                            v-if="showCategoryDropdown"
                            class="absolute z-10 w-full md:w-64 mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
                            @click.stop
                          >
                            <input
                              v-model="categorySearch"
                              type="text"
                              placeholder="Cari category..."
                              class="w-full px-3 py-2 border-b border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                              @click.stop
                            />
                            <div class="max-h-60 overflow-auto">
                              <div
                                v-if="filteredCategories.length === 0"
                                class="px-3 py-2 text-sm text-gray-500"
                              >
                                Tidak ada category yang ditemukan
                              </div>
                              <div
                                v-for="cat in filteredCategories"
                                :key="cat.id"
                                @click="category.category_id = cat.id; loadBudgetInfoForCategory(outletIdx, categoryIdx, outlet.outlet_id, cat.id); showCategoryDropdown = false; categorySearch = ''"
                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm"
                                :class="{ 'bg-blue-100': category.category_id == cat.id }"
                              >
                                [{{ cat.division }}] {{ cat.name }}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-green-800">
                          Total: {{ formatCurrency(getCategoryTotal(outletIdx, categoryIdx)) }}
                        </span>
                        <button
                          type="button"
                          @click="removeCategory(outletIdx, categoryIdx)"
                          :disabled="outlet.categories.length === 1"
                          class="text-red-500 hover:text-red-700 disabled:opacity-50"
                          title="Remove Category"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </div>
                    </div>

                    <!-- Category Details & Budget Info -->
                    <div v-if="getCategoryDetails(outletIdx, categoryIdx)" class="mb-3">
                      <!-- Category Details -->
                      <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg mb-2">
                        <div class="space-y-1 text-xs">
                          <div>
                            <span class="font-medium text-blue-800">Division:</span>
                            <span class="text-blue-700 ml-2">{{ getCategoryDetails(outletIdx, categoryIdx).division }}</span>
                          </div>
                          <div>
                            <span class="font-medium text-blue-800">Subcategory:</span>
                            <span class="text-blue-700 ml-2">{{ getCategoryDetails(outletIdx, categoryIdx).subcategory }}</span>
                          </div>
                          <div>
                            <span class="font-medium text-blue-800">Budget Limit:</span>
                            <span class="text-blue-700 ml-2">{{ formatCurrency(getCategoryDetails(outletIdx, categoryIdx).budget_limit) }}</span>
                          </div>
                          <div v-if="getCategoryDetails(outletIdx, categoryIdx).description">
                            <span class="font-medium text-blue-800">Description:</span>
                            <p class="text-blue-700 mt-1">{{ getCategoryDetails(outletIdx, categoryIdx).description }}</p>
                          </div>
                        </div>
                      </div>

                      <!-- Budget Info -->
                      <div v-if="getCategoryBudgetInfo(outletIdx, categoryIdx)" class="p-3 bg-green-50 border border-green-200 rounded-lg">
                        <h4 class="text-sm font-semibold text-green-800 mb-2">
                          Budget Information
                          <span class="text-xs font-normal text-green-600 ml-2">
                            ({{ getCategoryBudgetInfo(outletIdx, categoryIdx).budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
                          </span>
                        </h4>
                        
                        <!-- Global Budget Info -->
                        <div v-if="getCategoryBudgetInfo(outletIdx, categoryIdx).budget_type === 'GLOBAL'" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                          <div>
                            <p class="text-green-600">Category Budget</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).category_budget) }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Used This Month</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).category_used_amount) }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Current Input</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).current_amount) }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Total After Input</p>
                            <p class="font-semibold" :class="getCategoryBudgetInfo(outletIdx, categoryIdx).exceeds_budget ? 'text-red-800' : 'text-green-800'">
                              {{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).total_with_current) }}
                            </p>
                          </div>
                        </div>

                        <!-- Per Outlet Budget Info -->
                        <div v-else-if="getCategoryBudgetInfo(outletIdx, categoryIdx).budget_type === 'PER_OUTLET'" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                          <div>
                            <p class="text-green-600">Outlet Budget</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).outlet_budget) }}</p>
                            <p class="text-xs text-gray-500">{{ getCategoryBudgetInfo(outletIdx, categoryIdx).outlet_info?.name || 'Unknown' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Used This Month</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).outlet_used_amount) }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Current Input</p>
                            <p class="font-semibold text-green-800">{{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).current_amount) }}</p>
                          </div>
                          <div>
                            <p class="text-green-600">Total After Input</p>
                            <p class="font-semibold" :class="getCategoryBudgetInfo(outletIdx, categoryIdx).exceeds_budget ? 'text-red-800' : 'text-green-800'">
                              {{ formatCurrency(getCategoryBudgetInfo(outletIdx, categoryIdx).total_with_current) }}
                            </p>
                          </div>
                        </div>

                        <!-- Warning if exceeds budget -->
                        <div v-if="getCategoryBudgetInfo(outletIdx, categoryIdx).exceeds_budget" class="mt-2 p-2 bg-red-50 border border-red-300 rounded">
                          <p class="text-xs text-red-800 font-semibold">
                            <i class="fa fa-exclamation-triangle mr-1"></i>
                            Budget Exceeded! Total melebihi budget yang tersedia.
                          </p>
                        </div>
                      </div>
                    </div>

                    <!-- Items per Category -->
                    <div class="ml-4">
                      <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                          <thead class="bg-gray-50">
                            <tr>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                              <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                              <th class="px-3 py-2"></th>
                            </tr>
                          </thead>
                          <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(item, itemIdx) in category.items" :key="itemIdx">
                              <td class="px-3 py-2 min-w-[200px]">
                                <input
                                  type="text"
                                  v-model="item.item_name"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  required
                                  placeholder="Enter item name..."
                                />
                              </td>
                              <td class="px-3 py-2 min-w-[100px]">
                                <input 
                                  type="number" 
                                  min="0.01" 
                                  step="0.01" 
                                  v-model.number="item.qty" 
                                  @input="calculateSubtotalForCategory(outletIdx, categoryIdx, itemIdx)" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  required 
                                />
                              </td>
                              <td class="px-3 py-2 min-w-[100px]">
                                <input 
                                  type="text" 
                                  v-model="item.unit" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  required 
                                  placeholder="pcs, kg, etc" 
                                />
                              </td>
                              <td class="px-3 py-2 min-w-[150px]">
                                <input 
                                  type="number" 
                                  min="0" 
                                  step="0.01" 
                                  v-model.number="item.unit_price" 
                                  @input="calculateSubtotalForCategory(outletIdx, categoryIdx, itemIdx)" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  required 
                                />
                              </td>
                              <td class="px-3 py-2 min-w-[150px] text-right">
                                {{ formatCurrency(item.subtotal) }}
                              </td>
                              <td class="px-3 py-2">
                                <button 
                                  type="button" 
                                  @click="removeItemFromCategory(outletIdx, categoryIdx, itemIdx)" 
                                  class="text-red-500 hover:text-red-700" 
                                  :disabled="category.items.length === 1"
                                >
                                  <i class="fa-solid fa-trash"></i>
                                </button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div class="mt-2">
                        <button type="button" @click="addItemToCategory(outletIdx, categoryIdx)" class="text-blue-500 hover:text-blue-700">
                          <i class="fa fa-plus mr-1"></i> Add Item
                        </button>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Add Category Button -->
                  <div class="ml-4">
                    <button type="button" @click="addCategory(outletIdx)" class="text-green-500 hover:text-green-700">
                      <i class="fa fa-plus mr-1"></i> Add Category
                    </button>
                  </div>
                </div>
              </div>

              <!-- Add Outlet Button -->
              <div>
                <button type="button" @click="addOutlet" class="text-blue-500 hover:text-blue-700 font-semibold">
                  <i class="fa fa-plus mr-1"></i> Add Outlet
                </button>
              </div>

              <!-- Grand Total -->
              <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                <div class="flex justify-between items-center">
                  <span class="text-lg font-bold text-gray-900">Grand Total:</span>
                  <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(totalAmount) }}</span>
                </div>
              </div>
            </div>

            <!-- Travel Application Structure -->
            <div v-else-if="form.mode === 'travel_application'" class="space-y-6">
              <!-- Destination Outlets -->
              <div class="border-2 border-purple-200 rounded-lg p-4 bg-purple-50">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-purple-800">
                    <i class="fa fa-map-marker-alt mr-2"></i>
                    Outlet Tujuan Perjalanan Dinas
                  </h3>
                  <button
                    type="button"
                    @click="addTravelOutlet"
                    class="text-purple-600 hover:text-purple-700 font-semibold"
                  >
                    <i class="fa fa-plus mr-1"></i> Add Outlet
                  </button>
                </div>
                
                <div class="space-y-3">
                  <div v-for="(outlet, outletIdx) in form.travel_outlets" :key="outletIdx" class="flex items-center gap-3">
                    <select
                      v-model="outlet.outlet_id"
                      required
                      @change="loadBudgetInfoForTravel(outlet.outlet_id)"
                      class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                    >
                      <option value="">Select Outlet Tujuan</option>
                      <option v-for="outletOption in outlets" :key="outletOption.id_outlet" :value="outletOption.id_outlet">
                        {{ outletOption.nama_outlet }}
                      </option>
                    </select>
                    <button
                      type="button"
                      @click="removeTravelOutlet(outletIdx)"
                      :disabled="form.travel_outlets.length === 1"
                      class="text-red-500 hover:text-red-700 disabled:opacity-50"
                      title="Remove Outlet"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Travel Items -->
              <div class="border-2 border-purple-200 rounded-lg p-4 bg-purple-50">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-lg font-semibold text-purple-800">
                    <i class="fa fa-list mr-2"></i>
                    Items Perjalanan
                  </h3>
                  <button
                    type="button"
                    @click="addTravelItem"
                    class="text-purple-600 hover:text-purple-700 font-semibold"
                  >
                    <i class="fa fa-plus mr-1"></i> Add Item
                  </button>
                </div>

                <div class="space-y-4">
                  <div v-for="(item, itemIdx) in form.travel_items" :key="itemIdx" class="border border-purple-200 rounded-lg p-4 bg-white">
                    <!-- Item Type -->
                    <div class="mb-4">
                      <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Item *</label>
                      <select
                        v-model="item.item_type"
                        required
                        class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                      >
                        <option value="transport">Transport</option>
                        <option value="allowance">Allowance</option>
                        <option value="others">Others</option>
                      </select>
                    </div>

                    <!-- Allowance Fields -->
                    <div v-if="item.item_type === 'allowance'" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Penerima Allowance *</label>
                        <input
                          type="text"
                          v-model="item.allowance_recipient_name"
                          required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="Nama penerima allowance..."
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">No. Rekening *</label>
                        <input
                          type="text"
                          v-model="item.allowance_account_number"
                          required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="Nomor rekening..."
                        />
                      </div>
                    </div>

                    <!-- Others Notes -->
                    <div v-if="item.item_type === 'others'" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                      <label class="block text-sm font-medium text-gray-700 mb-2">Notes Others *</label>
                      <textarea
                        v-model="item.others_notes"
                        required
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Masukkan notes untuk item others..."
                      ></textarea>
                    </div>

                    <!-- Item Details -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Qty *</label>
                        <input
                          type="number"
                          min="0.01"
                          step="0.01"
                          v-model.number="item.qty"
                          @input="calculateTravelItemSubtotal(itemIdx)"
                          required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit *</label>
                        <input
                          type="text"
                          v-model="item.unit"
                          required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder="pcs, hari, etc"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Unit Price *</label>
                        <input
                          type="number"
                          min="0"
                          step="0.01"
                          v-model.number="item.unit_price"
                          @input="calculateTravelItemSubtotal(itemIdx)"
                          required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subtotal</label>
                        <div class="px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-right font-semibold">
                          {{ formatCurrency(item.subtotal) }}
                        </div>
                      </div>
                    </div>

                    <!-- Remove Item Button -->
                    <div class="mt-3 flex justify-end">
                      <button
                        type="button"
                        @click="removeTravelItem(itemIdx)"
                        :disabled="form.travel_items.length === 1"
                        class="text-red-500 hover:text-red-700 disabled:opacity-50"
                        title="Remove Item"
                      >
                        <i class="fa-solid fa-trash mr-1"></i> Remove Item
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Total -->
                <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                  <div class="flex justify-between items-center">
                    <span class="text-lg font-bold text-gray-900">Total:</span>
                    <span class="text-2xl font-bold text-purple-600">{{ formatCurrency(totalAmount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Simple Items Structure for Other Modes (Kasbon) -->
            <div v-else>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item Name</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                      <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                      <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                      <th class="px-3 py-2"></th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(item, idx) in form.items" :key="idx">
                      <td class="px-3 py-2 min-w-[200px]">
                        <input
                          type="text"
                          v-model="item.item_name"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          required
                          placeholder="Enter item name..."
                        />
                      </td>
                      <td class="px-3 py-2 min-w-[100px]">
                        <input 
                          type="number" 
                          min="0.01" 
                          step="0.01" 
                          v-model.number="item.qty" 
                          @input="calculateSubtotal(idx)" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                          required 
                        />
                      </td>
                      <td class="px-3 py-2 min-w-[100px]">
                        <input 
                          type="text" 
                          v-model="item.unit" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                          required 
                          placeholder="pcs, kg, etc" 
                        />
                      </td>
                      <td class="px-3 py-2 min-w-[150px]">
                        <input 
                          type="number" 
                          min="0" 
                          step="0.01" 
                          v-model.number="item.unit_price" 
                          @input="calculateSubtotal(idx)" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                          required 
                        />
                      </td>
                      <td class="px-3 py-2 min-w-[150px] text-right">
                        {{ formatCurrency(item.subtotal) }}
                      </td>
                      <td class="px-3 py-2">
                        <button 
                          type="button" 
                          @click="removeItem(idx)" 
                          class="text-red-500 hover:text-red-700" 
                          :disabled="form.items.length === 1"
                        >
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="4" class="px-3 py-2 text-right font-bold">Total Amount:</td>
                      <td class="px-3 py-2 text-right font-bold">{{ formatCurrency(totalAmount) }}</td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div class="mt-2">
                <button type="button" @click="addItem" class="text-blue-500 hover:text-blue-700">
                  <i class="fa fa-plus mr-1"></i> Add Item
                </button>
              </div>
            </div>
          </div>

          <!-- Description / Travel Agenda -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              {{ form.mode === 'travel_application' ? 'Agenda Kerja *' : 'Description' }}
            </label>
            
            <!-- Note for Travel Application -->
            <div v-if="form.mode === 'travel_application'" class="mb-3 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <i class="fa fa-info-circle text-yellow-600 text-lg mt-0.5"></i>
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-yellow-800">
                    <strong>Catatan Penting:</strong>
                  </p>
                  <p class="text-sm text-yellow-700 mt-1">
                    Wajib mencantumkan <strong>tanggal keberangkatan</strong> dan <strong>tanggal pulang</strong> dalam agenda kerja.
                  </p>
                </div>
              </div>
            </div>
            
            <!-- For travel_application: Agenda Kerja -->
            <textarea
              v-if="form.mode === 'travel_application'"
              v-model="form.travel_agenda"
              rows="8"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Masukkan agenda kerja perjalanan dinas (bisa sangat panjang)..."
            ></textarea>
            
            <!-- For other modes: Description -->
            <textarea
              v-else
              v-model="form.description"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter description..."
            ></textarea>
            
            <p v-if="form.mode === 'travel_application'" class="mt-1 text-xs text-gray-500">
              Jelaskan secara detail agenda kerja yang akan dilakukan selama perjalanan dinas
            </p>
          </div>

          <!-- Travel Notes -->
          <div v-if="form.mode === 'travel_application'" class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea
              v-model="form.travel_notes"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Masukkan catatan tambahan jika diperlukan..."
            ></textarea>
          </div>

          <!-- Kasbon Section -->
          <div v-if="form.mode === 'kasbon'" class="mb-6 space-y-4">
            <!-- Periode Kasbon Info -->
            <div class="p-3 bg-blue-50 border border-blue-300 rounded-lg">
              <h4 class="text-sm font-semibold text-blue-800 mb-2">
                <i class="fa fa-calendar mr-2"></i>
                Periode Kasbon
              </h4>
              <p class="text-sm text-blue-700">
                <strong>Periode Aktif:</strong> {{ getKasbonPeriodText() }}
              </p>
              <p class="text-xs text-blue-600 mt-1">
                <i class="fa fa-info-circle mr-1"></i>
                Periode kasbon: Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
              </p>
              <p v-if="userOutletId && userOutletId != 1" class="text-xs text-amber-600 mt-2 font-semibold">
                <i class="fa fa-exclamation-triangle mr-1"></i>
                Per periode hanya diijinkan 1 user saja per outlet. Jika sudah ada user lain yang mengajukan kasbon di periode ini, pengajuan akan ditolak.
              </p>
            </div>

            <!-- Nilai Kasbon -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nilai Kasbon *</label>
              <select
                v-model.number="form.kasbon_amount"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="0">Pilih nilai kasbon</option>
                <option
                  v-for="amountOption in kasbonAmountOptions"
                  :key="amountOption"
                  :value="amountOption"
                >
                  {{ formatCurrency(amountOption) }}
                </option>
              </select>
              <p class="mt-1 text-xs text-gray-500">
                Total: {{ formatCurrency(form.kasbon_amount || 0) }}
              </p>
            </div>

            <!-- Termin Kasbon -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Termin *</label>
              <select
                v-model.number="form.kasbon_termin"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="0">Pilih termin</option>
                <option v-for="terminOption in kasbonTerminOptions" :key="terminOption" :value="terminOption">
                  {{ terminOption }}x Termin
                </option>
              </select>
            </div>

            <!-- Reason Kasbon -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reason / Alasan Kasbon *</label>
              <textarea
                v-model="form.kasbon_reason"
                rows="6"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Masukkan alasan atau tujuan penggunaan kasbon..."
              ></textarea>
              <p class="mt-1 text-xs text-gray-500">
                Jelaskan secara detail alasan dan tujuan penggunaan kasbon, termasuk rencana pelunasan
              </p>
            </div>

            <!-- Info Box -->
            <div class="p-3 bg-blue-50 border border-blue-300 rounded-lg">
              <p class="text-sm font-medium text-blue-800">
                <i class="fa fa-info-circle mr-2"></i>
                Untuk pengajuan kasbon/uang muka, pastikan informasi lengkap termasuk tujuan penggunaan dan rencana pelunasan
              </p>
            </div>
          </div>

          <!-- Attachments Section (Only for non-pr_ops and non-purchase_payment modes) -->
          <div v-if="form.mode !== 'pr_ops' && form.mode !== 'purchase_payment'" class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Attachments</label>
            
            <!-- Simple Attachments for Other Modes -->
            <div>
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                <div class="text-center">
                  <input
                    ref="fileInput"
                    type="file"
                    multiple
                    @change="handleFileUpload"
                    class="hidden"
                    accept="*/*"
                  />
                  <button
                    type="button"
                    @click="$refs.fileInput?.click()"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                  >
                    <i class="fa fa-upload mr-2"></i>
                    Upload Files
                  </button>
                  <p class="mt-2 text-sm text-gray-500">
                    Upload any file type (Max 10MB per file)
                  </p>
                </div>
              </div>

              <!-- Uploaded Files List -->
              <div v-if="attachments.length > 0" class="mt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Uploaded Files:</h4>
                <div class="space-y-2">
                  <div
                    v-for="(attachment, index) in attachments"
                    :key="index"
                    class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
                  >
                    <div class="flex items-center space-x-3">
                      <i :class="getFileIcon(attachment.file_name)" class="text-lg"></i>
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ attachment.file_name }}</p>
                        <p class="text-xs text-gray-500">{{ formatFileSize(attachment.file_size) }}</p>
                      </div>
                    </div>
                    <div class="flex items-center space-x-2">
                      <button
                        type="button"
                        @click="downloadFile(attachment)"
                        class="text-blue-600 hover:text-blue-800"
                        title="Download"
                      >
                        <i class="fa fa-download"></i>
                      </button>
                      <button
                        type="button"
                        @click="removeAttachment(index)"
                        class="text-red-600 hover:text-red-800"
                        title="Remove"
                      >
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Approval Flow Section -->
          <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Approval Flow</h3>
            <p class="text-sm text-gray-600 mb-4">Add approvers in order from lowest to highest level. The first approver will be the lowest level, and the last approver will be the highest level.</p>
            
            <!-- Warning for Kasbon Mode -->
            <div v-if="form.mode === 'kasbon'" class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded-lg">
              <p class="text-sm font-medium text-yellow-800 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                Wajib sertakan GM Finance sebagai Approver
              </p>
              <p class="text-xs text-yellow-700">
                Untuk pengajuan kasbon, pastikan GM Finance sudah ditambahkan dalam daftar approvers.
              </p>
            </div>
            
            <!-- Warning for Payment Application Mode -->
            <div v-if="form.mode === 'purchase_payment'" class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded-lg">
              <p class="text-sm font-medium text-yellow-800 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                Untuk Payment Application wajib sertakan GM Finance sebagai Approver
              </p>
            </div>
            
            <!-- Warning for Travel Application Mode -->
            <div v-if="form.mode === 'travel_application'" class="mb-4 p-3 bg-yellow-50 border border-yellow-300 rounded-lg">
              <p class="text-sm font-medium text-yellow-800 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                Untuk Travel Application wajib menyertakan GA Supervisor sebagai Approver pertama
              </p>
              <p class="text-sm font-medium text-yellow-800 mb-2">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                Wajib sertakan GM Finance sebagai Approver
              </p>
              <p class="text-xs text-yellow-700 ml-6">
                Contoh urutan approver: GA Supervisor → GM HR → GM Finance → BOD
              </p>
            </div>
            
            <!-- Warning for Kasbon Mode -->
            <div v-if="form.mode === 'kasbon'" class="mb-4 p-3 bg-blue-50 border border-blue-300 rounded-lg">
              <p class="text-sm font-medium text-blue-800 mb-2">
                <i class="fa fa-info-circle mr-2"></i>
                Untuk Kasbon disarankan menyertakan GM Finance sebagai Approver
              </p>
            </div>
            
            <!-- Add Approver Input -->
            <div class="mb-4">
              <div class="relative">
                <input
                  v-model="approverSearch"
                  type="text"
                  placeholder="Search users by name, email, or jabatan..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                />
                
                <!-- Dropdown Results -->
                <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                  <div
                    v-for="user in approverResults"
                    :key="user.id"
                    @click="addApprover(user)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0"
                  >
                    <div class="font-medium">{{ user.name }}</div>
                    <div class="text-sm text-gray-600">{{ user.email }}</div>
                    <div v-if="user.jabatan" class="text-xs text-blue-600 font-medium">{{ user.jabatan }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="form.approvers && form.approvers.length > 0" class="space-y-2">
              <h4 class="font-medium text-gray-700">Approval Order (Lowest to Highest):</h4>
              
              <!-- Warning if GA Supervisor not first or GM Finance missing -->
              <div v-if="(form.mode === 'kasbon' && !hasGMFinance) || (form.mode === 'purchase_payment' && !hasGMFinance) || (form.mode === 'travel_application' && (!hasGMFinance || !hasGASupervisorAsFirst))" class="mb-2 p-2 bg-red-50 border border-red-300 rounded-md">
                <p v-if="form.mode === 'kasbon' && !hasGMFinance" class="text-xs text-red-800 mb-1">
                  <i class="fa fa-exclamation-circle mr-1"></i>
                  GM Finance wajib disertakan sebagai Approver untuk Kasbon
                </p>
                <p v-if="form.mode === 'purchase_payment' && !hasGMFinance" class="text-xs text-red-800 mb-1">
                  <i class="fa fa-exclamation-circle mr-1"></i>
                  GM Finance wajib disertakan sebagai Approver untuk Payment Application
                </p>
                <p v-if="form.mode === 'travel_application' && !hasGMFinance" class="text-xs text-red-800 mb-1">
                  <i class="fa fa-exclamation-circle mr-1"></i>
                  GM Finance wajib disertakan sebagai Approver untuk Travel Application
                </p>
                <p v-if="form.mode === 'travel_application' && !hasGASupervisorAsFirst" class="text-xs text-red-800">
                  <i class="fa fa-exclamation-circle mr-1"></i>
                  GA Supervisor harus menjadi approver pertama untuk Travel Application
                </p>
              </div>
              
              <div
                v-for="(approver, index) in form.approvers"
                :key="approver.id || index"
                :class="[
                  'flex items-center justify-between p-3 rounded-md',
                  (form.mode === 'travel_application' && index === 0 && isGASupervisor(approver)) || 
                  ((form.mode === 'kasbon' || form.mode === 'purchase_payment' || form.mode === 'travel_application') && isGMFinance(approver))
                    ? 'bg-green-50 border-2 border-green-300' 
                    : 'bg-gray-50 border border-gray-200'
                ]"
              >
                <div class="flex items-center space-x-3">
                  <div class="flex items-center space-x-2">
                    <button
                      v-if="index > 0"
                      @click="reorderApprover(index, index - 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Up"
                    >
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button
                      v-if="index < form.approvers.length - 1"
                      @click="reorderApprover(index, index + 1)"
                      class="p-1 text-gray-500 hover:text-gray-700"
                      title="Move Down"
                    >
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                      Level {{ index + 1 }}
                    </span>
                    <div>
                      <div class="font-medium">{{ approver.name }}</div>
                      <div class="text-sm text-gray-600">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                    <span v-if="form.mode === 'travel_application' && index === 0 && isGASupervisor(approver)" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      <i class="fa fa-check-circle mr-1"></i>
                      GA Supervisor
                    </span>
                    <span v-if="(form.mode === 'kasbon' || form.mode === 'purchase_payment' || form.mode === 'travel_application') && isGMFinance(approver)" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      <i class="fa fa-check-circle mr-1"></i>
                      GM Finance
                    </span>
                  </div>
                </div>
                <button
                  @click="removeApprover(index)"
                  :disabled="form.mode === 'travel_application' && index === 0 && isGASupervisor(approver)"
                  :class="[
                    'p-1',
                    form.mode === 'travel_application' && index === 0 && isGASupervisor(approver)
                      ? 'text-gray-400 cursor-not-allowed'
                      : 'text-red-500 hover:text-red-700'
                  ]"
                  :title="form.mode === 'travel_application' && index === 0 && isGASupervisor(approver) ? 'GA Supervisor tidak dapat dihapus' : 'Remove Approver'"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Budget Info -->
          <div v-if="budgetInfo" class="mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h3 class="text-lg font-medium text-blue-800 mb-2">
                {{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Outlet Budget Information' : 'Category Budget Information' }}
                <span class="text-sm font-normal text-blue-600 ml-2">
                  ({{ budgetInfo.budget_type === 'PER_OUTLET' ? 'Per Outlet' : 'Global' }})
                </span>
              </h3>
              
              <!-- Global Budget Info -->
              <div v-if="budgetInfo.budget_type === 'GLOBAL'" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                  <p class="text-sm text-blue-600">Category Budget</p>
                  <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_budget) }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Used This Month</p>
                  <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.category_used_amount) }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Current Input</p>
                  <p class="text-lg font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Total After Input</p>
                  <p class="text-lg font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-blue-800'">
                    {{ formatCurrency(budgetInfo.total_with_current) }}
                  </p>
                </div>
              </div>

              <!-- Per Outlet Budget Info -->
              <div v-else-if="budgetInfo.budget_type === 'PER_OUTLET'" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                  <p class="text-sm text-blue-600">Outlet Budget</p>
                  <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.outlet_budget) }}</p>
                  <p class="text-xs text-gray-500">{{ budgetInfo.outlet_info?.name || 'Unknown Outlet' }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Used This Month</p>
                  <p class="text-lg font-semibold text-blue-800">{{ formatCurrency(budgetInfo.outlet_used_amount) }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Current Input</p>
                  <p class="text-lg font-semibold text-green-800">{{ formatCurrency(budgetInfo.current_amount) }}</p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Total After Input</p>
                  <p class="text-lg font-semibold" :class="budgetInfo.exceeds_budget ? 'text-red-800' : 'text-blue-800'">
                    {{ formatCurrency(budgetInfo.total_with_current) }}
                  </p>
                </div>
              </div>
              
              <!-- Remaining Budget -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <p class="text-sm text-blue-600">Remaining Before Input</p>
                  <p class="text-lg font-semibold text-blue-800">
                    {{ formatCurrency(budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_remaining_amount : budgetInfo.category_remaining_amount) }}
                  </p>
                </div>
                <div>
                  <p class="text-sm text-blue-600">Remaining After Input</p>
                  <p class="text-lg font-semibold" :class="budgetInfo.remaining_after_current < 0 ? 'text-red-800' : 'text-blue-800'">
                    {{ formatCurrency(budgetInfo.remaining_after_current) }}
                  </p>
                </div>
              </div>
              
              <!-- Warning Messages -->
              <div v-if="budgetInfo.exceeds_budget" class="mt-4 p-3 bg-red-100 border border-red-300 rounded text-red-800 text-sm">
                <i class="fa fa-exclamation-triangle mr-2"></i>
                <strong>Budget Exceeded!</strong> 
                <span v-if="budgetInfo.budget_type === 'PER_OUTLET'">
                  Total amount ({{ formatCurrency(budgetInfo.total_with_current) }}) exceeds outlet budget limit ({{ formatCurrency(budgetInfo.outlet_budget) }}) for this month.
                </span>
                <span v-else>
                  Total amount ({{ formatCurrency(budgetInfo.total_with_current) }}) exceeds category budget limit ({{ formatCurrency(budgetInfo.category_budget) }}) for this month.
                </span>
                You cannot save this payment.
              </div>
              <div v-else-if="budgetInfo.remaining_after_current < ((budgetInfo.budget_type === 'PER_OUTLET' ? budgetInfo.outlet_budget : budgetInfo.category_budget) * 0.1)" class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded text-yellow-800 text-sm">
                <i class="fa fa-exclamation-circle mr-2"></i>
                <strong>Budget Warning!</strong> Only {{ formatCurrency(budgetInfo.remaining_after_current) }} remaining after this input.
              </div>
            </div>
          </div>

          <!-- Budget Info Message for PER_OUTLET without outlet selection -->
          <div v-if="!budgetInfo && selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id" class="mb-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <h3 class="text-lg font-medium text-yellow-800 mb-2">
                <i class="fa fa-info-circle mr-2"></i>
                Outlet Selection Required
              </h3>
              <p class="text-yellow-700">
                This category uses per-outlet budget allocation. Please select an outlet above to view the budget information.
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-3">
            <Link
              :href="'/purchase-requisitions'"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="loading || isBudgetExceeded || (selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id)"
              class="px-4 py-2 text-white rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
              :class="isBudgetExceeded ? 'bg-red-600 hover:bg-red-700' : (selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id) ? 'bg-gray-400' : 'bg-blue-600 hover:bg-blue-700'"
            >
              <span v-if="loading">Updating...</span>
              <span v-else-if="isBudgetExceeded">Budget Exceeded - Cannot Save</span>
              <span v-else-if="selectedCategoryDetails && selectedCategoryDetails.budget_type === 'PER_OUTLET' && !form.outlet_id">Select Outlet First</span>
              <span v-else>Update Payment</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Tutorial Modal -->
    <div v-if="showTutorial" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showTutorial = false">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showTutorial = false"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
          <!-- Header -->
          <div class="bg-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
              <i class="fa fa-graduation-cap mr-2"></i>
              Tutorial: Panduan Purchase Requisition
            </h3>
            <button
              @click="showTutorial = false"
              class="text-white hover:text-gray-200 focus:outline-none"
            >
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <!-- Tab Navigation -->
          <div class="bg-gray-100 border-b border-gray-200 px-6">
            <div class="flex space-x-1 overflow-x-auto">
              <button
                @click="activeTutorialTab = 'pr_ops'"
                :class="[
                  'px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                  activeTutorialTab === 'pr_ops'
                    ? 'border-blue-600 text-blue-600 bg-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-shopping-cart mr-2"></i>
                Purchase Requisition
              </button>
              <button
                @click="activeTutorialTab = 'purchase_payment'"
                :class="[
                  'px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                  activeTutorialTab === 'purchase_payment'
                    ? 'border-blue-600 text-blue-600 bg-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-money-bill-wave mr-2"></i>
                Payment Application
              </button>
              <button
                @click="activeTutorialTab = 'travel_application'"
                :class="[
                  'px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                  activeTutorialTab === 'travel_application'
                    ? 'border-blue-600 text-blue-600 bg-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-plane mr-2"></i>
                Travel Application
              </button>
              <button
                @click="activeTutorialTab = 'kasbon'"
                :class="[
                  'px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                  activeTutorialTab === 'kasbon'
                    ? 'border-blue-600 text-blue-600 bg-white'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-wallet mr-2"></i>
                Kasbon
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="bg-white px-6 py-4 max-h-[70vh] overflow-y-auto">
            <!-- Mode Selection Guide (shown in all tabs) -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-6">
              <h4 class="font-semibold text-blue-800 mb-3">
                <i class="fa fa-info-circle mr-2"></i>
                Panduan Pemilihan Mode:
              </h4>
              <ul class="text-sm text-blue-700 space-y-2 list-none">
                <li class="flex items-start">
                  <span class="font-semibold text-blue-800 mr-2">•</span>
                  <div>
                    <strong>Purchase Requisition:</strong> Gunakan untuk pengajuan pembelian barang/jasa yang memerlukan proses pembelian melalui Purchase Order (PO)
                  </div>
                </li>
                <li class="flex items-start">
                  <span class="font-semibold text-blue-800 mr-2">•</span>
                  <div>
                    <strong>Payment Application:</strong> Gunakan untuk pengajuan Reimbursement dan pembayaran lainnya yang tidak memerlukan proses pembelian
                  </div>
                </li>
                <li class="flex items-start">
                  <span class="font-semibold text-blue-800 mr-2">•</span>
                  <div>
                    <strong>Travel Application:</strong> Gunakan untuk pengajuan perjalanan dinas dengan approval khusus GA Supervisor sebagai approver pertama
                  </div>
                </li>
                <li class="flex items-start">
                  <span class="font-semibold text-blue-800 mr-2">•</span>
                  <div>
                    <strong>Kasbon:</strong> Gunakan untuk pengajuan kasbon/uang muka
                  </div>
                </li>
              </ul>
            </div>

            <!-- Tab Content: Purchase Requisition (pr_ops) -->
            <div v-if="activeTutorialTab === 'pr_ops'" class="space-y-6">

              <!-- Introduction -->
              <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                <h4 class="font-semibold text-green-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu PR Ops?
                </h4>
                <p class="text-sm text-green-700">
                  Purchase Requisition (PR Ops) memungkinkan Anda membuat satu PR untuk <strong>multiple outlet</strong>, 
                  dengan <strong>multiple category per outlet</strong>, dan <strong>multiple items per category</strong>. 
                  Fitur ini sangat berguna untuk pembelian yang melibatkan beberapa outlet sekaligus.
                </p>
                <p class="text-sm text-green-700 mt-2">
                  <strong>Catatan:</strong> Mode <strong>Purchase Requisition</strong> dan <strong>Payment Application</strong> 
                  menggunakan struktur input yang sama (multi-outlet, multi-category, multi-item).
                </p>
              </div>

              <!-- Step 1 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Informasi Dasar</h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Title:</strong> Masukkan judul PR (wajib)</li>
                      <li><strong>Division:</strong> Pilih divisi yang bertanggung jawab (wajib)</li>
                      <li><strong>Priority:</strong> Pilih prioritas (Low, Medium, High, Urgent)</li>
                      <li><strong>Description:</strong> Tambahkan deskripsi jika diperlukan</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Step 2 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Outlet</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Klik tombol <strong>"Add Outlet"</strong> untuk menambahkan outlet pertama. 
                      Untuk menambah outlet tambahan, klik lagi tombol <strong>"Add Outlet"</strong>.
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Anda bisa membuat PR untuk beberapa outlet sekaligus dalam satu form.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih Outlet</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di setiap card outlet (warna biru), pilih outlet dari dropdown <strong>"Outlet *"</strong>.
                    </p>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Pilih outlet terlebih dahulu sebelum menambah category atau upload attachment.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 4 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    4
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Upload Attachment (Opsional)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Setelah memilih outlet, Anda bisa upload file attachment untuk outlet tersebut:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Klik tombol <strong>"Upload Files"</strong> di section Attachments</li>
                      <li>Pilih file yang ingin di-upload (maksimal 10MB per file)</li>
                      <li>File akan terasosiasi dengan outlet yang dipilih</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Step 5 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    5
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Category</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di dalam setiap outlet, klik tombol <strong>"Add Category"</strong> untuk menambahkan category pertama.
                      Untuk menambah category tambahan, klik lagi tombol <strong>"Add Category"</strong>.
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Satu outlet bisa memiliki beberapa category berbeda.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 6 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    6
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih Category</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di setiap card category (warna hijau), pilih category dari dropdown <strong>"Category *"</strong>.
                    </p>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Category harus dipilih sebelum menambah items.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 7 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    7
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Items</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di dalam setiap category, isi item pertama yang sudah tersedia. Untuk menambah item tambahan, 
                      klik tombol <strong>"Add Item"</strong> di bawah tabel items.
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <p class="text-xs text-gray-600 mb-2">
                        <strong>Isi informasi item:</strong>
                      </p>
                      <ul class="text-xs text-gray-600 space-y-1 list-disc list-inside">
                        <li><strong>Item Name:</strong> Nama barang/jasa</li>
                        <li><strong>Qty:</strong> Jumlah/kuantitas</li>
                        <li><strong>Unit:</strong> Satuan (pcs, kg, liter, dll)</li>
                        <li><strong>Unit Price:</strong> Harga per satuan</li>
                        <li><strong>Subtotal:</strong> Akan dihitung otomatis (Qty × Unit Price)</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 8 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    8
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Approvers</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di section <strong>"Approval Flow"</strong>, tambahkan approvers dengan urutan dari level terendah ke tertinggi:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Ketik nama, email, atau jabatan di search box</li>
                      <li>Pilih approver dari hasil pencarian</li>
                      <li>Urutkan dengan tombol panah (↑ ↓)</li>
                      <li>Minimal harus ada 1 approver</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Step 9 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    9
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Review dan Submit</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Sebelum submit, pastikan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Semua outlet sudah dipilih</li>
                      <li>Semua category sudah dipilih</li>
                      <li>Semua items sudah lengkap (name, qty, unit, price)</li>
                      <li>Approvers sudah ditambahkan</li>
                      <li>Grand Total sudah sesuai</li>
                    </ul>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mt-2">
                      <p class="text-xs text-green-800">
                        <i class="fa fa-check-circle mr-1"></i>
                        <strong>Grand Total</strong> akan menampilkan total keseluruhan dari semua outlet → category → items.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Visual Structure -->
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">
                  <i class="fa fa-sitemap mr-2"></i>
                  Struktur Hierarki PR Ops:
                </h4>
                <div class="text-sm text-gray-700 space-y-2">
                  <div class="ml-4">
                    <div class="font-semibold text-blue-700">📦 Purchase Requisition</div>
                    <div class="ml-4 mt-1">
                      <div class="font-semibold text-blue-600">🏪 Outlet 1</div>
                      <div class="ml-4 mt-1">
                        <div class="font-semibold text-green-600">📁 Category A</div>
                        <div class="ml-4 text-gray-600">
                          • Item 1<br>
                          • Item 2
                        </div>
                        <div class="font-semibold text-green-600 mt-2">📁 Category B</div>
                        <div class="ml-4 text-gray-600">
                          • Item 3
                        </div>
                      </div>
                      <div class="font-semibold text-blue-600 mt-2">🏪 Outlet 2</div>
                      <div class="ml-4 mt-1">
                        <div class="font-semibold text-green-600">📁 Category C</div>
                        <div class="ml-4 text-gray-600">
                          • Item 4<br>
                          • Item 5
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tips -->
              <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <h4 class="font-semibold text-yellow-800 mb-2">
                  <i class="fa fa-lightbulb mr-2"></i>
                  Tips & Best Practices:
                </h4>
                <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                  <li>Gunakan nama item yang jelas dan deskriptif</li>
                  <li>Pastikan harga dan kuantitas sudah benar sebelum submit</li>
                  <li>Upload attachment yang relevan untuk setiap outlet</li>
                  <li>Pilih approvers sesuai dengan alur approval yang berlaku</li>
                  <li>Review Grand Total sebelum submit untuk memastikan akurasi</li>
                </ul>
              </div>
            </div>

            <!-- Tab Content: Payment Application (purchase_payment) -->
            <div v-if="activeTutorialTab === 'purchase_payment'" class="space-y-6">
              <!-- Introduction -->
              <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                <h4 class="font-semibold text-green-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu Payment Application?
                </h4>
                <p class="text-sm text-green-700">
                  Payment Application digunakan untuk pengajuan <strong>Reimbursement</strong> dan pembayaran lainnya 
                  yang <strong>tidak memerlukan proses pembelian</strong> melalui Purchase Order (PO).
                </p>
                <p class="text-sm text-green-700 mt-2">
                  <strong>Catatan:</strong> Mode <strong>Payment Application</strong> menggunakan struktur input yang sama 
                  dengan <strong>Purchase Requisition</strong> (multi-outlet, multi-category, multi-item).
                </p>
              </div>

              <!-- Steps (same as pr_ops) -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Informasi Dasar</h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Title:</strong> Masukkan judul Payment Application (wajib)</li>
                      <li><strong>Division:</strong> Pilih divisi yang bertanggung jawab (wajib)</li>
                      <li><strong>Priority:</strong> Pilih prioritas (Low, Medium, High, Urgent)</li>
                      <li><strong>Description:</strong> Tambahkan deskripsi jika diperlukan</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Outlet, Category, dan Items</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Struktur input sama dengan Purchase Requisition:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Tambah outlet → Pilih outlet</li>
                      <li>Tambah category → Pilih category</li>
                      <li>Isi items (name, qty, unit, price)</li>
                      <li>Upload attachment per outlet (opsional)</li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Approvers</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      <strong>Wajib sertakan GM Finance sebagai Approver</strong>
                    </p>
                    <p class="text-sm text-gray-700 mb-2">
                      Tambahkan approvers dengan urutan dari level terendah ke tertinggi.
                    </p>
                  </div>
                </div>
              </div>

              <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded">
                <h4 class="font-semibold text-amber-800 mb-2">
                  <i class="fa fa-exclamation-triangle mr-2"></i>
                  Perbedaan dengan Purchase Requisition:
                </h4>
                <ul class="text-sm text-amber-700 space-y-1 list-disc list-inside">
                  <li>Payment Application <strong>tidak akan</strong> dibuat menjadi Purchase Order (PO)</li>
                  <li>Langsung masuk ke proses pembayaran setelah approval</li>
                  <li>Cocok untuk reimbursement dan pembayaran langsung</li>
                </ul>
              </div>
            </div>

            <!-- Tab Content: Travel Application -->
            <div v-if="activeTutorialTab === 'travel_application'" class="space-y-6">
              <!-- Introduction -->
              <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded">
                <h4 class="font-semibold text-purple-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu Travel Application?
                </h4>
                <p class="text-sm text-purple-700">
                  Travel Application digunakan untuk pengajuan <strong>perjalanan dinas</strong> dengan struktur input khusus 
                  yang berbeda dari mode lainnya.
                </p>
              </div>

              <!-- Important Notes -->
              <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <h4 class="font-semibold text-yellow-800 mb-3">
                  <i class="fa fa-exclamation-triangle mr-2"></i>
                  Catatan Penting:
                </h4>
                <ul class="text-sm text-yellow-700 space-y-2 list-none">
                  <li class="flex items-start">
                    <i class="fa fa-check-circle mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Untuk Travel Application wajib menyertakan GA Supervisor sebagai Approver pertama</strong>
                    </div>
                  </li>
                  <li class="flex items-start">
                    <i class="fa fa-check-circle mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Wajib sertakan GM Finance sebagai Approver</strong>
                    </div>
                  </li>
                  <li class="flex items-start">
                    <i class="fa fa-info-circle mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Contoh urutan approver:</strong> GA Supervisor - GM HR - GM Finance → BOD
                    </div>
                  </li>
                </ul>
              </div>

              <!-- Step 1 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Informasi Dasar</h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Title:</strong> Masukkan judul Travel Application (wajib)</li>
                      <li><strong>Division:</strong> Pilih divisi yang bertanggung jawab (wajib)</li>
                      <li><strong>Priority:</strong> Pilih prioritas (Low, Medium, High, Urgent)</li>
                      <li><strong>Category:</strong> Otomatis terpilih "Transport" (tidak bisa diubah)</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Step 2 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih Outlet Tujuan Perjalanan Dinas</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Klik tombol <strong>"Add Outlet Tujuan"</strong> untuk menambahkan outlet tujuan perjalanan.
                      Anda bisa menambahkan beberapa outlet tujuan sekaligus.
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Pilih semua outlet yang akan dikunjungi dalam perjalanan dinas ini.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Agenda Kerja</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Masukkan <strong>Agenda Kerja</strong> perjalanan dinas. Field ini adalah free text yang besar 
                      karena bisa sangat panjang.
                    </p>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Jelaskan secara detail agenda kerja yang akan dilakukan di setiap outlet tujuan.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 4 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    4
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Items Perjalanan</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Untuk setiap item, pilih <strong>Tipe Item</strong>:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-2 list-disc list-inside">
                      <li>
                        <strong>Transport:</strong> Untuk biaya transportasi (tiket, dll)
                        <br><span class="text-xs text-gray-500 ml-4">Item name akan otomatis menjadi "Transport"</span>
                      </li>
                      <li>
                        <strong>Allowance:</strong> Untuk uang saku/harian
                        <br><span class="text-xs text-gray-500 ml-4">Wajib isi: Nama Penerima Allowance dan No. Rekening</span>
                        <br><span class="text-xs text-gray-500 ml-4">Item name akan otomatis menjadi "Allowance - [Nama Penerima]"</span>
                      </li>
                      <li>
                        <strong>Others:</strong> Untuk biaya lainnya
                        <br><span class="text-xs text-gray-500 ml-4">Wajib isi: Notes Others</span>
                        <br><span class="text-xs text-gray-500 ml-4">Item name akan otomatis menjadi "Others - [Notes]"</span>
                      </li>
                    </ul>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mt-2">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Field "Item Name" tidak perlu diisi karena akan otomatis di-generate dari tipe item + nama/notes.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 5 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    5
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Notes (Opsional)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Tambahkan catatan tambahan jika diperlukan di field <strong>"Notes"</strong>.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Step 6 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    6
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Approvers</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      <strong>WAJIB:</strong> Tambahkan approvers dengan urutan berikut:
                    </p>
                    <div class="bg-red-50 border-l-4 border-red-500 p-3 rounded">
                      <p class="text-sm text-red-800 font-semibold mb-2">
                        <i class="fa fa-exclamation-circle mr-1"></i>
                        Urutan Approver Wajib:
                      </p>
                      <ol class="text-sm text-red-700 space-y-1 list-decimal list-inside ml-2">
                        <li><strong>GA Supervisor</strong> (Wajib sebagai approver pertama)</li>
                        <li>GM HR (atau approver lainnya sesuai kebutuhan)</li>
                        <li><strong>GM Finance</strong> (Wajib)</li>
                        <li>BOD (jika diperlukan)</li>
                      </ol>
                      <p class="text-xs text-red-600 mt-2">
                        <strong>Contoh:</strong> GA Supervisor → GM HR → GM Finance → BOD
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 7 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold">
                    7
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Review dan Submit</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Sebelum submit, pastikan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Semua outlet tujuan sudah dipilih</li>
                      <li>Agenda Kerja sudah diisi dengan lengkap</li>
                      <li>Semua items sudah lengkap (tipe, qty, unit, price)</li>
                      <li>Jika Allowance: nama penerima dan no. rekening sudah diisi</li>
                      <li>Jika Others: notes others sudah diisi</li>
                      <li>GA Supervisor sudah ditambahkan sebagai approver pertama</li>
                      <li>GM Finance sudah ditambahkan sebagai approver</li>
                      <li>Grand Total sudah sesuai</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab Content: Kasbon -->
            <div v-if="activeTutorialTab === 'kasbon'" class="space-y-6">
              <!-- Introduction -->
              <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded">
                <h4 class="font-semibold text-orange-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu Kasbon?
                </h4>
                <p class="text-sm text-orange-700">
                  Kasbon digunakan untuk pengajuan <strong>uang muka</strong> atau <strong>kasbon</strong> dengan struktur input yang sederhana dan otomatis.
                </p>
                <p class="text-sm text-orange-700 mt-2">
                  <strong>Catatan Penting:</strong> Per periode hanya diijinkan 1 user saja per outlet. Periode kasbon adalah tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya.
                </p>
              </div>

              <!-- Important Notes -->
              <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <h4 class="font-semibold text-yellow-800 mb-3">
                  <i class="fa fa-exclamation-triangle mr-2"></i>
                  Catatan Penting:
                </h4>
                <ul class="text-sm text-yellow-700 space-y-2 list-none">
                  <li class="flex items-start">
                    <i class="fa fa-calendar mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Periode Kasbon:</strong> Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
                    </div>
                  </li>
                  <li class="flex items-start">
                    <i class="fa fa-users mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Batasan:</strong> Per periode hanya diijinkan 1 user saja per outlet. Jika sudah ada user lain yang mengajukan kasbon di periode yang sama untuk outlet yang sama, pengajuan akan ditolak.
                    </div>
                  </li>
                  <li class="flex items-start">
                    <i class="fa fa-info-circle mr-2 mt-0.5 text-yellow-600"></i>
                    <div>
                      <strong>Auto-Select:</strong> Division, Outlet, dan Category otomatis terpilih sesuai data user yang login.
                    </div>
                  </li>
                </ul>
              </div>

              <!-- Step 1 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    1
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Informasi Dasar</h4>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Title:</strong> Masukkan judul Kasbon (wajib)</li>
                      <li><strong>Division:</strong> Otomatis terpilih sesuai division user yang login (tidak bisa diubah)</li>
                      <li><strong>Category:</strong> Otomatis terpilih "Kasbon" (tidak bisa diubah)</li>
                      <li><strong>Outlet:</strong> Otomatis terpilih sesuai outlet user yang login (tidak bisa diubah)</li>
                      <li><strong>Priority:</strong> Pilih prioritas (Low, Medium, High, Urgent)</li>
                    </ul>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 mt-2">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Division, Category, dan Outlet sudah otomatis terpilih, jadi Anda hanya perlu mengisi Title dan Priority.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 2 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    2
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Pahami Periode Kasbon</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Sistem akan menampilkan <strong>Periode Aktif</strong> kasbon di form. Periode ini adalah:
                    </p>
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                      <p class="text-sm text-blue-800 font-semibold mb-1">
                        <i class="fa fa-calendar mr-1"></i>
                        Periode Kasbon:
                      </p>
                      <p class="text-sm text-blue-700">
                        <strong>Tanggal 20 bulan berjalan</strong> hingga <strong>tanggal 10 bulan selanjutnya</strong>
                      </p>
                      <p class="text-xs text-blue-600 mt-2">
                        <i class="fa fa-info-circle mr-1"></i>
                        Contoh: Jika saat ini tanggal 15 Januari 2024, maka periode adalah 20 Desember 2023 - 10 Februari 2024
                      </p>
                    </div>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mt-2">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Jika sudah ada user lain yang mengajukan kasbon untuk outlet yang sama di periode ini, pengajuan Anda akan ditolak.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 3 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    3
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Nilai Kasbon</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Masukkan <strong>Nilai Kasbon</strong> yang ingin diajukan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Masukkan nilai kasbon di field <strong>"Nilai Kasbon *"</strong></li>
                      <li>Nilai akan otomatis ditampilkan dalam format currency</li>
                      <li>Pastikan nilai yang dimasukkan sudah benar</li>
                    </ul>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 mt-2">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Total akan otomatis dihitung dan ditampilkan di bawah field.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 4 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    4
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Isi Reason / Alasan Kasbon</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Masukkan <strong>Reason / Alasan Kasbon</strong> secara detail:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Jelaskan alasan atau tujuan penggunaan kasbon</li>
                      <li>Sertakan rencana pelunasan jika ada</li>
                      <li>Field ini wajib diisi</li>
                    </ul>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 mt-2">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Jelaskan secara detail agar proses approval lebih mudah.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 5 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    5
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Upload Attachment (Opsional)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Upload file pendukung jika diperlukan (maksimal 10MB per file).
                    </p>
                    <p class="text-sm text-gray-700">
                      File yang bisa di-upload: PDF, Word, Excel, Image, dll.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Step 6 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    6
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Approvers</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Tambahkan approvers dengan urutan dari level terendah ke tertinggi:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Ketik nama, email, atau jabatan di search box</li>
                      <li>Pilih approver dari hasil pencarian</li>
                      <li>Urutkan dengan tombol panah (↑ ↓)</li>
                      <li>Minimal harus ada 1 approver</li>
                    </ul>
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-3 rounded mt-3">
                      <p class="text-sm text-yellow-800 font-semibold mb-1">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        Wajib sertakan GM Finance sebagai Approver
                      </p>
                      <p class="text-xs text-yellow-700">
                        Untuk pengajuan kasbon, pastikan GM Finance sudah ditambahkan dalam daftar approvers.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Step 7 -->
              <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-600 text-white rounded-full flex items-center justify-center font-bold">
                    7
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-gray-900 mb-2">Review dan Submit</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Sebelum submit, pastikan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Title sudah diisi</li>
                      <li>Nilai kasbon sudah benar</li>
                      <li>Reason/alasan kasbon sudah diisi dengan lengkap</li>
                      <li>Approvers sudah ditambahkan</li>
                      <li>Periode kasbon sudah sesuai</li>
                      <li>Tidak ada user lain yang sudah mengajukan kasbon untuk outlet yang sama di periode yang sama</li>
                    </ul>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mt-2">
                      <p class="text-xs text-green-800">
                        <i class="fa fa-check-circle mr-1"></i>
                        Sistem akan otomatis mengecek apakah sudah ada kasbon untuk outlet Anda di periode yang sama sebelum submit.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tips -->
              <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded">
                <h4 class="font-semibold text-yellow-800 mb-2">
                  <i class="fa fa-lightbulb mr-2"></i>
                  Tips & Best Practices:
                </h4>
                <ul class="text-sm text-yellow-700 space-y-1 list-disc list-inside">
                  <li>Pastikan nilai kasbon sudah sesuai dengan kebutuhan</li>
                  <li>Jelaskan alasan kasbon secara detail dan jelas</li>
                  <li>Upload dokumen pendukung jika diperlukan</li>
                  <li>Pilih approvers sesuai dengan alur approval yang berlaku</li>
                  <li>Perhatikan periode kasbon untuk menghindari konflik dengan user lain</li>
                  <li>Jika pengajuan ditolak karena sudah ada kasbon di periode yang sama, tunggu hingga periode berikutnya</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button
              @click="showTutorial = false"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Saya Mengerti
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick, getCurrentInstance } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  purchaseRequisition: Object,
  categories: Array,
  outlets: Array,
  tickets: Array,
  divisions: Array,
  modeSpecificData: Object,
})

// Search query for category
const categorySearch = ref('')
const showCategoryDropdown = ref(false)

// Filtered categories based on mode and search
const filteredCategories = computed(() => {
  let categories = props.categories
  
  // Untuk mode pr_ops dan purchase_payment, hilangkan category "Transport & akomodasi" dan "kasbon"
  if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
    categories = categories.filter(cat => {
      const categoryName = (cat.name || '').toLowerCase()
      // Hilangkan category yang mengandung "transport", "akomodasi", atau "kasbon"
      return !categoryName.includes('transport') && 
             !categoryName.includes('akomodasi') && 
             !categoryName.includes('kasbon')
    })
  }
  
  // Filter berdasarkan search query jika ada
  if (categorySearch.value.trim()) {
    const searchTerm = categorySearch.value.toLowerCase().trim()
    categories = categories.filter(cat => {
      const categoryName = (cat.name || '').toLowerCase()
      const divisionName = (cat.division || '').toLowerCase()
      return categoryName.includes(searchTerm) || divisionName.includes(searchTerm)
    })
  }
  
  return categories
})

// Watch mode changes to reset search
watch(() => form.mode, () => {
  categorySearch.value = ''
  showCategoryDropdown.value = false
})

// Close dropdown when clicking outside
onMounted(() => {
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.category-dropdown-container')) {
      showCategoryDropdown.value = false
    }
  })
})

const getCategoryDisplayName = (categoryId) => {
  const category = props.categories.find(cat => cat.id == categoryId)
  return category ? `[${category.division}] ${category.name}` : ''
}

// Get current user info
const page = usePage()
const currentUser = computed(() => page.props.auth?.user || {})
const userOutletId = computed(() => currentUser.value.id_outlet || null)
const userDivisionId = computed(() => currentUser.value.division_id || null)

const loading = ref(false)
const budgetInfo = ref(null)

// Check if any budget is exceeded (for all modes)
const isBudgetExceeded = computed(() => {
  // For pr_ops and purchase_payment mode, check all category budgets
  if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
    // Check if any category has exceeded budget
    for (const key in categoryBudgetInfo.value) {
      const info = categoryBudgetInfo.value[key]
      if (info && info.exceeds_budget) {
        return true
      }
    }
    return false
  } else {
    // For other modes, check main budgetInfo
    return budgetInfo.value && budgetInfo.value.exceeds_budget
  }
})
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
const selectedCategoryDetails = ref(null)
const attachments = ref([]) // For non-pr_ops mode
const outletAttachments = ref({}) // For pr_ops mode: { outletId: [attachments] }
const uploading = ref(false)
const fileInputs = ref({}) // For outlet file inputs
const showTutorial = ref(false) // Tutorial modal
const activeTutorialTab = ref('pr_ops') // Active tab in tutorial modal
const categoryBudgetInfo = ref({}) // Budget info per category: { 'outletIdx-categoryIdx': budgetInfo }
const categoryDetails = ref({}) // Category details per category: { 'outletIdx-categoryIdx': details }
const kasbonOutOfPeriod = ref(false) // Flag to hide form when kasbon is out of period

function newItem() {
  return {
    item_name: '',
    qty: '',
    unit: '',
    unit_price: 0,
    subtotal: 0
  }
}

function newTravelItem() {
  return {
    item_type: 'transport', // transport, allowance, others
    qty: '',
    unit: '',
    unit_price: 0,
    subtotal: 0,
    allowance_recipient_name: '', // For allowance type
    allowance_account_number: '', // For allowance type
    others_notes: '' // For others type
    // item_name will be auto-generated from item_type + name/notes
  }
}

function newCategory() {
  return {
    category_id: '',
    items: [newItem()]
  }
}

function newOutlet() {
  return {
    outlet_id: '',
    categories: [newCategory()]
  }
}

const kasbonAmountOptions = [500000, 1000000, 1500000, 2000000, 2500000, 3000000]
const kasbonTerminOptions = [1, 2, 3]

// Initialize form with existing data or defaults
// Use props directly to ensure we get the latest data
const pr = computed(() => props.purchaseRequisition || {})
const modeSpecific = computed(() => props.modeSpecificData || {})

const form = reactive({
  title: '',
  description: '',
  division_id: '',
  category_id: '', // For non-pr_ops mode
  outlet_id: '', // For non-pr_ops mode
  ticket_id: '',
  currency: 'IDR',
  priority: 'MEDIUM',
  items: [newItem()], // For non-pr_ops mode (will be populated in onMounted)
  outlets: [newOutlet()], // For pr_ops mode: multi-outlet structure (will be populated in onMounted)
  travel_outlets: [newOutlet()], // For travel_application mode: destination outlets (will be populated in onMounted)
  travel_items: [newTravelItem()], // For travel_application mode: travel items (will be populated in onMounted)
  travel_agenda: '', // For travel_application mode: work agenda (will be populated in onMounted)
  travel_notes: '', // For travel_application mode: notes (will be populated in onMounted)
  kasbon_amount: 0, // For kasbon mode: nilai kasbon (will be populated in onMounted)
  kasbon_termin: 0, // For kasbon mode: termin 1-3x (will be populated in onMounted)
  kasbon_reason: '', // For kasbon mode: reason/alasan kasbon (will be populated in onMounted)
  approvers: [], // Will be populated in onMounted
  mode: 'pr_ops' // Will be set in onMounted
})

// Calculate total amount based on mode
const totalAmount = computed(() => {
  if (form.mode === 'kasbon') {
    // For kasbon: use kasbon_amount
    const amount = parseFloat(form.kasbon_amount) || 0
    return isNaN(amount) ? 0 : amount
  } else if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
    // For pr_ops and purchase_payment: sum from all outlets -> categories -> items
    return form.outlets.reduce((outletSum, outlet) => {
      const outletTotal = outlet.categories.reduce((categorySum, category) => {
        const categoryTotal = category.items.reduce((itemSum, item) => {
          const subtotal = parseFloat(item.subtotal) || 0
          return itemSum + (isNaN(subtotal) ? 0 : subtotal)
        }, 0)
        return categorySum + categoryTotal
      }, 0)
      return outletSum + outletTotal
    }, 0)
  } else if (form.mode === 'travel_application') {
    // For travel_application: sum from travel_items
    return form.travel_items.reduce((sum, item) => {
      const subtotal = parseFloat(item.subtotal) || 0
      return sum + (isNaN(subtotal) ? 0 : subtotal)
    }, 0)
  } else {
    // For other modes: use simple items array
    return form.items.reduce((sum, item) => {
      const subtotal = parseFloat(item.subtotal) || 0
      return sum + (isNaN(subtotal) ? 0 : subtotal)
    }, 0)
  }
})

// Check if approver is GA Supervisor
const isGASupervisor = (approver) => {
  if (!approver) return false
  const jabatan = approver.jabatan?.toLowerCase() || ''
  const name = approver.name?.toLowerCase() || ''
  return jabatan.includes('ga supervisor') || name.includes('ga supervisor')
}

// Check if approver is GM Finance
const isGMFinance = (approver) => {
  if (!approver) return false
  const jabatan = approver.jabatan?.toLowerCase() || ''
  const name = approver.name?.toLowerCase() || ''
  return jabatan.includes('gm finance') || name.includes('gm finance')
}

// Check if GA Supervisor is first approver (only required for Travel Application)
const hasGASupervisorAsFirst = computed(() => {
  if (form.mode !== 'travel_application' || form.approvers.length === 0) {
    return true // Not required for non-travel applications
  }
  return isGASupervisor(form.approvers[0])
})

// Check if GM Finance is in approvers list
const hasGMFinance = computed(() => {
  if (form.mode !== 'kasbon' && form.mode !== 'purchase_payment' && form.mode !== 'travel_application') {
    return true // Not required for other modes
  }
  if (!form.approvers || form.approvers.length === 0) {
    return false
  }
  return form.approvers.some(approver => isGMFinance(approver))
})

// Computed property to ensure approvers are reactive
const approversList = computed(() => {
  // Force reactivity by accessing the array
  if (!form.approvers || !Array.isArray(form.approvers)) return []
  // Return a new array reference to force Vue to detect changes
  return form.approvers.slice() // Use slice() instead of spread for better compatibility
})

// Watch approvers changes to ensure reactivity
watch(() => form.approvers, (newApprovers) => {
  console.log('Approvers changed:', newApprovers)
}, { deep: true, immediate: true })

// Watch totalAmount changes to update budget info
watch(totalAmount, (newTotal) => {
  form.amount = newTotal
  // Reload budget info when total amount changes
  if (form.category_id) {
    if (form.mode === 'travel_application') {
      // For travel_application, use first outlet if available, or null
      const outletId = form.travel_outlets.length > 0 && form.travel_outlets[0].outlet_id 
        ? form.travel_outlets[0].outlet_id 
        : null
      loadBudgetInfoForTravel(outletId)
    } else if (form.mode !== 'pr_ops' && form.mode !== 'purchase_payment' && form.mode !== 'kasbon') {
      loadBudgetInfo()
    }
  }
})

// Note: Budget info is not loaded for kasbon mode

// Watch mode changes to reset structure
watch(() => form.mode, (newMode) => {
  if (newMode === 'pr_ops' || newMode === 'purchase_payment') {
    // Reset to multi-outlet structure for pr_ops and purchase_payment
    form.outlets = [newOutlet()]
    form.items = []
    form.travel_outlets = []
    form.travel_items = []
    form.travel_agenda = ''
    form.travel_notes = ''
    form.outlet_id = ''
    form.category_id = ''
    outletAttachments.value = {}
  } else if (newMode === 'travel_application') {
    // Reset to travel application structure
    form.travel_outlets = [newOutlet()]
    form.travel_items = [newTravelItem()]
    form.travel_agenda = ''
    form.travel_notes = ''
    form.items = []
    form.outlets = []
    form.outlet_id = ''
    // Auto-select transport category (category name contains 'transport')
    const transportCategory = props.categories.find(cat => 
      cat.name && cat.name.toLowerCase().includes('transport')
    )
    if (transportCategory) {
      form.category_id = transportCategory.id
      loadCategoryDetails()
      // Load budget info after category is selected
      setTimeout(() => {
        if (form.travel_outlets.length > 0 && form.travel_outlets[0].outlet_id) {
          loadBudgetInfoForTravel(form.travel_outlets[0].outlet_id)
        } else {
          loadBudgetInfoForTravel(null)
        }
      }, 100)
    } else {
      form.category_id = ''
    }
    attachments.value = []
    outletAttachments.value = {}
  } else if (newMode === 'kasbon') {
    // Validate kasbon period
    if (!isWithinKasbonPeriod()) {
      const { startDate, endDate } = getKasbonPeriod()
      const startStr = startDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
      const endStr = endDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
      
      // Reset to default structure first
      form.items = []
      form.outlets = [newOutlet()]
      form.travel_outlets = []
      form.travel_items = []
      form.travel_agenda = ''
      form.travel_notes = ''
      form.kasbon_amount = 0
      form.kasbon_termin = 0
      form.kasbon_reason = ''
      form.outlet_id = ''
      form.category_id = ''
      attachments.value = []
      outletAttachments.value = {}
      
      // Set flag to hide form
      kasbonOutOfPeriod.value = true
      
      // Reset mode to default
      form.mode = 'pr_ops'
      
      // Show error alert (use setTimeout to ensure mode is reset first)
      setTimeout(() => {
        Swal.fire({
          icon: 'error',
          title: 'Tidak Dapat Input Kasbon',
          html: `
            <p class="text-sm mb-3">Anda tidak dapat menginput kasbon di luar periode yang ditentukan.</p>
            <div class="bg-blue-50 border border-blue-200 rounded p-3 text-left">
              <p class="text-sm font-semibold text-blue-800 mb-2">Periode Kasbon Aktif:</p>
              <p class="text-sm text-blue-700">${startStr} - ${endStr}</p>
            </div>
            <p class="text-xs text-gray-600 mt-3">
              Periode kasbon: Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
            </p>
          `,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444'
        })
      }, 100)
      
      return
    }
    
    // Reset flag
    kasbonOutOfPeriod.value = false
    
    // Reset to kasbon structure
    form.items = []
    form.outlets = []
    form.travel_outlets = []
    form.travel_items = []
    form.travel_agenda = ''
    form.travel_notes = ''
    form.kasbon_amount = 0
    form.kasbon_termin = 1
    form.kasbon_reason = ''
    attachments.value = []
    
    // Auto-select division user
    if (userDivisionId.value) {
      form.division_id = userDivisionId.value
    }
    
    // Auto-select outlet user
    if (userOutletId.value) {
      form.outlet_id = userOutletId.value
      onOutletChange()
    }
    
    // Auto-select category containing "kasbon"
    const kasbonCategory = props.categories.find(cat => 
      cat.name && cat.name.toLowerCase().includes('kasbon')
    )
    if (kasbonCategory) {
      form.category_id = kasbonCategory.id
      loadCategoryDetails()
      // Note: Budget info is not loaded for kasbon mode
    } else {
      form.category_id = ''
    }
  } else {
    // Reset to simple items structure for other modes
    form.items = [newItem()]
    form.outlets = []
    form.travel_outlets = []
    form.travel_items = []
    form.travel_agenda = ''
    form.travel_notes = ''
    form.kasbon_amount = 0
    form.kasbon_termin = 0
    form.kasbon_reason = ''
    attachments.value = []
  }
})

// Functions for non-pr_ops mode (simple items)
function addItem() {
  form.items.push(newItem())
}

function removeItem(idx) {
  if (form.items.length === 1) return
  form.items.splice(idx, 1)
}

function calculateSubtotal(idx) {
  const item = form.items[idx]
  const qty = parseFloat(item.qty) || 0
  const unitPrice = parseFloat(item.unit_price) || 0
  item.subtotal = qty * unitPrice
}

// Functions for pr_ops mode (multi-outlet structure)
function addOutlet() {
  form.outlets.push(newOutlet())
}

function removeOutlet(outletIdx) {
  if (form.outlets.length === 1) return
  form.outlets.splice(outletIdx, 1)
}

function addCategory(outletIdx) {
  form.outlets[outletIdx].categories.push(newCategory())
}

function removeCategory(outletIdx, categoryIdx) {
  if (form.outlets[outletIdx].categories.length === 1) return
  form.outlets[outletIdx].categories.splice(categoryIdx, 1)
}

function addItemToCategory(outletIdx, categoryIdx) {
  form.outlets[outletIdx].categories[categoryIdx].items.push(newItem())
}

function removeItemFromCategory(outletIdx, categoryIdx, itemIdx) {
  const items = form.outlets[outletIdx].categories[categoryIdx].items
  if (items.length === 1) return
  items.splice(itemIdx, 1)
}

// Functions for travel_application mode
function addTravelOutlet() {
  form.travel_outlets.push(newOutlet())
}

function removeTravelOutlet(outletIdx) {
  if (form.travel_outlets.length === 1) return
  form.travel_outlets.splice(outletIdx, 1)
}

function addTravelItem() {
  form.travel_items.push(newTravelItem())
}

function removeTravelItem(itemIdx) {
  if (form.travel_items.length === 1) return
  form.travel_items.splice(itemIdx, 1)
}

function calculateTravelItemSubtotal(itemIdx) {
  const item = form.travel_items[itemIdx]
  const qty = parseFloat(item.qty) || 0
  const unitPrice = parseFloat(item.unit_price) || 0
  item.subtotal = qty * unitPrice
}

function calculateSubtotalForCategory(outletIdx, categoryIdx, itemIdx) {
  const item = form.outlets[outletIdx].categories[categoryIdx].items[itemIdx]
  const qty = parseFloat(item.qty) || 0
  const unitPrice = parseFloat(item.unit_price) || 0
  item.subtotal = qty * unitPrice
  
  // Reload budget info when item subtotal changes
  const outlet = form.outlets[outletIdx]
  const category = outlet.categories[categoryIdx]
  if (outlet.outlet_id && category.category_id) {
    loadBudgetInfoForCategory(outletIdx, categoryIdx, outlet.outlet_id, category.category_id)
  }
}

// Get category total for a specific category
function getCategoryTotal(outletIdx, categoryIdx) {
  const category = form.outlets[outletIdx].categories[categoryIdx]
  return category.items.reduce((sum, item) => {
    const subtotal = parseFloat(item.subtotal) || 0
    return sum + (isNaN(subtotal) ? 0 : subtotal)
  }, 0)
}

// Get outlet total
function getOutletTotal(outletIdx) {
  const outlet = form.outlets[outletIdx]
  return outlet.categories.reduce((sum, category) => {
    return sum + category.items.reduce((itemSum, item) => {
      const subtotal = parseFloat(item.subtotal) || 0
      return itemSum + (isNaN(subtotal) ? 0 : subtotal)
    }, 0)
  }, 0)
}

const onCategoryChange = () => {
  // Load category details
  loadCategoryDetails()
  // Load budget info
  loadBudgetInfo()
}

const onOutletChange = () => {
  // Load budget info when outlet changes
  if (form.category_id) {
    loadBudgetInfo()
  }
}

const loadCategoryDetails = () => {
  if (!form.category_id) {
    selectedCategoryDetails.value = null
    return
  }
  
  // Find category from props
  const category = props.categories.find(cat => cat.id == form.category_id)
  if (category) {
    selectedCategoryDetails.value = {
      division: category.division,
      subcategory: category.subcategory,
      budget_limit: category.budget_limit,
      budget_type: category.budget_type,
      description: category.description
    }
  } else {
    selectedCategoryDetails.value = null
  }
}

const loadBudgetInfo = async () => {
  if (!form.category_id) {
    budgetInfo.value = null
    return
  }
  
  // For PER_OUTLET budget type, require outlet selection
  if (selectedCategoryDetails.value && selectedCategoryDetails.value.budget_type === 'PER_OUTLET' && !form.outlet_id) {
    budgetInfo.value = null
    return
  }
  
  try {
    const response = await axios.get('/purchase-requisitions/budget-info', {
      params: {
        category_id: form.category_id,
        outlet_id: form.outlet_id,
        current_amount: totalAmount.value,
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      budgetInfo.value = response.data
    } else {
      budgetInfo.value = null
    }
  } catch (error) {
    budgetInfo.value = null
  }
}

// Load budget info for specific category in pr_ops/purchase_payment mode
const loadBudgetInfoForCategory = async (outletIdx, categoryIdx, outletId, categoryId) => {
  if (!categoryId) {
    const key = `${outletIdx}-${categoryIdx}`
    categoryBudgetInfo.value[key] = null
    categoryDetails.value[key] = null
    return
  }

  const key = `${outletIdx}-${categoryIdx}`
  
  // Load category details
  const category = props.categories.find(cat => cat.id == categoryId)
  if (category) {
    categoryDetails.value[key] = {
      division: category.division,
      subcategory: category.subcategory,
      budget_limit: category.budget_limit,
      budget_type: category.budget_type,
      description: category.description
    }
  } else {
    categoryDetails.value[key] = null
  }

  // Load budget info
  if (!outletId && category?.budget_type === 'PER_OUTLET') {
    categoryBudgetInfo.value[key] = null
    return
  }

  try {
    const categoryTotal = getCategoryTotal(outletIdx, categoryIdx)
    const response = await axios.get('/purchase-requisitions/budget-info', {
      params: {
        category_id: categoryId,
        outlet_id: outletId || null,
        current_amount: categoryTotal,
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      categoryBudgetInfo.value[key] = response.data
    } else {
      categoryBudgetInfo.value[key] = null
    }
  } catch (error) {
    categoryBudgetInfo.value[key] = null
  }
}

// Get budget info for specific category
const getCategoryBudgetInfo = (outletIdx, categoryIdx) => {
  const key = `${outletIdx}-${categoryIdx}`
  return categoryBudgetInfo.value[key] || null
}

// Get category details for specific category
const getCategoryDetails = (outletIdx, categoryIdx) => {
  const key = `${outletIdx}-${categoryIdx}`
  return categoryDetails.value[key] || null
}

// Get travel category name for display
const getTravelCategoryName = () => {
  if (!form.category_id) return 'Transport Category'
  const category = props.categories.find(c => c.id == form.category_id)
  return category ? `[${category.division}] ${category.name}` : 'Transport Category'
}

// Get kasbon outlet name for display
const getKasbonOutletName = () => {
  if (!form.outlet_id) return 'Loading...'
  const outlet = props.outlets.find(o => o.id_outlet == form.outlet_id)
  return outlet ? outlet.nama_outlet : 'Unknown Outlet'
}

// Get kasbon category name for display
const getKasbonCategoryName = () => {
  if (!form.category_id) return 'Kasbon Category'
  const category = props.categories.find(c => c.id == form.category_id)
  return category ? `[${category.division}] ${category.name}` : 'Kasbon Category'
}

// Get kasbon division name for display
const getKasbonDivisionName = () => {
  if (!form.division_id) return 'Loading...'
  const division = props.divisions.find(d => d.id == form.division_id)
  return division ? division.nama_divisi : 'Unknown Division'
}

// Get kasbon period (tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya)
const getKasbonPeriod = () => {
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() // 0-11
  const currentDay = now.getDate()
  
  let startDate, endDate
  
  // Tentukan periode berdasarkan tanggal sekarang
  if (currentDay < 20) {
    // Jika tanggal < 20: periode = 20 bulan sebelumnya - 10 bulan berjalan
    startDate = new Date(currentYear, currentMonth - 1, 20)
    endDate = new Date(currentYear, currentMonth, 10)
  } else {
    // Jika tanggal >= 20: periode = 20 bulan berjalan - 10 bulan selanjutnya
    startDate = new Date(currentYear, currentMonth, 20)
    endDate = new Date(currentYear, currentMonth + 1, 10)
  }
  
  return { startDate, endDate }
}

// Check if current date is within kasbon period
// Jika tanggal sekarang < 20: periode = 20 bulan sebelumnya - 10 bulan berjalan
// Jika tanggal sekarang >= 20: periode = 20 bulan berjalan - 10 bulan selanjutnya
const isWithinKasbonPeriod = () => {
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = now.getMonth() // 0-11
  const currentDay = now.getDate()
  
  let startDate, endDate
  
  // Tentukan periode berdasarkan tanggal sekarang
  if (currentDay < 20) {
    // Jika tanggal < 20: periode = 20 bulan sebelumnya - 10 bulan berjalan
    startDate = new Date(currentYear, currentMonth - 1, 20)
    endDate = new Date(currentYear, currentMonth, 10)
  } else {
    // Jika tanggal >= 20: periode = 20 bulan berjalan - 10 bulan selanjutnya
    startDate = new Date(currentYear, currentMonth, 20)
    endDate = new Date(currentYear, currentMonth + 1, 10)
  }
  
  // Set time to 00:00:00 for accurate date comparison
  const nowDate = new Date(currentYear, currentMonth, currentDay)
  const start = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate())
  const end = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate())
  
  // Check if current date is within the period
  return nowDate >= start && nowDate <= end
}

// Get kasbon period text for display
const getKasbonPeriodText = () => {
  const { startDate, endDate } = getKasbonPeriod()
  const startStr = startDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  const endStr = endDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  return `${startStr} - ${endStr}`
}

// Check if kasbon exists for outlet in current period
// DISABLED: Validasi duplikasi kasbon untuk outlet yang sama telah dinonaktifkan
const checkKasbonExists = async () => {
  if (!userOutletId.value || userOutletId.value == 1) {
    return { exists: false, message: null }
  }
  
  if (!form.outlet_id) {
    return { exists: false, message: null }
  }
  
  const { startDate, endDate } = getKasbonPeriod()
  
  // Get current PR ID for edit mode
  const currentPrId = props.purchaseRequisition?.id || null
  
  try {
    const response = await axios.get('/purchase-requisitions/check-kasbon-period', {
      params: {
        outlet_id: form.outlet_id,
        start_date: startDate.toISOString().split('T')[0],
        end_date: endDate.toISOString().split('T')[0],
        exclude_id: currentPrId // For edit mode, exclude current PR
      }
    })
    
    if (response.data.exists) {
      return {
        exists: true,
        message: `Sudah ada pengajuan kasbon untuk outlet ini di periode ${getKasbonPeriodText()}. Per periode hanya diijinkan 1 user saja per outlet.`
      }
    }
    
    return { exists: false, message: null }
  } catch (error) {
    console.error('Error checking kasbon period:', error)
    return { exists: false, message: null }
  }
}

// Load budget info for travel_application mode
const loadBudgetInfoForTravel = async (outletId = null) => {
  if (!form.category_id) {
    budgetInfo.value = null
    return
  }

  // For PER_OUTLET budget type, require outlet selection
  if (selectedCategoryDetails.value && selectedCategoryDetails.value.budget_type === 'PER_OUTLET' && !outletId) {
    budgetInfo.value = null
    return
  }

  try {
    const response = await axios.get('/purchase-requisitions/budget-info', {
      params: {
        category_id: form.category_id,
        outlet_id: outletId,
        current_amount: totalAmount.value,
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      budgetInfo.value = response.data
    } else {
      budgetInfo.value = null
    }
  } catch (error) {
    budgetInfo.value = null
  }
}

const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/purchase-requisitions/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      approverResults.value = response.data.users
      showApproverDropdown.value = true
    }
  } catch (error) {
    console.error('Failed to load approvers:', error)
    approverResults.value = []
  }
}

const addApprover = (user) => {
  // Check if user already exists
  if (!form.approvers.find(approver => approver.id === user.id)) {
    form.approvers.push(user)
  }
  approverSearch.value = ''
  showApproverDropdown.value = false
}

const removeApprover = (index) => {
  // Prevent removing GA Supervisor from first position in Travel Application
  if (form.mode === 'travel_application' && index === 0 && isGASupervisor(form.approvers[index])) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak Dapat Dihapus',
      text: 'GA Supervisor tidak dapat dihapus karena wajib menjadi approver pertama untuk Travel Application',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }
  form.approvers.splice(index, 1)
}

const reorderApprover = (fromIndex, toIndex) => {
  // Prevent moving GA Supervisor from first position in Travel Application
  if (form.mode === 'travel_application' && fromIndex === 0 && isGASupervisor(form.approvers[fromIndex])) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak Dapat Dipindahkan',
      text: 'GA Supervisor harus tetap menjadi approver pertama untuk Travel Application',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }
  // Prevent moving other approvers to first position if GA Supervisor is required
  if (form.mode === 'travel_application' && toIndex === 0 && form.approvers.length > 0 && isGASupervisor(form.approvers[0])) {
    Swal.fire({
      icon: 'warning',
      title: 'Tidak Dapat Dipindahkan',
      text: 'GA Supervisor harus tetap menjadi approver pertama untuk Travel Application',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }
  const approver = form.approvers.splice(fromIndex, 1)[0]
  form.approvers.splice(toIndex, 0, approver)
}

const submitForm = async () => {
  loading.value = true
  
  // Validate approvers
  if (form.approvers.length === 0) {
    loading.value = false
    await Swal.fire({
      icon: 'warning',
      title: 'Approver Diperlukan',
      text: 'Silakan pilih minimal satu approver sebelum menyimpan',
      confirmButtonText: 'OK',
      confirmButtonColor: '#F59E0B'
    })
    return
  }
  
  // Validate travel_application mode structure
  if (form.mode === 'travel_application') {
    // Validate travel outlets
    for (let outletIdx = 0; outletIdx < form.travel_outlets.length; outletIdx++) {
      const outlet = form.travel_outlets[outletIdx]
      if (!outlet.outlet_id) {
        loading.value = false
        await Swal.fire({
          icon: 'warning',
          title: 'Outlet Tujuan Diperlukan',
          text: `Silakan pilih outlet tujuan untuk outlet ke-${outletIdx + 1}`,
          confirmButtonText: 'OK',
          confirmButtonColor: '#F59E0B'
        })
        return
      }
    }

    // Validate travel items
    if (form.travel_items.length === 0) {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Items Diperlukan',
        text: 'Silakan tambahkan minimal satu item perjalanan',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    for (let itemIdx = 0; itemIdx < form.travel_items.length; itemIdx++) {
      const item = form.travel_items[itemIdx]
      if (!item.qty || !item.unit || !item.unit_price) {
        loading.value = false
        await Swal.fire({
          icon: 'warning',
          title: 'Item Tidak Lengkap',
          text: `Silakan lengkapi semua field untuk item ke-${itemIdx + 1}`,
          confirmButtonText: 'OK',
          confirmButtonColor: '#F59E0B'
        })
        return
      }

      // Validate allowance fields
      if (item.item_type === 'allowance') {
        if (!item.allowance_recipient_name || !item.allowance_account_number) {
          loading.value = false
          await Swal.fire({
            icon: 'warning',
            title: 'Data Allowance Tidak Lengkap',
            text: `Silakan lengkapi nama penerima dan nomor rekening untuk item allowance ke-${itemIdx + 1}`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#F59E0B'
          })
          return
        }
      }

      // Validate others notes
      if (item.item_type === 'others') {
        if (!item.others_notes) {
          loading.value = false
          await Swal.fire({
            icon: 'warning',
            title: 'Notes Others Diperlukan',
            text: `Silakan isi notes untuk item others ke-${itemIdx + 1}`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#F59E0B'
          })
          return
        }
      }
    }

    // Validate travel agenda
    if (!form.travel_agenda || form.travel_agenda.trim() === '') {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Agenda Kerja Diperlukan',
        text: 'Silakan isi agenda kerja perjalanan dinas',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }
  }

  // Validate kasbon mode structure
  if (form.mode === 'kasbon') {
    // Validate kasbon period first
    if (!isWithinKasbonPeriod()) {
      loading.value = false
      const { startDate, endDate } = getKasbonPeriod()
      const startStr = startDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
      const endStr = endDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
      
      await Swal.fire({
        icon: 'error',
        title: 'Tidak Dapat Input Kasbon',
        html: `
          <p class="text-sm mb-3">Anda tidak dapat menginput kasbon di luar periode yang ditentukan.</p>
          <div class="bg-blue-50 border border-blue-200 rounded p-3 text-left">
            <p class="text-sm font-semibold text-blue-800 mb-2">Periode Kasbon Aktif:</p>
            <p class="text-sm text-blue-700">${startStr} - ${endStr}</p>
          </div>
          <p class="text-xs text-gray-600 mt-3">
            Periode kasbon: Tanggal 20 bulan berjalan hingga tanggal 10 bulan selanjutnya
          </p>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
      return
    }
    
    // Validate kasbon amount
    if (!kasbonAmountOptions.includes(Number(form.kasbon_amount))) {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Nilai Kasbon Diperlukan',
        text: 'Silakan pilih nilai kasbon dari pilihan yang tersedia',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    // Validate kasbon termin
    if (!kasbonTerminOptions.includes(Number(form.kasbon_termin))) {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Termin Kasbon Diperlukan',
        text: 'Silakan pilih termin kasbon (1x, 2x, atau 3x)',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    // Validate kasbon reason
    if (!form.kasbon_reason || form.kasbon_reason.trim() === '') {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Alasan Kasbon Diperlukan',
        text: 'Silakan isi alasan atau tujuan penggunaan kasbon',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    // Validate outlet
    if (!form.outlet_id) {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Outlet Diperlukan',
        text: 'Outlet tidak terdeteksi. Silakan refresh halaman dan coba lagi.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    // Validate category
    if (!form.category_id) {
      loading.value = false
      await Swal.fire({
        icon: 'warning',
        title: 'Category Diperlukan',
        text: 'Category tidak terdeteksi. Silakan refresh halaman dan coba lagi.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#F59E0B'
      })
      return
    }

    // Check if kasbon already exists for this outlet in current period
    if (userOutletId.value && userOutletId.value != 1) {
      const kasbonCheck = await checkKasbonExists()
      if (kasbonCheck.exists) {
        loading.value = false
        await Swal.fire({
          icon: 'error',
          title: 'Kasbon Sudah Ada',
          html: kasbonCheck.message,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444'
        })
        return
      }
    }
  }

  // Validate pr_ops and purchase_payment mode structure
  if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
    // Validate outlets
    for (let outletIdx = 0; outletIdx < form.outlets.length; outletIdx++) {
      const outlet = form.outlets[outletIdx]
      if (!outlet.outlet_id) {
        loading.value = false
        await Swal.fire({
          icon: 'warning',
          title: 'Outlet Diperlukan',
          text: `Silakan pilih outlet untuk outlet ke-${outletIdx + 1}`,
          confirmButtonText: 'OK',
          confirmButtonColor: '#F59E0B'
        })
        return
      }
      
      // Validate categories
      for (let categoryIdx = 0; categoryIdx < outlet.categories.length; categoryIdx++) {
        const category = outlet.categories[categoryIdx]
        if (!category.category_id) {
          loading.value = false
          const outletName = props.outlets.find(o => o.id_outlet == outlet.outlet_id)?.nama_outlet || 'Unknown'
          await Swal.fire({
            icon: 'warning',
            title: 'Category Diperlukan',
            text: `Silakan pilih category untuk outlet "${outletName}" category ke-${categoryIdx + 1}`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#F59E0B'
          })
          return
        }
        
        // Validate items
        if (category.items.length === 0 || category.items.some(item => !item.item_name || !item.qty || !item.unit || !item.unit_price)) {
          loading.value = false
          await Swal.fire({
            icon: 'warning',
            title: 'Items Diperlukan',
            text: `Silakan lengkapi semua items untuk setiap category`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#F59E0B'
          })
          return
        }
      }
    }
  }
  
  // Note: Validasi GM Finance dan GA Supervisor hanya sebagai warning di UI,
  // tidak menghalangi penyimpanan data. User tetap bisa simpan meskipun
  // tidak memenuhi requirement tersebut.
  
  // Calculate total amount from items
  form.amount = totalAmount.value
  
  // Prepare form data with approver IDs only
  const formData = {
    ...form,
    approvers: form.approvers.map(approver => approver.id)
  }
  
  // For kasbon mode, prepare kasbon data
  if (form.mode === 'kasbon') {
    // Backend expects kasbon_amount and kasbon_reason directly
    // Keep kasbon_amount and kasbon_reason in formData for backend processing
    formData.kasbon_amount = form.kasbon_amount || 0
    formData.kasbon_termin = form.kasbon_termin || 1
    formData.kasbon_reason = form.kasbon_reason || ''
    
    // Store kasbon_reason in description as well
    formData.description = form.kasbon_reason
    
    // Backend will create the item from kasbon_amount and kasbon_reason
    // So we don't need to create items array here
    formData.items = []
  } else if (form.mode === 'travel_application') {
    // Flatten travel items and generate item_name from type + name/notes
    formData.items = form.travel_items.map(item => {
      // Generate item_name based on item_type
      let generatedItemName = ''
      if (item.item_type === 'transport') {
        generatedItemName = 'Transport'
      } else if (item.item_type === 'allowance') {
        generatedItemName = `Allowance - ${item.allowance_recipient_name || ''}`
      } else if (item.item_type === 'others') {
        generatedItemName = `Others - ${item.others_notes || ''}`
      }
      
      return {
        item_name: generatedItemName,
        qty: item.qty,
        unit: item.unit,
        unit_price: item.unit_price,
        subtotal: item.subtotal,
        item_type: item.item_type,
        allowance_recipient_name: item.item_type === 'allowance' ? item.allowance_recipient_name : null,
        allowance_account_number: item.item_type === 'allowance' ? item.allowance_account_number : null,
        others_notes: item.item_type === 'others' ? item.others_notes : null
      }
    })
    
    // Add travel specific data
    formData.travel_outlet_ids = form.travel_outlets.map(outlet => outlet.outlet_id).filter(id => id)
    formData.travel_agenda = form.travel_agenda
    formData.travel_notes = form.travel_notes
    formData.description = form.travel_agenda // Use travel_agenda as description
    
    // Clear travel specific fields
    delete formData.travel_outlets
    delete formData.travel_items
    formData.outlet_id = null
  } else if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
    // For pr_ops and purchase_payment mode, flatten outlets structure to items array with outlet_id and category_id
    formData.items = []
    form.outlets.forEach(outlet => {
      outlet.categories.forEach(category => {
        category.items.forEach(item => {
          formData.items.push({
            ...item,
            outlet_id: outlet.outlet_id,
            category_id: category.category_id
          })
        })
      })
    })
    // Clear outlets from formData (backend doesn't need it)
    delete formData.outlets
    // For pr_ops, outlet_id and category_id in main form should be null
    formData.outlet_id = null
    formData.category_id = null
  } else {
    // For other modes, clear outlets
    delete formData.outlets
    delete formData.travel_outlets
    delete formData.travel_items
  }
  
  try {
    // Update the payment using PUT method
    const prId = props.purchaseRequisition.id
    
    // Upload new attachments if any
    uploading.value = true
    
    try {
      if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
        // Upload attachments per outlet for pr_ops and purchase_payment mode
        for (const [outletId, outletAtts] of Object.entries(outletAttachments.value)) {
          if (outletAtts && outletAtts.length > 0) {
            for (const attachment of outletAtts) {
              if (attachment.file) {
                const attachFormData = new FormData()
                attachFormData.append('file', attachment.file)
                attachFormData.append('outlet_id', outletId)
                
                try {
                  await axios.post(`/purchase-requisitions/${prId}/attachments`, attachFormData, {
                    headers: {
                      'Content-Type': 'multipart/form-data',
                    }
                  })
                } catch (error) {
                  console.error('Failed to upload attachment:', error)
                  // Continue with other attachments even if one fails
                }
              }
            }
          }
        }
      } else {
        // Upload simple attachments for other modes
        if (attachments.value.length > 0) {
          for (const attachment of attachments.value) {
            if (attachment.file) {
              const attachFormData = new FormData()
              attachFormData.append('file', attachment.file)
              
              try {
                await axios.post(`/purchase-requisitions/${prId}/attachments`, attachFormData, {
                  headers: {
                    'Content-Type': 'multipart/form-data',
                  }
                })
              } catch (error) {
                console.error('Failed to upload attachment:', error)
                // Continue with other attachments even if one fails
              }
            }
          }
        }
      }
      
      uploading.value = false
    } catch (error) {
      uploading.value = false
      console.error('Error uploading attachments:', error)
    }
    
    // Update the PR using PUT method
    router.put(`/purchase-requisitions/${prId}`, formData, {
      onSuccess: () => {
        loading.value = false
        router.visit(`/purchase-requisitions/${prId}`)
      },
      onError: (errors) => {
        loading.value = false
        console.error('Form errors:', errors)
      }
    })
    
  } catch (error) {
    loading.value = false
    console.error('Form errors:', error.response?.data || error.message)
    
    // Handle budget exceeded error
    if (error.response?.data?.errors?.budget_exceeded) {
      const budgetError = error.response.data.errors.budget_exceeded
      const errorMessage = Array.isArray(budgetError) ? budgetError[0] : budgetError
      await Swal.fire({
        icon: 'error',
        title: 'Budget Melebihi Limit',
        html: `<p class="text-sm">${errorMessage || 'Budget melebihi limit yang tersedia.'}</p>`,
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
      return
    }
    
    // Handle other validation errors
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors
      const errorMessages = Object.values(errors).flat().filter(msg => msg).join('<br>')
      if (errorMessages) {
        await Swal.fire({
          icon: 'error',
          title: 'Terjadi Kesalahan',
          html: `<p class="text-sm">${errorMessages}</p>`,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444'
        })
        return
      }
    }
    
    // Handle general errors
    await Swal.fire({
      icon: 'error',
      title: 'Terjadi Kesalahan',
      text: error.response?.data?.message || error.message || 'Gagal menyimpan data. Silakan coba lagi.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  }
}

const formatCurrency = (amount) => {
  if (!amount) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount)
}

// File upload functions for non-pr_ops mode
const handleFileUpload = async (event) => {
  const files = Array.from(event.target.files)
  
  for (const file of files) {
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
      alert(`File ${file.name} is too large. Maximum size is 10MB.`)
      continue
    }
    
    // Add to attachments list (will be uploaded after PR is created)
    attachments.value.push({
      file_name: file.name,
      file_size: file.size,
      file: file,
      mime_type: file.type,
      temp_id: Date.now() + Math.random() // Temporary ID for frontend
    })
  }
  
  // Clear the input
  event.target.value = ''
}

// File upload functions for pr_ops mode (per outlet)
const handleOutletFileUpload = async (event, outletId) => {
  const files = Array.from(event.target.files)
  
  if (!outletId) {
    alert('Please select an outlet first')
    event.target.value = ''
    return
  }
  
  // Initialize outlet attachments array if not exists
  if (!outletAttachments.value[outletId]) {
    outletAttachments.value[outletId] = []
  }
  
  for (const file of files) {
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
      alert(`File ${file.name} is too large. Maximum size is 10MB.`)
      continue
    }
    
    // Add to outlet attachments list
    outletAttachments.value[outletId].push({
      file_name: file.name,
      file_size: file.size,
      file: file,
      mime_type: file.type,
      outlet_id: outletId,
      temp_id: Date.now() + Math.random() // Temporary ID for frontend
    })
  }
  
  // Clear the input
  event.target.value = ''
}

const removeAttachment = (index) => {
  attachments.value.splice(index, 1)
}

const removeOutletAttachment = (outletId, index) => {
  if (outletAttachments.value[outletId]) {
    outletAttachments.value[outletId].splice(index, 1)
    // Clean up empty outlet attachments
    if (outletAttachments.value[outletId].length === 0) {
      delete outletAttachments.value[outletId]
    }
  }
}

// Get attachments for a specific outlet
const getOutletAttachments = (outletId) => {
  return outletAttachments.value[outletId] || []
}

const getFileIcon = (fileName) => {
  const extension = fileName.split('.').pop().toLowerCase()
  
  const iconMap = {
    'pdf': 'fa-file-pdf text-red-500',
    'doc': 'fa-file-word text-blue-500',
    'docx': 'fa-file-word text-blue-500',
    'xls': 'fa-file-excel text-green-500',
    'xlsx': 'fa-file-excel text-green-500',
    'ppt': 'fa-file-powerpoint text-orange-500',
    'pptx': 'fa-file-powerpoint text-orange-500',
    'jpg': 'fa-file-image text-purple-500',
    'jpeg': 'fa-file-image text-purple-500',
    'png': 'fa-file-image text-purple-500',
    'gif': 'fa-file-image text-purple-500',
    'txt': 'fa-file-alt text-gray-500',
    'zip': 'fa-file-archive text-yellow-500',
    'rar': 'fa-file-archive text-yellow-500',
  }
  
  return iconMap[extension] || 'fa-file text-gray-500'
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const downloadFile = (attachment) => {
  // For temporary files, we can't download them yet
  // This will be available after the PR is created
  if (attachment.temp_id) {
    alert('File will be available for download after the payment is created.')
    return
  }
  
  // For uploaded files, create download link
  const link = document.createElement('a')
  link.href = `/purchase-requisitions/attachments/${attachment.id}/download`
  link.download = attachment.file_name
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Watch approver search
watch(approverSearch, (newSearch) => {
  if (newSearch.length >= 2) {
    loadApprovers(newSearch)
  } else {
    showApproverDropdown.value = false
    approverResults.value = []
  }
})

// Load existing data on mount
onMounted(async () => {
  console.log('🔵 onMounted called!')
  const pr = props.purchaseRequisition
  if (!pr) {
    console.error('❌ No purchaseRequisition prop found')
    return
  }
  
  // Initialize form with data from props
  form.title = pr.title || ''
  form.description = pr.description || ''
  form.division_id = pr.division_id || ''
  form.category_id = pr.category_id || ''
  form.outlet_id = pr.outlet_id || ''
  form.ticket_id = pr.ticket_id || ''
  form.currency = pr.currency || 'IDR'
  form.priority = pr.priority || 'MEDIUM'
  form.mode = pr.mode || 'pr_ops'
  
  // Load mode-specific data
  const modeSpecific = props.modeSpecificData || {}
  if (form.mode === 'travel_application') {
    form.travel_agenda = modeSpecific.travel_agenda || ''
    form.travel_notes = modeSpecific.travel_notes || ''
  } else if (form.mode === 'kasbon') {
    form.kasbon_amount = parseFloat(modeSpecific.kasbon_amount) || 0
    form.kasbon_termin = parseInt(modeSpecific.kasbon_termin, 10) || 1
    form.kasbon_reason = modeSpecific.kasbon_reason || ''
  }
  
  console.log('✅ Purchase Requisition data:', pr)
  console.log('✅ Approval Flows (camelCase):', pr.approvalFlows)
  console.log('✅ Approval Flows (snake_case):', pr.approval_flows)
  console.log('✅ All keys in pr:', Object.keys(pr))
  
  // Inertia.js sends relationships as snake_case, so use approval_flows
  const approvalFlows = pr.approval_flows || pr.approvalFlows
  
  console.log('✅ Approval Flows (final):', approvalFlows)
  console.log('✅ Approval Flows type:', typeof approvalFlows)
  console.log('✅ Approval Flows is array?', Array.isArray(approvalFlows))
  console.log('✅ Approval Flows length:', approvalFlows?.length)
  
  // Load approvers
  if (approvalFlows && Array.isArray(approvalFlows) && approvalFlows.length > 0) {
    console.log('✅ Found approval flows, processing...')
    const approvers = approvalFlows
      .sort((a, b) => (a.approval_level || 0) - (b.approval_level || 0))
      .map(flow => {
        const approver = flow.approver
        if (!approver) {
          console.log('Flow without approver:', flow)
          return null
        }
        
        // Map approver to match expected structure (name, email, jabatan)
        // Handle both nested jabatan object and direct jabatan string
        let jabatanName = ''
        if (approver.jabatan) {
          if (typeof approver.jabatan === 'object' && approver.jabatan.nama_jabatan) {
            jabatanName = approver.jabatan.nama_jabatan
          } else if (typeof approver.jabatan === 'string') {
            jabatanName = approver.jabatan
          }
        }
        
        const mappedApprover = {
          id: approver.id,
          name: approver.nama_lengkap || approver.name || approver.nik || '',
          email: approver.email || '',
          jabatan: jabatanName
        }
        
        console.log('Flow:', flow)
        console.log('Approver raw:', approver)
        console.log('Mapped approver:', mappedApprover)
        return mappedApprover
      })
      .filter(approver => approver !== null && approver !== undefined && approver.id)
    
    // Clear existing approvers and add new ones to maintain reactivity
    // Direct assignment should work with reactive()
    if (approvers.length > 0) {
      console.log('📝 About to assign approvers. Current form.approvers.length:', form.approvers.length)
      console.log('📝 Approvers to assign:', approvers)
      
      // Clear array first using splice
      form.approvers.splice(0, form.approvers.length)
      console.log('📝 After clearing, form.approvers.length:', form.approvers.length)
      
      // Add all approvers one by one
      approvers.forEach((approver, idx) => {
        console.log(`📝 Pushing approver ${idx + 1}:`, approver)
        form.approvers.push(approver)
      })
      
      console.log('✅ Approvers loaded successfully!')
      console.log('Final approvers array:', form.approvers)
      console.log('Approvers length:', form.approvers.length)
      console.log('First approver:', form.approvers[0])
      console.log('form.approvers is reactive?', form.approvers)
      
      // Force Vue to update by using nextTick
      await nextTick()
      console.log('After nextTick - Approvers length:', form.approvers.length)
      console.log('After nextTick - First approver:', form.approvers[0])
      console.log('ApproversList computed value:', approversList.value)
      
      // Double check after a short delay
      setTimeout(() => {
        console.log('After 500ms - Approvers length:', form.approvers.length)
        console.log('After 500ms - Form approvers:', form.approvers)
        if (form.approvers.length === 0) {
          console.error('❌ ERROR: Approvers were assigned but array is empty after 500ms!')
        }
      }, 500)
    } else {
      console.warn('⚠️ No approvers to load! approvers array is empty')
    }
  } else {
    console.error('❌ No approvalFlows found or empty.')
    console.error('pr.approvalFlows:', pr.approvalFlows)
    console.error('pr.approval_flows:', pr.approval_flows)
    console.error('approvalFlows (final):', approvalFlows)
    console.error('approvalFlows type:', typeof approvalFlows)
    console.error('approvalFlows is array?', Array.isArray(approvalFlows))
    console.error('approvalFlows length:', approvalFlows?.length)
  }
  
  // Load attachments
  if (pr.attachments && pr.attachments.length > 0) {
    if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
      // Group attachments by outlet
      pr.attachments.forEach(attachment => {
        const outletId = attachment.outlet_id || 'no-outlet'
        if (!outletAttachments.value[outletId]) {
          outletAttachments.value[outletId] = []
        }
        outletAttachments.value[outletId].push(attachment)
      })
    } else {
      attachments.value = pr.attachments
    }
  }
  
  // Load items based on mode
  if (pr.items && pr.items.length > 0) {
    if (form.mode === 'pr_ops' || form.mode === 'purchase_payment') {
      // Group items by outlet and category
      const outletMap = {}
      pr.items.forEach(item => {
        const outletId = item.outlet_id || pr.outlet_id || 'no-outlet'
        const categoryId = item.category_id || pr.category_id || 'no-category'
        
        if (!outletMap[outletId]) {
          outletMap[outletId] = {}
        }
        if (!outletMap[outletId][categoryId]) {
          outletMap[outletId][categoryId] = []
        }
        
        const qty = parseFloat(item.qty) || 0
        const unitPrice = parseFloat(item.unit_price) || 0
        const subtotal = qty * unitPrice
        
        outletMap[outletId][categoryId].push({
          item_name: item.item_name || '',
          qty: qty,
          unit: item.unit || '',
          unit_price: unitPrice,
          subtotal: subtotal,
          item_type: item.item_type || null,
          allowance_recipient_name: item.allowance_recipient_name || '',
          allowance_account_number: item.allowance_account_number || '',
          others_notes: item.others_notes || ''
        })
      })
      
      // Convert to form structure
      form.outlets = []
      Object.keys(outletMap).forEach(outletId => {
        const categories = []
        Object.keys(outletMap[outletId]).forEach(categoryId => {
          categories.push({
            category_id: categoryId !== 'no-category' ? categoryId : '',
            items: outletMap[outletId][categoryId]
          })
        })
        form.outlets.push({
          outlet_id: outletId !== 'no-outlet' ? outletId : '',
          categories: categories
        })
      })
      
      // If no outlets, add one empty outlet
      if (form.outlets.length === 0) {
        form.outlets = [newOutlet()]
      }
    } else if (form.mode === 'travel_application') {
      // Load travel items
      form.travel_items = pr.items.map(item => {
        const qty = parseFloat(item.qty) || 0
        const unitPrice = parseFloat(item.unit_price) || 0
        const subtotal = qty * unitPrice
        
        return {
          item_type: item.item_type || 'transport',
          qty: qty,
          unit: item.unit || '',
          unit_price: unitPrice,
          subtotal: subtotal,
          allowance_recipient_name: item.allowance_recipient_name || '',
          allowance_account_number: item.allowance_account_number || '',
          others_notes: item.others_notes || ''
        }
      })
      
      // Load travel outlets from modeSpecificData
      const modeSpecific = props.modeSpecificData || {}
      if (modeSpecific.travel_outlet_ids && modeSpecific.travel_outlet_ids.length > 0) {
        form.travel_outlets = modeSpecific.travel_outlet_ids.map(outletId => ({
          outlet_id: outletId,
          categories: []
        }))
      }
      
      if (form.travel_items.length === 0) {
        form.travel_items = [newTravelItem()]
      }
      if (form.travel_outlets.length === 0) {
        form.travel_outlets = [newOutlet()]
      }
    } else if (form.mode === 'kasbon') {
      // Kasbon data is already loaded from modeSpecificData
      // No need to load items
    } else {
      // Legacy mode or other modes: load simple items
      form.items = pr.items.map(item => {
        const qty = parseFloat(item.qty) || 0
        const unitPrice = parseFloat(item.unit_price) || 0
        const subtotal = qty * unitPrice
        
        return {
          item_name: item.item_name || '',
          qty: qty,
          unit: item.unit || '',
          unit_price: unitPrice,
          subtotal: subtotal
        }
      })
      
      if (form.items.length === 0) {
        form.items = [newItem()]
      }
    }
  }
  
  // Load category details if category is selected
  if (form.category_id) {
    loadCategoryDetails()
    if (form.mode === 'travel_application') {
      // Load budget info for travel application
      const outletId = form.travel_outlets.length > 0 && form.travel_outlets[0].outlet_id 
        ? form.travel_outlets[0].outlet_id 
        : null
      loadBudgetInfoForTravel(outletId)
    } else if (form.mode !== 'pr_ops' && form.mode !== 'purchase_payment' && form.mode !== 'kasbon') {
      loadBudgetInfo()
    }
  }
  
  // Check kasbon period if kasbon mode
  if (form.mode === 'kasbon') {
    if (!isWithinKasbonPeriod()) {
      kasbonOutOfPeriod.value = true
    } else {
      kasbonOutOfPeriod.value = false
    }
  }
})
</script>