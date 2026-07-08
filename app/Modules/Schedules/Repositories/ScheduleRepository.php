<?php

namespace App\Modules\Schedules\Repositories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        $year = (string) ($filters['year'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return Schedule::query()
            ->with([
                'defaultBranch:id,branch_name,branch_code',
                'days.branch:id,branch_name,branch_code',
                'employee:id,employee_code,first_name,last_name,department_id',
                'employee.department:id,name',
            ])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('schedule_code', 'like', "%{$q}%")
                        ->orWhere('schedule_notes', 'like', "%{$q}%")
                        ->orWhereHas('defaultBranch', function ($branchQuery) use ($q): void {
                            $branchQuery
                                ->where('branch_name', 'like', "%{$q}%")
                                ->orWhere('branch_code', 'like', "%{$q}%");
                        })
                        ->orWhereHas('days.branch', function ($branchQuery) use ($q): void {
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
            ->when($branchId !== '', fn ($query) => $this->applyBranchFilter($query, (int) $branchId))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', (int) $employeeId))
            ->when($weekNumber !== '', fn ($query) => $query->where('week_number', (int) $weekNumber))
            ->when($year !== '', fn ($query) => $query->where('year', (int) $year))
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->orderBy('schedule_code')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, Schedule>
     */
    public function listForCalendar(array $filters, CarbonImmutable $month): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');
        $employeeId = (string) ($filters['employee_id'] ?? '');
        $weekNumber = (string) ($filters['week_number'] ?? '');
        $year = (string) ($filters['year'] ?? '');
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        return Schedule::query()
            ->with([
                'defaultBranch:id,branch_name,branch_code',
                'days' => fn ($query) => $query
                    ->with('branch:id,branch_name,branch_code')
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('work_date'),
                'employee:id,employee_code,first_name,last_name,department_id',
                'employee.department:id,name',
            ])
            ->whereHas('days', fn ($query) => $query->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]))
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('schedule_code', 'like', "%{$q}%")
                        ->orWhere('schedule_notes', 'like', "%{$q}%")
                        ->orWhereHas('defaultBranch', function ($branchQuery) use ($q): void {
                            $branchQuery
                                ->where('branch_name', 'like', "%{$q}%")
                                ->orWhere('branch_code', 'like', "%{$q}%");
                        })
                        ->orWhereHas('days.branch', function ($branchQuery) use ($q): void {
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
            ->when($branchId !== '', fn ($query) => $this->applyBranchFilter($query, (int) $branchId))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', (int) $employeeId))
            ->when($weekNumber !== '', fn ($query) => $query->where('week_number', (int) $weekNumber))
            ->when($year !== '', fn ($query) => $query->where('year', (int) $year))
            ->orderBy('year')
            ->orderBy('week_number')
            ->orderBy('schedule_code')
            ->get();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, Schedule>
     */
    public function listForExport(array $filters, ?CarbonImmutable $month = null): Collection
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $branchId = (string) ($filters['branch_id'] ?? '');
        $employeeId = (string) ($filters['employee_id'] ?? '');
        $weekNumber = (string) ($filters['week_number'] ?? '');
        $year = (string) ($filters['year'] ?? '');
        $start = $month?->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month?->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        return Schedule::query()
            ->with([
                'defaultBranch:id,branch_name,branch_code',
                'days' => fn ($query) => $query
                    ->with('branch:id,branch_name,branch_code')
                    ->when($start && $end, fn ($dayQuery) => $dayQuery->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]))
                    ->orderBy('work_date'),
                'employee:id,employee_code,first_name,last_name,department_id',
                'employee.department:id,name',
            ])
            ->when($start && $end, fn ($query) => $query->whereHas('days', fn ($dayQuery) => $dayQuery->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])))
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('schedule_code', 'like', "%{$q}%")
                        ->orWhere('schedule_notes', 'like', "%{$q}%")
                        ->orWhereHas('defaultBranch', function ($branchQuery) use ($q): void {
                            $branchQuery
                                ->where('branch_name', 'like', "%{$q}%")
                                ->orWhere('branch_code', 'like', "%{$q}%");
                        })
                        ->orWhereHas('days.branch', function ($branchQuery) use ($q): void {
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
            ->when($branchId !== '', fn ($query) => $this->applyBranchFilter($query, (int) $branchId))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', (int) $employeeId))
            ->when($weekNumber !== '', fn ($query) => $query->where('week_number', (int) $weekNumber))
            ->when($year !== '', fn ($query) => $query->where('year', (int) $year))
            ->orderBy('year')
            ->orderBy('week_number')
            ->orderBy('schedule_code')
            ->get();
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

    /**
     * @return array<int, int>
     */
    private function isoWeeksForMonth(CarbonImmutable $month): array
    {
        $weeks = [];
        $date = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        while ($date->lte($end)) {
            $weeks[] = (int) $date->isoWeek();
            $date = $date->addWeek();
        }

        return array_values(array_unique($weeks));
    }

    private function applyBranchFilter($query, int $branchId): void
    {
        $query->where(function ($inner) use ($branchId): void {
            $inner
                ->where('default_branch_id', $branchId)
                ->orWhereHas('days', fn ($dayQuery) => $dayQuery->where('branch_id', $branchId));
        });
    }
}
