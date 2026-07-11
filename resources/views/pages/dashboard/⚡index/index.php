<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock_mvt;
use App\Models\Supplier;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view([
            'totalProducts' => Product::count(),
            'totalCategories' => Category::count(),
            'totalSuppliers' => Supplier::count(),
            'totalCustomers' => Customer::count(),

            'stockValue' => (float) (Product::query()
                ->selectRaw('COALESCE(SUM(quantity * purchase_price), 0) as value')
                ->value('value') ?? 0),

            'lowStockProducts' => Product::query()
                ->whereColumn('quantity', '<=', 'minimum_stock')
                ->orderBy('quantity')
                ->limit(8)
                ->get(),

            'pendingOrdersCount' => Order::where('status', 'pending')->count(),
            'confirmedOrdersCount' => Order::where('status', 'confirmed')->count(),
            'deliveredOrdersCount' => Order::where('status', 'delivered')->count(),

            'pendingPurchasesCount' => Purchase::where('is_completed', false)->count(),

            'recentMovements' => Stock_mvt::query()
                ->with('products')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
};