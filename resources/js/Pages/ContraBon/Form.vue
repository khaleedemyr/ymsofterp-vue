<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  contraBon: Object, // for edit, can be null for create
});

const isEdit = computed(() => !!props.contraBon);

const poWithGRList = ref([]);
const loadingPOGR = ref(false);
const supplierDetail = ref(null);
const fileImage = ref(null);
const fileImagePreview = ref(null);

// Retail Food variables
const retailFoodList = ref([]);

// Warehouse Retail Food variables
const warehouseRetailFoodList = ref([]);

// Loading state untuk initial load
const initialLoading = ref(false);

// Multiple sources support - array untuk menyimpan semua sources yang sudah dipilih
const selectedSources = ref([]); // Array of { type, id, display, data }

// Modal search functionality
const showPOListModal = ref(false);
const showRetailFoodModal = ref(false);
const showWarehouseRetailFoodModal = ref(false);
const poSearchQuery = ref('');
const retailFoodSearchQuery = ref('');
const warehouseRetailFoodSearchQuery = ref('');
const filteredPOList = ref([]);
const filteredRetailFoodList = ref([]);
const filteredWarehouseRetailFoodList = ref([]);

// Tutorial modal
const showTutorial = ref(false);

const form = useForm({
  date: props.contraBon?.date ? props.contraBon.date.substring(0, 10) : '',
  notes: props.contraBon?.notes || '',
  supplier_invoice_number: props.contraBon?.supplier_invoice_number || '',
  // Discount total fields
  discount_total_percent: props.contraBon?.discount_total_percent || 0,
  discount_total_amount: props.contraBon?.discount_total_amount || 0,
  // Source info fields - akan di-update saat submit
  source_type: props.contraBon?.source_type || 'purchase_order',
  source_id: props.contraBon?.source_id || null,
  po_id: props.contraBon?.po_id || null,
  gr_id: props.contraBon?.gr_id || null,
  sources: [], // Multiple sources support
  items: props.contraBon?.items?.map(i => ({
    gr_item_id: i.gr_item_id,
    item_id: i.item_id,
    quantity: i.quantity,
    unit_id: i.unit_id,
    price: i.price,
    discount_percent: i.discount_percent || 0,
    discount_amount: i.discount_amount || 0,
    po_item_total: i.po_item_total || null,
    notes: i.notes || '',
    item: i.item,
    unit: i.unit,
    // Fix: Tambahkan item_name dan unit_name dari relasi jika ada
    item_name: i.item?.name || i.item_name || null,
    unit_name: i.unit?.name || i.unit_name || null,
    // Source info untuk multiple sources
    source_type: props.contraBon?.source_type || 'purchase_order',
    source_id: props.contraBon?.source_id || '',
    source_display: props.contraBon?.source_type_display || '',
    po_id: props.contraBon?.po_id || null,
    gr_id: props.contraBon?.gr_id || null,
    selected: true, // Default selected untuk edit mode
    _rowKey: Date.now() + '-' + Math.random(),
  })) || [],
  selectedItems: [], // Array untuk menyimpan item yang dicentang
});

onMounted(async () => {
  if (!isEdit.value) {
    // Set default date ke hari ini
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    form.date = `${yyyy}-${mm}-${dd}`;
    
    // Tidak load data di sini - akan di-load saat diperlukan (lazy loading)
    initialLoading.value = false;
  } else {
    // Mode edit: set preview image jika ada
    if (props.contraBon?.image_path) {
      fileImagePreview.value = `/storage/${props.contraBon.image_path}`;
    }
    
    // Initialize selectedSources for edit mode
    if (props.contraBon) {
      // PRIORITAS: Gunakan sources array untuk multi-source (jika ada)
      if (props.contraBon.sources && Array.isArray(props.contraBon.sources) && props.contraBon.sources.length > 0) {
        // Multi-source: load dari sources array
        props.contraBon.sources.forEach((source) => {
          if (source.source_type === 'purchase_order' && source.po_id && source.gr_id) {
            // Gunakan camelCase untuk relasi Laravel
            const poNumber = (source.purchaseOrder || source.purchase_order)?.number || 'PO';
            selectedSources.value.push({
              key: `po-${source.po_id}-${source.gr_id}`,
              type: 'purchase_order',
              po_id: source.po_id,
              gr_id: source.gr_id,
              display: `${poNumber} - GR`,
              supplier_id: props.contraBon.supplier_id,
              supplier_name: props.contraBon.supplier?.name || '',
              data: null
            });
          } else if (source.source_type === 'retail_food' && source.source_id) {
            // Gunakan camelCase untuk relasi Laravel
            const retailFood = source.retailFood || source.retail_food;
            const retailNumber = retailFood?.retail_number || 'Retail Food';
            selectedSources.value.push({
              key: `rf-${source.source_id}`,
              type: 'retail_food',
              source_id: source.source_id,
              display: retailNumber,
              supplier_id: props.contraBon.supplier_id,
              supplier_name: props.contraBon.supplier?.name || '',
              data: null
            });
          } else if (source.source_type === 'warehouse_retail_food' && source.source_id) {
            // Gunakan camelCase untuk relasi Laravel
            const warehouseRetailFood = source.warehouseRetailFood || source.warehouse_retail_food;
            const retailNumber = warehouseRetailFood?.retail_number || 'Warehouse Retail Food';
            selectedSources.value.push({
              key: `rwf-${source.source_id}`,
              type: 'warehouse_retail_food',
              source_id: source.source_id,
              display: retailNumber,
              supplier_id: props.contraBon.supplier_id,
              supplier_name: props.contraBon.supplier?.name || '',
              data: null
            });
          }
        });
      } else {
        // Backward compatibility: gunakan source_type dan source_id untuk single source
        if (props.contraBon.source_type === 'purchase_order' && props.contraBon.po_id && props.contraBon.gr_id) {
          selectedSources.value.push({
            key: `po-${props.contraBon.po_id}-${props.contraBon.gr_id}`,
            type: 'purchase_order',
            po_id: props.contraBon.po_id,
            gr_id: props.contraBon.gr_id,
            display: props.contraBon.purchase_order?.number ? `${props.contraBon.purchase_order.number} - GR` : 'PO/GR',
            supplier_id: props.contraBon.supplier_id,
            supplier_name: props.contraBon.supplier?.name || '',
            data: null
          });
        } else if (props.contraBon.source_type === 'retail_food' && props.contraBon.source_id) {
          selectedSources.value.push({
            key: `rf-${props.contraBon.source_id}`,
            type: 'retail_food',
            source_id: props.contraBon.source_id,
            display: props.contraBon.retailFood?.retail_number || 'Retail Food',
            supplier_id: props.contraBon.supplier_id,
            supplier_name: props.contraBon.supplier?.name || '',
            data: null
          });
        } else if (props.contraBon.source_type === 'warehouse_retail_food' && props.contraBon.source_id) {
          selectedSources.value.push({
            key: `rwf-${props.contraBon.source_id}`,
            type: 'warehouse_retail_food',
            source_id: props.contraBon.source_id,
            display: props.contraBon.warehouseRetailFood?.retail_number || 'Warehouse Retail Food',
            supplier_id: props.contraBon.supplier_id,
            supplier_name: props.contraBon.supplier?.name || '',
            data: null
          });
        }
      }
      
      // Load supplier detail
      if (props.contraBon.supplier_id) {
        axios.get(`/api/suppliers/${props.contraBon.supplier_id}`)
          .then(res => supplierDetail.value = res.data)
          .catch(() => supplierDetail.value = null);
      }
    }
    
    initialLoading.value = false;
  }
});

