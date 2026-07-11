<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToUser;

class Order extends Model
{
    use BelongsToUser;
    
    protected $table = 'orders';
    protected $fillable = [
        'customer_id',
        'reference',
        'status',
        'total_amount',
        'notes',
    ];

    //
    public function customers(){
        return $this->belongsTo(Customer::class);
    }

    public function order_items(){
        return $this->hasMany(Order_item::class);
    }
}
