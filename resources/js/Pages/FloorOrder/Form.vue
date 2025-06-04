<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { Teleport } from 'vue';
import Swal from 'sweetalert2';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  fo_mode: String,
  input_mode: String,
  user: Object,
  order: Object,
});
const { props: globalProps } = usePage();
const userGlobal = computed(() => globalProps.value?.auth?.user || {});

console.log('DEBUG props.user', props.user);
console.log('DEBUG userGlobal', userGlobal.value);

const mode = ref(props.input_mode || 'pc');
const selectedFOMode = ref(props.fo_mode || '');
const showScheduleModal = ref(false);
const scheduleData = ref(null);
const loading = ref(false);
const error = ref('');
const jadwalSiap = ref(false);
const outletSchedules = ref([]);
const loadingItems = ref(false);
const todaySchedules = ref([]);
const todayScheduleNotes = ref('');
const showPreview = ref(false);
const isSubmitting = ref(false);

// Set tanggal hari ini
const today = new Date();
const yyyy = today.getFullYear();
const mm = String(today.getMonth() + 1).padStart(2, '0');
const dd = String(today.getDate()).padStart(2, '0');
const tanggal = ref(`${yyyy}-${mm}-${dd}`);

const form = ref({
  tanggal: tanggal.value,
  description: '',
  fo_schedule_id: null,
  items: [
    { item_id: '', item_name: '', qty: '', unit: '', price: 0, subtotal: 0, suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, _rowKey: Date.now() + '-' + Math.random() }
  ],
});

const itemInputRefs = ref([]);

// Jika ada props.order, load ke form
if (props.order) {
  form.value.tanggal = props.order.tanggal;
  form.value.description = props.order.description;
  form.value.items = props.order.items?.map(item => ({ ...item, suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, _rowKey: Date.now() + '-' + Math.random() })) || form.value.items;
  selectedFOMode.value = props.order.fo_mode;
  mode.value = props.order.input_mode;
}

const draftId = ref(props.order?.id || null);

const region_id = computed(() =>
  props.user.region_id ||
  props.user.outlet?.region_id ||
  userGlobal.value.region_id ||
  userGlobal.value.outlet?.region_id
);
const outlet_id = computed(() => props.user.id_outlet || userGlobal.value.id_outlet);

function addItem() {
  // Validasi khusus FO Tambahan: max 6 item
  if (selectedFOMode.value === 'RO Tambahan' && form.value.items.length >= 6) {
    Swal.fire({
      icon: 'warning',
      title: 'Maksimal 6 Item',
      text: 'Anda hanya bisa input maksimal 6 item untuk FO Tambahan.',
      confirmButtonColor: '#3085d6',
    });
    return;
  }
  form.value.items.push({ item_id: '', item_name: '', qty: '', unit: '', price: 0, subtotal: 0, suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, _rowKey: Date.now() + '-' + Math.random() });
  nextTick(() => {
    setTimeout(() => {
      const lastIdx = form.value.items.length - 1;
      if (itemInputRefs.value[lastIdx]) {
        itemInputRefs.value[lastIdx].focus();
      }
    }, 0);
  });
}
function removeItem(idx) {
  if (form.value.items.length === 1) return;
  form.value.items.splice(idx, 1);
}

const itemsByFOSchedule = ref([]);
const categories = ref([]); // Untuk mode tab, jika ingin dikelompokkan per kategori

// Ambil item valid setelah FO Schedule aktif
async function fetchItemsByFOSchedule(foScheduleId) {
  loadingItems.value = true;
  try {
    console.log('DEBUG region_id', region_id.value, 'outlet_id', outlet_id.value);
    const res = await axios.get(`/api/items/by-fo-schedule/${foScheduleId}`, {
      params: { region_id: region_id.value, outlet_id: outlet_id.value }
    });
    itemsByFOSchedule.value = res.data.items || [];
    // Log hasil fetch
    console.log('itemsByFOSchedule:', itemsByFOSchedule.value);
    // Optional: Kelompokkan per kategori jika ingin di mode tab
    const grouped = {};
    itemsByFOSchedule.value.forEach(item => {
      // Gunakan nama kategori default jika kosong/null
      const catName = item.category_name && item.category_name.trim() !== '' ? item.category_name : 'Tanpa Kategori';
      if (!grouped[item.category_id]) grouped[item.category_id] = { id: item.category_id, name: catName, items: [] };
      grouped[item.category_id].items.push({ ...item, qty: 0 });
    });
    categories.value = Object.values(grouped);
    // Log hasil grouping
    console.log('categories:', categories.value);
  } catch (e) {
    itemsByFOSchedule.value = [];
    categories.value = [];
  } finally {
    loadingItems.value = false;
  }
}

