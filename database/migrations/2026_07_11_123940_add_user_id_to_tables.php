<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'categories',
        'suppliers',
        'products',
        'customers',
        'orders',
        'purchases',
        'stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')
                          ->nullable()
                          ->after('id')
                          ->constrained()
                          ->cascadeOnDelete();
            });
        }

        // Rattache toutes les données existantes au premier utilisateur trouvé,
        // pour ne rien perdre de ce qui a été créé avant cette séparation par compte.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $firstUserId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['user_id']);
                $blueprint->dropColumn('user_id');
            });
        }
    }
};