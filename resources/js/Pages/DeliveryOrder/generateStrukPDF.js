import jsPDF from 'jspdf';

export async function generateStrukPDF({ orderNumber, date, outlet, items, kasirName, divisionName, warehouseName, roNumber, roDate, roCreatorName, showReprintLabel = false }) {
  const pdf = new jsPDF({ unit: 'mm', format: [80, 297] });
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
  y += 7;
  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 6;
  // Warehouse division/warehouse rata tengah, bold
  pdf.setFont(undefined, 'bold');
  if (divisionName || warehouseName) {
    pdf.text(`${divisionName || ''}${divisionName && warehouseName ? ' - ' : ''}${warehouseName || ''}`, 40, y, { align: 'center' });
    y += 6;
  }
  pdf.setFont(undefined, 'normal'); // Kembalikan ke normal untuk info lain
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${orderNumber}`, 2, y); y += 4.5;
  pdf.text(`Tanggal: ${date}`, 2, y); y += 4.5;
  pdf.text(`Outlet: ${outlet}`, 2, y); y += 4.5;
  if (roNumber) { pdf.text(`RO: ${roNumber}`, 2, y); y += 4.5; }
  if (roDate) { pdf.text(`Tgl RO: ${roDate}`, 2, y); y += 4.5; }
  if (roCreatorName) { pdf.text(`Pembuat RO: ${roCreatorName}`, 2, y); y += 4.5; }
  // Garis full width, spasi sebelum dan sesudah
  y += 2;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 3;
  // ITEM LIST
  if (items && items.length) {
    items.forEach(i => {
      const itemLines = pdf.splitTextToSize(i.name, 60);
      itemLines.forEach(line => {
        pdf.text(line, 2, y);
        y += 3.8;
      });
      pdf.text(`${i.qty_scan} ${i.unit_code || i.unit}`, 2, y);
      y += 5;
    });
  } else {
    pdf.text('TIDAK ADA ITEM', 2, y); y += 4.5;
  }
  // Garis full width sebelum kasir
  y += 2;
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 3;
  if (kasirName) { pdf.text(`Kasir: ${kasirName}`, 2, y); y += 4.5; }
  pdf.text('Terima kasih', 2, y);
  pdf.output('dataurlnewwindow');
} 