// Filter functions for modal search
function filterPOList() {
  if (!poSearchQuery.value.trim()) {
    filteredPOList.value = poWithGRList.value;
    return;
  }
  
  const query = poSearchQuery.value.toLowerCase();
  filteredPOList.value = poWithGRList.value.filter(p => 
    p.po_number.toLowerCase().includes(query) ||
    p.gr_number.toLowerCase().includes(query) ||
    p.supplier_name.toLowerCase().includes(query) ||
    (p.outlet_names && p.outlet_names.some(outlet => outlet.toLowerCase().includes(query)))
  );
}

function filterRetailFoodList() {
  if (!retailFoodSearchQuery.value.trim()) {
    filteredRetailFoodList.value = retailFoodList.value;
    return;
  }
  
  const query = retailFoodSearchQuery.value.toLowerCase();
  filteredRetailFoodList.value = retailFoodList.value.filter(rf => 
    rf.retail_number.toLowerCase().includes(query) ||
    rf.supplier_name.toLowerCase().includes(query) ||
    (rf.outlet_name && rf.outlet_name.toLowerCase().includes(query)) ||
    (rf.warehouse_outlet_name && rf.warehouse_outlet_name.toLowerCase().includes(query))
  );
}

function filterWarehouseRetailFoodList() {
  if (!warehouseRetailFoodSearchQuery.value.trim()) {
    filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
    return;
  }
  
  const query = warehouseRetailFoodSearchQuery.value.toLowerCase();
  filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value.filter(rwf => 
    rwf.retail_number.toLowerCase().includes(query) ||
    rwf.supplier_name.toLowerCase().includes(query) ||
    (rwf.warehouse_name && rwf.warehouse_name.toLowerCase().includes(query)) ||
    (rwf.warehouse_division_name && rwf.warehouse_division_name.toLowerCase().includes(query))
  );
}

// Modal functions
async function openPOListModal() {
  showPOListModal.value = true;
  poSearchQuery.value = '';
  
  // Lazy load: hanya load data jika belum pernah di-load
  if (poWithGRList.value.length === 0) {
    loadingPOGR.value = true;
    try {
      const response = await axios.get('/api/contra-bon/po-with-approved-gr');
      poWithGRList.value = response.data || [];
      filteredPOList.value = poWithGRList.value;
    } catch (e) {
      Swal.fire('Error', 'Gagal mengambil data PO/GR: ' + (e.response?.data?.error || e.message), 'error');
      poWithGRList.value = [];
      filteredPOList.value = [];
    } finally {
      loadingPOGR.value = false;
    }
  } else {
    filteredPOList.value = poWithGRList.value;
  }
}

async function openRetailFoodModal() {
  showRetailFoodModal.value = true;
  retailFoodSearchQuery.value = '';
  
  // Lazy load: hanya load data jika belum pernah di-load
  if (retailFoodList.value.length === 0) {
    loadingPOGR.value = true;
    try {
      const response = await axios.get('/api/contra-bon/retail-food-contra-bon');
      retailFoodList.value = response.data || [];
      filteredRetailFoodList.value = retailFoodList.value;
    } catch (e) {
      console.error('Error loading retail food:', e);
      Swal.fire('Error', 'Gagal mengambil data Retail Food', 'error');
      retailFoodList.value = [];
      filteredRetailFoodList.value = [];
    } finally {
      loadingPOGR.value = false;
    }
  } else {
    filteredRetailFoodList.value = retailFoodList.value;
  }
}

async function openWarehouseRetailFoodModal() {
  showWarehouseRetailFoodModal.value = true;
  warehouseRetailFoodSearchQuery.value = '';
  
  // Lazy load: hanya load data jika belum pernah di-load
  if (warehouseRetailFoodList.value.length === 0) {
    loadingPOGR.value = true;
    try {
      const response = await axios.get('/api/contra-bon/warehouse-retail-food-contra-bon');
      warehouseRetailFoodList.value = response.data || [];
      filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
    } catch (e) {
      console.error('Error loading warehouse retail food:', e);
      Swal.fire('Error', 'Gagal mengambil data Warehouse Retail Food', 'error');
      warehouseRetailFoodList.value = [];
      filteredWarehouseRetailFoodList.value = [];
    } finally {
      loadingPOGR.value = false;
    }
  } else {
    filteredWarehouseRetailFoodList.value = warehouseRetailFoodList.value;
  }
}

function closePOListModal() {
  showPOListModal.value = false;
  poSearchQuery.value = '';
}

function closeRetailFoodModal() {
  showRetailFoodModal.value = false;
  retailFoodSearchQuery.value = '';
}

function closeWarehouseRetailFoodModal() {
  showWarehouseRetailFoodModal.value = false;
  warehouseRetailFoodSearchQuery.value = '';
}

function selectPOFromModal(po) {
  
  // Check if this source already exists
  const existingSource = selectedSources.value.find(s => 
    s.type === 'purchase_order' && s.po_id === po.po_id && s.gr_id === po.gr_id
  );
  
  if (existingSource) {
    Swal.fire('Info', 'Source ini sudah ditambahkan', 'info');
    closePOListModal();
    return;
  }
  
  // Add source to selectedSources
  const sourceKey = `po-${po.po_id}-${po.gr_id}`;
  const sourceData = {
    key: sourceKey,
    type: 'purchase_order',
    po_id: po.po_id,
    gr_id: po.gr_id,
    display: `${po.po_number} - ${po.gr_number} - ${po.supplier_name}`,
    supplier_id: po.supplier_id,
    supplier_name: po.supplier_name,
    data: po
  };
  selectedSources.value.push(sourceData);
  
  // Add items from this source to form.items
  if (po.items && po.items.length > 0) {
    const newItems = po.items.map(item => {
      // Debug: Log item data untuk troubleshooting
      if (!item.item_name || !item.unit_name) {
        console.warn('Item missing name/unit:', item);
        console.warn('Available keys:', Object.keys(item));
      }
      
      // Pastikan item_name dan unit_name selalu ada
      const itemName = item.item_name || '';
      const unitName = item.unit_name || '';
      
      const mappedItem = {
      gr_item_id: item.id,
      item_id: item.item_id,
      po_item_id: item.po_item_id,
      unit_id: item.unit_id,
        item_name: itemName, // Langsung gunakan dari API
        unit_name: unitName, // Langsung gunakan dari API
      quantity: item.qty_received,
      price: item.po_price,
      discount_percent: item.discount_percent || 0,
      discount_amount: item.discount_amount || 0,
      po_item_total: item.po_item_total || (item.po_price * item.qty_received),
      notes: '',
      selected: false, // Default tidak dicentang
      // Source info
      source_type: 'purchase_order',
      source_id: `${po.po_id}-${po.gr_id}`,
      source_display: `${po.po_number} - ${po.gr_number}`,
      po_id: po.po_id,
      gr_id: po.gr_id,
      _rowKey: Date.now() + '-' + Math.random() + '-po',
      };
      
      // Warn jika masih kosong setelah mapping
      if (!mappedItem.item_name || !mappedItem.unit_name) {
        console.error('WARNING: Item still missing name/unit after mapping:', {
          item_id: mappedItem.item_id,
          unit_id: mappedItem.unit_id,
          item_name: mappedItem.item_name,
          unit_name: mappedItem.unit_name,
          raw_item: item
        });
      }
      
      return mappedItem;
    });
    form.items.push(...newItems);
  }
  
  // Update supplier detail (use first supplier or merge if multiple)
  if (po.supplier_id) {
    if (!supplierDetail.value) {
      axios.get(`/api/suppliers/${po.supplier_id}`)
        .then(res => supplierDetail.value = res.data)
        .catch(() => supplierDetail.value = null);
    } else if (supplierDetail.value.id !== po.supplier_id) {
      // Multiple suppliers - show info
      Swal.fire('Info', 'Contra Bon ini memiliki beberapa supplier. Supplier detail akan menampilkan supplier pertama.', 'info');
    }
  }
  
  closePOListModal();
}

