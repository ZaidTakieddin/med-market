<?php

use App\Constants\DriverStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicate if the migration should be run within a transaction.
     *
     * Neon's pooled endpoint aborts DDL issued inside a transaction (SQLSTATE 25P02),
     * so each statement must run un-transacted.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('work_start_time')->nullable()->after('role');
            $table->time('work_end_time')->nullable()->after('work_start_time');
            $table->date('employment_start_date')->nullable()->after('work_end_time');
            $table->date('employment_end_date')->nullable()->after('employment_start_date');
            $table->string('driver_status')->nullable()->after('employment_end_date')->default(DriverStatus::AVAILABLE);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'work_start_time',
                'work_end_time',
                'employment_start_date',
                'employment_end_date',
                'driver_status',
            ]);
        });
    }
};
