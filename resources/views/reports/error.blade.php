<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Error</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            padding: 24px;
        }
        .card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            text-align: center;
            backdrop-filter: blur(12px);
        }
        .code { font-size: 48px; font-weight: 800; color: #f87171; margin-bottom: 12px; }
        .message { font-size: 16px; line-height: 1.6; color: #cbd5e1; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">{{ $status }}</div>
        <p class="message">{{ $message }}</p>
    </div>
</body>
</html>
