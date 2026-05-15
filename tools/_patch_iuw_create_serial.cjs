const fs = require('fs');
const p = 'd:/Gawean/web/ymsofterp/resources/js/Pages/InternalUseWaste/Create.vue';
let c = fs.readFileSync(p, 'utf8');

const scriptInsert = `
const serialMode = ref(false)
const serialInput = ref('')
const serialInputRef = ref(null)
const serialScanning = ref(false)
const serialFeedback = ref('')
const serialFeedbackSuccess = ref(false)
const scannedSerials = ref([])

watch(
  () => form.value.warehouse_id,
  () => {
    serialFeedback.value = ''
  }
)
`;

if (!c.includes('serialMode')) {
  c = c.replace(
    "const loading = ref(false)\n",
    "const loading = ref(false)\n" + scriptInsert
  );
}

const serialFns = `
async function onSerialScan() {
  const input = serialInput.value.trim()
  if (!input) return
  if (!form.value.warehouse_id) {
    serialFeedback.value = 'Pilih warehouse dulu'
    serialFeedbackSuccess.value = false
    return
  }
  if (scannedSerials.value.some((s) => s.serial_number === input)) {
    serialFeedback.value = \`Serial "\${input}" sudah discan\`
    serialFeedbackSuccess.value = false
    serialInput.value = ''
    return
  }
  serialScanning.value = true
  try {
    const res = await axios.post(route('internal-use-waste.validate-serial'), {
      serial_number: input,
      warehouse_id: form.value.warehouse_id,
    })
    if (res.data.valid) {
      const serial = res.data.serial
      scannedSerials.value.push({
        serial_id: serial.id,
        serial_number: serial.serial_number,
        item_id: serial.item_id,
        item_name: serial.item_name,
        unit_id: serial.unit_id,
        unit_name: serial.unit_name,
        qty: serial.qty,
        qty_small: serial.qty_small,
      })
      serialFeedback.value = \`Serial "\${input}" valid\`
      serialFeedbackSuccess.value = true
    } else {
      serialFeedback.value = res.data.message || 'Serial tidak valid'
      serialFeedbackSuccess.value = false
    }
  } catch {
    serialFeedback.value = 'Gagal validasi serial'
    serialFeedbackSuccess.value = false
  } finally {
    serialScanning.value = false
    serialInput.value = ''
    nextTick(() => serialInputRef.value?.focus())
  }
}

function removeSerial(idx) {
  scannedSerials.value.splice(idx, 1)
}

function handleSerialKeyPress(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    onSerialScan()
  }
}
`;

if (!c.includes('onSerialScan')) {
  c = c.replace('function buildPayload()', serialFns + '\nfunction buildPayload()');
}

c = c.replace(
  `function buildPayload() {
  return {
    type: form.value.type,
    date: form.value.date,
    warehouse_id: form.value.warehouse_id,
    ruko_id: form.value.type === 'internal_use' ? form.value.ruko_id : null,
    notes: form.value.notes || null,
    items: form.value.items.map((l) => ({
      item_id: Number(l.item_id),
      qty: Number(l.qty),
      unit_id: Number(l.unit_id),
      notes: l.notes || null,
    })),
  }
}`,
  `function buildPayload() {
  const hasItems = form.value.items.some((l) => l.item_id && l.unit_id && Number(l.qty) > 0)
  const hasSerials = scannedSerials.value.length > 0
  return {
    type: form.value.type,
    date: form.value.date,
    warehouse_id: form.value.warehouse_id,
    ruko_id: form.value.type === 'internal_use' ? form.value.ruko_id : null,
    notes: form.value.notes || null,
    items: hasItems
      ? form.value.items
          .filter((l) => l.item_id && l.unit_id && Number(l.qty) > 0)
          .map((l) => ({
            item_id: Number(l.item_id),
            qty: Number(l.qty),
            unit_id: Number(l.unit_id),
            notes: l.notes || null,
          }))
      : [],
    serial_items: hasSerials
      ? scannedSerials.value.map((s) => ({
          serial_id: s.serial_id,
          serial_number: s.serial_number,
          item_id: s.item_id,
          unit_id: s.unit_id,
          unit_name: s.unit_name,
          qty: s.qty,
          qty_small: s.qty_small,
        }))
      : [],
  }
}`
);

