<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Ex: 'created', 'updated', 'deleted', 'delivered', 'cancelled', 'received'
            $table->string('action');

            // Ex: 'Product', 'Order', 'Purchase'...
            $table->string('subject_type');

            $table->unsignedBigInteger('subject_id')->nullable();

            // Ex: "Produit « Casserole INOX 30cm » créé"
            $table->string('description');

            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};