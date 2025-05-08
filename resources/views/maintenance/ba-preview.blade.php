<!DOCTYPE html>
<html>
<head>
    <title>Preview BA - {{ $pr->pr_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 300px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .signatures {
            margin-top: 50px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .signature-box {
            text-align: center;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .signature-box strong {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .signature-img {
            max-width: 150px;
            height: 60px;
            object-fit: contain;
            margin-bottom: 15px;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 4px;
        }
        .signature-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .signature-note {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 4px;
        }
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print BA
        </button>
    </div>

    <div class="header">
        <img src="{{ asset('images/logojustusgroup.png') }}" alt="Justus Group" class="logo">
        <h1 class="title">BERITA ACARA PENGAJUAN</h1>
    </div>

    <table style="border: none; margin-bottom: 30px;">
        <tr>
            <td style="border: none; width: 150px;">BA Number</td>
            <td style="border: none;">: {{ $pr->pr_number }}</td>
            <td style="border: none; width: 150px;">Created Date</td>
            <td style="border: none;">: {{ date('d M Y', strtotime($pr->created_at)) }}</td>
        </tr>
        <tr>
            <td style="border: none;">Task Number</td>
            <td style="border: none;">: {{ $pr->task_number }}</td>
            <td style="border: none;">Status</td>
            <td style="border: none;">: {{ $pr->status }}</td>
        </tr>
        <tr>
            <td style="border: none;">Created By</td>
            <td style="border: none;">: {{ $creator->nama_lengkap }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Specifications</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->description ?? '-' }}</td>
                <td>{{ $item->specifications ?? '-' }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->unit_name ?? '-' }}</td>
                <td>{{ number_format($item->price) }}</td>
                <td>{{ number_format($item->subtotal) }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="7" style="text-align: right;"><strong>Total:</strong></td>
                <td><strong>{{ number_format($pr->total_amount) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin: 30px 0;">
        <strong>Tujuan Pembelian:</strong>
        <p>{{ $pr->pr_description ?? '-' }}</p>
    </div>

    <!-- Task Media -->
    @if($taskMedia && count($taskMedia) > 0)
    <div style="margin: 30px 0;">
        <strong>Dokumentasi Task:</strong>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 10px;">
            @foreach($taskMedia as $media)
                <div style="text-align: center;">
                    <img src="{{ asset('storage/' . $media->file_path) }}" alt="Task Media" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Action Plans -->
    @if($actionPlans && count($actionPlans) > 0)
    <div style="margin: 30px 0;">
        <strong>Action Plans:</strong>
        @foreach($actionPlans as $plan)
            <div style="margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="margin-bottom: 8px;">
                    <small style="color: #666;">
                        Dibuat oleh: {{ $plan->created_by_name }} - 
                        {{ date('d M Y H:i', strtotime($plan->created_at)) }}
                    </small>
                </div>
                <p style="margin-bottom: 10px; font-size: 14px;">{{ $plan->description }}</p>
                
                @if($plan->media && count($plan->media) > 0)
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px;">
                        @foreach($plan->media as $media)
                            <div style="text-align: center;">
                                <img src="{{ asset('storage/' . $media->file_path) }}" alt="Action Plan Media" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <div class="signatures">
        <div class="signature-box">
            <strong>Dibuat Oleh</strong>
            @if($creator->signature_path)
                <img src="{{ asset('storage/' . $creator->signature_path) }}" alt="Signature" class="signature-img">
            @endif
            <div class="signature-name">{{ $creator->nama_lengkap }}</div>
            <div class="signature-title">{{ $creator->nama_jabatan }}</div>
            @if($pr->created_at)
                <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->created_at)) }}</div>
            @endif
        </div>

        <div class="signature-box">
            <strong>Disetujui Oleh</strong>
            @if($signatures['chief_engineering']['official'])
                @if($signatures['chief_engineering']['official']->signature_path)
                    <img src="{{ asset('storage/' . $signatures['chief_engineering']['official']->signature_path) }}" alt="Signature" class="signature-img">
                @endif
                <div class="signature-name">{{ $signatures['chief_engineering']['official']->nama_lengkap }}</div>
                <div class="signature-title">{{ $signatures['chief_engineering']['official']->nama_jabatan }}</div>
                @if($signatures['chief_engineering']['approver'] && $signatures['chief_engineering']['approver']->id != $signatures['chief_engineering']['official']->id)
                    <div class="signature-note" style="color: #666; font-style: italic;">
                        {{ $signatures['chief_engineering']['approver']->nama_lengkap }}<br>
                        Bertindak atas nama {{ $signatures['chief_engineering']['official']->nama_jabatan }}
                    </div>
                @endif
                @if($pr->chief_engineering_approval_date)
                    <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->chief_engineering_approval_date)) }}</div>
                @endif
            @endif
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <strong>Mengetahui dan Menyetujui</strong>
        @if($pr->total_amount < 5000000)
            <div style="margin-top: 20px;">
                <div class="signature-box" style="margin: 0 auto; float: none; display: block;">
                    @if($signatures['coo']['official'])
                        @if($signatures['coo']['official']->signature_path)
                            <img src="{{ asset('storage/' . $signatures['coo']['official']->signature_path) }}" alt="Signature" class="signature-img">
                        @endif
                        <div class="signature-name">{{ $signatures['coo']['official']->nama_lengkap }}</div>
                        <div class="signature-title">{{ $signatures['coo']['official']->nama_jabatan }}</div>
                        @if($signatures['coo']['approver'] && $signatures['coo']['approver']->id != $signatures['coo']['official']->id)
                            <div class="signature-note" style="color: #666; font-style: italic;">
                                {{ $signatures['coo']['approver']->nama_lengkap }}<br>
                                Bertindak atas nama {{ $signatures['coo']['official']->nama_jabatan }}
                            </div>
                        @endif
                        @if($pr->coo_approval_date)
                            <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->coo_approval_date)) }}</div>
                        @endif
                    @endif
                </div>
            </div>
        @else
            <div class="signatures" style="margin-top: 20px;">
                <div class="signature-box">
                    @if($signatures['coo']['official'])
                        @if($signatures['coo']['official']->signature_path)
                            <img src="{{ asset('storage/' . $signatures['coo']['official']->signature_path) }}" alt="Signature" class="signature-img">
                        @endif
                        <div class="signature-name">{{ $signatures['coo']['official']->nama_lengkap }}</div>
                        <div class="signature-title">{{ $signatures['coo']['official']->nama_jabatan }}</div>
                        @if($signatures['coo']['approver'] && $signatures['coo']['approver']->id != $signatures['coo']['official']->id)
                            <div class="signature-note" style="color: #666; font-style: italic;">
                                {{ $signatures['coo']['approver']->nama_lengkap }}<br>
                                Bertindak atas nama {{ $signatures['coo']['official']->nama_jabatan }}
                            </div>
                        @endif
                        @if($pr->coo_approval_date)
                            <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->coo_approval_date)) }}</div>
                        @endif
                    @endif
                </div>

                <div class="signature-box">
                    @if($signatures['ceo']['official'])
                        @if($signatures['ceo']['official']->signature_path)
                            <img src="{{ asset('storage/' . $signatures['ceo']['official']->signature_path) }}" alt="Signature" class="signature-img">
                        @endif
                        <div class="signature-name">{{ $signatures['ceo']['official']->nama_lengkap }}</div>
                        <div class="signature-title">{{ $signatures['ceo']['official']->nama_jabatan }}</div>
                        @if($signatures['ceo']['approver'] && $signatures['ceo']['approver']->id != $signatures['ceo']['official']->id)
                            <div class="signature-note" style="color: #666; font-style: italic;">
                                {{ $signatures['ceo']['approver']->nama_lengkap }}<br>
                                Bertindak atas nama {{ $signatures['ceo']['official']->nama_jabatan }}
                            </div>
                        @endif
                        @if($pr->ceo_approval_date)
                            <div class="signature-note">{{ date('d M Y H:i', strtotime($pr->ceo_approval_date)) }}</div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>

   
</body>
</html> 