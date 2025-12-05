import jsPDF from 'jspdf';

export async function generateStrukPDF({ sale, items, customer, warehouse, division, showReprintLabel = false }) {
  // Hitung tinggi yang dibutuhkan
  let totalHeight = 0;
  totalHeight += 15; // Header (judul + company) - dikurangi
  totalHeight += 25; // Info (no, tanggal, customer, warehouse) - dikurangi
  totalHeight += 8; // Garis - dikurangi
  totalHeight += 10; // Header item - dikurangi
  totalHeight += 8; // Garis - dikurangi
  
  // Hitung tinggi untuk items
  if (items && items.length) {
    items.forEach(i => {
      const itemLines = i.item_name.length > 20 ? Math.ceil(i.item_name.length / 20) : 1;
      totalHeight += (itemLines * 3.5) + 8; // 3.5mm per line + 8mm spacing (termasuk price)
    });
  } else {
    totalHeight += 8; // "TIDAK ADA ITEM" - dikurangi
  }
  
  totalHeight += 12; // Garis + total - dikurangi
  totalHeight += 15; // Margin bottom - dikurangi
  
  // Buat PDF dengan tinggi yang tepat
  const pdf = new jsPDF({ unit: 'mm', format: [80, Math.max(297, totalHeight)] });
  let y = 10;
  
  // Label reprint kecil di pojok kanan atas
  if (showReprintLabel) {
    pdf.setFontSize(8);
    pdf.setFont(undefined, 'normal');
    pdf.text('reprint', 78, 7, { align: 'right' });
  }
  
  // Judul rata tengah, font besar, bold
  pdf.setFontSize(13);
  pdf.setFont(undefined, 'bold');
  pdf.text('RETAIL WAREHOUSE SALE', 40, y, { align: 'center' });
  y += 6; // dikurangi dari 7
  
  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 5; // dikurangi dari 6
  
  // Warehouse division/warehouse rata tengah, bold
  pdf.setFont(undefined, 'bold');
  if (division?.name || warehouse?.name) {
    pdf.text(`${division?.name || ''}${division?.name && warehouse?.name ? ' - ' : ''}${warehouse?.name || ''}`, 40, y, { align: 'center' });
    y += 5; // dikurangi dari 6
  }
  
  pdf.setFont(undefined, 'normal'); // Kembalikan ke normal untuk info lain
  
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${sale?.number || ''}`, 2, y); y += 4.5;
  pdf.text(`Tanggal: ${formatDate(sale?.sale_date || sale?.created_at)}`, 2, y); y += 4.5;
  pdf.text(`Customer: ${customer?.name || ''}`, 2, y); y += 4.5;
  if (customer?.code) { pdf.text(`Kode: ${customer.code}`, 2, y); y += 4.5; }
  if (customer?.phone) { pdf.text(`Telp: ${customer.phone}`, 2, y); y += 4.5; }
  
  // Garis pemisah
  y += 2;
  pdf.line(2, y, 78, y);
  y += 4;
  
  // Items
  if (items && items.length) {
    items.forEach(item => {
      const qty = String(item.qty || 0);
      const unit = String(item.unit || '').substring(0, 4); // Max 4 chars for unit
      const itemName = String(item.item_name || '');
      
      // Format: Qty Unit Item Name
      let itemLine = `${qty} ${unit.padEnd(4)} ${itemName}`;
      
      // Split long item names
      if (itemLine.length > 32) {
        const words = itemName.split(' ');
        let currentLine = `${qty} ${unit.padEnd(4)} `;
        let currentLength = currentLine.length;
        
        for (let i = 0; i < words.length; i++) {
          const word = words[i];
          if (currentLength + word.length + 1 <= 32) {
            currentLine += (i === 0 ? '' : ' ') + word;
            currentLength = currentLine.length;
          } else {
            pdf.text(currentLine, 2, y);
            y += 3.5;
            currentLine = '    ' + word; // Indent for continuation
            currentLength = currentLine.length;
          }
        }
        if (currentLine.trim()) {
          pdf.text(currentLine, 2, y);
          y += 3.5;
        }
      } else {
        pdf.text(itemLine, 2, y);
        y += 3.5;
      }
      
      // Price info
      const priceLine = `    @${formatCurrency(item.price || 0)} = ${formatCurrency(item.subtotal || 0)}`;
      pdf.text(priceLine, 2, y);
      y += 4.5; // Extra space for price line
    });
  } else {
    pdf.text('TIDAK ADA ITEM', 2, y);
    y += 8;
  }
  
  // Garis pemisah
  y += 2;
  pdf.line(2, y, 78, y);
  y += 4;
  
  // Total
  pdf.setFontSize(10);
  pdf.setFont(undefined, 'bold');
  pdf.text(`TOTAL: ${formatCurrency(sale?.total_amount || 0)}`, 2, y);
  y += 6;
  
  // Thanks message
  pdf.setFontSize(10);
  pdf.setFont(undefined, 'bold');
  pdf.text('Terima kasih', 40, y, { align: 'center' });
  
  // Notes if any
  if (sale?.notes) {
    pdf.setFontSize(8);
    pdf.setFont(undefined, 'normal');
    pdf.text('Catatan:', 2, y);
    y += 3;
    
    const notes = String(sale.notes);
    if (notes.length <= 32) {
      pdf.text(notes, 2, y);
      y += 3.5;
    } else {
      // Split long notes
      const words = notes.split(' ');
      let currentLine = '';
      for (const word of words) {
        if (currentLine.length + word.length + 1 <= 32) {
          currentLine += (currentLine ? ' ' : '') + word;
        } else {
          if (currentLine) {
            pdf.text(currentLine, 2, y);
            y += 3.5;
          }
          currentLine = word;
        }
      }
      if (currentLine) {
        pdf.text(currentLine, 2, y);
        y += 3.5;
      }
    }
  }
  
  return pdf;
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
