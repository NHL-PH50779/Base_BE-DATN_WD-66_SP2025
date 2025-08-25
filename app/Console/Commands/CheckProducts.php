<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class CheckProducts extends Command
{
    protected $signature = 'check:products';
    protected $description = 'Check products data';

    public function handle()
    {
        $this->info('=== CHECKING PRODUCTS ===');
        
        $products = Product::withTrashed()->get(['id', 'name', 'brand_id', 'is_active', 'deleted_at']);
        
        foreach ($products as $product) {
            $active = $product->is_active ? 'YES' : 'NO';
            $deleted = $product->deleted_at ? 'YES' : 'NO';
            $this->line("ID: {$product->id} | Name: {$product->name} | Active: {$active} | Deleted: {$deleted}");
        }
        
        $this->info("\n=== ACTIVE PRODUCTS ONLY ===");
        $activeProducts = Product::where('is_active', true)->get(['id', 'name', 'brand_id']);
        $this->info("Count: " . $activeProducts->count());
        
        foreach ($activeProducts as $product) {
            $this->line("ID: {$product->id} | Name: {$product->name} | Brand ID: {$product->brand_id}");
        }
        
        return 0;
    }
}