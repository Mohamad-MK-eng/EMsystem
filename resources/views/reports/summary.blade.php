@php
    $labels   = collect($breakdown)->pluck('label')->values();
    $revenues = collect($breakdown)->pluck('total_revenue')->values();
    $orders   = collect($breakdown)->pluck('total_orders')->values();
    $hasData  = count($breakdown) > 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Summary — {{ $from }} to {{ $to }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(165deg, #1e1b4b 0%, #312e81 30%, #4c1d95 60%, #831843 100%);
            color: #f8fafc;
            min-height: 100vh;
            padding: 32px 20px 56px;
        }

        .wrap { max-width: 1100px; margin: 0 auto; }

        .hero {
            text-align: center;
            margin-bottom: 32px;
        }

        .hero .eyebrow {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 999px;
            padding: 6px 16px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }

        .hero h1 {
            font-size: clamp(28px, 5vw, 40px);
            font-weight: 800;
            background: linear-gradient(90deg, #fbbf24, #f472b6, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero .period {
            margin-top: 10px;
            font-size: 16px;
            color: #c4b5fd;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 18px;
            padding: 20px 22px;
            backdrop-filter: blur(10px);
        }

        .kpi-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a5b4fc;
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: 26px;
            font-weight: 800;
        }

        .kpi.highlight .kpi-value { color: #fbbf24; }
        .kpi.green .kpi-value    { color: #34d399; }
        .kpi.pink .kpi-value     { color: #f472b6; }
        .kpi.purple .kpi-value   { color: #c4b5fd; }

        .charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }

        .chart-card {
            background: rgba(255,255,255,0.95);
            border-radius: 22px;
            padding: 24px 24px 16px;
            color: #1e293b;
            box-shadow: 0 16px 48px rgba(0,0,0,0.25);
            min-width: 0;
        }

        .chart-card h2 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #312e81;
        }

        .chart-card p {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .chart-wrap {
            position: relative;
            height: 280px;
            width: 100%;
            min-width: 0;
        }

        .table-card {
            background: rgba(255,255,255,0.95);
            border-radius: 22px;
            padding: 24px;
            color: #1e293b;
            box-shadow: 0 16px 48px rgba(0,0,0,0.2);
            overflow-x: auto;
        }

        .table-card h2 {
            font-size: 16px;
            font-weight: 700;
            color: #312e81;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            text-align: left;
            padding: 12px 14px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        tr:hover td { background: #f8fafc; }

        .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .date { color: #312e81; font-weight: 600; }
        .rev { color: #059669; }
        .ord { color: #7c3aed; }
        .avg { color: #0369a1; }

        .empty {
            text-align: center;
            padding: 48px 24px;
            background: rgba(255,255,255,0.08);
            border-radius: 20px;
            border: 1px dashed rgba(255,255,255,0.25);
            color: #c4b5fd;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        .footer a {
            color: #fbbf24;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="hero">
            <div class="eyebrow">Period Summary</div>
            <h1>Sales Performance Overview</h1>
            <p class="period">{{ \Carbon\Carbon::parse($from)->format('M j, Y') }} — {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}</p>
        </header>

        <div class="kpi-grid">
            <div class="kpi highlight">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">{{ number_format($summary['total_revenue'], 2) }}</div>
            </div>
            <div class="kpi green">
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-value">{{ number_format($summary['total_orders']) }}</div>
            </div>
            <div class="kpi pink">
                <div class="kpi-label">Avg. Order Value</div>
                <div class="kpi-value">{{ number_format($summary['avg_order_value'], 2) }}</div>
            </div>
            <div class="kpi purple">
                <div class="kpi-label">Days Reported</div>
                <div class="kpi-value">{{ $summary['report_count'] }}</div>
            </div>
            <div class="kpi green">
                <div class="kpi-label">Best Day Revenue</div>
                <div class="kpi-value">{{ number_format($summary['best_day_revenue'], 2) }}</div>
            </div>
            <div class="kpi pink">
                <div class="kpi-label">Lowest Day Revenue</div>
                <div class="kpi-value">{{ number_format($summary['worst_day_revenue'], 2) }}</div>
            </div>
        </div>

        @if($hasData)
            <div class="charts">
                <div class="chart-card">
                    <h2>Daily Revenue</h2>
                    <p>Total sales amount per completed report day</p>
                    <div class="chart-wrap">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h2>Daily Orders</h2>
                    <p>Paid order count per day — volume trend</p>
                    <div class="chart-wrap">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <h2>Daily Breakdown</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="num">Orders</th>
                            <th class="num">Revenue</th>
                            <th class="num">Avg. Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($breakdown as $row)
                            <tr>
                                <td class="date">{{ $row['date'] }}</td>
                                <td class="num ord">{{ number_format($row['total_orders']) }}</td>
                                <td class="num rev">{{ number_format($row['total_revenue'], 2) }}</td>
                                <td class="num avg">{{ number_format($row['average_order_value'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty">
                <p style="font-size:18px;font-weight:600;margin-bottom:8px;">No completed reports in this range</p>
                <p>Generate daily reports first, then return here for the period summary.</p>
            </div>
        @endif

        <p class="footer">
            EMSystem Sales Intelligence &nbsp;·&nbsp;
            <a href="?from={{ $from }}&to={{ $to }}&format=json">JSON API</a>
        </p>
    </div>

    @if($hasData)
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const labels   = @json($labels);
            const revenues = @json($revenues);
            const orders   = @json($orders);
            const singleDay = labels.length <= 1;

            function initCharts() {
                if (typeof Chart === 'undefined') {
                    return;
                }

                const baseOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                };

                const revenueChart = new Chart(document.getElementById('revenueChart'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Revenue',
                            data: revenues,
                            backgroundColor: 'rgba(124, 58, 237, 0.75)',
                            borderColor: '#7c3aed',
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                            maxBarThickness: singleDay ? 80 : undefined,
                        }],
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: '#e2e8f0' },
                                ticks: { color: '#64748b' },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#64748b', maxRotation: 45 },
                            },
                        },
                    },
                });

                const ordersChart = new Chart(document.getElementById('ordersChart'), {
                    type: singleDay ? 'bar' : 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Orders',
                            data: orders,
                            borderColor: '#ec4899',
                            backgroundColor: singleDay
                                ? 'rgba(236, 72, 153, 0.75)'
                                : 'rgba(236, 72, 153, 0.15)',
                            borderWidth: singleDay ? 2 : 3,
                            fill: !singleDay,
                            tension: singleDay ? 0 : 0.35,
                            borderRadius: singleDay ? 8 : 0,
                            borderSkipped: false,
                            maxBarThickness: singleDay ? 80 : undefined,
                            pointBackgroundColor: '#ec4899',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: singleDay ? 0 : 5,
                            pointHoverRadius: singleDay ? 0 : 7,
                        }],
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: '#e2e8f0' },
                                ticks: {
                                    color: '#64748b',
                                    stepSize: 1,
                                    precision: 0,
                                },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#64748b', maxRotation: 45 },
                            },
                        },
                    },
                });

                requestAnimationFrame(function () {
                    revenueChart.resize();
                    ordersChart.resize();
                });
            }

            if (document.readyState === 'complete') {
                initCharts();
            } else {
                window.addEventListener('load', initCharts);
            }
        })();
    </script>
    @endif
</body>
</html>
