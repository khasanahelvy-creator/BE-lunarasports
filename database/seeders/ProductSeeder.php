<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Sepatu Futsal Specs Lightspeed 3', 'category' => 'Sepatu', 'brand' => 'Specs', 'price' => 550000, 'rating' => 4.8, 'sold' => 124, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Sepatu+Specs'],
            ['name' => 'Raket Yonex Astrox 99 Pro', 'category' => 'Raket', 'brand' => 'Yonex', 'price' => 2800000, 'rating' => 5.0, 'sold' => 45, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Raket+Yonex'],
            ['name' => 'Bola Basket Molten BG4500', 'category' => 'Bola', 'brand' => 'Molten', 'price' => 850000, 'rating' => 4.9, 'sold' => 89, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Molten+BG4500'],
            ['name' => 'Jersey Lunara Official Elite', 'category' => 'Pakaian', 'brand' => 'Lunara', 'price' => 150000, 'rating' => 4.7, 'sold' => 312, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Jersey+Elite'],
            ['name' => 'Sepatu Badminton Lining Cloud', 'category' => 'Sepatu', 'brand' => 'Li-Ning', 'price' => 750000, 'rating' => 4.6, 'sold' => 67, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Sepatu+Lining'],
            ['name' => 'Shuttlecock Yonex AS-30 (Tube)', 'category' => 'Aksesoris', 'brand' => 'Yonex', 'price' => 320000, 'rating' => 4.9, 'sold' => 430, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Kok+Yonex'],
            ['name' => 'Tas Olahraga Nike Brasilia', 'category' => 'Aksesoris', 'brand' => 'Nike', 'price' => 450000, 'rating' => 4.8, 'sold' => 112, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Tas+Nike'],
            ['name' => 'Bola Futsal Ortuseight', 'category' => 'Bola', 'brand' => 'Ortuseight', 'price' => 300000, 'rating' => 4.5, 'sold' => 56, 'image' => 'https://placehold.co/400x400/F8F8F8/111111?text=Bola+Ortus'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}