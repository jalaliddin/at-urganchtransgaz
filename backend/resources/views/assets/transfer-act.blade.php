<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $transfer->number }}</title>
    <style>
        @page { margin: 18mm 15mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2933; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; margin-bottom: 14px; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #9aa5b1; padding: 4px; text-align: left; }
        table.items th { background: #e4e7eb; }
        .signs { width: 100%; margin-top: 36px; }
        .signs td { width: 50%; padding-top: 24px; vertical-align: top; }
        .line { border-bottom: 1px solid #1f2933; height: 18px; margin: 6px 24px 2px 0; }
    </style>
</head>
<body>
    <h1>Topshirish-qabul qilish dalolatnomasi № {{ $transfer->number }}</h1>
    <div class="sub">{{ $date }}</div>
    <table class="meta">
        <tr><td><strong>Topshiruvchi tashkilot:</strong></td><td>{{ $transfer->fromOrganization->name }}</td></tr>
        <tr><td><strong>Qabul qiluvchi tashkilot:</strong></td><td>{{ $transfer->toOrganization->name }}</td></tr>
        @if ($transfer->note)
            <tr><td><strong>Izoh:</strong></td><td>{{ $transfer->note }}</td></tr>
        @endif
    </table>
    <table class="items">
        <thead>
            <tr><th>№</th><th>Inventar raqami</th><th>Nomi</th><th>Toifa</th><th>Seriya raqami</th><th>Holati / izoh</th></tr>
        </thead>
        <tbody>
            @foreach ($transfer->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->asset->inventory_number }}</td>
                    <td>{{ $item->asset->name }}{{ $item->asset->model ? ' ('.$item->asset->model->name.')' : '' }}</td>
                    <td>{{ $item->asset->category?->name }}</td>
                    <td>{{ $item->asset->serial_number }}</td>
                    <td>{{ $item->condition_note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Jami: {{ $transfer->items->count() }} dona.</p>
    <table class="signs">
        <tr>
            <td>Topshirdi:<div class="line"></div>{{ $sender }}</td>
            <td>Qabul qildi:<div class="line"></div>(F.I.Sh., imzo)</td>
        </tr>
    </table>
</body>
</html>
