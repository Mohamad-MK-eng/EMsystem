<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #f8fafc;
        }

        .page {
            padding: 28px 32px;
        }

        .header {
            background: #5b21b6;
            border-radius: 12px;
            padding: 24px 28px;
            color: #ffffff;
            margin-bottom: 24px;
        }

        .header-top {
            width: 100%;
            margin-bottom: 18px;
        }

        .brand {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .brand-sub {
            font-size: 11px;
            opacity: 0.9;
            margin-top: 4px;
        }

        .invoice-badge {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 8px;
            padding: 10px 14px;
            text-align: right;
            float: right;
            min-width: 150px;
        }

        .invoice-badge .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.85;
        }

        .invoice-badge .number {
            font-size: 18px;
            font-weight: bold;
            margin-top: 2px;
        }

        .meta-grid {
            width: 100%;
            clear: both;
            border-collapse: collapse;
        }

        .meta-grid td {
            width: 50%;
            vertical-align: top;
            padding-top: 4px;
        }

        .meta-card {
            background: rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            padding: 12px 14px;
            margin-right: 8px;
        }

        .meta-card.right {
            margin-right: 0;
            margin-left: 8px;
        }

        .meta-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .meta-value {
            font-size: 12px;
            line-height: 1.6;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }

        .items-table thead th {
            background: #eef2ff;
            color: #4338ca;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 12px;
            text-align: left;
            border-bottom: 2px solid #c7d2fe;
        }

        .items-table thead th.right {
            text-align: right;
        }

        .items-table tbody td {
            padding: 11px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .items-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .items-table tbody td.right {
            text-align: right;
        }

        .product-name {
            font-weight: bold;
            color: #0f172a;
        }

        .summary-wrap {
            width: 100%;
            margin-bottom: 20px;
        }

        .summary-box {
            width: 280px;
            height : 100px;
            float: right;
            background: #ffffff;
            border: 2px solid #c7d2fe;
            border-radius: 10px;
            overflow: hidden;
        }

        .summary-row {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row.total {
            background: #4f46e5;
            color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            border-bottom: none;
        }

        .summary-label {
            float: left;
        }

        .summary-value {
            float: right;
        }

        .clearfix {
            clear: both;
        }

        .payment-box {
            clear: both;
            background: #ffffff;
            border-left: 4px solid #10b981;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 8px;
        }

        .payment-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-grid td {
            width: 33.33%;
            padding: 4px 8px 4px 0;
            vertical-align: top;
        }

        .field-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .field-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
            background: #10b981;
        }

        .footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            color: #64748b;
            font-size: 10px;
        }

        .footer strong {
            color: #4f46e5;
        }
    </style>
</head>
<body>
@php
    $paymentRef = $order->payment->transaction_ref ?? 'N/A';
    $paymentStatus = $order->payment->status ?? 'N/A';
    $paymentAmount = $order->payment->amount ?? $order->total_price;
    $statusColor = match (strtolower($order->status)) {
        'completed', 'paid', 'success' => '#10b981',
        'pending' => '#f59e0b',
        'cancelled', 'failed' => '#ef4444',
        default => '#6366f1',
    };
@endphp

<div class="page">
    <div class="header">
        <div class="header-top">
            <div class="invoice-badge">
                <div class="label">Invoice</div>
                <div class="number">#{{ $order->id }}</div>
            </div>
            <div class="brand">{{ config('app.name', 'E-Commerce System') }}</div>
            <div class="brand-sub">Official Purchase Invoice</div>
        </div>

        <table class="meta-grid">
            <tr>
                <td>
                    <div class="meta-card">
                        <div class="meta-title">Invoice Date</div>
                        <div class="meta-value">{{ $invoiceDate->format('Y-m-d H:i:s') }}</div>
                    </div>
                </td>
                <td>
                    <div class="meta-card right">
                        <div class="meta-title">Customer</div>
                        <div class="meta-value">
                            <strong>{{ $order->user->name }}</strong><br>
                            {{ $order->user->email }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Order Items</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Product</th>
                <th class="right" style="width: 15%;">Qty</th>
                <th class="right" style="width: 20%;">Unit Price</th>
                <th class="right" style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                @php $subtotal = $item->quantity * $item->price_at_purchase; @endphp
                <tr>
                    <td>
                        <div class="product-name">{{ $item->product->name }}</div>
                    </td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->price_at_purchase, 2) }}</td>
                    <td class="right">{{ number_format($subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrap">
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Items</span>
                <span class="summary-value">{{ $order->items->count() }}</span>
                <div class="clearfix"></div>
            </div>
            <div class="summary-row total">
                <span class="summary-label">Total</span>
                <span class="summary-value">{{ number_format($order->total_price, 2) }}</span>
                <div class="clearfix"></div>
            </div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="section-title">Payment Details</div>

    <div class="payment-box">
        <table class="payment-grid">
            <tr>
                <td>
                    <div class="field-label">Payment Reference</div>
                    <div class="field-value">{{ $paymentRef }}</div>
                </td>
                <td>
                    <div class="field-label">Payment Amount</div>
                    <div class="field-value">{{ number_format($paymentAmount, 2) }}</div>
                </td>
                <td>
                    <div class="field-label">Order Status</div>
                    <div class="field-value">
                        <span class="status-pill" style="background: {{ $statusColor }};">
                            {{ strtoupper($order->status) }}
                        </span>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding-top: 10px;">
                    <div class="field-label">Payment Status</div>
                    <div class="field-value">{{ strtoupper($paymentStatus) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Thank you for your purchase from <strong>{{ config('app.name', 'E-Commerce System') }}</strong>.<br>
        This document was generated automatically on {{ $invoiceDate->format('F j, Y \a\t g:i A') }}.
    </div>
</div>
</body>
</html>
