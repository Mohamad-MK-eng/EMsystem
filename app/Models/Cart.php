<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;


use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',
    ];

    //  User relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    //  Cart Items
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
