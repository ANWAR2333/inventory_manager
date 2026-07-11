<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToUser;

class Purchase extends Model
{
    use BelongsToUser;
    
    protected $table = 'purchases';
    
    protected $fillable = [
        'supplier_id',
        'purchase_number',
        'purchase_date',
        'is_completed',
        'total_amount',
        'notes',
    ];
 
    // AJOUT NÉCESSAIRE : sans ça, purchase_date reste une simple chaîne de caractères
    // et is_completed reste un entier 0/1 plutôt qu'un booléen.
    protected $casts = [
        'purchase_date' => 'date',
        'is_completed' => 'boolean',
        'total_amount' => 'decimal:2',
    ];
 
    // Le reste de ton fichier ne change pas :
    public function suppliers(){
        return $this->belongsTo(Supplier::class);
    }
 
    public function purchase_items(){
        return $this->hasMany(Purchase_Item::class);
    }
}