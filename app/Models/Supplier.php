<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToUser;

class Supplier extends Model
{
    use BelongsToUser;
    
    protected $table = 'suppliers';
    
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];
    
    //
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function purchases(){
        return $this->hasMany(Purchase::class);
    }
}