// Ganti allItems dengan itemsByFOSchedule
function fetchItemSuggestions(idx, q) {
  console.log('fetchItemSuggestions', { idx, q, itemsByFOSchedule: itemsByFOSchedule.value });
  if (!q || q.length < 2) {
    form.value.items[idx].suggestions = [];
    form.value.items[idx].highlightedIndex = -1;
    return;
  }
  form.value.items[idx].loading = true;
  setTimeout(() => {
    form.value.items[idx].suggestions = itemsByFOSchedule.value.filter(item => item.name.toLowerCase().includes(q.toLowerCase()) || item.sku?.toLowerCase().includes(q.toLowerCase()));
    form.value.items[idx].showDropdown = true;
    form.value.items[idx].highlightedIndex = 0;
    form.value.items[idx].loading = false;
    console.log('suggestions:', form.value.items[idx].suggestions, 'showDropdown:', form.value.items[idx].showDropdown);
  }, 300);
}

function selectItem(idx, item) {
  form.value.items[idx].item_id = item.id;
  form.value.items[idx].item_name = item.name;
  form.value.items[idx].unit = item.unit_medium_name || item.unit_medium || item.unit_small || item.unit || '';
  form.value.items[idx].price = item.price;
  form.value.items[idx].qty = '';
  form.value.items[idx].subtotal = 0;
  form.value.items[idx].suggestions = [];
  form.value.items[idx].showDropdown = false;
  form.value.items[idx].highlightedIndex = -1;
}
function onItemInput(idx, e) {
  const value = e.target.value;
  form.value.items[idx].item_id = '';
  form.value.items[idx].item_name = value;
  form.value.items[idx].showDropdown = true;
  fetchItemSuggestions(idx, value);
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
function onQtyInput(idx, e) {
  const val = e.target.value.replace(/[^0-9.]/g, '');
  form.value.items[idx].qty = val;
  const price = Number(form.value.items[idx].price) || 0;
  const qty = Number(val) || 0;
  form.value.items[idx].subtotal = price * qty;
}

const expanded = ref([]); // array of expanded category ids
const search = ref('');

function toggleCategory(catId) {
  if (expanded.value.includes(catId)) {
    expanded.value = expanded.value.filter(id => id !== catId);
  } else {
    expanded.value.push(catId);
  }
}

function filteredItems(cat) {
  if (!search.value) return cat.items;
  return cat.items.filter(item => item.name.toLowerCase().includes(search.value.toLowerCase()));
}

function subtotal(item) {
  return item.price * (Number(item.qty) || 0);
}

function setQty(item, val) {
  if (selectedFOMode.value === 'FO Tambahan') {
    // Hitung total item yang qty > 0, setelah perubahan
    let count = 0;
    categories.value.forEach(cat => {
      cat.items.forEach(i => {
        // Jika item yang sedang diinput, gunakan nilai baru
        const qty = (i === item) ? Number(val.replace(/[^0-9.]/g, '')) : Number(i.qty);
        if (qty > 0) count++;
      });
    });
    // Jika sudah 6 dan user mau isi item ke-7, blok
    if (count > 6) {
      Swal.fire({
        icon: 'warning',
        title: 'Maksimal 6 Item',
        text: 'Anda hanya bisa input maksimal 6 item untuk RO Tambahan.',
        confirmButtonColor: '#3085d6',
      });
      item.qty = 0;
      syncTabItemsToForm();
      return;
    }
  }
  item.qty = val.replace(/[^0-9.]/g, '');
  syncTabItemsToForm();
}

function syncTabItemsToForm() {
  // Ambil semua item dari categories yang qty > 0
  const items = [];
  categories.value.forEach(cat => {
    cat.items.forEach(item => {
      if (Number(item.qty) > 0) {
        items.push({
          item_id: item.id,
          item_name: item.name,
          qty: item.qty,
          unit: item.unit_medium_name || item.unit_medium || item.unit_small || item.unit || '',
          price: item.price,
          subtotal: (Number(item.qty) || 0) * (Number(item.price) || 0),
        });
      }
    });
  });
  form.value.items = items.length ? items : [{
    item_id: '', item_name: '', qty: '', unit: '', price: 0, subtotal: 0,
    suggestions: [], showDropdown: false, loading: false, highlightedIndex: -1, _rowKey: Date.now() + '-' + Math.random()
  }];
}

// FO Modes
const foModes = [
  { value: 'RO Utama', label: 'RO Utama' },
  { value: 'RO Tambahan', label: 'RO Tambahan' },
  { value: 'RO Pengambilan', label: 'RO Pengambilan' },
  { value: 'RO Khusus', label: 'RO Khusus' },
];

// Get current day name
const getCurrentDay = () => {
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  return days[new Date().getDay()];
};

// Check FO Schedule
async function checkFOSchedule() {
  if (!selectedFOMode.value) {
    error.value = 'Silakan pilih mode FO terlebih dahulu';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    console.log('DEBUG region_id', region_id.value, 'outlet_id', outlet_id.value);
    const response = await axios.get('/api/fo-schedules/check', {
      params: {
        fo_mode: selectedFOMode.value,
        day: getCurrentDay(),
        region_id: region_id.value,
        outlet_id: outlet_id.value
      }
    });

    if (response.data.schedule) {
      if (response.data.schedule.is_active) {
        // Validasi exists hanya untuk FO Utama/Tambahan
        if (selectedFOMode.value === 'RO Utama' || selectedFOMode.value === 'RO Tambahan') {
          const res = await axios.post('/api/floor-order/check-exists', {
            tanggal: tanggal.value,
            id_outlet: props.user.outlet.id_outlet,
            fo_mode: selectedFOMode.value,
            exclude_id: draftId.value,
          });
          if (res.data.exists) {
            Swal.fire({
              icon: 'error',
              title: 'Sudah Ada',
              text: `RO ${selectedFOMode.value} untuk hari ini sudah dibuat, tidak bisa membuat lagi. Edit RO Utama/RO Tambahan yang sudah ada jika ingin menambah pesanan.`,
              confirmButtonColor: '#3085d6',
            });
            loading.value = false;
            return;
          }
        }
        // Lanjutkan proses untuk semua mode
        scheduleData.value = response.data.schedule;
        form.value.fo_schedule_id = response.data.schedule.id;
        showScheduleModal.value = true;
        jadwalSiap.value = true;
      } else {
        error.value = 'Di luar jam operasional';
        showScheduleModal.value = false;
        jadwalSiap.value = false;
      }
    } else {
      error.value = 'Tidak ada jadwal FO yang tersedia untuk hari ini';
      jadwalSiap.value = false;
    }
  } catch (err) {
    error.value = 'Gagal memeriksa jadwal RO';
    jadwalSiap.value = false;
    console.error(err);
  } finally {
    loading.value = false;
  }
}

// Setelah FO Schedule aktif, fetch item valid
async function afterScheduleActive() {
  if (scheduleData.value?.id) {
    await fetchItemsByFOSchedule(scheduleData.value.id);
  }
}

// Panggil afterScheduleActive setelah FO Schedule aktif
watch(showScheduleModal, async (val) => {
  if (val && scheduleData.value?.id) {
    await afterScheduleActive();
  }
});

function categorySubtotal(cat) {
  return cat.items.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.qty) || 0), 0);
}
const grandTotal = computed(() =>
  categories.value.reduce((sum, cat) => sum + categorySubtotal(cat), 0)
);

