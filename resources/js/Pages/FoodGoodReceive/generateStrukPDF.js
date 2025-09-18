import jsPDF from 'jspdf';

export async function generateStrukPDF({ grNumber, date, supplier, items, receivedByName, poNumber, notes, showReprintLabel = false }) {
  // Hitung tinggi yang dibutuhkan
  let totalHeight = 0;
  totalHeight += 15; // Header (judul + company) - dikurangi
  totalHeight += 20; // Info (no, tanggal, supplier, po) - dikurangi
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
  
  // Hitung tinggi untuk notes jika ada
  if (notes && notes.trim()) {
    totalHeight += 4; // Spacing sebelum notes
    const notesLines = pdf.splitTextToSize(notes, 76).length;
    totalHeight += (notesLines * 3.5) + 4; // 3.5mm per line + 4mm spacing
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
  pdf.text('GOOD RECEIVE', 40, y, { align: 'center' });
  y += 6; // dikurangi dari 7
  
  // JUSTUS GROUP di bawah judul
  pdf.setFontSize(10);
  pdf.text('JUSTUS GROUP', 40, y, { align: 'center' });
  y += 5; // dikurangi dari 6
  
  pdf.setFont(undefined, 'normal'); // Kembalikan ke normal untuk info lain
  // Info lain, font kecil
  pdf.setFontSize(9);
  pdf.text(`No: ${grNumber}`, 2, y); y += 4; // dikurangi dari 4.5
  pdf.text(`Tanggal: ${date}`, 2, y); y += 4; // dikurangi dari 4.5
  pdf.text(`Supplier: ${supplier}`, 2, y); y += 4; // dikurangi dari 4.5
  if (poNumber) { pdf.text(`PO: ${poNumber}`, 2, y); y += 4; } // dikurangi dari 4.5
  if (receivedByName) { pdf.text(`Petugas: ${receivedByName}`, 2, y); y += 4; } // dikurangi dari 4.5
  
  // Garis full width, spasi sebelum dan sesudah
  y += 1.5; // dikurangi dari 2
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5; // dikurangi dari 3
  
  // ITEM LIST - Format: Qty Unit Code Nama Item (sejajar)
  if (items && items.length) {
    items.forEach(i => {
      // Format: "Qty Unit Code Nama Item" - menggunakan unit_code
      const itemText = `${i.qty_received} ${i.unit_code || i.unit} ${i.name}`;
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
  
  // Notes jika ada
  if (notes && notes.trim()) {
    y += 2; // Spacing sebelum notes
    pdf.setFontSize(8);
    pdf.setFont(undefined, 'bold');
    pdf.text('Catatan:', 2, y); y += 3.5;
    pdf.setFont(undefined, 'normal');
    const notesLines = pdf.splitTextToSize(notes, 76);
    notesLines.forEach(line => {
      pdf.text(line, 2, y);
      y += 3.5;
    });
  }
  
  // Garis full width sebelum footer
  y += 1.5; // dikurangi dari 2
  pdf.setLineWidth(0.5);
  pdf.line(2, y, 78, y);
  y += 2.5; // dikurangi dari 3
  
  pdf.text('Terima kasih', 2, y);
  
  // Tambahkan margin bottom untuk roll paper
  y += 8; // dikurangi dari 10
  
  pdf.output('dataurlnewwindow');
}