c = c.replace(
  `async function submit() {
  for (const l of form.value.items) {
    if (!l.item_id || !l.unit_id || l.qty === '' || l.qty == null || Number(l.qty) <= 0) {
      Swal.fire({ icon: 'warning', title: 'Lengkapi semua baris', text: 'Setiap baris wajib punya item, qty, dan unit.' })
      return
    }
  }`,
  `async function submit() {
  const hasItems = form.value.items.some((l) => l.item_id && l.unit_id && Number(l.qty) > 0)
  const hasSerials = scannedSerials.value.length > 0
  if (!hasItems && !hasSerials) {
    Swal.fire({ icon: 'warning', title: 'Data kosong', text: 'Minimal 1 baris item (qty) atau 1 nomor seri.' })
    return
  }
  if (hasItems) {
    for (const l of form.value.items) {
      if (!l.item_id) continue
      if (!l.unit_id || l.qty === '' || l.qty == null || Number(l.qty) <= 0) {
        Swal.fire({ icon: 'warning', title: 'Lengkapi semua baris', text: 'Setiap baris item wajib punya qty dan unit.' })
        return
      }
    }
  }`
);

if (!c.includes('nextTick')) {
  c = c.replace("import { ref, watch } from 'vue'", "import { ref, watch, nextTick } from 'vue'");
}

const templateBlock = `
          <motion class="mb-4 border rounded-lg p-4" :class="serialMode ? 'border-indigo-300 bg-indigo-50/30' : 'border-gray-200'">
            <label class="flex items-center justify-between cursor-pointer">
              <span class="text-sm font-medium text-gray-700"><i class="fa-solid fa-qrcode mr-1 text-indigo-500"></i> Mode Nomor Seri</span>
              <input type="checkbox" v-model="serialMode" class="sr-only peer" />
              <motion class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-indigo-500 relative after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></motion>
            </label>
            <motion v-if="serialMode" class="mt-3 space-y-2">
              <input ref="serialInputRef" v-model="serialInput" @keypress="handleSerialKeyPress" type="text" placeholder="Scan nomor seri..." class="input input-bordered w-full" :disabled="serialScanning" />
              <p v-if="serialFeedback" class="text-sm" :class="serialFeedbackSuccess ? 'text-green-600' : 'text-red-600'">{{ serialFeedback }}</p>
              <motion v-if="scannedSerials.length" class="space-y-2">
                <p class="text-xs font-semibold text-indigo-700">{{ scannedSerials.length }} serial discan</p>
                <motion v-for="(s, sIdx) in scannedSerials" :key="s.serial_number" class="flex justify-between items-center border border-indigo-200 rounded-lg p-2 bg-white text-sm">
                  <motion>
                    <span class="font-mono font-semibold">{{ s.serial_number }}</span>
                    <span class="block text-gray-600">{{ s.item_name }} — {{ s.qty }} {{ s.unit_name }}</span>
                  </motion>
                  <button type="button" class="text-red-600" @click="removeSerial(sIdx)"><i class="fa fa-times"></i></button>
                </motion>
              </motion>
            </motion>
          </motion>

`.replace(/motion/g, 'motion');

if (!c.includes('Mode Nomor Seri')) {
  c = c.replace(
    '          <div class="border-t pt-4">',
    templateBlock.replace(/motion/g, 'motion') + '          <div class="border-t pt-4">'
  );
  // fix motion to div
  c = c.replace(/motion/g, 'div');
}

fs.writeFileSync(p, c);
console.log('OK');