async function fetchOutletSchedules() {
  try {
    console.log('DEBUG region_id', region_id.value, 'outlet_id', outlet_id.value);
    const res = await axios.get('/api/fo-schedules/outlet-schedules', {
      params: {
        outlet_id: outlet_id.value,
        region_id: region_id.value
      }
    });
    outletSchedules.value = res.data.schedules || [];
  } catch (e) {
    outletSchedules.value = [];
  }
}

watch(error, (val) => {
  if (val) {
    fetchOutletSchedules();
  }
});

const daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const groupedSchedules = computed(() => {
  // Group by fo_mode
  const group = {};
  outletSchedules.value.forEach(s => {
    if (!group[s.fo_mode]) group[s.fo_mode] = [];
    group[s.fo_mode].push(s);
  });
  // Sort each group by daysOrder
  Object.keys(group).forEach(mode => {
    group[mode] = group[mode].slice().sort((a, b) => daysOrder.indexOf(a.day) - daysOrder.indexOf(b.day));
  });
  return group;
});

function getDropdownStyle(idx) {
  // Cari input yang sesuai
  const inputs = document.querySelectorAll('input[placeholder="Cari nama item..."]');
  const input = inputs[idx];
  if (!input) return {};
  const rect = input.getBoundingClientRect();
  return {
    left: rect.left + 'px',
    top: rect.bottom + 'px',
    width: rect.width + 'px',
    position: 'fixed',
    zIndex: 9999
  };
}

