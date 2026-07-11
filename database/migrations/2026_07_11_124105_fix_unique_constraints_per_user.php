<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['user_id', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->unique(['user_id', 'sku']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['purchase_number']);
            $table->unique(['user_id', 'purchase_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->unique(['user_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->unique('slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'sku']);
            $table->unique('sku');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'purchase_number']);
            $table->unique('purchase_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'reference']);
            $table->unique('reference');
        });
    }
};