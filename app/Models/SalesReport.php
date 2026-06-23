<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    protected $fillable = [
        'report_date',
        'file_path',
        'total_orders',
        'total_revenue',
        'average_order_value',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'report_date'         => 'date',
        'total_revenue'       => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'processed_at'        => 'datetime',
        'total_orders'        => 'integer',
    ];

    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('report_date', [$from, $to]);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
