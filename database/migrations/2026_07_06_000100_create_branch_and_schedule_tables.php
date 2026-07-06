<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch and schedule table migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->string('branch_code', 50)->unique();
            $table->string('branch_address');
            $table->string('branch_image_path')->nullable();
            $table->text('branch_description')->nullable();
            $table->timestamps();

            $table->index('branch_name');
        });

        Schema::create('branch_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->dateTime('date');
            $table->timestamps();

            $table->index(['branch_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code', 50)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->string('s_monday')->nullable();
            $table->string('s_tuesday')->nullable();
            $table->string('s_wednesday')->nullable();
            $table->string('s_thursday')->nullable();
            $table->string('s_friday')->nullable();
            $table->string('s_saturday')->nullable();
            $table->string('s_sunday')->nullable();
            $table->string('schedule_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'week_number']);
            $table->index(['branch_id', 'week_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('branch_histories');
        Schema::dropIfExists('branches');
    }
};
