<template>
  <div>
    <!-- Print Controls (hidden when printing) -->
    <div class="print-controls">
      <button @click="window.print()" class="print-btn">
        <i class="fa-solid fa-print"></i> Print
      </button>
      <button @click="downloadPDF" class="download-btn">
        <i class="fa-solid fa-download"></i> Download PDF
      </button>
    </div>

    <!-- Struk Content -->
    <div id="struk" class="struk-print">
      <div class="logo-wrap">
        <img :src="logoSrc" alt="Logo" class="logo" />
      </div>
      <pre class="pre-struk">{{ strukText }}</pre>
      <div class="footer">
        <div class="thanks">Terima kasih</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { generateStrukPDF } from './generateStrukPDF.js';

const props = defineProps({
  sale: Object,
  items: Array,
  customer: Object,
  warehouse: Object,
  division: Object
});

const logoSrc = '/images/logojustusgroup.png';

function pad(str, len, align = 'left') {
  str = String(str);
  if (str.length >= len) return str.slice(0, len);
  if (align === 'right') return ' '.repeat(len - str.length) + str;
  if (align === 'center') {
    const pad = len - str.length;
    const left = Math.floor(pad / 2);
    const right = pad - left;
    return ' '.repeat(left) + str + ' '.repeat(right);
  }
  return str + ' '.repeat(len - str.length);
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
}

const strukText = computed(() => {
  // Lebar total 32 karakter (untuk 80mm, Courier New 12px)
  let lines = [];
  
  // Header
  lines.push('RETAIL WAREHOUSE SALE');
  lines.push('JUSTUS GROUP');
  
  // Warehouse/Division info
  if (props.division?.name || props.warehouse?.name) {
    const warehouseInfo = `${props.division?.name || ''}${props.division?.name && props.warehouse?.name ? ' - ' : ''}${props.warehouse?.name || ''}`;
    lines.push(warehouseInfo);
  }
  
  lines.push('--------------------------------');
  
  // Sale info
  lines.push(`No: ${props.sale?.number || ''}`);
  lines.push(`Tanggal: ${formatDate(props.sale?.sale_date || props.sale?.created_at)}`);
  lines.push(`Customer: ${props.customer?.name || ''}`);
  if (props.customer?.code) {
    lines.push(`Kode: ${props.customer.code}`);
  }
  if (props.customer?.phone) {
    lines.push(`Telp: ${props.customer.phone}`);
  }
  
  lines.push('--------------------------------');
  
  // Items
  if (props.items && props.items.length > 0) {
    props.items.forEach(item => {
      // Format: Qty Unit Item Name
      const qty = String(item.qty || 0);
      const unit = String(item.unit || '').substring(0, 4); // Max 4 chars for unit
      const itemName = String(item.item_name || '');
      
      // Calculate available space for item name
      const usedSpace = qty.length + 1 + unit.length + 1; // qty + space + unit + space
      const availableSpace = 32 - usedSpace;
      
      if (itemName.length <= availableSpace) {
        lines.push(`${qty} ${unit.padEnd(4)} ${itemName}`);
      } else {
        // Split long item names
        const words = itemName.split(' ');
        let currentLine = `${qty} ${unit.padEnd(4)} `;
        let currentLength = currentLine.length;
        
        for (let i = 0; i < words.length; i++) {
          const word = words[i];
          if (currentLength + word.length + 1 <= 32) {
            currentLine += (i === 0 ? '' : ' ') + word;
            currentLength = currentLine.length;
          } else {
            lines.push(currentLine);
            currentLine = '    ' + word; // Indent for continuation
            currentLength = currentLine.length;
          }
        }
        if (currentLine.trim()) {
          lines.push(currentLine);
        }
      }
      
      // Price info
      const priceLine = `    @${formatCurrency(item.price || 0)} = ${formatCurrency(item.subtotal || 0)}`;
      lines.push(priceLine);
    });
  } else {
    lines.push('TIDAK ADA ITEM');
  }
  
  lines.push('--------------------------------');
  
  // Total
  lines.push(`TOTAL: ${formatCurrency(props.sale?.total_amount || 0)}`);
  
  // Notes if any
  if (props.sale?.notes) {
    lines.push('--------------------------------');
    lines.push('Catatan:');
    const notes = String(props.sale.notes);
    if (notes.length <= 32) {
      lines.push(notes);
    } else {
      // Split long notes
      const words = notes.split(' ');
      let currentLine = '';
      for (const word of words) {
        if (currentLine.length + word.length + 1 <= 32) {
          currentLine += (currentLine ? ' ' : '') + word;
        } else {
          if (currentLine) lines.push(currentLine);
          currentLine = word;
        }
      }
      if (currentLine) lines.push(currentLine);
    }
  }
  
  return lines.join('\n');
});

// Auto print when component mounts
onMounted(() => {
  // Auto print after a short delay
  setTimeout(() => {
    window.print();
  }, 500);
});

// Function to generate and download PDF
async function downloadPDF() {
  try {
    const pdf = await generateStrukPDF({
      sale: props.sale,
      items: props.items,
      customer: props.customer,
      warehouse: props.warehouse,
      division: props.division
    });
    
    const filename = `Retail_Sale_${props.sale?.number || 'Unknown'}.pdf`;
    pdf.save(filename);
  } catch (error) {
    console.error('Error generating PDF:', error);
    alert('Gagal generate PDF: ' + error.message);
  }
}
</script>

<style>
.struk-print {
  width: 72mm;
  font-family: 'Courier New', Consolas, monospace;
  font-size: 12px;
  color: #000;
  background: #fff;
  padding: 0 0 0 4mm;
}
.logo-wrap {
  text-align: center;
  margin-bottom: 2px;
}
.logo {
  width: 28mm;
  max-width: 60%;
  margin: 0 auto 2px auto;
  display: block;
}
.pre-struk {
  margin: 0 0 4px 0;
  padding: 0;
  font-size: 12px;
  line-height: 1.2;
  white-space: pre-wrap;
  word-wrap: break-word;
}
.footer {
  text-align: center;
  margin-top: 8px;
}
.thanks {
  font-size: 12px;
  font-weight: bold;
  margin-bottom: 2px;
}
 /* Print Controls Styling */
.print-controls {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1000;
  display: flex;
  gap: 10px;
}

.print-btn, .download-btn {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 10px 15px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 5px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: background 0.2s;
}

.print-btn:hover, .download-btn:hover {
  background: #2563eb;
}

.download-btn {
  background: #10b981;
}

.download-btn:hover {
  background: #059669;
}

@media print {
  .print-controls {
    display: none !important;
  }
  
  html, body { 
    width: 80mm !important; 
    margin: 0 !important; 
    padding: 0 !important; 
    background: #fff !important; 
    height: auto !important;
    min-height: auto !important;
  }
  body * { visibility: hidden !important; }
  #struk, #struk * { visibility: visible !important; }
  #struk {
    position: relative !important;
    left: 0 !important;
    top: 0 !important;
    width: 80mm !important;
    min-width: 80mm !important;
    max-width: 80mm !important;
    margin: 0 !important;
    padding: 5mm 0 10mm 4mm !important;
    background: #fff !important;
    z-index: 99999 !important;
    page-break-after: always !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .struk-print {
    width: 72mm !important;
    margin: 0 !important;
    padding: 0 !important;
  }
}
</style>
