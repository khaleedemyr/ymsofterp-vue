<template>
  <div id="struk" class="struk-print">
    <div class="logo-wrap">
      <img :src="logoSrc" alt="Logo" class="logo" />
    </div>
    <pre class="pre-struk">{{ strukText }}</pre>
    <div class="footer">
      <div class="thanks">Terima kasih</div>
      <div class="signatures">
        <div class="signature-block">
          <div class="signature-label">Checker</div>
          <div class="signature-line"></div>
          <div class="signature-hint">(tanda tangan / nama)</div>
        </div>
        <div class="signature-block">
          <div class="signature-label">Driver</div>
          <div class="signature-line"></div>
          <div class="signature-hint">(tanda tangan / nama)</div>
        </div>
        <div class="signature-block">
          <div class="signature-label">Outlet</div>
          <div class="signature-line"></div>
          <div class="signature-hint">(tanda tangan / nama)</div>
        </div>
      </div>
      <div class="qr-wrap">
        <qrcode-vue :value="orderNumber" :size="70" />
      </div>
      <div class="qr-caption">Scan QR untuk cek status</div>
    </div>
  </div>
</template>

<script setup>
import QrcodeVue from 'qrcode.vue';
import { computed } from 'vue';
const props = defineProps(['orderNumber', 'date', 'outlet', 'items']);
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

const strukText = computed(() => {
  // Lebar total 32 karakter (untuk 80mm, Courier New 12px)
  let lines = [];
  lines.push('DELIVERY ORDER');
  lines.push('No: ' + props.orderNumber);
  lines.push('Tanggal: ' + props.date);
  lines.push('Outlet: ' + props.outlet);
  lines.push('--------------------------------');
  // Format: Qty Unit Code Nama Item (sejajar) - menggunakan unit_code
  for (const item of props.items) {
    lines.push(`${item.qty_scan} ${item.unit_code || item.unit} ${item.name}`);
  }
  lines.push('--------------------------------');
  return lines.join('\n');
});
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
}
.footer {
  text-align: center;
  margin-top: 8px;
}
.thanks {
  font-size: 12px;
  font-weight: bold;
  margin-bottom: 8px;
  text-align: left;
}
.signatures {
  text-align: left;
  margin: 6px 0 10px 0;
}
.signature-block {
  margin-bottom: 10px;
}
.signature-label {
  font-size: 11px;
  font-weight: bold;
  margin-bottom: 14px;
}
.signature-line {
  border-bottom: 1px solid #000;
  width: 45mm;
  margin-bottom: 2px;
}
.signature-hint {
  font-size: 9px;
  color: #333;
}
.qr-wrap {
  margin: 4px auto 2px auto;
  display: flex;
  justify-content: center;
}
.qr-caption {
  font-size: 10px;
  margin-top: 1px;
}
@media print {
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