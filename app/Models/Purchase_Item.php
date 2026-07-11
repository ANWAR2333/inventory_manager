<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase_Item extends Model
{
    protected $table = 'purchase_items';
    
    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    //
    public function purchases(){
        return $this->belongsTo(Purchase::class);
    }

    public function products(){
        return $this->belongsTo(Product::class);
    }
}
