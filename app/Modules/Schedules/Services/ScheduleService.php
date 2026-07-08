<?php

namespace App\Modules\Schedules\Services;

use App\Models\Schedule;
use App\Models\ScheduleDay;
use App\Modules\Schedules\Repositories\ScheduleRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleService
{
    public function __construct(private readonly ScheduleRepository $scheduleRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createSchedules(array $payload): int
    {
        return DB::transaction(function () use ($payload): int {
            $createdCount = 0;
            $employeeIds = array_values(array_unique(array_map('intval', $payload['employee_ids'])));
            $weeks = $payload['weeks'] ?? [];

            foreach ($employeeIds as $employeeId) {
                foreach ($weeks as $week) {
                    $weekPayload = array_merge($week, [
                        'default_branch_id' => $payload['default_branch_id'],
                        'default_start_time' => $payload['default_start_time'],
                        'default_end_time' => $payload['default_end_time'],
                    ]);

                    $schedule = $this->scheduleRepository->create(array_merge(
                        $this->scheduleAttributes($weekPayload, $employeeId),
                        ['schedule_code' => $this->generateScheduleCode((int) $weekPayload['year'], (int) $weekPayload['week_number'], (int) $weekPayload['default_branch_id'], $employeeId)]
                    ));

                    $this->replaceScheduleDays($schedule, $weekPayload);
                    $createdCount++;
                }
            }

            return $createdCount;
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateSchedule(Schedule $schedule, array $payload): Schedule
    {
        return DB::transaction(function () use ($schedule, $payload): Schedule {
            $employeeId = (int) $payload['employee_id'];
            $branchId = (int) $payload['default_branch_id'];
            $year = (int) $payload['year'];
            $weekNumber = (int) $payload['week_number'];
            $attributes = $this->scheduleAttributes($payload, $employeeId);

            if ((int) $schedule->employee_id !== $employeeId || (int) $schedule->default_branch_id !== $branchId || (int) $schedule->week_number !== $weekNumber || (int) $schedule->year !== $year) {
                $attributes['schedule_code'] = $this->generateScheduleCode($year, $weekNumber, $branchId, $employeeId, $schedule->id);
            }

            $this->scheduleRepository->update($schedule, $attributes);
            $this->replaceScheduleDays($schedule->refresh(), $payload);

            return $schedule->fresh(['days.branch', 'defaultBranch']) ?? $schedule;
        });
    }

    public function deleteSchedule(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule): void {
            $this->scheduleRepository->delete($schedule);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $payload, int $employeeId): array
    {
        return [
            'employee_id' => $employeeId,
            'branch_id' => (int) $payload['default_branch_id'],
            'default_branch_id' => (int) $payload['default_branch_id'],
            'year' => (int) $payload['year'],
            'week_number' => (int) $payload['week_number'],
            'default_start_time' => $this->normalizeTime($payload['default_start_time'] ?? null),
            'default_end_time' => $this->normalizeTime($payload['default_end_time'] ?? null),
            's_monday' => $this->legacyDayTime($payload, 'monday'),
            's_tuesday' => $this->legacyDayTime($payload, 'tuesday'),
            's_wednesday' => $this->legacyDayTime($payload, 'wednesday'),
            's_thursday' => $this->legacyDayTime($payload, 'thursday'),
            's_friday' => $this->legacyDayTime($payload, 'friday'),
            's_saturday' => $this->legacyDayTime($payload, 'saturday'),
            's_sunday' => $this->legacyDayTime($payload, 'sunday'),
            'schedule_notes' => $payload['schedule_notes'] ?? null,
            'status' => $payload['status'] ?? 'active',
        ];
    }

    private function generateScheduleCode(int $year, int $weekNumber, int $branchId, int $employeeId, ?int $ignoreScheduleId = null): string
    {
        do {
            $scheduleCode = sprintf('SCH-%d-W%02d-B%d-E%d-%s', $year, $weekNumber, $branchId, $employeeId, Str::upper(Str::random(4)));
        } while ($this->scheduleRepository->scheduleCodeExists($scheduleCode, $ignoreScheduleId));

        return $scheduleCode;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function replaceScheduleDays(Schedule $schedule, array $payload): void
    {
        $schedule->days()->delete();

        $weekStart = CarbonImmutable::now()->setISODate((int) $payload['year'], (int) $payload['week_number']);
        $rows = [];

        foreach ($this->dayNames() as $offset => $dayName) {
            $dayPayload = $payload['days'][$dayName] ?? [];
            $status = (string) ($dayPayload['status'] ?? ($offset < 5 ? ScheduleDay::STATUS_WORKING : ScheduleDay::STATUS_REST_DAY));
            $isWorking = $status === ScheduleDay::STATUS_WORKING;
            $startTime = $isWorking ? $this->resolveTime($dayPayload['start_time'] ?? null, $payload['default_start_time'] ?? null) : null;
            $endTime = $isWorking ? $this->resolveTime($dayPayload['end_time'] ?? null, $payload['default_end_time'] ?? null) : null;
            $branchId = $dayPayload['branch_id'] ?? null;

            $rows[] = [
                'work_date' => $weekStart->addDays($offset)->toDateString(),
                'day_of_week' => $dayName,
                'branch_id' => $isWorking ? (int) ($branchId !== null && $branchId !== '' ? $branchId : $payload['default_branch_id']) : null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'notes' => $dayPayload['notes'] ?? null,
            ];
        }

        $schedule->days()->createMany($rows);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function legacyDayTime(array $payload, string $dayName): ?string
    {
        $dayPayload = $payload['days'][$dayName] ?? [];

        if (($dayPayload['status'] ?? ScheduleDay::STATUS_WORKING) !== ScheduleDay::STATUS_WORKING) {
            return null;
        }

        return $this->resolveTime($dayPayload['start_time'] ?? null, $payload['default_start_time'] ?? null);
    }

    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        return substr($time, 0, 5);
    }

    private function resolveTime(?string $override, ?string $default): ?string
    {
        return $this->normalizeTime($override) ?? $this->normalizeTime($default);
    }

    /**
     * @return array<int, string>
     */
    private function dayNames(): array
    {
        return [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];
    }
}
