<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        // Mengambil semua data produk dari database
        $products = Product::all();
        
        // Mengembalikan data dalam format JSON
        return response()->json([
            'success' => true,
            'message' => 'Katalog Produk Lunara Store',
            'data'    => $products
        ], 200);
    }
}