function selectRetailFoodFromModal(rf) {
  // Check if this source already exists
  const existingSource = selectedSources.value.find(s => 
    s.type === 'retail_food' && s.source_id === rf.retail_food_id
  );
  
  if (existingSource) {
    Swal.fire('Info', 'Source ini sudah ditambahkan', 'info');
    closeRetailFoodModal();
    return;
  }
  
  // Add source to selectedSources
  const sourceKey = `rf-${rf.retail_food_id}`;
  const sourceData = {
    key: sourceKey,
    type: 'retail_food',
    source_id: rf.retail_food_id,
    display: `${rf.retail_number} - ${rf.supplier_name}`,
    supplier_id: rf.supplier_id,
    supplier_name: rf.supplier_name,
    data: rf
  };
  selectedSources.value.push(sourceData);
  
  // Add items from this source to form.items
  if (rf.items && rf.items.length > 0) {
    const newItems = rf.items.map(item => ({
      gr_item_id: null,
      item_id: item.item_id || null, // Ambil dari join dengan items table
      po_item_id: null,
      unit_id: item.unit_id || null, // Ambil dari join dengan units table
      quantity: item.qty,
      price: item.price,
      discount_percent: 0,
      discount_amount: 0,
      notes: '',
      item_name: item.item_name,
      unit_name: item.unit_name,
      retail_food_item_id: item.id, // Simpan ID item dari retail food
      selected: false, // Default tidak dicentang
      // Source info
      source_type: 'retail_food',
      source_id: rf.retail_food_id,
      source_display: rf.retail_number,
      po_id: null,
      gr_id: null,
      _rowKey: Date.now() + '-' + Math.random() + '-rf',
    }));
    form.items.push(...newItems);
  }
  
  // Update supplier detail
  if (rf.supplier_id) {
    if (!supplierDetail.value) {
      axios.get(`/api/suppliers/${rf.supplier_id}`)
        .then(res => supplierDetail.value = res.data)
        .catch(() => supplierDetail.value = null);
    } else if (supplierDetail.value.id !== rf.supplier_id) {
      Swal.fire('Info', 'Contra Bon ini memiliki beberapa supplier. Supplier detail akan menampilkan supplier pertama.', 'info');
    }
  }
  
  closeRetailFoodModal();
}

function selectWarehouseRetailFoodFromModal(rwf) {
  // Check if this source already exists
  const existingSource = selectedSources.value.find(s => 
    s.type === 'warehouse_retail_food' && s.source_id === rwf.retail_warehouse_food_id
  );
  
  if (existingSource) {
    Swal.fire('Info', 'Source ini sudah ditambahkan', 'info');
    closeWarehouseRetailFoodModal();
    return;
  }
  
  // Add source to selectedSources
  const sourceKey = `rwf-${rwf.retail_warehouse_food_id}`;
  const sourceData = {
    key: sourceKey,
    type: 'warehouse_retail_food',
    source_id: rwf.retail_warehouse_food_id,
    display: `${rwf.retail_number} - ${rwf.supplier_name}`,
    supplier_id: rwf.supplier_id,
    supplier_name: rwf.supplier_name,
    data: rwf
  };
  selectedSources.value.push(sourceData);
  
  // Add items from this source to form.items
  if (rwf.items && rwf.items.length > 0) {
    const newItems = rwf.items.map(item => ({
      gr_item_id: null,
      item_id: item.item_id || null, // Ambil dari join dengan items table
      po_item_id: null,
      unit_id: item.unit_id || null, // Ambil dari join dengan units table
      quantity: item.qty,
      price: item.price,
      discount_percent: 0,
      discount_amount: 0,
      notes: '',
      item_name: item.item_name,
      unit_name: item.unit_name,
      warehouse_retail_food_item_id: item.id, // Simpan ID item dari warehouse retail food
      selected: false, // Default tidak dicentang
      // Source info
      source_type: 'warehouse_retail_food',
      source_id: rwf.retail_warehouse_food_id,
      source_display: rwf.retail_number,
      po_id: null,
      gr_id: null,
      _rowKey: Date.now() + '-' + Math.random() + '-rwf',
    }));
    form.items.push(...newItems);
  }
  
  // Update supplier detail
  if (rwf.supplier_id) {
    if (!supplierDetail.value) {
      axios.get(`/api/suppliers/${rwf.supplier_id}`)
        .then(res => supplierDetail.value = res.data)
        .catch(() => supplierDetail.value = null);
    } else if (supplierDetail.value.id !== rwf.supplier_id) {
      Swal.fire('Info', 'Contra Bon ini memiliki beberapa supplier. Supplier detail akan menampilkan supplier pertama.', 'info');
    }
  }
  
  closeWarehouseRetailFoodModal();
}

// Function to remove a source and its items
function removeSource(sourceKey) {
  // Remove items from this source
  form.items = form.items.filter(item => {
    if (item.source_type === 'purchase_order') {
      const itemSourceKey = `po-${item.po_id}-${item.gr_id}`;
      return itemSourceKey !== sourceKey;
    } else if (item.source_type === 'retail_food') {
      const itemSourceKey = `rf-${item.source_id}`;
      return itemSourceKey !== sourceKey;
    } else if (item.source_type === 'warehouse_retail_food') {
      const itemSourceKey = `rwf-${item.source_id}`;
      return itemSourceKey !== sourceKey;
    }
    return true;
  });
  
  // Remove source from selectedSources
  selectedSources.value = selectedSources.value.filter(s => s.key !== sourceKey);
  
  // Update supplier detail if needed
  if (selectedSources.value.length === 0) {
    supplierDetail.value = null;
  }
}

function onFileChange(e) {
  const file = e.target.files[0];
  fileImage.value = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      fileImagePreview.value = ev.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    fileImagePreview.value = null;
  }
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

// Computed untuk menghitung subtotal dari item yang dicentang (with discount item)
const subtotalAmount = computed(() => {
  return form.items
    .filter(item => item.selected)
    .reduce((sum, item) => {
      return sum + calculateItemTotal(item);
    }, 0);
});

// Computed untuk menghitung total setelah discount total
const totalAmount = computed(() => {
  const subtotal = subtotalAmount.value;
  const discountTotalPercent = Number(form.discount_total_percent) || 0;
  const discountTotalAmount = Number(form.discount_total_amount) || 0;
  
  let discountTotal = 0;
  if (discountTotalPercent > 0) {
    // Discount total percent applies to subtotal
    discountTotal = subtotal * (discountTotalPercent / 100);
  } else if (discountTotalAmount > 0) {
    // Use discount total amount directly
    discountTotal = discountTotalAmount;
  }
  
  return subtotal - discountTotal;
});

// Function to calculate item total (with discount)
function calculateItemTotal(item) {
  const quantity = Number(item.quantity) || 0;
  const price = Number(item.price) || 0;
  const subtotal = quantity * price;
  
  // Apply discount item if available (for all source types)
  const discountPercent = Number(item.discount_percent) || 0;
  const discountAmount = Number(item.discount_amount) || 0;
  
  let discount = 0;
  if (discountPercent > 0) {
    // Discount percent applies to subtotal
    discount = subtotal * (discountPercent / 100);
  } else if (discountAmount > 0) {
    // For purchase_order, discount amount might be proportional to quantity ratio
    if (item.source_type === 'purchase_order') {
      // Find the source data to get original PO item quantity
      const source = selectedSources.value.find(s => 
        s.type === 'purchase_order' && s.po_id === item.po_id && s.gr_id === item.gr_id
      );
      if (source && source.data && source.data.items) {
        const poItem = source.data.items.find(i => i.id === item.gr_item_id || i.item_id === item.item_id);
        if (poItem && poItem.qty_received > 0) {
          const quantityRatio = quantity / poItem.qty_received;
          discount = discountAmount * quantityRatio;
        } else {
          discount = discountAmount;
        }
      } else {
        discount = discountAmount;
      }
    } else {
      // For retail_food and warehouse_retail_food, use discount amount directly
      discount = discountAmount;
    }
  }
  
  return subtotal - discount;
}

