import jsPDF from 'jspdf';

function formatNumber(amount) {
  const n = Number(amount) || 0;
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(n);
}

function formatQty(qty) {
  const n = Number(qty);
  if (Number.isNaN(n)) return String(qty ?? '');
  return n.toFixed(2);
}

export async function generateStrukPDF({ orderNumber, date, outlet, items, kasirName, divisionName, warehouseName, roNumber, roDate, roCreatorName, showReprintLabel = false }) {
  // Hitung tinggi yang dibutuhkan
  let totalHeight = 0;
  totalHeight += 15; // Header (judul + company)
  totalHeight += 20; // Info (no, tanggal, outlet, ro)
  totalHeight += 8; // Garis
  totalHeight += 10; // Header item
  totalHeight += 8; // Garis

  // Hitung tinggi untuk items: nama + baris harga
  if (items && items.length) {
    items.forEach(i => {
      const nameLen = (i.name || '').length;
      const nameLines = nameLen > 28 ? Math.ceil(nameLen / 28) : 1;
      totalHeight += (nameLines * 3.5) + 3.5 + 4; // name + price line + spacing
    });
  } else {
    totalHeight += 8; // "TIDAK ADA ITEM"
  }

  totalHeight += 12; // Garis + kasir + thanks
  totalHeight += 55; // Signature blocks (Checker, Driver, Outlet) vertical
  totalHeight += 10; // Margin bottom

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
  pdf.text('DELIVERY ORDER', 40, y, { align: 'center' });
  y += 6;

  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 5;

  // Warehouse division/warehouse rata tengah, bold
  pdf.setFont(undefined, 'bold');
  if (divisionName || warehouseName) {
    pdf.text(`${divisionName || ''}${divisionName && warehouseName ? ' - ' : ''}${warehouseName || ''}`, 40, y, { align: 'center' });
    y += 5;
  }

  pdf.setFont(undefined, 'normal');
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${orderNumber}`, 2, y); y += 4;
  pdf.text(`Tanggal: ${date}`, 2, y); y += 4;
  pdf.text(`Outlet: ${outlet}`, 2, y); y += 4;
  if (roNumber) { pdf.text(`RO: ${roNumber}`, 2, y); y += 4; }
  if (roDate) { pdf.text(`Tgl RO: ${roDate}`, 2, y); y += 4; }
  if (roCreatorName) { pdf.text(`Pembuat RO: ${roCreatorName}`, 2, y); y += 4; }

  // Garis full width
  y += 1.5;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5;

  // ITEM LIST
  // Nama Item
  // QTY Unit Harga = Subtotal
  if (items && items.length) {
    items.forEach(i => {
      const nameLines = pdf.splitTextToSize(String(i.name || ''), 76);
      nameLines.forEach(line => {
        pdf.text(line, 2, y);
        y += 3.5;
      });

      const qty = formatQty(i.qty_scan);
      const unit = i.unit_code || i.unit || '';
      const price = formatNumber(i.unit_price ?? 0);
      const subtotal = formatNumber(i.subtotal ?? ((Number(i.qty_scan) || 0) * (Number(i.unit_price) || 0)));
      const priceLine = `${qty} ${unit} x ${price} = ${subtotal}`;
      const priceLines = pdf.splitTextToSize(priceLine, 76);
      priceLines.forEach(line => {
        pdf.text(line, 2, y);
        y += 3.5;
      });
      y += 1.5;
    });
  } else {
    pdf.text('TIDAK ADA ITEM', 2, y); y += 3.5;
  }

  // Garis full width sebelum kasir
  y += 1.5;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5;

  if (kasirName) { pdf.text(`Kasir: ${kasirName}`, 2, y); y += 4; }
  pdf.text('Terima kasih', 2, y);
  y += 8;

  // Kolom tanda tangan vertikal
  pdf.setFontSize(9);
  const signatureRoles = ['Checker', 'Driver', 'Outlet'];
  signatureRoles.forEach((role) => {
    pdf.setFont(undefined, 'bold');
    pdf.text(role, 2, y);
    y += 12;
    pdf.setDrawColor(0);
    pdf.setLineWidth(0.3);
    pdf.line(2, y, 50, y);
    y += 4;
    pdf.setFont(undefined, 'normal');
    pdf.setFontSize(7);
    pdf.text('(tanda tangan / nama)', 2, y);
    y += 8;
    pdf.setFontSize(9);
  });

  // Tambahkan margin bottom untuk roll paper
  y += 6;

  pdf.output('dataurlnewwindow');
}
