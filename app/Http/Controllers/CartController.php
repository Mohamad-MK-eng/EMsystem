<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService
            ->getUserCart($request->user());

        return response()->json($cart);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem = $this->cartService
            ->addItem($request->user(), $validated);

        return response()->json([
            'message' => 'Product added to cart successfully.',
            'data' => $cartItem
        ], 201);
    }

    public function updateItem(
        Request $request,
        CartItem $cartItem
    ): JsonResponse {

        $validated = $request->validate([
            'increment' => ['required', 'integer'],
        ]);

        $updatedItem = $this->cartService
            ->updateItem(
                $request->user(),
                $cartItem,
                $validated
            );

        return response()->json([
            'message' => 'Cart item updated successfully.',
            'data' => $updatedItem
        ]);
    }

    public function removeItem(
        Request $request,
        CartItem $cartItem
    ): JsonResponse {

        $this->cartService
            ->removeItem(
                $request->user(),
                $cartItem
            );

        return response()->json([
            'message' => 'Item removed from cart successfully.'
        ]);
    }
}