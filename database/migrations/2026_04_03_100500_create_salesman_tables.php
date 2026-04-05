<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('salesman_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesman_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['salesman_id', 'region_id']);
        });

        Schema::create('salesman_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salesman_user_id')->nullable();
            $table->unsignedBigInteger('salesman_company_id')->nullable();
            $table->foreignId('pharmacy_company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('salesman_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('salesman_company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->index(['salesman_user_id', 'pharmacy_company_id'], 'salesman_user_pharmacy_idx');
            $table->index(['salesman_company_id', 'pharmacy_company_id'], 'salesman_company_pharmacy_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesman_clients');
        Schema::dropIfExists('salesman_regions');
    }
};