const grandTotalPC = computed(() =>
  form.value.items.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0)
);

// Watch selectedFOMode, jika FO Khusus, langsung fetch item availabilities
watch(selectedFOMode, async (val) => {
  if (val === 'FO Khusus') {
    jadwalSiap.value = true;
    showScheduleModal.value = false;
    error.value = '';
    loadingItems.value = true;
    try {
      console.log('DEBUG region_id', region_id.value, 'outlet_id', outlet_id.value);
      const res = await axios.get('/api/items/by-fo-khusus', {
        params: {
          region_id: region_id.value,
          outlet_id: outlet_id.value
        }
      });
      itemsByFOSchedule.value = res.data.items || [];
      // Optional: Kelompokkan per kategori jika ingin di mode tab
      const grouped = {};
      itemsByFOSchedule.value.forEach(item => {
        if (!grouped[item.category_id]) grouped[item.category_id] = { id: item.category_id, name: item.category_name || '-', items: [] };
        grouped[item.category_id].items.push({ ...item, qty: 0 });
      });
      categories.value = Object.values(grouped);
    } catch (e) {
      itemsByFOSchedule.value = [];
      categories.value = [];
    } finally {
      loadingItems.value = false;
    }
  }
});

const isTabQtyLimitReached = computed(() => {
  let count = 0;
  categories.value.forEach(cat => {
    cat.items.forEach(item => {
      if (Number(item.qty) > 0) count++;
    });
  });
  return count >= 6;
});

async function fetchTodaySchedules() {
  try {
    const res = await axios.get('/api/item-schedules/today');
    todaySchedules.value = res.data.schedules || [];
    if (todaySchedules.value.length > 0) {
      todayScheduleNotes.value = 'Item berikut wajib diorder hari ini: ' +
        todaySchedules.value.map(s => s.item_name).join(', ');
    } else {
      todayScheduleNotes.value = '';
    }
  } catch (e) {
    todaySchedules.value = [];
    todayScheduleNotes.value = '';
  }
}

watch(jadwalSiap, (val) => {
  if (val) fetchTodaySchedules();
});

function isTodaySchedule(itemId) {
  return todaySchedules.value.some(s => s.item_id == itemId);
}

function focusLastItemInput() {
  nextTick(() => {
    setTimeout(() => {
      const lastIdx = form.value.items.length - 1;
      if (itemInputRefs.value[lastIdx]) {
        itemInputRefs.value[lastIdx].focus();
      }
    }, 0);
  });
}

function handleF1Key(e) {
  if (mode.value === 'pc' && jadwalSiap.value && e.key === 'F1') {
    e.preventDefault();
    const lastIdx = form.value.items.length - 1;
    const inputId = `item-input-${lastIdx}`;
    const input = document.getElementById(inputId);
    if (input) {
      input.focus();
      input.select();
      input.style.outline = '2px solid #2563eb';
      input.style.boxShadow = '0 0 0 2px rgba(37, 99, 235, 0.2)';
      setTimeout(() => {
        input.style.outline = '';
        input.style.boxShadow = '';
      }, 1000);
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleF1Key);
});
onUnmounted(() => {
  window.removeEventListener('keydown', handleF1Key);
});

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let autosaveTimeout = null;
function triggerAutosave() {
  clearTimeout(autosaveTimeout);
  autosaveTimeout = setTimeout(() => {
    const cleanItems = form.value.items.map(item => ({
      item_id: item.item_id,
      item_name: item.item_name,
      qty: item.qty,
      unit: item.unit,
      price: item.price,
      subtotal: item.subtotal,
    }));
    if (draftId.value) {
      axios.put(`/floor-order/${draftId.value}`, {
        ...form.value,
        items: cleanItems,
        fo_schedule_id: form.value.fo_schedule_id,
      });
    } else {
      axios.post('/floor-order', {
        ...form.value,
        items: cleanItems,
        fo_mode: selectedFOMode.value,
        input_mode: mode.value,
        fo_schedule_id: form.value.fo_schedule_id,
      }).then(res => {
        draftId.value = res.data.id;
        // router.visit(`/floor-order/edit/${draftId.value}`); // Dihapus agar tidak redirect
      });
    }
  }, 2000);
}

