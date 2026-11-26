<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index()
    {
        $products = DB::table('products')
            ->orderBy('created_at')
            ->orderBy('product_id')
            ->get([
                'product_id', 'name', 'category', 'price', 'stock',
                'description', 'badge', 'image', 'color', 'icon',
            ])
            ->map(static fn (object $row): array => [
                'id' => $row->product_id,
                'name' => $row->name,
                'category' => $row->category,
                'price' => (int) $row->price,
                'stock' => (int) $row->stock,
                'description' => $row->description,
                'badge' => $row->badge,
                'image' => $row->image,
                'color' => $row->color,
                'icon' => $row->icon,
            ])->all();

        return response()
            ->view('store', [
                'initialState' => [
                    'artesoft:v3:productos' => $products,
                    'artesoft:v3:pedidos' => [],
                ],
                'csrfToken' => csrf_token(),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
