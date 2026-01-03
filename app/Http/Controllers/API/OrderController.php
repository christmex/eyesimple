<?php

namespace App\Http\Controllers\API;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'client_token' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
        ]);

        $items = collect($validated['items']);

        $productIds = $items->pluck('product_id')
            ->unique()
            ->values();

        $existingOrder = Order::where('client_token', $validated['client_token'])
            ->with('items')
            ->first();

        if ($existingOrder) {
            return response()->json($existingOrder, 200);
        }

        $products = Product::whereIn('id', $productIds)->get();

        if ($products->count() !== $productIds->count()) {
            return response()->json(['message' => 'Invalid product'], 422);
        }

        DB::beginTransaction();
        try {
            $lockedProducts = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $groupedItems = $items->groupBy('product_id')
                ->map(function($rows, $productId) {
                    return [
                        'product_id' => (int) $productId,
                        'qty' => $rows->sum('qty'),
                    ];
            })
                ->values();

            foreach ($groupedItems as $item) {
                $product = $lockedProducts[$item['product_id']];
                if ($product->stock < $item['qty']) {
                    throw new \RuntimeException("out of stock for product {$product->name}");
                }
                $product->decrement('stock', $item['qty']);
            }

            $order = Order::create([
                'user_id' => $validated['user_id'],
                'client_token' => $validated['client_token'],
            ]);

            $orderItems = [];
            $totalCents = 0;

            foreach ($groupedItems as $item) {
                $product = $lockedProducts[$item['product_id']];
                $lineTotal = $product->price_cents * $item['qty'];
                $totalCents += $lineTotal;

                $orderItems[] = [
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'qty' => $item['qty'],
                    'unit_price_cents' => $product->price_cents,
                    'line_total_cents' => $lineTotal,
                ];
            }

            $order->items()->createMany($orderItems);

            $order->update(['total_cents' => $totalCents]);

            DB::commit();

            return response()->json($order->load('items'), 201);

        } catch (\RuntimeException $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