watch(form, triggerAutosave, { deep: true });

function submitOrderWithLoading() {
  if (!draftId.value) return;
  Swal.fire({
    icon: 'warning',
    title: 'Konfirmasi RO',
    html: `<div style="font-size:1.1em;">Ingat: Barang yang sudah selesai di-RO tidak dapat di batalkan dengan alasan apapun<br><br><b>Apakah RO ini sudah benar dan sesuai item barang-barang yang ingin dipesan?</b></div>`,
    showCancelButton: true,
    confirmButtonText: 'Ya, sudah benar',
    cancelButtonText: 'Cek Lagi',
    focusCancel: true,
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      isSubmitting.value = true;
      axios.post(`/floor-order/${draftId.value}/submit`).then(() => {
        isSubmitting.value = false;
        showPreview.value = false;
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Floor Order berhasil disubmit.',
          confirmButtonColor: '#3085d6',
        }).then(() => {
          router.visit('/floor-order');
        });
      }).catch(() => {
        isSubmitting.value = false;
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: 'Terjadi kesalahan saat submit.',
        });
      });
    }
  });
}

// Setelah fetchItemsByFOSchedule dan categories terisi, jika props.order dan mode tab, sinkronkan qty
watch(categories, (val) => {
  if (props.order && mode.value === 'tab' && Array.isArray(props.order.items)) {
    const itemMap = {};
    props.order.items.forEach(i => { itemMap[i.item_id] = i.qty; });
    categories.value.forEach(cat => {
      cat.items.forEach(item => {
        item.qty = itemMap[item.id] || 0;
      });
    });
  }
}, { immediate: true });

async function periksaJadwalFO() {
  // Validasi: hanya 1 FO Utama & 1 FO Tambahan per hari per outlet
  if (selectedFOMode.value === 'RO Utama' || selectedFOMode.value === 'RO Tambahan') {
    const res = await axios.post('/api/floor-order/check-exists', {
      tanggal: tanggal.value,
      id_outlet: props.user.outlet.id_outlet,
      fo_mode: selectedFOMode.value,
      exclude_id: draftId.value,
    });
    if (res.data.exists) {
      Swal.fire({
        icon: 'error',
        title: 'Sudah Ada',
        text: `RO ${selectedFOMode.value} untuk hari ini sudah dibuat, tidak bisa membuat lagi.`,
        confirmButtonColor: '#3085d6',
      });
      return;
    }
  }
  // ...lanjutkan proses periksa jadwal FO seperti biasa...
  // ... existing code ...
}

