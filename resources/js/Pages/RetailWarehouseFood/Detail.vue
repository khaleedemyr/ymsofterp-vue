<template>
  <AppLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 md:px-8">
      <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl p-8">
        <div class="flex justify-between items-center mb-8">
          <h1 class="text-2xl font-bold flex items-center gap-2 text-blue-700">
            <i class="fa-solid fa-warehouse text-blue-500"></i> Detail Warehouse Retail Food
          </h1>
          <div class="flex gap-2">
            <button @click="goBack" class="btn btn-ghost px-4 py-2 rounded-lg">
              <i class="fa fa-arrow-left mr-2"></i> Kembali
            </button>
            <button v-if="canDelete" @click="confirmDelete" class="btn btn-error px-4 py-2 rounded-lg">
              <i class="fa fa-trash mr-2"></i> Hapus
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
          <div>
            <div class="text-sm text-gray-500 mb-1">Tanggal</div>
            <div class="font-medium">{{ formatDate(props.retailWarehouseFood.transaction_date) }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">No Transaksi</div>
            <div class="font-medium">{{ props.retailWarehouseFood.retail_number }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Warehouse</div>
            <div class="font-medium">{{ props.retailWarehouseFood.warehouse?.name || props.retailWarehouseFood.warehouse_name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Warehouse Division</div>
            <div class="font-medium">{{ props.retailWarehouseFood.warehouse_division?.name || props.retailWarehouseFood.warehouse_division_name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Status</div>
            <div>
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                props.retailWarehouseFood.status === 'approved' ? 'bg-green-100 text-green-800' :
                props.retailWarehouseFood.status === 'rejected' ? 'bg-red-100 text-red-800' :
                'bg-yellow-100 text-yellow-800'
              ]">
                {{ formatStatus(props.retailWarehouseFood.status) }}
              </span>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Metode Pembayaran</div>
            <div>
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                props.retailWarehouseFood.payment_method === 'cash' ? 'bg-green-100 text-green-800' :
                'bg-blue-100 text-blue-800'
              ]">
                {{ props.retailWarehouseFood.payment_method === 'cash' ? 'Cash' : 'Contra Bon' }}
              </span>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Supplier</div>
            <div class="font-medium">{{ props.retailWarehouseFood.supplier?.name || '-' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500 mb-1">Dibuat Oleh</div>
            <div class="font-medium">{{ props.retailWarehouseFood.creator?.nama_lengkap || '-' }}</div>
          </div>
        </div>

        <div class="mb-8">
          <div class="text-sm text-gray-500 mb-2">Items</div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Harga</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Serial</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="item in props.retailWarehouseFood.items" :key="item.id">
                  <td class="px-3 py-2">{{ item.item_name }}</td>
                  <td class="px-3 py-2">{{ item.qty }}</td>
                  <td class="px-3 py-2">{{ item.unit }}</td>
                  <td class="px-3 py-2">{{ formatRupiah(item.price) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatRupiah(item.subtotal) }}</td>
                  <td class="px-3 py-2">
                    <div class="flex flex-col gap-1 min-w-[140px]">
                      <button
                        type="button"
                        class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200"
                        @click="generateSerial(item)"
                      >
                        Generate Serial
                      </button>
                      <button
                        type="button"
                        class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200"
                        @click="showSerials(item)"
                      >
                        Lihat Serial ({{ serialSummary[item.id] || 0 }})
                      </button>
                      <button
                        type="button"
                        class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="(serialInUse[item.id] || 0) > 0"
                        :title="(serialInUse[item.id] || 0) > 0 ? 'Ada serial yang sudah digunakan — tidak bisa rollback.' : ''"
                        @click="rollbackSerial(item)"
                      >
                        Rollback Serial
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="px-3 py-2 text-right font-bold">Total:</td>
                  <td class="px-3 py-2 text-right font-bold">{{ formatRupiah(props.retailWarehouseFood.total_amount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <div v-if="props.retailWarehouseFood.notes" class="mb-8">
          <div class="text-sm text-gray-500 mb-1">Catatan</div>
          <div class="bg-gray-50 p-4 rounded-lg">{{ props.retailWarehouseFood.notes }}</div>
        </div>

        <div v-if="props.retailWarehouseFood.invoices && props.retailWarehouseFood.invoices.length" class="mb-8">
          <div class="text-sm text-gray-500 mb-1">Bon/Invoice</div>
          <div class="flex flex-wrap gap-3">
            <div v-for="inv in props.retailWarehouseFood.invoices" :key="inv.id" class="w-32 h-32 border rounded overflow-hidden flex items-center justify-center bg-gray-50">
              <a :href="`/storage/${inv.file_path}`" target="_blank" rel="noopener">
                <img :src="`/storage/${inv.file_path}`" class="object-contain w-full h-full hover:scale-110 transition-transform duration-200" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import JsBarcode from 'jsbarcode'
import jsPDF from 'jspdf'

const props = defineProps({
  retailWarehouseFood: Object
})

const serialSummary = ref({})
const serialInUse = ref({})

const loadSerialSummary = async () => {
  if (!props.retailWarehouseFood?.id) return
  try {
    const { data } = await axios.get(`/api/retail-warehouse-food/${props.retailWarehouseFood.id}/serial-summary`)
    const mapped = {}
    const inUseMap = {}
    ;(data || []).forEach((row) => {
      const id = Number(row.retail_warehouse_food_item_id)
      if (!Number.isFinite(id)) return
      mapped[id] = Number(row.total || 0)
      inUseMap[id] = Number(row.in_use || 0)
    })
    serialSummary.value = mapped
    serialInUse.value = inUseMap
  } catch {
    serialSummary.value = {}
    serialInUse.value = {}
  }
}

const generateSerial = async (item) => {
  try {
    const { data } = await axios.get(`/api/retail-warehouse-food-items/${item.id}/serial-units`)
    const units = data?.units || []
    if (!units.length) {
      await Swal.fire('Info', 'Unit konversi item tidak ditemukan.', 'info')
      return
    }

    const options = units.reduce((acc, unit) => {
      acc[unit.unit_id] = `${unit.unit_name} (qty: ${unit.converted_qty})`
      return acc
    }, {})

    const selected = await Swal.fire({
      title: `Generate Serial — ${item.item_name}`,
      html: `Qty: <b>${data.qty_received}</b> ${data.received_unit_name || ''}`,
      input: 'select',
      inputOptions: options,
      inputPlaceholder: 'Pilih unit',
      showCancelButton: true,
      confirmButtonText: 'Lanjut',
      cancelButtonText: 'Batal',
      inputValidator: (value) => (!value ? 'Unit wajib dipilih' : undefined),
    })

    if (!selected.isConfirmed) return

    const unitSelected = units.find((u) => Number(u.unit_id) === Number(selected.value))
    const baseQty = Number(unitSelected?.converted_qty ?? 0)
    const baseUnitName = unitSelected?.unit_name || ''

    let allUnits = []
    try {
      const { data: fetchedUnits } = await axios.get('/api/fgr-serial/units')
      allUnits = fetchedUnits || []
    } catch {
      /* ignore */
    }

    const unitOptionsHtml = allUnits.map((u) => `<option value="${u.id}">${u.name}</option>`).join('')

    const { value: formValues, isConfirmed } = await Swal.fire({
      title: 'Konversi Unit (Opsional)',
      html: `
        <div style="text-align:left;font-size:14px;">
          <div style="margin-bottom:10px;">
            <strong>Unit terpilih:</strong> ${baseUnitName}<br>
            <strong>Qty hasil konversi:</strong> ${baseQty}
          </div>
          <div style="margin-bottom:10px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Mode:</label>
            <div style="display:flex;gap:16px;">
              <label style="cursor:pointer;"><input type="radio" name="swal-rwf-conv-mode" value="no" checked> Tanpa Konversi</label>
              <label style="cursor:pointer;"><input type="radio" name="swal-rwf-conv-mode" value="yes"> Konversi Unit</label>
            </div>
          </div>
          <div id="swal-rwf-conv-wrapper" style="display:none;margin-bottom:10px;">
            <div style="margin-bottom:8px;">
              <label style="font-weight:600;display:block;margin-bottom:4px;">Unit Tujuan Serial:</label>
              <select id="swal-rwf-repack-unit" class="swal2-select" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
                <option value="">-- Pilih Unit --</option>
                ${unitOptionsHtml}
              </select>
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:4px;">1 <span id="swal-rwf-target-unit-label">[unit tujuan]</span> = berapa ${baseUnitName}?</label>
              <input type="number" id="swal-rwf-repack-qty" min="0.01" step="0.01" value="1" class="swal2-input" style="width:100%;margin:0;">
            </div>
          </div>
          <div style="margin-top:12px;padding:8px;background:#f3f4f6;border-radius:6px;">
            <span style="font-weight:600;">Jumlah serial:</span> <span id="swal-rwf-serial-count">${baseQty}</span>
          </div>
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, generate',
      cancelButtonText: 'Batal',
      didOpen: () => {
        const radios = document.querySelectorAll('input[name="swal-rwf-conv-mode"]')
        const convWrapper = document.getElementById('swal-rwf-conv-wrapper')
        const unitSelect = document.getElementById('swal-rwf-repack-unit')
        const qtyInput = document.getElementById('swal-rwf-repack-qty')
        const countDisplay = document.getElementById('swal-rwf-serial-count')
        const targetUnitLabel = document.getElementById('swal-rwf-target-unit-label')

        const updateCount = () => {
          const mode = document.querySelector('input[name="swal-rwf-conv-mode"]:checked')?.value || 'no'
          if (mode === 'yes') {
            const repackQty = Math.max(0.01, parseFloat(qtyInput.value) || 1)
            countDisplay.textContent = Math.ceil(baseQty / repackQty)
          } else {
            countDisplay.textContent = baseQty
          }
        }

        radios.forEach((r) =>
          r.addEventListener('change', (e) => {
            convWrapper.style.display = e.target.value === 'yes' ? 'block' : 'none'
            updateCount()
          })
        )

        unitSelect.addEventListener('change', () => {
          const selectedOption = unitSelect.options[unitSelect.selectedIndex]
          targetUnitLabel.textContent = selectedOption?.text || '[unit tujuan]'
        })

        qtyInput.addEventListener('input', updateCount)
      },
      preConfirm: () => {
        const mode = document.querySelector('input[name="swal-rwf-conv-mode"]:checked')?.value || 'no'
        if (mode === 'yes') {
          const repackUnitId = document.getElementById('swal-rwf-repack-unit')?.value
          const repackQty = parseFloat(document.getElementById('swal-rwf-repack-qty')?.value) || 0
          if (!repackUnitId) {
            Swal.showValidationMessage('Pilih unit tujuan terlebih dahulu')
            return false
          }
          if (repackQty <= 0) {
            Swal.showValidationMessage('Qty konversi harus lebih dari 0')
            return false
          }
          return { repack_unit_id: parseInt(repackUnitId, 10), repack_qty: repackQty }
        }
        return { repack_unit_id: null, repack_qty: null }
      },
    })

    if (!isConfirmed || !formValues) return

    const response = await axios.post(`/api/retail-warehouse-food-items/${item.id}/generate-serials`, {
      unit_id: Number(selected.value),
      repack_unit_id: formValues.repack_unit_id,
      repack_qty: formValues.repack_qty,
    })

    await Swal.fire('Berhasil', response.data?.message || 'Serial berhasil dibuat.', 'success')
    await loadSerialSummary()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal generate serial.'
    await Swal.fire('Error', message, 'error')
  }
}

const downloadSerialPDF = (serials, itemName, meta = {}) => {
  if (!serials?.length) return

  const labelWidth = 100
  const labelHeight = 50
  const gap = 5
  const marginLeft = 5
  const marginTop = 5
  const columnsPerRow = 3
  const pdfWidth = 297
  const pdfHeight = 210
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: [pdfWidth, pdfHeight] })
  const rowHeight = labelHeight + gap
  const usableHeight = pdfHeight - marginTop * 2
  const rowsPerPage = Math.max(1, Math.floor(usableHeight / rowHeight))

  serials.forEach((serial, idx) => {
    const itemsPerPage = columnsPerRow * rowsPerPage
    const indexInPage = idx % itemsPerPage
    if (idx > 0 && indexInPage === 0) {
      doc.addPage([pdfWidth, pdfHeight], 'landscape')
    }

    const rowIdx = Math.floor(indexInPage / columnsPerRow)
    const colIdx = indexInPage % columnsPerRow
    const x = marginLeft + colIdx * (labelWidth + gap)
    const y = marginTop + rowIdx * rowHeight

    doc.setDrawColor(0, 0, 0)
    doc.setLineWidth(0.5)
    doc.rect(x, y, labelWidth, labelHeight)

    const areaBarcodeW = labelWidth - 10
    const areaBarcodeH = 20
    const scale = 3
    const canvas = document.createElement('canvas')
    canvas.width = areaBarcodeW * scale
    canvas.height = areaBarcodeH * scale
    JsBarcode(canvas, serial, { width: 1.5 * scale, height: areaBarcodeH * scale, displayValue: false })

    const barcodeX = x + (labelWidth - areaBarcodeW) / 2
    doc.addImage(canvas, 'PNG', barcodeX, y + 3, areaBarcodeW, areaBarcodeH)

    let currentY = y + areaBarcodeH + 5
    doc.setFontSize(8)
    doc.setFont(undefined, 'bold')
    doc.text(`SERIAL: ${serial}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 4.5
    doc.setFontSize(9)
    doc.setFont(undefined, 'bold')
    doc.text(`${itemName || ''}`, x + labelWidth / 2, currentY, { align: 'center' })
    currentY += 3.5

    if (meta?.repackUnitName && meta?.repackQty) {
      const fmtRepackQty = parseFloat(Number(meta.repackQty).toFixed(4)).toString()
      doc.setFontSize(7)
      doc.setFont(undefined, 'bold')
      doc.text(`1 ${meta.repackUnitName.toUpperCase()} = ${fmtRepackQty} ${(meta.unitName || '').toUpperCase()}`, x + labelWidth / 2, currentY, { align: 'center' })
    }
  })

  const firstSerial = serials[0] || 'serial'
  doc.save(`${firstSerial}_labels_10x5cm.pdf`)
}

const showSerials = async (item) => {
  try {
    const { data } = await axios.get(`/api/retail-warehouse-food-items/${item.id}/serials`)
    if (!data || !data.length) {
      await Swal.fire('Info', 'Belum ada serial untuk item ini.', 'info')
      return
    }

    const fmtQty = (v) => (v != null ? parseFloat(Number(v).toFixed(4)).toString() : '')

    const rowsHtml = data
      .slice(0, 200)
      .map((row, idx) => {
        const convInfo =
          row.repack_unit_id && row.repack_qty
            ? `<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${row.repack_unit_name || '?'} = ${fmtQty(row.repack_qty)} ${row.unit_name || ''}</span>`
            : `<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>`
        return `<tr>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">${idx + 1}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.serial_number}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.unit_name || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${convInfo}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.pr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.po_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;">${row.gr_number || '-'}</td>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">
              <button type="button" class="rwf-serial-pdf-btn" data-serial="${row.serial_number}"
                data-repack-unit-name="${row.repack_unit_name || ''}" data-repack-qty="${row.repack_qty || ''}"
                data-unit-name="${row.unit_name || ''}"
                style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;">PDF 10x5</button>
            </td>
          </tr>`
      })
      .join('')

    await Swal.fire({
      title: `Serial — ${item.item_name}`,
      width: 980,
      html: `
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button id="rwf-download-all-serial-pdf-btn" type="button"
            style="padding:6px 10px;background:#dbeafe;color:#1d4ed8;border-radius:6px;border:0;cursor:pointer;font-size:12px;font-weight:600;">
            Download All PDF (10x5cm)
          </button>
        </div>
        <div style="max-height:420px;overflow:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr>
                <th style="border:1px solid #ddd;padding:4px;">No</th>
                <th style="border:1px solid #ddd;padding:4px;">Serial</th>
                <th style="border:1px solid #ddd;padding:4px;">Unit</th>
                <th style="border:1px solid #ddd;padding:4px;">Konversi</th>
                <th style="border:1px solid #ddd;padding:4px;">No PR</th>
                <th style="border:1px solid #ddd;padding:4px;">No PO</th>
                <th style="border:1px solid #ddd;padding:4px;">No RWF</th>
                <th style="border:1px solid #ddd;padding:4px;">Print</th>
              </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>
      `,
      didOpen: () => {
        const downloadAllBtn = document.getElementById('rwf-download-all-serial-pdf-btn')
        if (downloadAllBtn) {
          downloadAllBtn.addEventListener('click', () => {
            downloadSerialPDF(
              data.map((row) => row.serial_number),
              item.item_name,
              {
                repackUnitName: data[0]?.repack_unit_name || null,
                repackQty: data[0]?.repack_qty || null,
                unitName: data[0]?.unit_name || '',
              }
            )
          })
        }

        document.querySelectorAll('.rwf-serial-pdf-btn').forEach((btn) => {
          btn.addEventListener('click', (event) => {
            const serial = event.target?.getAttribute('data-serial')
            const repackUnitName = event.target?.getAttribute('data-repack-unit-name') || null
            const repackQty = event.target?.getAttribute('data-repack-qty') || null
            const unitName = event.target?.getAttribute('data-unit-name') || ''
            if (serial) {
              downloadSerialPDF([serial], item.item_name, {
                repackUnitName: repackUnitName || null,
                repackQty: repackQty ? parseFloat(repackQty) : null,
                unitName,
              })
            }
          })
        })
      },
    })
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal mengambil serial.'
    await Swal.fire('Error', message, 'error')
  }
}

const rollbackSerial = async (item) => {
  const confirm = await Swal.fire({
    title: 'Rollback serial?',
    text: 'Semua serial untuk baris ini akan dihapus.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, rollback',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#d33',
  })

  if (!confirm.isConfirmed) return

  try {
    const { data } = await axios.delete(`/api/retail-warehouse-food-items/${item.id}/serials`)
    await Swal.fire('Berhasil', data?.message || 'Rollback serial berhasil.', 'success')
    await loadSerialSummary()
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal rollback serial.'
    await Swal.fire('Error', message, 'error')
  }
}

onMounted(() => {
  loadSerialSummary()
})

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')

const canDelete = computed(() => {
  return userOutletId.value === 1
})

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

function formatStatus(status) {
  const statusMap = {
    draft: 'Draft',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan'
  }
  return statusMap[status] || status
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function goBack() {
  router.visit('/retail-warehouse-food')
}

async function confirmDelete() {
  const result = await Swal.fire({
    title: 'Hapus Transaksi?',
    text: 'Transaksi yang dihapus tidak dapat dikembalikan',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#ef4444'
  })

  if (result.isConfirmed) {
    try {
      const res = await axios.delete(`/retail-warehouse-food/${props.retailWarehouseFood.id}`)
      if (res.data.message) {
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: res.data.message,
          timer: 1500,
          showConfirmButton: false
        })
        router.visit('/retail-warehouse-food')
      }
    } catch (e) {
      Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: e.response?.data?.message || 'Gagal menghapus transaksi'
      })
    }
  }
}
</script>

