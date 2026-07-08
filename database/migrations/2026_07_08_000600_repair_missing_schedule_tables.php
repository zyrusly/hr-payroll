<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->string('schedule_code', 50)->unique();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('default_branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->time('default_start_time')->nullable();
                $table->time('default_end_time')->nullable();
                $table->unsignedInteger('week_number');
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('s_monday')->nullable();
                $table->string('s_tuesday')->nullable();
                $table->string('s_wednesday')->nullable();
                $table->string('s_thursday')->nullable();
                $table->string('s_friday')->nullable();
                $table->string('s_saturday')->nullable();
                $table->string('s_sunday')->nullable();
                $table->string('schedule_notes')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->index(['employee_id', 'week_number']);
                $table->index(['branch_id', 'week_number']);
                $table->index(['default_branch_id', 'year', 'week_number'], 'schedules_default_branch_week_index');
                $table->index(['employee_id', 'year', 'week_number'], 'schedules_employee_year_week_index');
            });
        } else {
            Schema::table('schedules', function (Blueprint $table) {
                if (! Schema::hasColumn('schedules', 'branch_id')) {
                    $table->foreignId('branch_id')->after('employee_id')->constrained('branches')->cascadeOnDelete();
                }

                if (! Schema::hasColumn('schedules', 'default_branch_id')) {
                    $table->foreignId('default_branch_id')->nullable()->after('branch_id')->constrained('branches')->nullOnDelete();
                }

                if (! Schema::hasColumn('schedules', 'default_start_time')) {
                    $table->time('default_start_time')->nullable()->after('default_branch_id');
                }

                if (! Schema::hasColumn('schedules', 'default_end_time')) {
                    $table->time('default_end_time')->nullable()->after('default_start_time');
                }

                if (! Schema::hasColumn('schedules', 'year')) {
                    $table->unsignedSmallInteger('year')->nullable()->after('week_number');
                }

                if (! Schema::hasColumn('schedules', 'status')) {
                    $table->string('status', 30)->default('active')->after('schedule_notes');
                }
            });
        }

        if (! Schema::hasTable('schedule_days')) {
            Schema::create('schedule_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
                $table->date('work_date');
                $table->string('day_of_week', 20);
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('status', 30)->default('working');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['schedule_id', 'work_date']);
                $table->index(['branch_id', 'work_date']);
                $table->index(['status', 'work_date']);
            });
        }
    }

    public function down(): void
    {
        // This migration repairs a database whose migration history and schema drifted apart.
        // Keep rollback non-destructive so existing schedule data is not dropped accidentally.
    }
};
