<!DOCTYPE html>
<html>
<head>
    <title>Floor Order Supplier</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .qr-code {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Floor Order Supplier</h2>
        <h3>{{ $supplierFoNumber }}</h3>
    </div>

    <div class="info">
        <p><strong>Tanggal:</strong> {{ $date }}</p>
        <p><strong>Supplier:</strong> {{ $supplier->name }}</p>
        <p><strong>Outlet:</strong> {{ $outlet->nama_outlet }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item['item_name'] }}</td>
                <td>{{ $item['qty'] }}</td>
                <td>{{ $item['unit'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="qr-code">
        <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl={{ urlencode($supplierFoNumber) }}" alt="QR Code">
    </div>
</body>
</html> 