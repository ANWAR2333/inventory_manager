<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \App\Traits\BelongsToUser;

    protected $table = 'customers';
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];

    //
    public function orders(){
        return $this->hasMany(Order::class);
    }
}