// Function to update item total when price changes
function updateItemTotal(item) {
  // Ensure price is a valid number
  if (item.price === null || item.price === undefined || item.price === '') {
    item.price = 0;
  } else {
    // Convert to number if it's a string
    const numPrice = parseFloat(item.price);
    // Ensure price is not negative
    item.price = numPrice >= 0 ? numPrice : 0;
  }
  // Update po_item_total untuk purchase_order (jika ada)
  if (item.source_type === 'purchase_order' && item.po_item_id) {
    item.po_item_total = calculateItemTotal(item);
  }
}

// Function to handle discount item input - make discount_percent and discount_amount mutually exclusive
function onDiscountItemPercentChange(item) {
  if (item.discount_percent > 0) {
    item.discount_amount = 0;
  }
  updateItemTotal(item);
}

function onDiscountItemAmountChange(item) {
  if (item.discount_amount > 0) {
    item.discount_percent = 0;
  }
  updateItemTotal(item);
}

// Function to handle discount total input - make discount_total_percent and discount_total_amount mutually exclusive
function onDiscountTotalPercentChange() {
  if (form.discount_total_percent > 0) {
    form.discount_total_amount = 0;
  }
}

function onDiscountTotalAmountChange() {
  if (form.discount_total_amount > 0) {
    form.discount_total_percent = 0;
  }
}

