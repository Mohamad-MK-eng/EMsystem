<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{

    public function getUserCart(User $user)
    {
        $query = $user->cart();



        if (config('performance.use_eager_loading')) {

            $query->with([
                'items.product.inventory'
            ]);
        }

        $cart_data = $query->first();

        return [
            'cart_id' => $cart_data->id,

            'items' => $cart_data->items->map(function ($item) {

                return [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'description' => $item->product->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                    'stock_quantity' => $item->product->inventory->stock_quantity,
                    'version' => $item->product->inventory->version,
                ];
            }),

            'total_price' => round($cart_data->items->sum(function ($item) {
                return $item->quantity * $item->product->price;
            }) ,2)
        ];
    }


    public function addItem(User $user, array $data): CartItem
    {
        $callback = function () use ($user, $data) {

            $cart = $user->cart;

            $productQuery = Product::query();

            $product = $productQuery
                ->findOrFail($data['product_id']);

                $existingItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $product->id)
                    ->exists();

                if ($existingItem) {

                    throw ValidationException::withMessages([
                        'product_id' => 'Product already exists in cart.'
                    ]);
                }

            // قرار تصميمي يناقش لاحقا
            if (config('performance.use_stock_validation')) {

                if ($data['quantity'] > $product->inventory->stock_quantity) {

                    throw ValidationException::withMessages([
                        'quantity' => 'Requested quantity exceeds available stock.'
                    ]);
                }
            }



            return CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
            ]);
        };
        return $callback();
    }

    public function updateItem(User $user, CartItem $cartItem, array $data): CartItem
    {
        return DB::transaction(function () use ($user, $cartItem, $data) {

            /*
            |--------------------------------------------------------------------------
            | Ownership Validation
            |--------------------------------------------------------------------------
            */


                if ($cartItem->cart->user_id !== $user->id) {

                    abort(403);
                }


            $product = $cartItem->product()
                ->with('inventory')
                ->first();


            if (config('performance.use_stock_validation')) {

                if (
                    $data['increment'] + $cartItem->quantity >
                    $product->inventory->stock_quantity
                ) {

                    throw ValidationException::withMessages([
                        'quantity' => 'Requested quantity exceeds available stock.'
                    ]);
                }
            }


            $cartItem->update([
                'quantity' => $data['increment'] + $cartItem->quantity
            ]);

            return $cartItem->fresh();
        });
    }


    public function removeItem(User $user, CartItem $cartItem): void
    {
        $callback = (function () use ($user, $cartItem) {

            /*
            |--------------------------------------------------------------------------
            | Ownership Validation
            |--------------------------------------------------------------------------
            */

            if ($cartItem->cart->user_id !== $user->id) {
                abort(403);
            }

            $cartItem->delete();
        });

        if (config('performance.use_transactions')) {
            DB::transaction($callback);
        } else {
            $callback();
        }
    }
}
