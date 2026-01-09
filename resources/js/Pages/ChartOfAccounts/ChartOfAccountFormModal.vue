<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  chartOfAccount: Object,
  parents: Array,
  menus: Array,
  allCoAs: Array, // All COAs for counter account dropdown
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  type: 'Asset',
  parent_id: null,
  description: '',
  is_active: true,
  show_in_menu_payment: false,
  static_or_dynamic: null,
  menu_id: [],
  mode_payment: [],
  budget_limit: null,
  default_counter_account_id: null,
});

const menuSearch = ref('');
const showMenuDropdown = ref(false);
const menuDropdownRef = ref(null);

const filteredMenus = computed(() => {
  if (!props.menus || !Array.isArray(props.menus)) return [];
  
  let menus = props.menus;
  
  // Filter by search if there's a search term
  if (menuSearch.value) {
    const search = menuSearch.value.toLowerCase();
    menus = props.menus.filter(menu => 
      (menu.name && menu.name.toLowerCase().includes(search)) ||
      (menu.code && menu.code.toLowerCase().includes(search)) ||
      (menu.route && menu.route.toLowerCase().includes(search)) ||
      (menu.parent && menu.parent.name && menu.parent.name.toLowerCase().includes(search))
    );
  }
  
  // Sort: parent menus first, then children
  return menus.sort((a, b) => {
    // If both have parent or both don't have parent, sort by name
    if ((a.parent_id && b.parent_id) || (!a.parent_id && !b.parent_id)) {
      return (a.name || '').localeCompare(b.name || '');
    }
    // Parent menus come first
    return a.parent_id ? 1 : -1;
  });
});

const getMenuDisplayName = (menu) => {
  if (menu.parent) {
    return `${menu.parent.name} > ${menu.name}`;
  }
  return menu.name;
};

function toggleMenu(menu) {
  const menuId = menu.id;
  if (!form.menu_id || !Array.isArray(form.menu_id)) {
    form.menu_id = [];
  }
  
  const index = form.menu_id.indexOf(menuId);
  if (index > -1) {
    // Remove if already selected
    form.menu_id.splice(index, 1);
  } else {
    // Add if not selected
    form.menu_id.push(menuId);
  }
  
  // Update search display
  updateMenuSearchDisplay();
}

function isMenuSelected(menuId) {
  if (!form.menu_id || !Array.isArray(form.menu_id)) return false;
  return form.menu_id.includes(menuId);
}

function updateMenuSearchDisplay() {
  if (!form.menu_id || form.menu_id.length === 0) {
    menuSearch.value = '';
    return;
  }
  
  const selectedMenus = props.menus.filter(m => form.menu_id.includes(m.id));
  if (selectedMenus.length === 0) {
    menuSearch.value = '';
  } else if (selectedMenus.length === 1) {
    menuSearch.value = getMenuDisplayName(selectedMenus[0]);
  } else {
    menuSearch.value = `${selectedMenus.length} menu dipilih`;
  }
}

function getMenuNameById(menuId) {
  if (!props.menus) return '';
  const menu = props.menus.find(m => m.id === menuId);
  return menu ? getMenuDisplayName(menu) : `Menu #${menuId}`;
}

function clearMenu() {
  form.menu_id = [];
  menuSearch.value = '';
  showMenuDropdown.value = false;
}

function handleClickOutside(event) {
  if (menuDropdownRef.value && !menuDropdownRef.value.contains(event.target)) {
    showMenuDropdown.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.chartOfAccount) {
    form.code = props.chartOfAccount.code || '';
    form.name = props.chartOfAccount.name || '';
    form.type = props.chartOfAccount.type || 'Asset';
    form.parent_id = props.chartOfAccount.parent_id || null;
    form.description = props.chartOfAccount.description || '';
    form.is_active = props.chartOfAccount.is_active == 1;
    form.show_in_menu_payment = props.chartOfAccount.show_in_menu_payment == 1;
    form.static_or_dynamic = props.chartOfAccount.static_or_dynamic || null;
    form.menu_id = props.chartOfAccount.menu_id || null;
    // Handle mode_payment as array (from JSON in database)
    if (props.chartOfAccount.mode_payment) {
      if (Array.isArray(props.chartOfAccount.mode_payment)) {
        form.mode_payment = props.chartOfAccount.mode_payment;
      } else {
        // If it's still a string (old data), convert to array
        form.mode_payment = [props.chartOfAccount.mode_payment];
      }
    } else {
      form.mode_payment = [];
    }
    form.budget_limit = props.chartOfAccount.budget_limit || null;
    form.default_counter_account_id = props.chartOfAccount.default_counter_account_id || null;
    
    // Set menu search value if menu_id exists
    if (form.menu_id && props.menus) {
      const selectedMenu = props.menus.find(m => m.id === form.menu_id);
      if (selectedMenu) {
        menuSearch.value = selectedMenu.name;
      }
    } else {
      menuSearch.value = '';
    }
  } else if (val && props.mode === 'create') {
    form.reset();
    form.type = 'Asset';
    // Jika ada parent_id dari props (untuk create child), set parent_id
    form.parent_id = props.chartOfAccount?.parent_id || null;
    form.is_active = true;
    form.show_in_menu_payment = false;
    form.static_or_dynamic = null;
    form.menu_id = [];
    form.mode_payment = [];
    form.budget_limit = null;
    form.default_counter_account_id = null;
    menuSearch.value = '';
  }
});

