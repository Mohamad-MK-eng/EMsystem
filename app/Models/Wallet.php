<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'balance',
        'held_balance',
        'version',
    ];

    protected $casts = [
        'balance'      => 'decimal:2',
        'held_balance' => 'decimal:2',
        'version'      => 'integer',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function availableBalance(): float
    {
        return (float) $this->balance - (float) $this->held_balance;
    }


    public function canAfford(float $amount): bool
    {
        return $this->availableBalance() >= $amount;
    }
}
