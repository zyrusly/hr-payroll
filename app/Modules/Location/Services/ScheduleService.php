<?php

namespace App\Modules\Location\Services;

use App\Models\Schedule;
use App\Modules\Location\Repositories\ScheduleRepository;
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

            foreach ($employeeIds as $employeeId) {
                $this->scheduleRepository->create(array_merge(
                    $this->scheduleAttributes($payload, $employeeId),
                    ['schedule_code' => $this->generateScheduleCode((int) $payload['week_number'], (int) $payload['branch_id'], $employeeId)]
                ));

                $createdCount++;
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
            $branchId = (int) $payload['branch_id'];
            $weekNumber = (int) $payload['week_number'];
            $attributes = $this->scheduleAttributes($payload, $employeeId);

            if ((int) $schedule->employee_id !== $employeeId || (int) $schedule->branch_id !== $branchId || (int) $schedule->week_number !== $weekNumber) {
                $attributes['schedule_code'] = $this->generateScheduleCode($weekNumber, $branchId, $employeeId, $schedule->id);
            }

            $this->scheduleRepository->update($schedule, $attributes);

            return $schedule->fresh() ?? $schedule;
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
            'branch_id' => (int) $payload['branch_id'],
            'week_number' => (int) $payload['week_number'],
            's_monday' => $payload['s_monday'] ?? null,
            's_tuesday' => $payload['s_tuesday'] ?? null,
            's_wednesday' => $payload['s_wednesday'] ?? null,
            's_thursday' => $payload['s_thursday'] ?? null,
            's_friday' => $payload['s_friday'] ?? null,
            's_saturday' => $payload['s_saturday'] ?? null,
            's_sunday' => $payload['s_sunday'] ?? null,
            'schedule_notes' => $payload['schedule_notes'] ?? null,
        ];
    }

    private function generateScheduleCode(int $weekNumber, int $branchId, int $employeeId, ?int $ignoreScheduleId = null): string
    {
        do {
            $scheduleCode = sprintf('SCH-W%02d-B%d-E%d-%s', $weekNumber, $branchId, $employeeId, Str::upper(Str::random(4)));
        } while ($this->scheduleRepository->scheduleCodeExists($scheduleCode, $ignoreScheduleId));

        return $scheduleCode;
    }
}
