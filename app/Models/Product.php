<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToUser;

class Product extends Model
{
    use BelongsToUser;
    
    protected $table = 'products';
    
    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'sku',
        'barcode',
        'purchase_price',
        'selling_price',
        'quantity',
        'minimum_stock',
        'description',
        'image',
    ];

    //
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase_items(){
        return $this->hasMany(Purchase_Item::class);
    }

    public function order_items(){
        return $this->hasMany(Order_item::class);
    }
    
    public function stockMovements()
    {
        return $this->hasMany(Stock_mvt::class);
    }
}
