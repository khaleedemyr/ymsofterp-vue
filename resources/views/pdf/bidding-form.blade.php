<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bidding Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 200px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f5f5f5;
        }
        .supplier-info {
            margin-bottom: 20px;
        }
        .terms {
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signature {
            margin-top: 40px;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" class="logo">
        <h2>FORM PENAWARAN</h2>
        <p>Tanggal: {{ $date }}</p>
        <p>No. Ref: {{ $group->session->id }}/{{ $group->nama }}</p>
    </div>

    <div class="supplier-info">
        <h3>Informasi Supplier</h3>
        <table>
            <tr>
                <td width="150">Nama Perusahaan</td>
                <td width="10">:</td>
                <td>_____________________</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td>:</td>
                <td>_____________________</td>
            </tr>
            <tr>
                <td>Contact Person</td>
                <td>:</td>
                <td>_____________________</td>
            </tr>
            <tr>
                <td>No. Telepon</td>
                <td>:</td>
                <td>_____________________</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>:</td>
                <td>_____________________</td>
            </tr>
        </table>
    </div>

    <h3>Daftar Item</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Item</th>
                <th>Spesifikasi</th>
                <th>Jumlah</th>
                <th>Unit</th>
                <th>Harga Satuan</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($group->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->prItem->item_name }}</td>
                <td>{{ $item->prItem->specifications ?: '-' }}</td>
                <td style="text-align: center">{{ $item->prItem->quantity }}</td>
                <td>{{ $item->prItem->unit_name }}</td>
                <td>_____________________</td>
                <td>_____________________</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align: right"><strong>Total:</strong></td>
                <td>_____________________</td>
            </tr>
        </tfoot>
    </table>

    <div class="terms">
        <h3>Syarat & Ketentuan:</h3>
        <ol>
            <li>Harga sudah termasuk pajak dan biaya pengiriman</li>
            <li>Waktu pengiriman maksimal: _____ hari</li>
            <li>Masa berlaku penawaran: _____ hari</li>
            <li>Garansi: _____ bulan</li>
            <li>Syarat pembayaran: _____ hari setelah barang diterima</li>
        </ol>
    </div>

    <div class="signature">
        <p>Tanda tangan & Stempel Perusahaan:</p>
        <br><br><br>
        <p>(_____________________)</p>
        <p>Nama Jelas & Jabatan</p>
    </div>
</body>
</html>
