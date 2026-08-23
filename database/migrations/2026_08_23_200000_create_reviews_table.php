<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicate if the migration should be run within a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // Product or Company (supplier) reviews.
            $table->morphs('reviewable');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('body')->nullable();
            $table->string('status', 20)->default('approved'); // approved | pending | rejected
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_user_reviewable_unique');
            $table->index(['reviewable_type', 'reviewable_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
