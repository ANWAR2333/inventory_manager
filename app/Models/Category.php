<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToUser;

class Category extends Model
{
    use BelongsToUser;

    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Les produits rattachés à cette catégorie.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}