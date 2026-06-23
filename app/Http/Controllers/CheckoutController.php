<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkoutService)
    {
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        try{

            $result = $this->checkoutService->checkout($request->user());

            return response()->json([
                'success' => true,
                'data'    => $result,
            ], 201);

        } catch (\Exception $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ,
            ], $status);
        }
    }
}
