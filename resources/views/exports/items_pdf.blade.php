<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0070C0; color: #fff; padding: 8px; }
        td { border: 1px solid #ddd; padding: 8px; }
    </style>
</head>
<body>
    <h2>Items List</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Sub Category</th>
                <th>Type</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td>{{ $item->sku }}</td>
                <td>{{ $item->category->name ?? '' }}</td>
                <td>{{ $item->subCategory->name ?? '' }}</td>
                <td>{{ $item->type }}</td>
                <td>{{ $item->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 