<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 md:px-8">
      <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl p-8">
        <h1 class="text-2xl font-bold mb-8 flex items-center gap-2 text-green-700">
          <i class="fa-solid fa-recycle text-green-500"></i> Input Internal Use & Waste Outlet
        </h1>
        <form @submit.prevent="submit" class="space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tipe</label>
              <select v-model="form.type" class="input input-bordered w-full" required>
                <option value="">Pilih Tipe</option>
                <option value="internal_use">Internal Use</option>
                <option value="spoil">Spoil</option>
                <option value="waste">Waste</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Tanggal</label>
              <input type="date" v-model="form.date" class="input input-bordered w-full" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-600 mb-1">Outlet</label>
              <select v-model="form.outlet_id" :disabled="outletDisabled" class="input input-bordered w-full" required>
                <option value="">Pilih Outlet</option>
                <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Items</label>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(item, idx) in form.items" :key="idx">
                    <td class="px-3 py-2 min-w-[200px]">
                      <div class="relative">
                        <input
                          :id="`item-input-${idx}`"
                          type="text"
                          v-model="item.item_name"
                          @input="onItemInput(idx, $event)"
                          @focus="onItemInput(idx, $event)"
                          @blur="onItemBlur(idx)"
                          @keydown.down="onItemKeydown(idx, $event)"
                          @keydown.up="onItemKeydown(idx, $event)"
                          @keydown.enter="onItemKeydown(idx, $event)"
                          @keydown.esc="onItemKeydown(idx, $event)"
                          class="input input-bordered w-full"
                          required
                          autocomplete="off"
                          placeholder="Cari nama item..."
                        />
                        <Teleport to="body">
                          <div v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                            :style="getDropdownStyle(idx)"
                            :id="`autocomplete-dropdown-${idx}`"
                            class="fixed z-[99999] bg-white border border-blue-200 rounded shadow max-w-xs w-[260px] max-h-96 overflow-auto mt-1"
                          >
                            <div v-for="(s, sidx) in item.suggestions" :key="s.id"
                              :id="`autocomplete-item-${idx}-${sidx}`"
                              @mousedown.prevent="selectItem(idx, s)"
                              :class="['px-3 py-2 flex justify-between items-center cursor-pointer', item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50']"
                            >
                              <div>
                                <div class="font-medium">{{ s.name }}</div>
                                <div class="text-xs text-gray-500">{{ s.sku }}</div>
                              </div>
                              <div class="text-sm text-gray-600">{{ s.unit_small || s.unit || '' }}</div>
                            </div>
                          </div>
                        </Teleport>
                        <div v-if="item.loading" class="absolute right-2 top-2">
                          <i class="fa fa-spinner fa-spin text-blue-400"></i>
                        </div>
                      </div>
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <input type="number" min="0.01" step="0.01" v-model.number="item.qty" class="input input-bordered w-full" required />
                    </td>
                    <td class="px-3 py-2 min-w-[100px]">
                      <select v-model="item.unit_id" class="input input-bordered w-full" required>
                        <option value="">Pilih Unit</option>
                        <option v-for="u in item.unitOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
                      </select>
                    </td>
                    <td class="px-3 py-2 min-w-[120px]">
                      <input type="text" v-model="item.note" class="input input-bordered w-full" />
                    </td>
                    <td class="px-3 py-2">
                      <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" @click="addItem" class="mt-3 px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 font-semibold"><i class="fa fa-plus"></i> Tambah Item</button>
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">Catatan Umum</label>
            <textarea v-model="form.notes" class="input input-bordered w-full" rows="2" placeholder="Catatan tambahan"></textarea>
          </div>
          <div class="flex justify-end gap-2 mt-8">
            <button type="button" class="btn btn-ghost px-6 py-2 rounded-lg" @click="goBack">Batal</button>
            <button type="submit" class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all" :disabled="loading">
              <span v-if="loading">
                <i class="fa fa-spinner fa-spin"></i> Menyimpan...
              </span>
              <span v-else>
                Simpan
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  outlets: Array,
  items: Array
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')