watch(() => form.menu_id, () => {
  updateMenuSearchDisplay();
}, { deep: true });

watch(() => form.static_or_dynamic, (val) => {
  if (val !== 'static') {
    form.menu_id = null;
    menuSearch.value = '';
  }
});

const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  
  if (props.mode === 'create') {
    form.post('/chart-of-accounts', {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Chart of Account berhasil dibuat!', 'success');
        emit('success');
      },
      onError: (errors) => {
        Swal.fire('Error', Object.values(errors).flat().join(', '), 'error');
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  } else {
    form.put(`/chart-of-accounts/${props.chartOfAccount.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Chart of Account berhasil diperbarui!', 'success');
        emit('success');
      },
      onError: (errors) => {
        Swal.fire('Error', Object.values(errors).flat().join(', '), 'error');
      },
      onFinish: () => {
        isSubmitting.value = false;
      },
    });
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
      <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-900">
          {{ mode === 'create' ? 'Buat Chart of Account Baru' : 'Edit Chart of Account' }}
        </h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
          <i class="fa-solid fa-times text-xl"></i>
        </button>
      </div>
      
      <form @submit.prevent="submit" class="p-6">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Code *</label>
            <input
              v-model="form.code"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Contoh: 1000"
            />
            <div v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Nama Chart of Account"
            />
            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
            <select
              v-model="form.type"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="Asset">Asset</option>
              <option value="Liability">Liability</option>
              <option value="Equity">Equity</option>
              <option value="Revenue">Revenue</option>
              <option value="Expense">Expense</option>
            </select>
            <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Parent Account (Optional)</label>
            <select
              v-model="form.parent_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Tidak ada parent (Root Account)</option>
              <option v-for="parent in parents" :key="parent.id" :value="parent.id">
                {{ parent.display || `${parent.code} - ${parent.name} (${parent.type})` }}
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500">Pilih parent jika ini adalah child account</p>
            <div v-if="form.errors.parent_id" class="mt-1 text-sm text-red-600">{{ form.errors.parent_id }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Deskripsi Chart of Account"
            ></textarea>
            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Budget Limit</label>
            <div class="relative">
              <span class="absolute left-3 top-2 text-gray-500">Rp</span>
              <input
                v-model="form.budget_limit"
                type="number"
                step="0.01"
                min="0"
                class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="0.00"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500">Masukkan budget limit untuk COA ini (opsional)</p>
            <div v-if="form.errors.budget_limit" class="mt-1 text-sm text-red-600">{{ form.errors.budget_limit }}</div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Default Counter Account (Akun Lawan)
            </label>
            <select
              v-model="form.default_counter_account_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Tidak ada (kosongkan)</option>
              <option 
                v-for="coa in allCoAs" 
                :key="coa.id" 
                :value="coa.id"
                :disabled="mode === 'edit' && chartOfAccount && coa.id === chartOfAccount.id"
              >
                {{ coa.code }} - {{ coa.name }} ({{ coa.type }})
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500">
              Pilih akun lawan default untuk memudahkan pembuatan jurnal. 
              Saat membuat jurnal dengan akun ini, akun lawan akan terisi otomatis.
            </p>
            <div v-if="form.errors.default_counter_account_id" class="mt-1 text-sm text-red-600">{{ form.errors.default_counter_account_id }}</div>
          </div>
          
          <div>
            <label class="flex items-center gap-2">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <span class="text-sm font-medium text-gray-700">Active</span>
            </label>
          </div>
          
          <div class="border-t pt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Static or Dynamic</h4>
            
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Static or Dynamic</label>
              <select
                v-model="form.static_or_dynamic"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option :value="null">Pilih tipe</option>
                <option value="static">Static</option>
                <option value="dynamic">Dynamic</option>
              </select>
              <p class="mt-1 text-xs text-gray-500">Pilih tipe COA: Static (terikat menu) atau Dynamic (tidak terikat menu)</p>
              <div v-if="form.errors.static_or_dynamic" class="mt-1 text-sm text-red-600">{{ form.errors.static_or_dynamic }}</div>
            </div>
            
            <div v-if="form.static_or_dynamic === 'static'" class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pilih Menu 
                <span class="text-red-500">*</span>
              </label>
              
              <!-- Search Input -->
              <div class="mb-3">
                <input
                  v-model="menuSearch"
                  @focus="showMenuDropdown = true"
                  @input="showMenuDropdown = true"
                  @click="showMenuDropdown = true"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Cari menu..."
                  autocomplete="off"
                />
                <div v-if="form.menu_id && form.menu_id.length > 0" class="mt-2 flex items-center gap-2">
                  <span class="text-xs text-gray-600">{{ form.menu_id.length }} menu dipilih</span>
                  <button
                    type="button"
                    @click="clearMenu"
                    class="text-xs text-red-600 hover:text-red-700"
                  >
                    Hapus semua
                  </button>
                </div>
              </div>
              
              <!-- Menu List with Checkboxes -->
              <div class="relative" ref="menuDropdownRef">
                <div
                  v-if="showMenuDropdown || menuSearch"
                  class="border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto bg-white"
                >
                  <div
                    v-if="filteredMenus.length > 0"
                  >
                    <label
                      v-for="menu in filteredMenus"
                      :key="menu.id"
                      @click.stop
                      class="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 cursor-pointer border-b border-gray-200 last:border-b-0 transition"
                      :class="{ 'bg-blue-50': isMenuSelected(menu.id) }"
                    >
                      <input
                        type="checkbox"
                        :checked="isMenuSelected(menu.id)"
                        @change="toggleMenu(menu)"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <div class="flex-1">
                        <div class="flex items-center gap-2">
                          <i v-if="menu.icon" :class="menu.icon" class="text-gray-400 text-sm"></i>
                          <div class="font-medium text-gray-900">{{ getMenuDisplayName(menu) }}</div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                          <span class="font-mono">{{ menu.code }}</span>
                          <span v-if="menu.route" class="ml-2">• {{ menu.route }}</span>
                        </div>
                      </div>
                      <span v-if="!menu.parent_id" class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded">Parent</span>
                    </label>
                  </div>
                  <div
                    v-else-if="props.menus && props.menus.length === 0"
                    class="p-4 text-center text-gray-500"
                  >
                    Tidak ada menu tersedia
                  </div>
                  <div
                    v-else
                    class="p-4 text-center text-gray-500"
                  >
                    Tidak ada menu ditemukan
                  </div>
                </div>
                
                <!-- Show selected menus when dropdown is closed -->
                <div v-if="!showMenuDropdown && form.menu_id && form.menu_id.length > 0" class="mt-2 space-y-1">
                  <div class="text-xs font-medium text-gray-700 mb-1">Menu yang dipilih:</div>
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="menuId in form.menu_id"
                      :key="menuId"
                      class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded"
                    >
                      {{ getMenuNameById(menuId) }}
                      <button
                        type="button"
                        @click="toggleMenu({ id: menuId })"
                        class="text-blue-600 hover:text-blue-800"
                      >
                        <i class="fa-solid fa-times"></i>
                      </button>
                    </span>
                  </div>
                </div>
              </div>
              
              <p class="mt-1 text-xs text-gray-500">Pilih satu atau lebih menu dari erp_menu yang akan digunakan untuk COA ini</p>
              <div v-if="form.errors.menu_id" class="mt-1 text-sm text-red-600">{{ form.errors.menu_id }}</div>
            </div>
          </div>
          
          <div class="border-t pt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Payment Menu Settings</h4>
            
            <div class="mb-4">
              <label class="flex items-center gap-2">
                <input
                  v-model="form.show_in_menu_payment"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <span class="text-sm font-medium text-gray-700">Show in Menu Payment</span>
              </label>
              <p class="mt-1 text-xs text-gray-500">Centang untuk menampilkan COA ini di menu payment</p>
              <div v-if="form.errors.show_in_menu_payment" class="mt-1 text-sm text-red-600">{{ form.errors.show_in_menu_payment }}</div>
            </div>
            
            <div v-if="form.show_in_menu_payment" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Mode Payment 
                  <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2 border border-gray-300 rounded-lg p-3">
                  <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input
                      type="checkbox"
                      value="pr_ops"
                      v-model="form.mode_payment"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span class="text-sm text-gray-700">Purchase Requisition (PR Ops)</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input
                      type="checkbox"
                      value="purchase_payment"
                      v-model="form.mode_payment"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span class="text-sm text-gray-700">Payment Application</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input
                      type="checkbox"
                      value="travel_application"
                      v-model="form.mode_payment"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span class="text-sm text-gray-700">Travel Application</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input
                      type="checkbox"
                      value="kasbon"
                      v-model="form.mode_payment"
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span class="text-sm text-gray-700">Kasbon</span>
                  </label>
                </div>
                <p class="mt-1 text-xs text-gray-500">Pilih satu atau lebih mode payment sesuai dengan menu Purchase Requisition Ops</p>
                <div v-if="form.errors.mode_payment" class="mt-1 text-sm text-red-600">{{ form.errors.mode_payment }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition"
          >
            Batal
          </button>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
            {{ isSubmitting ? 'Menyimpan...' : (mode === 'create' ? 'Simpan' : 'Update') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

