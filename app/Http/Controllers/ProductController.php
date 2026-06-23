<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getProducts($request);

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }


    public function show(int $id): JsonResponse
    {
        $product = $this->productService->getProductById($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "Product #{$id} not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $product,
            'meta'    => [
                'cache_enabled' => config('performance.use_caching'),
                'cache_driver'  => config('cache.default'),
            ],
        ]);
    }
}
