import jsPDF from 'jspdf';

export async function generateStrukPDF({ orderNumber, date, outlet, items, kasirName, divisionName, warehouseName, roNumber, roDate, roCreatorName, showReprintLabel = false }) {
  // Hitung tinggi yang dibutuhkan
  let totalHeight = 0;
  totalHeight += 15; // Header (judul + company) - dikurangi
  totalHeight += 20; // Info (no, tanggal, outlet, ro) - dikurangi
  totalHeight += 8; // Garis - dikurangi
  totalHeight += 10; // Header item - dikurangi
  totalHeight += 8; // Garis - dikurangi
  
  // Hitung tinggi untuk items
  if (items && items.length) {
    items.forEach(i => {
      const itemLines = i.name.length > 20 ? Math.ceil(i.name.length / 20) : 1;
      totalHeight += (itemLines * 3.5) + 4; // 3.5mm per line + 4mm spacing - dikurangi
    });
  } else {
    totalHeight += 8; // "TIDAK ADA ITEM" - dikurangi
  }
  
  totalHeight += 12; // Garis + footer - dikurangi
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
  pdf.text('DELIVERY ORDER', 40, y, { align: 'center' });
  y += 6; // dikurangi dari 7
  
  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 5; // dikurangi dari 6
  
  // Warehouse division/warehouse rata tengah, bold
  pdf.setFont(undefined, 'bold');
  if (divisionName || warehouseName) {
    pdf.text(`${divisionName || ''}${divisionName && warehouseName ? ' - ' : ''}${warehouseName || ''}`, 40, y, { align: 'center' });
    y += 5; // dikurangi dari 6
  }
  
  pdf.setFont(undefined, 'normal'); // Kembalikan ke normal untuk info lain
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${orderNumber}`, 2, y); y += 4; // dikurangi dari 4.5
  pdf.text(`Tanggal: ${date}`, 2, y); y += 4; // dikurangi dari 4.5
  pdf.text(`Outlet: ${outlet}`, 2, y); y += 4; // dikurangi dari 4.5
  if (roNumber) { pdf.text(`RO: ${roNumber}`, 2, y); y += 4; } // dikurangi dari 4.5
  if (roDate) { pdf.text(`Tgl RO: ${roDate}`, 2, y); y += 4; } // dikurangi dari 4.5
  if (roCreatorName) { pdf.text(`Pembuat RO: ${roCreatorName}`, 2, y); y += 4; } // dikurangi dari 4.5
  
  // Garis full width, spasi sebelum dan sesudah
  y += 1.5; // dikurangi dari 2
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5; // dikurangi dari 3
  
  // ITEM LIST - Format: Qty Unit Code Nama Item (sejajar)
  if (items && items.length) {
    items.forEach(i => {
      // Format: "Qty Unit Code Nama Item" - menggunakan unit_code
      const itemText = `${i.qty_scan} ${i.unit_code || i.unit} ${i.name}`;
      const itemLines = pdf.splitTextToSize(itemText, 76); // Lebar maksimal 76mm
      itemLines.forEach(line => {
        pdf.text(line, 2, y);
        y += 3.5; // dikurangi dari 4.5
      });
      y += 1.5; // Spacing antar item - dikurangi dari 2
    });
  } else {
    pdf.text('TIDAK ADA ITEM', 2, y); y += 3.5; // dikurangi dari 4.5
  }
  
  // Garis full width sebelum kasir
  y += 1.5; // dikurangi dari 2
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5; // dikurangi dari 3
  
  if (kasirName) { pdf.text(`Kasir: ${kasirName}`, 2, y); y += 4; } // dikurangi dari 4.5
  pdf.text('Terima kasih', 2, y);
  
  // Tambahkan margin bottom untuk roll paper
  y += 8; // dikurangi dari 10
  
  pdf.output('dataurlnewwindow');
} 