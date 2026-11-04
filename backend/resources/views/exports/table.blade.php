<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #1f2933; }
        h1 { font-size: 13px; margin: 0 0 2px; color: #1D4E89; }
        .meta { font-size: 8px; color: #52606d; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1D4E89; color: #fff; text-align: left; padding: 4px 3px; font-weight: bold; }
        td { padding: 3px; border-bottom: 1px solid #d9e2ec; vertical-align: top; }
        tr:nth-child(even) td { background: #f5f7fa; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ $organization }} · {{ $generatedAt }} · {{ count($rows) }}</div>
    <table>
        <thead>
            <tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
