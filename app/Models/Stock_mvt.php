<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToUser;

class Stock_mvt extends Model
{
    use BelongsToUser;
    
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'reference',
        'notes',
    ];
    
    //
    public function products(){
        return $this->belongsTo(Product::class);
    }
}
