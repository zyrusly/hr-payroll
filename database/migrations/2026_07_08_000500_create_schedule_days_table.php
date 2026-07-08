<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('week_number');
            $table->foreignId('default_branch_id')->nullable()->after('branch_id')->constrained('branches')->nullOnDelete();
            $table->time('default_start_time')->nullable()->after('default_branch_id');
            $table->time('default_end_time')->nullable()->after('default_start_time');
            $table->string('status', 30)->default('active')->after('schedule_notes');

            $table->index(['default_branch_id', 'year', 'week_number'], 'schedules_default_branch_week_index');
            $table->index(['employee_id', 'year', 'week_number'], 'schedules_employee_year_week_index');
        });

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

        $this->backfillScheduleDays();
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_default_branch_week_index');
            $table->dropIndex('schedules_employee_year_week_index');
            $table->dropConstrainedForeignId('default_branch_id');
            $table->dropColumn([
                'year',
                'default_start_time',
                'default_end_time',
                'status',
            ]);
        });
    }

    private function backfillScheduleDays(): void
    {
        $currentYear = (int) now()->year;
        $dayFields = [
            'monday' => 's_monday',
            'tuesday' => 's_tuesday',
            'wednesday' => 's_wednesday',
            'thursday' => 's_thursday',
            'friday' => 's_friday',
            'saturday' => 's_saturday',
            'sunday' => 's_sunday',
        ];

        DB::table('schedules')
            ->select([
                'id',
                'branch_id',
                'week_number',
                's_monday',
                's_tuesday',
                's_wednesday',
                's_thursday',
                's_friday',
                's_saturday',
                's_sunday',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($schedules) use ($currentYear, $dayFields): void {
                foreach ($schedules as $schedule) {
                    $firstStartTime = collect($dayFields)
                        ->map(fn (string $field): ?string => $this->normalizeTime($schedule->{$field} ?? null))
                        ->first(fn (?string $time): bool => $time !== null);

                    DB::table('schedules')
                        ->where('id', $schedule->id)
                        ->update([
                            'year' => $currentYear,
                            'default_branch_id' => $schedule->branch_id,
                            'default_start_time' => $firstStartTime,
                            'default_end_time' => $this->addHours($firstStartTime, 9),
                        ]);

                    $weekStart = CarbonImmutable::now()->setISODate($currentYear, (int) $schedule->week_number);
                    $rows = [];
                    $offset = 0;

                    foreach ($dayFields as $dayOfWeek => $field) {
                        $startTime = $this->normalizeTime($schedule->{$field} ?? null);
                        $rows[] = [
                            'schedule_id' => $schedule->id,
                            'work_date' => $weekStart->addDays($offset)->toDateString(),
                            'day_of_week' => $dayOfWeek,
                            'branch_id' => $startTime === null ? null : $schedule->branch_id,
                            'start_time' => $startTime,
                            'end_time' => $this->addHours($startTime, 9),
                            'status' => $startTime === null ? 'rest_day' : 'working',
                            'notes' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $offset++;
                    }

                    DB::table('schedule_days')->insert($rows);
                }
            });
    }

    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('H:i', substr($time, 0, 5))->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function addHours(?string $time, int $hours): ?string
    {
        if ($time === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('H:i:s', $time)->addHours($hours)->format('H:i:s');
    }
};
