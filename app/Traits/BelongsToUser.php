<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToUser
{
    /**
     * - Filtre AUTOMATIQUEMENT toutes les requêtes (all(), find(), where(), paginate()...)
     *   pour ne renvoyer que les enregistrements de l'utilisateur connecté.
     * - Assigne AUTOMATIQUEMENT user_id = auth()->id() à la création, si non déjà défini.
     *
     * Grâce à ce global scope, aucune modification n'est nécessaire dans les composants
     * Livewire existants : Category::all(), Product::orderBy(...), etc. sont déjà filtrés.
     */
    public static function bootBelongsToUser(): void
    {
        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });

        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', auth()->id());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}