function newItem() {
  return {
    item_id: '',
    item_name: '',
    qty: '',
    unit_id: '',
    note: '',
    unitOptions: [],
    suggestions: [],
    showDropdown: false,
    highlightedIndex: -1,
    loading: false
  }
}

const form = ref({
  type: 'internal_use',
  date: '',
  outlet_id: userOutletId.value == 1 ? '' : userOutletId.value,
  notes: '',
  items: [newItem()]
})

const outletDisabled = computed(() => userOutletId.value != 1)
const loading = ref(false)

function addItem() {
  form.value.items.push(newItem())
}
function removeItem(idx) {
  if (form.value.items.length === 1) return
  form.value.items.splice(idx, 1)
}

async function fetchItemSuggestions(idx, q) {
  if (!q || q.length < 2) {
    form.value.items[idx].suggestions = [];
    form.value.items[idx].highlightedIndex = -1;
    return;
  }
  form.value.items[idx].loading = true;
  try {
    const res = await axios.get('/items/search-for-outlet-transfer', {
      params: {
        q: q,
        outlet_id: form.value.outlet_id,
        region_id: page.props.auth?.user?.region_id
      }
    });
    form.value.items[idx].suggestions = res.data;
    form.value.items[idx].showDropdown = true;
    form.value.items[idx].highlightedIndex = 0;
  } finally {
    form.value.items[idx].loading = false;
  }
}

function onItemInput(idx, e) {
  const value = e.target.value;
  form.value.items[idx].item_id = '';
  form.value.items[idx].item_name = value;
  form.value.items[idx].showDropdown = true;
  fetchItemSuggestions(idx, value);
}

function selectItem(idx, item) {
  form.value.items[idx].item_id = item.id;
  form.value.items[idx].item_name = item.name;
  form.value.items[idx].suggestions = [];
  form.value.items[idx].showDropdown = false;
  form.value.items[idx].highlightedIndex = -1;
  // Fetch unit options for selected item
  fetchUnitOptions(idx, item.id);
}

async function fetchUnitOptions(idx, itemId) {
  if (itemId) {
    const res = await axios.get(`/outlet-internal-use-waste/get-item-units/${itemId}`)
    form.value.items[idx].unitOptions = res.data.units
    form.value.items[idx].unit_id = ''
  } else {
    form.value.items[idx].unitOptions = []
    form.value.items[idx].unit_id = ''
  }
}

function onItemBlur(idx) {
  setTimeout(() => {
    form.value.items[idx].showDropdown = false;
  }, 200);
}

function onItemKeydown(idx, e) {
  const item = form.value.items[idx];
  if (!item.showDropdown || !item.suggestions.length) return;
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex + 1) % item.suggestions.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    item.highlightedIndex = (item.highlightedIndex - 1 + item.suggestions.length) % item.suggestions.length;
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (item.highlightedIndex >= 0 && item.suggestions[item.highlightedIndex]) {
      selectItem(idx, item.suggestions[item.highlightedIndex]);
    }
  } else if (e.key === 'Escape') {
    item.showDropdown = false;
  }
}

async function submit() {
  loading.value = true
  console.log('Submitting form data:', form.value)
  try {
    await router.post(route('outlet-internal-use-waste.store'), form.value, {
      onSuccess: () => {
        console.log('Form submitted successfully')
        Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: 'Data berhasil disimpan!',
          timer: 1500,
          showConfirmButton: false
        })
        loading.value = false
      },
      onError: (errors) => {
        console.error('Error submitting form:', errors)
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: 'Gagal menyimpan data. Silakan cek input Anda.',
        })
        loading.value = false
      },
      onFinish: () => {
        loading.value = false
      }
    })
  } catch (e) {
    console.error('Exception during form submission:', e)
    loading.value = false
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: 'Terjadi kesalahan sistem.',
    })
  }
}

function goBack() {
  router.visit(route('outlet-internal-use-waste.index'))
}

function getDropdownStyle(idx) {
  const input = document.getElementById(`item-input-${idx}`);
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    position: 'fixed',
    left: `${rect.left}px`,
    top: `${rect.bottom}px`,
    width: `${rect.width}px`,
    zIndex: 99999
  };
}
</script>

<style scoped>
.input { @apply border border-gray-300 rounded px-3 py-2; }
</style>