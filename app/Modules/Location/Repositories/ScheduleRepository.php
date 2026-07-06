<?php

namespace App\Modules\Location\Repositories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');
        $employeeId = (string) ($filters['employee_id'] ?? '');
        $weekNumber = (string) ($filters['week_number'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return Schedule::query()
            ->with([
                'branch:id,branch_name,branch_code',
                'employee:id,employee_code,first_name,last_name,department_id',
                'employee.department:id,name',
            ])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('schedule_code', 'like', "%{$q}%")
                        ->orWhere('schedule_notes', 'like', "%{$q}%")
                        ->orWhereHas('branch', function ($branchQuery) use ($q): void {
                            $branchQuery
                                ->where('branch_name', 'like', "%{$q}%")
                                ->orWhere('branch_code', 'like', "%{$q}%");
                        })
                        ->orWhereHas('employee', function ($employeeQuery) use ($q): void {
                            $employeeQuery
                                ->where('employee_code', 'like', "%{$q}%")
                                ->orWhere('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%");
                        });
                });
            })
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', (int) $branchId))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', (int) $employeeId))
            ->when($weekNumber !== '', fn ($query) => $query->where('week_number', (int) $weekNumber))
            ->orderByDesc('week_number')
            ->orderBy('schedule_code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Branch>
     */
    public function listBranches(): Collection
    {
        return Branch::query()
            ->select(['id', 'branch_name', 'branch_code'])
            ->orderBy('branch_name')
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function listActiveEmployees(): Collection
    {
        return Employee::query()
            ->with('department:id,name')
            ->select(['id', 'employee_code', 'first_name', 'last_name', 'department_id'])
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Schedule
    {
        return Schedule::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Schedule $schedule, array $attributes): void
    {
        $schedule->update($attributes);
    }

    public function delete(Schedule $schedule): void
    {
        $schedule->delete();
    }

    public function scheduleCodeExists(string $scheduleCode, ?int $ignoreScheduleId = null): bool
    {
        return Schedule::query()
            ->where('schedule_code', $scheduleCode)
            ->when($ignoreScheduleId !== null, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->exists();
    }
}