// Tambahkan computed untuk kategori dan total per kategori di preview
const itemsByCategory = computed(() => {
  const map = {};
  form.value.items.forEach(item => {
    const cat = item.category_name || item.category || 'Tanpa Kategori';
    if (!map[cat]) map[cat] = [];
    map[cat].push(item);
  });
  return map;
});
const categorySubtotals = computed(() => {
  const result = {};
  Object.entries(itemsByCategory.value).forEach(([cat, items]) => {
    result[cat] = items.reduce((sum, item) => sum + (Number(item.subtotal) || 0), 0);
  });
  return result;
});
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-4 mb-6">
        <button @click="$inertia.visit('/floor-order')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-calendar-check text-blue-500"></i> Buat Request Order (RO)
        </h1>
      </div>
      <div v-if="props.user?.outlet?.nama_outlet" class="mb-4 ml-16">
        <div class="inline-flex items-center gap-2 bg-blue-50 text-blue-800 px-4 py-2 rounded-xl font-semibold">
          <i class="fa fa-store"></i>
          Outlet Anda: <span class="font-bold">{{ props.user.outlet.nama_outlet }}</span>
        </div>
      </div>
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Mode Request Order (RO)</label>
        <div class="flex gap-4">
          <button 
            v-for="mode in foModes" 
            :key="mode.value"
            :class="[
              'px-4 py-2 rounded-lg font-semibold transition-all',
              selectedFOMode === mode.value 
                ? 'bg-blue-600 text-white shadow-lg' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            ]"
            @click="selectedFOMode = mode.value"
          >
            {{ mode.label }}
          </button>
        </div>
      </div>
      <div v-if="loading" class="text-center py-8">
        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
        <p class="mt-2 text-gray-600">Memeriksa jadwal RO...</p>
      </div>
      <div v-if="error" class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fas fa-exclamation-circle text-red-500"></i>
          </div>
          <div class="ml-3">
            <p class="text-sm text-red-700">{{ error }}</p>
          </div>
        </div>
      </div>
      <div v-if="outletSchedules.length" class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="font-semibold text-gray-700 mb-1">Jadwal RO Outlet Anda:</div>
        <div v-for="(schedules, mode) in groupedSchedules" :key="mode" class="mb-3">
          <div class="font-bold text-blue-700 mb-1">{{ mode }}</div>
          <ul class="ml-2 text-gray-700 text-sm">
            <li v-for="s in schedules" :key="s.id" class="mb-1">
              <span class="inline-block w-20 font-semibold">{{ s.day }}</span>
              <span class="inline-block w-32 font-mono">{{ s.open_time }} - {{ s.close_time }}</span>
              <span v-if="s.warehouse_divisions && s.warehouse_divisions.length" class="inline-block text-xs text-gray-600 ml-2">
                [<span v-for="(wd, i) in s.warehouse_divisions" :key="wd.id">{{ wd.name }}<span v-if="i < s.warehouse_divisions.length-1">, </span></span>]
              </span>
            </li>
          </ul>
        </div>
      </div>
      <div v-if="selectedFOMode && !loading" class="mb-6">
        <button 
          @click="checkFOSchedule"
          :disabled="!selectedFOMode"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-colors"
        >
          <i class="fas fa-calendar-check mr-2"></i>
          Periksa Jadwal FO
        </button>
      </div>
      <div v-if="showScheduleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Jadwal RO Tersedia</h3>
            <button @click="showScheduleModal = false" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <div v-if="scheduleData" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">Mode RO</label>
                <p class="mt-1 font-semibold">{{ scheduleData.fo_mode }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Hari</label>
                <p class="mt-1 font-semibold">{{ scheduleData.day }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">Jam Operasional</label>
                <p class="mt-1 font-semibold">{{ scheduleData.open_time }} - {{ scheduleData.close_time }}</p>
              </div>
            </div>

            <div class="border-t pt-4">
              <h4 class="font-semibold text-gray-800 mb-2">Warehouse Division</h4>
              <div class="flex flex-wrap gap-2">
                <span 
                  v-for="division in scheduleData.warehouse_divisions" 
                  :key="division.id"
                  class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"
                >
                  {{ division.name }}
                </span>
              </div>
            </div>

            <div class="border-t pt-4">
              <h4 class="font-semibold text-gray-800 mb-2">Region</h4>
              <div class="flex flex-wrap gap-2">
                <span 
                  v-for="region in scheduleData.regions" 
                  :key="region.id"
                  class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm"
                >
                  {{ region.name }}
                </span>
              </div>
            </div>

            <div class="border-t pt-4">
              <h4 class="font-semibold text-gray-800 mb-2">Outlet</h4>
              <div class="flex flex-wrap gap-2">
                <span 
                  v-for="outlet in scheduleData.outlets" 
                  :key="outlet.id_outlet"
                  class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm"
                >
                  {{ outlet.nama_outlet }}
                </span>
              </div>
            </div>

            <div class="border-t pt-4 flex justify-end gap-2">
              <button 
                @click="showScheduleModal = false"
                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300"
              >
                Batal
              </button>
              <button 
                @click="showScheduleModal = false"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700"
              >
                Lanjutkan
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="jadwalSiap" class="mb-6 flex gap-4">
        <button :class="['px-4 py-2 rounded-lg font-semibold', mode==='pc' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']" @click="mode='pc'">Mode PC</button>
        <button :class="['px-4 py-2 rounded-lg font-semibold', mode==='tab' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700']" @click="mode='tab'">Mode Tab</button>
      </div>
      <div v-if="mode==='pc' && jadwalSiap">
        <div v-if="!error || error !== 'Di luar jam operasional'" class="bg-white rounded-2xl shadow-2xl p-6">
          <div v-if="todayScheduleNotes" class="mb-2 text-yellow-700 bg-yellow-100 border-l-4 border-yellow-400 px-3 py-2 rounded">
            {{ todayScheduleNotes }}
          </div>
          <div v-if="loadingItems" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
            <p class="mt-2 text-gray-600">Memuat data item...</p>
          </div>
          <div v-else>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                <input type="date" v-model="form.tanggal" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" readonly />
              </div>
            </div>
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700">Keterangan</label>
              <input type="text" v-model="form.description" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" :disabled="loadingItems" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
              <div class="overflow-x-auto">
                <table class="w-full min-w-full divide-y divide-gray-200">
                  <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                    <tr>
                      <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                      <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                      <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                      <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Harga</th>
                      <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Subtotal</th>
                      <th class="px-3 py-2"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx" :class="{ 'bg-lime-100 border-2 border-lime-500': isTodaySchedule(item.item_id) }">
                      <td class="px-3 py-2 min-w-[220px]">
                        <div class="relative">
                          <input
                            ref="el => itemInputRefs.value[idx] = el"
                            type="text"
                            v-model="item.item_name"
                            @input="onItemInput(idx, $event)"
                            @focus="onItemInput(idx, $event)"
                            @blur="onItemBlur(idx)"
                            @keydown.down="onItemKeydown(idx, $event)"
                            @keydown.up="onItemKeydown(idx, $event)"
                            @keydown.enter="onItemKeydown(idx, $event)"
                            @keydown.esc="onItemKeydown(idx, $event)"
                            class="w-full rounded border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required
                            autocomplete="off"
                            placeholder="Cari nama item..."
                            :disabled="loadingItems"
                            :id="`item-input-${idx}`"
                          />
                          <Teleport to="body">
                            <div v-if="item.showDropdown && item.suggestions && item.suggestions.length > 0"
                              :style="getDropdownStyle(idx)"
                              :id="`autocomplete-dropdown-${idx}`"
                              class="fixed z-[9999] bg-white border border-blue-200 rounded shadow max-w-xs w-[220px] max-h-60 overflow-auto mt-1"
                            >
                              <div v-for="(s, sidx) in item.suggestions" :key="s.id"
                                @mousedown.prevent="selectItem(idx, s)"
                                :class="['px-3 py-2 flex justify-between items-center cursor-pointer', item.highlightedIndex === sidx ? 'bg-blue-100' : 'hover:bg-blue-50']"
                              >
                                <div class="font-medium">{{ s.name }}</div>
                                <div class="text-sm text-gray-600">{{ s.unit }}</div>
                              </div>
                            </div>
                          </Teleport>
                        </div>
                      </td>
                      <td class="px-3 py-2 min-w-[80px]">
                        <input type="number" min="0.01" step="0.01" v-model="item.qty" @input="onQtyInput(idx, $event)" class="w-full rounded border-gray-300" required :disabled="loadingItems" />
                      </td>
                      <td class="px-3 py-2 min-w-[80px]">
                        <input type="text" v-model="item.unit" class="w-full rounded border-gray-300 bg-gray-100" readonly />
                      </td>
                      <td class="px-3 py-2 min-w-[100px]">
                        {{ formatRupiah(item.price) }}
                      </td>
                      <td class="px-3 py-2 min-w-[120px] font-semibold">
                        Rp {{ (Number(item.subtotal) || 0).toLocaleString('id-ID') }}
                      </td>
                      <td class="px-3 py-2">
                        <button type="button" @click="removeItem(idx)" class="text-red-500 hover:text-red-700" :disabled="form.items.length === 1 || loadingItems"><i class="fa fa-trash"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" @click="addItem" class="mt-2 px-3 py-1 rounded bg-blue-100 text-blue-700 font-semibold hover:bg-blue-200" :disabled="loadingItems"><i class="fa fa-plus"></i> Tambah Item</button>
            </div>
            <div class="text-right font-bold text-xl mt-6">
              Grand Total: Rp {{ grandTotalPC.toLocaleString('id-ID') }}
            </div>
          </div>
        </div>
      </div>
      <div v-else-if="mode==='tab' && jadwalSiap">
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <div v-if="selectedFOMode === 'FO Tambahan'" class="mb-2 text-yellow-700 bg-yellow-100 border-l-4 border-yellow-400 px-3 py-2 rounded">
            <b>Notes:</b> Hanya dapat order 6 item
          </div>
          <div class="mb-4 flex items-center gap-2">
            <input v-model="search" type="text" placeholder="Cari item..." class="input w-full max-w-xs" />
            <i class="fa fa-search text-gray-400"></i>
          </div>
          <div>
            <div v-if="todayScheduleNotes" class="mb-2 text-yellow-700 bg-yellow-100 border-l-4 border-yellow-400 px-3 py-2 rounded">
              {{ todayScheduleNotes }}
            </div>
            <div v-if="loadingItems" class="text-center py-8">
              <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
              <p class="mt-2 text-gray-600">Memuat data item...</p>
            </div>
            <template v-else>
              <div v-for="cat in categories" :key="cat.id" class="mb-4 border rounded-xl overflow-hidden">
                <div @click="toggleCategory(cat.id)" class="flex items-center justify-between px-4 py-3 bg-blue-50 cursor-pointer select-none">
                  <span class="font-bold text-blue-700 text-lg">
                    {{ cat.name }} (Rp {{ categorySubtotal(cat).toLocaleString('id-ID') }})
                  </span>
                  <i :class="expanded.includes(cat.id) ? 'fa fa-chevron-up' : 'fa fa-chevron-down'" />
                </div>
                <div v-if="expanded.includes(cat.id)" class="bg-white px-4 py-2">
                  <table v-if="cat.items && cat.items.length" class="w-full text-sm">
                    <thead>
                      <tr class="text-blue-700">
                        <th class="py-2 text-left">Item</th>
                        <th class="py-2 text-left">Unit</th>
                        <th class="py-2 text-left">Harga/Medium</th>
                        <th class="py-2 text-left">Qty</th>
                        <th class="py-2 text-left">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in filteredItems(cat)" :key="item.id" :class="{ 'bg-lime-100 border-2 border-lime-500': isTodaySchedule(item.id) }">
                        <td class="py-2">{{ item.name }}</td>
                        <td class="py-2">{{ item.unit_medium_name || '-' }}</td>
                        <td class="py-2">
                          {{ formatRupiah(item.price) }}
                        </td>
                        <td class="py-2">
                          <input type="number" min="0" class="w-20 rounded border-gray-300" v-model="item.qty" @input="setQty(item, $event.target.value)" :disabled="isTabQtyLimitReached && Number(item.qty) === 0 && selectedFOMode === 'FO Tambahan'" />
                        </td>
                        <td class="py-2 font-semibold">Rp {{ ((item.price || 0) * (Number(item.qty) || 0)).toLocaleString('id-ID') }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div v-else class="text-gray-400 italic py-4 text-center">Tidak ada item</div>
                </div>
              </div>
            </template>
          </div>
          <div class="text-right font-bold text-xl mt-6">
            Grand Total: Rp {{ grandTotal.toLocaleString('id-ID') }}
          </div>
        </div>
      </div>
      <button @click="showPreview = true" class="mt-8 px-6 py-2 rounded bg-blue-600 text-white font-bold text-lg hover:bg-blue-700">Submit</button>
      <!-- Modal Preview -->
      <div v-if="showPreview" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-2xl w-full shadow-2xl" style="max-height: 90vh; overflow-y: auto;">
          <h2 class="text-xl font-bold mb-4">Preview Request Order (RO)</h2>
          <div class="mb-2"><b>Tanggal:</b> {{ form.tanggal }}</div>
          <div class="mb-2"><b>Keterangan:</b> {{ form.description }}</div>
          <div class="mb-2"><b>Items:</b></div>
          <div v-for="(items, cat) in itemsByCategory" :key="cat" class="mb-2">
            <div class="font-semibold text-blue-700 mb-1">{{ cat }}</div>
            <table class="w-full mb-2">
              <thead>
                <tr>
                  <th>Item</th><th>Qty</th><th>Unit</th><th>Harga</th><th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item._rowKey">
                  <td>{{ item.item_name }}</td>
                  <td>{{ item.qty }}</td>
                  <td>{{ item.unit }}</td>
                  <td>{{ formatRupiah(item.price) }}</td>
                  <td>{{ formatRupiah(item.subtotal) }}</td>
                </tr>
                <tr class="bg-blue-50 font-bold">
                  <td colspan="4" class="text-right">Total {{ cat }}</td>
                  <td>{{ formatRupiah(categorySubtotals[cat]) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="text-right font-bold text-lg mb-4">Grand Total: {{ formatRupiah(grandTotalPC) }}</div>
          <div class="flex justify-end gap-2">
            <button @click="showPreview = false" class="px-4 py-2 bg-gray-200 rounded">Batal</button>
            <button @click="submitOrderWithLoading" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="isSubmitting">
              <span v-if="isSubmitting"><i class='fa fa-spinner fa-spin'></i> Mengirim...</span>
              <span v-else>Kirim</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 