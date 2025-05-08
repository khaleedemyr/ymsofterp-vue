<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Daftar Barang Bidding</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Spesifikasi</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Harga Penawaran</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->specifications }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->unit_name }}</td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:40px;">Silakan isi harga penawaran pada kolom di atas dan kirimkan kembali ke bagian purchasing.</p>
</body>
</html> 