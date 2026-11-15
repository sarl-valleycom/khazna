<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 16px 0 4px; }
        .muted { color: #666; }
        .brand { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.grid th { background: #f2f2f2; text-align: left; padding: 5px 4px; border-bottom: 1px solid #bbb; }
        table.grid td { padding: 5px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
        table.grid th.right, table.grid td.right { text-align: right; }
        table.grid td.sum, table.grid td.sumlabel { font-weight: bold; border-top: 1px solid #888; border-bottom: 1px solid #888; background: #fafafa; }
        .box { border: 1px solid #ccc; padding: 6px 8px; margin-top: 6px; }
        .note { font-size: 8px; color: #666; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="brand">{{ $app }}</div>
    @yield('content')
</body>
</html>