async function onSubmit() {
  // Validasi: minimal harus ada 1 item yang dicentang
  const selectedItems = form.items.filter(item => item.selected);
  if (selectedItems.length === 0) {
    Swal.fire('Peringatan', 'Pilih minimal 1 item untuk dibuat contra bon', 'warning');
    return;
  }

  // REFACTORED: Tentukan source info dari item pertama yang dipilih
  const firstItem = selectedItems[0];
  
  // PRIORITAS: Ambil source_type dari item, bukan dari selectedSources
  const itemSourceType = firstItem?.source_type || 'purchase_order';
  const itemPoId = firstItem?.po_id || null;
  const itemGrId = firstItem?.gr_id || null;
  const itemSourceId = firstItem?.source_id || null;

  const loadingSwal = Swal.fire({
    title: isEdit.value ? 'Menyimpan Perubahan...' : 'Menyimpan Data...',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => Swal.showLoading(),
  });
  
  // Helper function untuk set source info ke FormData
  const setSourceInfo = (fd, sourceType, poId, grId, sourceId) => {
    if (sourceType === 'purchase_order') {
      fd.append('po_id', poId || '');
      fd.append('gr_id', grId || '');
      fd.append('source_type', 'purchase_order');
      fd.append('source_id', poId && grId ? `${poId}-${grId}` : '');
    } else if (sourceType === 'retail_food') {
      fd.append('po_id', '');
      fd.append('gr_id', '');
      fd.append('source_type', 'retail_food');
      fd.append('source_id', sourceId || '');
    } else if (sourceType === 'warehouse_retail_food') {
      fd.append('po_id', '');
      fd.append('gr_id', '');
      fd.append('source_type', 'warehouse_retail_food');
      fd.append('source_id', sourceId || '');
    }
  };
  
  // Helper function untuk set source info ke form object
  const setFormSourceInfo = (sourceType, poId, grId, sourceId) => {
    if (sourceType === 'purchase_order') {
      form.po_id = poId || '';
      form.gr_id = grId || '';
      form.source_type = 'purchase_order';
      form.source_id = poId && grId ? `${poId}-${grId}` : '';
    } else if (sourceType === 'retail_food') {
      form.po_id = '';
      form.gr_id = '';
      form.source_type = 'retail_food';
      form.source_id = sourceId || '';
    } else if (sourceType === 'warehouse_retail_food') {
      form.po_id = '';
      form.gr_id = '';
      form.source_type = 'warehouse_retail_food';
      form.source_id = sourceId || '';
    }
  };
  
  // Kirim pakai FormData jika ada file
  if (fileImage.value) {
    const fd = new FormData();
    fd.append('date', form.date);
    
    // REFACTORED: Selalu gunakan source info dari item pertama yang dipilih
    console.log('Setting FormData source info (with file):', {
      itemSourceType,
      itemPoId,
      itemGrId,
      itemSourceId
    });
    setSourceInfo(fd, itemSourceType, itemPoId, itemGrId, itemSourceId);
    
    // Send all sources as array for multiple source support
    // Jika selectedSources kosong, buat source dari item pertama
    if (selectedSources.value.length > 0) {
      selectedSources.value.forEach((source, index) => {
        fd.append(`sources[${index}][source_type]`, source.type);
        // Handle undefined/null source_id - untuk purchase_order, tidak perlu source_id (gunakan po_id)
        let sourceIdValue = source.source_id;
        if (source.type === 'purchase_order') {
          // Untuk purchase_order, source_id tidak diperlukan (akan diisi dengan po_id di backend)
          sourceIdValue = source.po_id || '';
        } else {
          // Untuk retail_food dan warehouse_retail_food, gunakan source_id
          sourceIdValue = source.source_id || '';
        }
        // Pastikan tidak mengirim undefined sebagai string
        if (sourceIdValue === undefined || sourceIdValue === null) {
          sourceIdValue = '';
        }
        fd.append(`sources[${index}][source_id]`, sourceIdValue);
        if (source.type === 'purchase_order') {
          fd.append(`sources[${index}][po_id]`, source.po_id || '');
          fd.append(`sources[${index}][gr_id]`, source.gr_id || '');
        } else {
          fd.append(`sources[${index}][po_id]`, '');
          fd.append(`sources[${index}][gr_id]`, '');
        }
      });
    } else {
      // Jika selectedSources kosong, buat source dari item pertama
      fd.append(`sources[0][source_type]`, itemSourceType);
      // Handle source_id - untuk purchase_order, gunakan po_id
      let sourceIdValue = '';
      if (itemSourceType === 'purchase_order') {
        sourceIdValue = itemPoId || '';
      } else {
        sourceIdValue = itemSourceId || '';
      }
      fd.append(`sources[0][source_id]`, sourceIdValue);
      if (itemSourceType === 'purchase_order') {
        fd.append(`sources[0][po_id]`, itemPoId || '');
        fd.append(`sources[0][gr_id]`, itemGrId || '');
      } else {
        fd.append(`sources[0][po_id]`, '');
        fd.append(`sources[0][gr_id]`, '');
      }
    }
    
    fd.append('notes', form.notes);
    fd.append('supplier_invoice_number', form.supplier_invoice_number);
    fd.append('discount_total_percent', form.discount_total_percent || 0);
    fd.append('discount_total_amount', form.discount_total_amount || 0);
    fd.append('image', fileImage.value);
    
    // Hanya kirim item yang dicentang
    selectedItems.forEach((item, idx) => {
      Object.keys(item).forEach(key => {
        // Jangan kirim field selected dan _rowKey
        if (key !== 'selected' && key !== '_rowKey') {
          let value = item[key];
          // Untuk FormData, null/undefined harus dikonversi
          // Tapi untuk item_id dan unit_id, jangan convert null menjadi string kosong
          // karena backend perlu tahu bahwa nilainya null (bukan string "null")
          if (value === null || value === undefined) {
            // Untuk field tertentu, kirim sebagai string "null" agar backend tahu
            if (key === 'item_id' || key === 'unit_id' || key === 'po_id' || key === 'gr_id' || 
                key === 'po_item_id' || key === 'gr_item_id' || key === 'retail_food_item_id' || 
                key === 'warehouse_retail_food_item_id') {
              // Kirim sebagai string kosong, backend akan handle
              value = '';
            } else {
              value = '';
            }
          }
          fd.append(`items[${idx}][${key}]`, value);
        }
      });
    });
    try {
      const url = isEdit.value ? `/contra-bons/${props.contraBon.id}` : '/contra-bons';
      const method = isEdit.value ? 'post' : 'post';
      const config = { 
        headers: { 
          'Content-Type': 'multipart/form-data',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        } 
      };
      let res;
      if (isEdit.value) {
        fd.append('_method', 'PUT');
        res = await axios.post(url, fd, config);
      } else {
        res = await axios.post(url, fd, config);
      }
      
      // Check if response indicates success
      if (res.status === 200 || res.status === 201) {
        if (res.data && res.data.success === false) {
          throw new Error(res.data.message || 'Gagal menyimpan data');
        }
        // Close loading Swal first
        await Swal.close();
        
        // Success - check if we got JSON response or redirect
        if (res.data && res.data.success === true) {
          await Swal.fire('Berhasil', res.data.message || 'Data berhasil disimpan', 'success');
          router.visit('/contra-bons');
        } else {
          // If no JSON response, assume success (redirect response)
          await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
          router.visit('/contra-bons');
        }
      } else {
        throw new Error('Unexpected response status: ' + res.status);
      }
    } catch (e) {
      console.error('Error saving contra bon:', e);
      // Close loading Swal first
      await Swal.close();
      
      let errorMessage = 'Terjadi kesalahan saat menyimpan data';
      
      if (e.response) {
        // Server responded with error
        if (e.response.data) {
          if (e.response.data.message) {
            errorMessage = e.response.data.message;
          } else if (e.response.data.errors) {
            // Validation errors
            const errors = e.response.data.errors;
            const errorMessages = Object.keys(errors).map(key => {
              return Array.isArray(errors[key]) ? errors[key].join(', ') : errors[key];
            });
            errorMessage = errorMessages.join('\n');
            form.setError(errors);
          } else if (e.response.data.error) {
            errorMessage = e.response.data.error;
          } else if (typeof e.response.data === 'string') {
            errorMessage = e.response.data;
          }
        }
        if (e.response.status === 422) {
          errorMessage = 'Validasi gagal: ' + errorMessage;
        } else if (e.response.status === 500) {
          errorMessage = 'Server error: ' + errorMessage;
        }
      } else if (e.request) {
        errorMessage = 'Tidak ada response dari server. Pastikan koneksi internet Anda stabil.';
      } else {
        errorMessage = e.message || errorMessage;
      }
      
      await Swal.fire({
        icon: 'error',
        title: 'Gagal Menyimpan',
        text: errorMessage,
        confirmButtonText: 'OK'
      });
    }
    return;
  }
  // Tanpa file, pakai inertia
  // REFACTORED: Selalu gunakan source info dari item pertama yang dipilih
  setFormSourceInfo(itemSourceType, itemPoId, itemGrId, itemSourceId);
  
  // Set all sources for multiple source support
  if (selectedSources.value.length > 0) {
    form.sources = selectedSources.value.map(source => {
      // Untuk purchase_order, source_id diisi dengan po_id (integer)
      // Untuk retail_food dan warehouse_retail_food, gunakan source_id
      let sourceIdValue = null;
      if (source.type === 'purchase_order') {
        sourceIdValue = source.po_id || null;
      } else {
        sourceIdValue = source.source_id || null;
      }
      
      return {
        source_type: source.type,
        source_id: sourceIdValue,
        po_id: source.type === 'purchase_order' ? source.po_id : null,
        gr_id: source.type === 'purchase_order' ? source.gr_id : null,
      };
    });
  } else {
    // Jika selectedSources kosong, buat source dari item pertama
    let sourceIdValue = null;
    if (itemSourceType === 'purchase_order') {
      // Untuk purchase_order, source_id diisi dengan po_id
      sourceIdValue = itemPoId || null;
    } else {
      // Untuk retail_food dan warehouse_retail_food, gunakan source_id
      sourceIdValue = itemSourceId || null;
    }
    
    form.sources = [{
      source_type: itemSourceType,
      source_id: sourceIdValue,
      po_id: itemSourceType === 'purchase_order' ? itemPoId : null,
      gr_id: itemSourceType === 'purchase_order' ? itemGrId : null,
    }];
  }
  
  // Hanya kirim item yang dicentang (hapus field selected dan _rowKey)
  const itemsToSubmit = selectedItems.map(item => {
    const { selected, _rowKey, ...itemData } = item;
    // Pastikan item_id dan unit_id dikirim dengan benar
    // Jika null/undefined, tetap kirim null (bukan string kosong)
    // Inertia akan handle null dengan benar
    return itemData;
  });
  form.items = itemsToSubmit;
  
  // Pastikan source info sudah ter-update sebelum submit
  if (isEdit.value) {
    form.put(`/contra-bons/${props.contraBon.id}`, {
      onSuccess: async () => {
        await Swal.close();
        await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
        router.visit('/contra-bons');
      },
      onError: async (errors) => {
        console.error('Error saving contra bon:', errors);
        // Close loading Swal first
        await Swal.close();
        
        let errorMessage = 'Terjadi kesalahan saat menyimpan data';
        
        if (errors) {
          // Inertia validation errors
          const errorMessages = Object.keys(errors).map(key => {
            const errorValue = errors[key];
            return Array.isArray(errorValue) ? errorValue.join(', ') : errorValue;
          });
          errorMessage = errorMessages.join('\n');
        }
        
        await Swal.fire({
          icon: 'error',
          title: 'Gagal Menyimpan',
          text: errorMessage,
          confirmButtonText: 'OK'
        });
      },
    });
  } else {
    form.post('/contra-bons', {
      onSuccess: async () => {
        await Swal.close();
        await Swal.fire('Berhasil', 'Data berhasil disimpan', 'success');
        router.visit('/contra-bons');
      },
      onError: async (errors) => {
        console.error('Error saving contra bon:', errors);
        // Close loading Swal first
        await Swal.close();
        
        let errorMessage = 'Terjadi kesalahan saat menyimpan data';
        
        if (errors) {
          // Inertia validation errors
          const errorMessages = Object.keys(errors).map(key => {
            const errorValue = errors[key];
            return Array.isArray(errorValue) ? errorValue.join(', ') : errorValue;
          });
          errorMessage = errorMessages.join('\n');
        }
        
        await Swal.fire({
          icon: 'error',
          title: 'Gagal Menyimpan',
          text: errorMessage,
          confirmButtonText: 'OK'
        });
      },
    });
  }
}

function goBack() {
  router.visit('/contra-bons');
}

function toggleAllItems(event) {
  const checked = event.target.checked;
  form.items.forEach(item => {
    item.selected = checked;
  });
}

// Helper function untuk mendapatkan item_name dari item atau source data
function getItemName(item) {
  
  if (item.item_name) return item.item_name;
  if (item.item?.name) return item.item.name;
  
  // Jika tidak ada, coba ambil dari source data
  if (item.source_type === 'purchase_order' && item.gr_item_id) {
    const source = selectedSources.value.find(s => 
      s.type === 'purchase_order' && s.po_id === item.po_id && s.gr_id === item.gr_id
    );
    if (source && source.data && source.data.items) {
      const grItem = source.data.items.find(i => i.id === item.gr_item_id || i.id === item.gr_item_id);
      if (grItem) {
        if (grItem.item_name) {
          item.item_name = grItem.item_name; // Cache untuk next time
          return grItem.item_name;
        }
      }
    }
  }
  
  return '-';
}

