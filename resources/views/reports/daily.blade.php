@php
    $statusColors = [
        'completed'  => ['bg' => '#10b981', 'light' => '#d1fae5', 'text' => '#065f46'],
        'processing' => ['bg' => '#3b82f6', 'light' => '#dbeafe', 'text' => '#1e40af'],
        'pending'    => ['bg' => '#f59e0b', 'light' => '#fef3c7', 'text' => '#92400e'],
        'failed'     => ['bg' => '#ef4444', 'light' => '#fee2e2', 'text' => '#991b1b'],
    ];
    $status = $report->status;
    $colors = $statusColors[$status] ?? $statusColors['pending'];
    $formattedDate = $report->report_date->format('l, F j, Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Sales Report — {{ $date }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(160deg, #ecfeff 0%, #f0fdfa 35%, #fff7ed 100%);
            color: #0f172a;
            min-height: 100vh;
            padding: 32px 20px 48px;
        }

        .wrap { max-width: 920px; margin: 0 auto; }

        .hero {
            background: linear-gradient(125deg, #0d9488 0%, #0891b2 45%, #f97316 100%);
            border-radius: 24px;
            padding: 36px 40px;
            color: #fff;
            box-shadow: 0 20px 50px rgba(13, 148, 136, 0.28);
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 280px;
            height: 280px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .hero h1 {
            font-size: clamp(26px, 4vw, 34px);
            font-weight: 800;
            line-height: 1.2;
        }

        .hero .sub {
            margin-top: 8px;
            font-size: 15px;
            opacity: 0.92;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 999px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #fff;
        }

        .status-pill.processing .status-dot {
            animation: pulse 1.4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 28px;
        }

        .card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 26px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            border: 1px solid rgba(15, 23, 42, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.1);
        }

        .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .card-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }

        .card-note {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .icon-orders  { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        .icon-revenue { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
        .icon-avg     { background: linear-gradient(135deg, #ffedd5, #fed7aa); }

        .val-orders  { color: #2563eb; }
        .val-revenue { color: #059669; }
        .val-avg     { color: #ea580c; }

        .meta-bar {
            margin-top: 24px;
            background: #fff;
            border-radius: 16px;
            padding: 20px 26px;
            display: flex;
            flex-wrap: wrap;
            gap: 24px 40px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
        }

        .meta-item label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .meta-item span {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .alert {
            margin-top: 24px;
            border-radius: 16px;
            padding: 18px 22px;
            font-size: 14px;
            line-height: 1.55;
        }

        .alert-warn {
            background: {{ $colors['light'] }};
            color: {{ $colors['text'] }};
            border: 1px solid {{ $colors['bg'] }}33;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        .footer a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .card:hover { transform: none; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <div class="hero-top">
                <div>
                    <div class="eyebrow">Daily Sales Report</div>
                    <h1>{{ $formattedDate }}</h1>
                    <p class="sub">Single-day performance snapshot</p>
                </div>
                <div class="status-pill {{ $status }}">
                    <span class="status-dot"></span>
                    {{ ucfirst($status) }}
                </div>
            </div>
        </header>

        @if($report->isCompleted())
            <div class="grid">
                <div class="card">
                    <div class="card-icon icon-orders">📦</div>
                    <div class="card-label">Total Orders</div>
                    <div class="card-value val-orders">{{ number_format($report->total_orders) }}</div>
                    <div class="card-note">Paid orders on this day</div>
                </div>
                <div class="card">
                    <div class="card-icon icon-revenue">💰</div>
                    <div class="card-label">Total Revenue</div>
                    <div class="card-value val-revenue">{{ number_format($report->total_revenue, 2) }}</div>
                    <div class="card-note">Gross sales amount</div>
                </div>
                <div class="card">
                    <div class="card-icon icon-avg">📊</div>
                    <div class="card-label">Avg. Order Value</div>
                    <div class="card-value val-avg">{{ number_format($report->average_order_value, 2) }}</div>
                    <div class="card-note">Revenue ÷ orders</div>
                </div>
            </div>

            <div class="meta-bar">
                <div class="meta-item">
                    <label>Report Date</label>
                    <span>{{ $report->report_date->format('Y-m-d') }}</span>
                </div>
                <div class="meta-item">
                    <label>Processed At</label>
                    <span>{{ $report->processed_at?->format('M j, Y — H:i') ?? '—' }}</span>
                </div>
                <div class="meta-item">
                    <label>Revenue per Order</label>
                    <span>{{ $report->total_orders > 0 ? number_format($report->total_revenue / $report->total_orders, 2) : '0.00' }}</span>
                </div>
            </div>
        @elseif($status === 'failed')
            <div class="alert alert-error">
                <strong>Report generation failed.</strong>
                @if($report->error_message)
                    <br>{{ $report->error_message }}
                @endif
            </div>
        @else
            <div class="alert alert-warn">
                @if($status === 'processing')
                    <strong>Report is being processed.</strong> Batch jobs are aggregating paid orders for this date. Refresh shortly.
                @else
                    <strong>Report is pending.</strong> Trigger generation via the admin API or wait for the nightly scheduler.
                @endif
            </div>

            <div class="grid">
                <div class="card">
                    <div class="card-icon icon-orders">📦</div>
                    <div class="card-label">Total Orders</div>
                    <div class="card-value val-orders" style="opacity:0.45">—</div>
                </div>
                <div class="card">
                    <div class="card-icon icon-revenue">💰</div>
                    <div class="card-label">Total Revenue</div>
                    <div class="card-value val-revenue" style="opacity:0.45">—</div>
                </div>
                <div class="card">
                    <div class="card-icon icon-avg">📊</div>
                    <div class="card-label">Avg. Order Value</div>
                    <div class="card-value val-avg" style="opacity:0.45">—</div>
                </div>
            </div>
        @endif

        <p class="footer">
            EMSystem Sales Intelligence &nbsp;·&nbsp;
            <a href="?format=json">JSON API</a>
        </p>
    </div>
</body>
</html>