// Helper function untuk mendapatkan unit_name dari item atau source data
function getUnitName(item) {
  
  if (item.unit_name) return item.unit_name;
  if (item.unit?.name) return item.unit.name;
  
  // Jika tidak ada, coba ambil dari source data
  if (item.source_type === 'purchase_order' && item.gr_item_id) {
    const source = selectedSources.value.find(s => 
      s.type === 'purchase_order' && s.po_id === item.po_id && s.gr_id === item.gr_id
    );
    if (source && source.data && source.data.items) {
      const grItem = source.data.items.find(i => i.id === item.gr_item_id || i.id === item.gr_item_id);
      if (grItem) {
        if (grItem.unit_name) {
          item.unit_name = grItem.unit_name; // Cache untuk next time
          return grItem.unit_name;
        }
      }
    }
  }
  
  return '-';
}
</script>
<template>
  <AppLayout>
    <div class="max-w-5xl w-full mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
          <button @click="goBack" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
            <i class="fa-solid fa-file-circle-xmark text-blue-500"></i> {{ isEdit ? 'Edit' : 'Tambah' }} Contra Bon
          </h1>
        </div>
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
            Cara Menggunakan Contra Bon
          </button>
        </div>
      </div>
      <form @submit.prevent="onSubmit" class="space-y-6">
        <!-- Date Input -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date" v-model="form.date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.date" class="text-xs text-red-500 mt-1">{{ form.errors.date }}</div>
          </div>
        </div>

        <!-- Add Source Section -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-3">Tambah Sumber Data</label>
          <div class="flex flex-wrap gap-3">
            <button 
              type="button" 
              @click="openPOListModal"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 flex items-center gap-2"
            >
              <i class="fa fa-plus"></i>
              <span>Tambah PO/GR</span>
            </button>
            <button 
              type="button" 
              @click="openRetailFoodModal"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:ring-2 focus:ring-purple-500 flex items-center gap-2"
            >
              <i class="fa fa-plus"></i>
              <span>Tambah Retail Food</span>
            </button>
            <button 
              type="button" 
              @click="openWarehouseRetailFoodModal"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 flex items-center gap-2"
            >
              <i class="fa fa-plus"></i>
              <span>Tambah Warehouse Retail Food</span>
            </button>
          </div>
        </div>

        <!-- Selected Sources List -->
        <div v-if="selectedSources.length > 0" class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Sumber Data yang Dipilih</label>
          <div class="space-y-2">
            <div 
              v-for="source in selectedSources" 
              :key="source.key"
              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200"
            >
              <div class="flex items-center gap-2">
                <span v-if="source.type === 'purchase_order'" class="text-blue-500">
                  <i class="fa fa-file-invoice"></i>
                </span>
                <span v-else-if="source.type === 'retail_food'" class="text-purple-500">
                  <i class="fa fa-shopping-cart"></i>
                </span>
                <span v-else-if="source.type === 'warehouse_retail_food'" class="text-indigo-500">
                  <i class="fa fa-warehouse"></i>
                </span>
                <span class="font-medium text-gray-700">{{ source.display }}</span>
              </div>
              <button 
                type="button"
                @click="removeSource(source.key)"
                class="text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50"
                title="Hapus source dan semua item-nya"
              >
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Card Info Supplier -->
        <div v-if="supplierDetail" class="bg-yellow-50 rounded-lg p-4 shadow mb-4">
          <h3 class="font-bold mb-2">Info Supplier</h3>
          <div><b>Nama:</b> {{ supplierDetail.name }}</div>
          <div><b>PIC:</b> {{ supplierDetail.contact_person }}</div>
          <div><b>No. Telp:</b> {{ supplierDetail.phone }}</div>
          <div><b>Email:</b> {{ supplierDetail.email }}</div>
          <div><b>Alamat:</b> {{ supplierDetail.address }}</div>
          <div><b>Kota:</b> {{ supplierDetail.city }}</div>
          <div><b>Provinsi:</b> {{ supplierDetail.province }}</div>
          <div><b>Kode Pos:</b> {{ supplierDetail.postal_code }}</div>
          <div><b>NPWP:</b> {{ supplierDetail.npwp }}</div>
        </div>

        <!-- Upload File Contra Bon Fisik -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Upload Scan/Foto Contra Bon Fisik</label>
          <input type="file" accept="image/*" @change="onFileChange" class="mt-1 block" />
          <div v-if="fileImagePreview" class="mt-2">
            <img :src="fileImagePreview" alt="Preview" class="max-w-xs rounded shadow" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">No Invoice Supplier</label>
          <input type="text" v-model="form.supplier_invoice_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="No Invoice dari Supplier" />
          <div v-if="form.errors.supplier_invoice_number" class="text-xs text-red-500 mt-1">{{ form.errors.supplier_invoice_number }}</div>
        </div>

                 <div>
           <label class="block text-sm font-medium text-gray-700 mb-2">Detail Item</label>
           <div class="overflow-x-auto">
             <!-- Loading spinner hanya saat initial load -->
             <div v-if="initialLoading && !isEdit" class="flex justify-center items-center py-8">
               <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
               <span class="ml-2 text-gray-600">Memuat data...</span>
             </div>
             
             <!-- Items table -->
             <table v-else class="w-full min-w-full divide-y divide-gray-200">
               <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
                 <tr>
                   <th class="px-3 py-2 text-center text-xs font-bold text-blue-700 uppercase tracking-wider w-12">
                     <input 
                       type="checkbox" 
                       :checked="form.items.length > 0 && form.items.every(item => item.selected)"
                       @change="toggleAllItems"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                     />
                   </th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Source</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Price</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Diskon Item</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total</th>
                   <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Notes</th>
                 </tr>
               </thead>
               <tbody>
                 <tr v-if="form.items.length === 0" class="text-center text-gray-500">
                   <td colspan="9" class="px-3 py-4">Tidak ada data item</td>
                 </tr>
                 <tr v-for="(item, idx) in form.items" :key="item._rowKey || idx" :class="{ 'bg-gray-50': !item.selected }">
                   <td class="px-3 py-2 text-center">
                     <input 
                       type="checkbox" 
                       v-model="item.selected"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                     />
                   </td>
                   <td class="px-3 py-2 min-w-[150px] text-xs">
                     <span v-if="item.source_type === 'purchase_order'" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                       <i class="fa fa-file-invoice mr-1"></i>
                       {{ item.source_display || 'PO/GR' }}
                     </span>
                     <span v-else-if="item.source_type === 'retail_food'" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                       <i class="fa fa-shopping-cart mr-1"></i>
                       {{ item.source_display || 'Retail Food' }}
                     </span>
                     <span v-else-if="item.source_type === 'warehouse_retail_food'" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                       <i class="fa fa-warehouse mr-1"></i>
                       {{ item.source_display || 'Warehouse RF' }}
                     </span>
                     <span v-else class="text-gray-400">-</span>
                   </td>
                   <td class="px-3 py-2 min-w-[200px]">
                     <template v-if="item.item_name && item.item_name !== '-' && item.item_name !== ''">
                       {{ item.item_name }}
                     </template>
                     <template v-else-if="item.item && item.item.name">
                       {{ item.item.name }}
                     </template>
                     <template v-else>
                       {{ getItemName(item) }}
                     </template>
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     {{ item.quantity }}
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     <template v-if="item.unit_name && item.unit_name !== '-' && item.unit_name !== ''">
                       {{ item.unit_name }}
                     </template>
                     <template v-else-if="item.unit && item.unit.name">
                       {{ item.unit.name }}
                     </template>
                     <template v-else>
                       {{ getUnitName(item) }}
                     </template>
                   </td>
                   <td class="px-3 py-2 min-w-[120px]">
                     <input 
                       type="number" 
                       v-model.number="item.price" 
                       @input="updateItemTotal(item)"
                       @blur="updateItemTotal(item)"
                       step="0.01"
                       min="0"
                       class="w-full px-2 py-1 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                       placeholder="0.00"
                     />
                   </td>
                   <td class="px-3 py-2 min-w-[150px]">
                     <div class="space-y-1">
                       <div>
                         <label class="block text-[10px] text-gray-600 font-medium mb-0.5">Diskon %</label>
                         <input 
                           type="number" 
                           v-model.number="item.discount_percent" 
                           @input="onDiscountItemPercentChange(item)"
                           @blur="onDiscountItemPercentChange(item)"
                           step="0.01"
                           min="0"
                           max="100"
                           class="w-full px-2 py-1 rounded border-gray-300 text-xs focus:ring-blue-500 focus:border-blue-500"
                           placeholder="0"
                         />
                       </div>
                       <div>
                         <label class="block text-[10px] text-gray-600 font-medium mb-0.5">Diskon Rp</label>
                         <input 
                           type="number" 
                           v-model.number="item.discount_amount" 
                           @input="onDiscountItemAmountChange(item)"
                           @blur="onDiscountItemAmountChange(item)"
                           step="0.01"
                           min="0"
                           class="w-full px-2 py-1 rounded border-gray-300 text-xs focus:ring-blue-500 focus:border-blue-500"
                           placeholder="0"
                         />
                       </div>
                     </div>
                   </td>
                   <td class="px-3 py-2 min-w-[100px]">
                     {{ formatCurrency(calculateItemTotal(item)) }}
                   </td>
                   <td class="px-3 py-2 min-w-[120px]">
                     <input type="text" v-model="item.notes" class="w-full rounded border-gray-300" />
                   </td>
                 </tr>
               </tbody>
               <tfoot class="bg-gray-100">
                 <tr>
                   <td colspan="6" class="px-3 py-3 text-right font-bold text-gray-700">
                     Subtotal:
                   </td>
                   <td class="px-3 py-3 font-bold text-gray-700">
                     {{ formatCurrency(subtotalAmount) }}
                   </td>
                   <td colspan="2" class="px-3 py-3"></td>
                 </tr>
                 <tr>
                   <td colspan="4" class="px-3 py-3 text-right font-medium text-gray-700">
                     Discount Total:
                   </td>
                   <td class="px-3 py-3">
                     <div>
                       <label class="block text-xs text-gray-600 font-medium mb-1">Diskon %</label>
                       <input 
                         type="number" 
                         v-model.number="form.discount_total_percent" 
                         @input="onDiscountTotalPercentChange"
                         @blur="onDiscountTotalPercentChange"
                         step="0.01"
                         min="0"
                         max="100"
                         class="w-full px-2 py-1 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                         placeholder="0"
                       />
                     </div>
                   </td>
                   <td class="px-3 py-3">
                     <div>
                       <label class="block text-xs text-gray-600 font-medium mb-1">Diskon Rp</label>
                       <input 
                         type="number" 
                         v-model.number="form.discount_total_amount" 
                         @input="onDiscountTotalAmountChange"
                         @blur="onDiscountTotalAmountChange"
                         step="0.01"
                         min="0"
                         class="w-full px-2 py-1 rounded border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                         placeholder="0"
                       />
                     </div>
                   </td>
                   <td class="px-3 py-3 font-medium text-red-600">
                     {{ formatCurrency(subtotalAmount - totalAmount) }}
                   </td>
                   <td colspan="2" class="px-3 py-3"></td>
                 </tr>
                 <tr>
                   <td colspan="6" class="px-3 py-3 text-right font-bold text-gray-700">
                     Total:
                   </td>
                   <td class="px-3 py-3 font-bold text-blue-700">
                     {{ formatCurrency(totalAmount) }}
                   </td>
                   <td colspan="2" class="px-3 py-3"></td>
                 </tr>
               </tfoot>
             </table>
           </div>
         </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Notes</label>
          <textarea v-model="form.notes" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
          <div v-if="form.errors.notes" class="text-xs text-red-500 mt-1">{{ form.errors.notes }}</div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="goBack" class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">{{ isEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
        </div>
      </form>
    </div>

    <!-- Modal PO List -->
    <div v-if="showPOListModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Purchase Order</h3>
            <button @click="closePOListModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="poSearchQuery" 
              @input="filterPOList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
              placeholder="Cari PO, GR, Supplier, atau Outlet..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingPOGR" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Memuat data...</p>
          </div>
          <div v-else-if="filteredPOList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="p in filteredPOList" 
              :key="p.po_id + '-' + p.gr_id" 
              @click="selectPOFromModal(p)"
              class="p-4 cursor-pointer hover:bg-blue-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span v-if="p.source_type_display === 'PR Foods'" class="text-blue-500 text-xl">🔵</span>
                <span v-else-if="p.source_type_display === 'RO Supplier'" class="text-green-500 text-xl">🟢</span>
                <span v-else class="text-gray-500 text-xl">⚪</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">
                    {{ p.po_number }} - {{ p.gr_number }}
                  </div>
                  <div class="text-gray-600">{{ p.supplier_name }}</div>
                  <div v-if="p.outlet_names && p.outlet_names.length > 0" class="text-sm text-orange-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ p.outlet_names.join(', ') }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">{{ p.source_type_display }}</div>
                  <div class="text-sm text-gray-500">{{ p.po_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Retail Food List -->
    <div v-if="showRetailFoodModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Retail Food</h3>
            <button @click="closeRetailFoodModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="retailFoodSearchQuery" 
              @input="filterRetailFoodList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500" 
              placeholder="Cari Retail Food, Supplier, atau Outlet..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingPOGR" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
            <p class="mt-2 text-gray-600">Memuat data...</p>
          </div>
          <div v-else-if="filteredRetailFoodList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="rf in filteredRetailFoodList" 
              :key="rf.retail_food_id" 
              @click="selectRetailFoodFromModal(rf)"
              class="p-4 cursor-pointer hover:bg-purple-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span class="text-purple-500 text-xl">🟣</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">{{ rf.retail_number }}</div>
                  <div class="text-gray-600">{{ rf.supplier_name }}</div>
                  <div v-if="rf.outlet_name || rf.warehouse_outlet_name" class="text-sm text-purple-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ rf.outlet_name || rf.warehouse_outlet_name }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">Retail Food</div>
                  <div class="text-sm text-gray-500">{{ rf.transaction_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Warehouse Retail Food List -->
    <div v-if="showWarehouseRetailFoodModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] flex flex-col">
        <div class="p-6 border-b">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold">Pilih Warehouse Retail Food</h3>
            <button @click="closeWarehouseRetailFoodModal" class="text-gray-500 hover:text-gray-700">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="mt-4">
            <input 
              v-model="warehouseRetailFoodSearchQuery" 
              @input="filterWarehouseRetailFoodList"
              type="text" 
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
              placeholder="Cari Warehouse Retail Food, Supplier, atau Warehouse..."
            />
          </div>
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingPOGR" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <p class="mt-2 text-gray-600">Memuat data...</p>
          </div>
          <div v-else-if="filteredWarehouseRetailFoodList.length === 0" class="p-8 text-center text-gray-500">
            Tidak ada data yang ditemukan
          </div>
          <div v-else class="divide-y divide-gray-200">
            <div 
              v-for="rwf in filteredWarehouseRetailFoodList" 
              :key="rwf.retail_warehouse_food_id" 
              @click="selectWarehouseRetailFoodFromModal(rwf)"
              class="p-4 cursor-pointer hover:bg-indigo-50 transition-colors"
            >
              <div class="flex items-center gap-3">
                <span class="text-indigo-500 text-xl">🟦</span>
                <div class="flex-1">
                  <div class="font-semibold text-lg">{{ rwf.retail_number }}</div>
                  <div class="text-gray-600">{{ rwf.supplier_name }}</div>
                  <div v-if="rwf.warehouse_name || rwf.warehouse_division_name" class="text-sm text-indigo-600 mt-1">
                    <i class="fa fa-map-marker-alt"></i> {{ rwf.warehouse_name || '' }}{{ rwf.warehouse_name && rwf.warehouse_division_name ? ' - ' : '' }}{{ rwf.warehouse_division_name || '' }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm text-gray-500">Warehouse Retail Food</div>
                  <div class="text-sm text-gray-500">{{ rwf.transaction_date }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
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
              Tutorial: Panduan Contra Bon
            </h3>
            <button
              @click="showTutorial = false"
              class="text-white hover:text-gray-200 focus:outline-none"
            >
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <!-- Content -->
          <div class="px-6 py-6 max-h-[70vh] overflow-y-auto">
            <div class="space-y-6">
              <!-- Introduction -->
              <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                <h4 class="font-semibold text-green-800 mb-2">
                  <i class="fa fa-info-circle mr-2"></i>
                  Apa itu Contra Bon?
                </h4>
                <p class="text-sm text-green-700">
                  Contra Bon adalah dokumen yang digunakan untuk mencatat pengembalian barang atau koreksi terhadap 
                  transaksi pembelian sebelumnya. Fitur ini memungkinkan Anda untuk membuat contra bon dari berbagai sumber data, 
                  seperti Purchase Order (PO), Good Receipt (GR), Retail Food, dan Warehouse Retail Food.
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
                      <li><strong>Tanggal:</strong> Pilih tanggal contra bon (wajib)</li>
                      <li><strong>Supplier Invoice Number:</strong> Masukkan nomor invoice dari supplier (opsional)</li>
                      <li><strong>Notes:</strong> Tambahkan catatan jika diperlukan (opsional)</li>
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
                    <h4 class="font-semibold text-gray-900 mb-2">Tambah Sumber Data</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Klik salah satu tombol untuk menambahkan sumber data:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Tambah PO/GR:</strong> Untuk menambahkan data dari Purchase Order dan Good Receipt</li>
                      <li><strong>Tambah Retail Food:</strong> Untuk menambahkan data dari Retail Food</li>
                      <li><strong>Tambah Warehouse Retail Food:</strong> Untuk menambahkan data dari Warehouse Retail Food</li>
                    </ul>
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 mt-2">
                      <p class="text-xs text-gray-600">
                        <i class="fa fa-lightbulb text-yellow-500 mr-1"></i>
                        <strong>Tip:</strong> Anda bisa menambahkan beberapa sumber data sekaligus dalam satu contra bon.
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
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih Item dari Modal</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Setelah membuka modal sumber data:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Gunakan kotak pencarian untuk mencari data yang diinginkan</li>
                      <li>Klik pada item yang ingin ditambahkan</li>
                      <li>Item akan otomatis ditambahkan ke form contra bon</li>
                    </ul>
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
                    <h4 class="font-semibold text-gray-900 mb-2">Pilih Item yang Akan Dikembalikan</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Di tabel item, centang checkbox pada item yang ingin dikembalikan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Gunakan checkbox di header untuk memilih semua item sekaligus</li>
                      <li>Atau centang item secara individual</li>
                      <li>Hanya item yang dicentang yang akan disimpan ke contra bon</li>
                    </ul>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded mt-2">
                      <p class="text-xs text-amber-800">
                        <i class="fa fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Pastikan untuk memilih item yang benar sebelum menyimpan.
                      </p>
                    </div>
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
                    <h4 class="font-semibold text-gray-900 mb-2">Edit Item (Opsional)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Anda bisa mengedit detail item sebelum menyimpan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li><strong>Quantity:</strong> Sesuaikan jumlah item yang dikembalikan</li>
                      <li><strong>Price:</strong> Sesuaikan harga jika diperlukan</li>
                      <li><strong>Notes:</strong> Tambahkan catatan untuk item tertentu</li>
                    </ul>
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
                    <h4 class="font-semibold text-gray-900 mb-2">Hapus Sumber Data (Opsional)</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Jika Anda ingin menghapus sumber data yang sudah ditambahkan:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Klik tombol <strong>"Hapus"</strong> pada card sumber data</li>
                      <li>Semua item dari sumber data tersebut akan ikut terhapus</li>
                    </ul>
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
                    <h4 class="font-semibold text-gray-900 mb-2">Simpan Contra Bon</h4>
                    <p class="text-sm text-gray-700 mb-2">
                      Setelah semua data diisi dengan benar:
                    </p>
                    <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside">
                      <li>Klik tombol <strong>"Simpan"</strong> di bagian bawah form</li>
                      <li>Sistem akan memvalidasi data sebelum menyimpan</li>
                      <li>Jika berhasil, Anda akan diarahkan ke halaman daftar contra bon</li>
                    </ul>
                    <div class="bg-green-50 border-l-4 border-green-500 p-3 rounded mt-2">
                      <p class="text-xs text-green-800">
                        <i class="fa fa-check-circle mr-1"></i>
                        <strong>Info:</strong> Pastikan minimal ada satu item yang dipilih sebelum menyimpan.
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Payment Type Info -->
              <div class="border border-blue-200 rounded-lg p-4 bg-blue-50">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                    <i class="fa fa-info-circle text-sm"></i>
                  </div>
                  <div class="ml-4 flex-1">
                    <h4 class="font-semibold text-blue-900 mb-2">
                      <i class="fa fa-money-bill-wave mr-2"></i>
                      Informasi: Payment Type di Purchase Order
                    </h4>
                    <p class="text-sm text-blue-800 mb-2">
                      PO yang digunakan sebagai sumber data contra bon mungkin memiliki metode pembayaran:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                      <div class="bg-white p-3 rounded border border-blue-200">
                        <p class="text-xs font-semibold text-green-800 mb-1">
                          <i class="fa fa-check-circle mr-1"></i>
                          Bayar Lunas:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside">
                          <li>Pembayaran penuh sekaligus</li>
                          <li>Hanya 1x payment di Non Food Payment</li>
                        </ul>
                      </div>
                      <div class="bg-white p-3 rounded border border-blue-200">
                        <p class="text-xs font-semibold text-blue-800 mb-1">
                          <i class="fa fa-calendar-alt mr-1"></i>
                          Termin Bayar:
                        </p>
                        <ul class="text-xs text-gray-700 space-y-1 list-disc list-inside">
                          <li>Pembayaran bertahap</li>
                          <li>Multiple payments di Non Food Payment</li>
                          <li>Ada progress tracking pembayaran</li>
                        </ul>
                      </div>
                    </div>
                    <div class="bg-blue-100 border-l-4 border-blue-500 p-3 rounded mt-2">
                      <p class="text-xs text-blue-800">
                        <i class="fa fa-info-circle mr-1"></i>
                        <strong>Catatan:</strong> Payment type ditentukan saat membuat PO di menu Purchase Order Ops. 
                        Informasi ini akan muncul di menu Non Food Payment saat membuat payment untuk PO tersebut.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end">
            <button
              @click="showTutorial = false"
              class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 