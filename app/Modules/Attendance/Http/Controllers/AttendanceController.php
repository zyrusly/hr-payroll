<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApiClient;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Attendance\Http\Requests\ImportAttendanceRequest;
use App\Modules\Attendance\Http\Requests\StoreAttendanceRequest;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
        private readonly AttendanceService $attendanceService
    ) {
    }
    /// Display a listing of attendance logs with filtering and pagination.

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $hasAllAccess = $this->hasAllAccess($user);
        $scopedEmployeeIds = $hasAllAccess ? null : $this->scopedEmployeeIds($user);

        $filters = [
            'from_date' => (string) $request->input('from_date', now()->startOfMonth()->format('Y-m-d')),
            'to_date' => (string) $request->input('to_date', now()->format('Y-m-d')),
            'employee_id' => (int) $request->input('employee_id', 0),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        if ($scopedEmployeeIds !== null && $filters['employee_id'] > 0 && ! in_array($filters['employee_id'], $scopedEmployeeIds, true)) {
            $filters['employee_id'] = 0;
        }

        return view('hr.attendance.index', [
            'attendanceRows' => $this->attendanceRepository->paginateSummary($filters, $scopedEmployeeIds),
            'employees' => $this->attendanceRepository->listEmployeesForScope($scopedEmployeeIds),
            'filters' => $filters,
            'canManageAttendance' => $user->hasPermission('attendance.manage'),
            'canAttendanceApiIntegration' => $user->hasAnyPermission(['attendance.api-integration', 'attendance.manage']),
            'currentEmployeeId' => $user->employee?->id,
            'hasAllAccess' => $hasAllAccess,
        ]);
    }

    // Show the form for creating a new attendance log.
    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $hasAllAccess = $this->hasAllAccess($user);
        $validated = $request->validated();

        $employeeId = (int) ($validated['employee_id'] ?? 0);
        if (! $hasAllAccess) {
            $employeeId = (int) ($user->employee?->id ?? 0);
        }

        if ($employeeId <= 0) {
            return back()->withErrors(['employee_id' => 'No employee profile is linked to your account.'])->withInput();
        }

        $this->attendanceService->addManualLog($employeeId, $validated, $user->id);

        return redirect()->route('attendance.index')->with('success', __('Attendance log added successfully.'));
    }

    // Export attendance logs as a CSV file based on filters and user access scope.
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $hasAllAccess = $this->hasAllAccess($user);
        $scopedEmployeeIds = $hasAllAccess ? null : $this->scopedEmployeeIds($user);

        $filters = [
            'from_date' => (string) $request->input('from_date', now()->startOfMonth()->format('Y-m-d')),
            'to_date' => (string) $request->input('to_date', now()->format('Y-m-d')),
            'employee_id' => (int) $request->input('employee_id', 0),
        ];

        if ($scopedEmployeeIds !== null && $filters['employee_id'] > 0 && ! in_array($filters['employee_id'], $scopedEmployeeIds, true)) {
            $filters['employee_id'] = 0;
        }

        $rows = $this->attendanceRepository->listRawLogsForExport($filters, $scopedEmployeeIds);
        $fileName = 'attendance_' . $filters['from_date'] . '_to_' . $filters['to_date'] . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = static function () use ($rows): void {

            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            $dailyBounds = [];
            foreach ($rows as $row) {
                $key = (string) $row->employee_id . '|' . (string) $row->attendance_date;
                if (! isset($dailyBounds[$key])) {
                    $dailyBounds[$key] = [
                        'first' => null,
                        'last' => null,
                    ];
                }

                if ($row->check_in_at) {
                    $checkIn = Carbon::parse((string) $row->check_in_at);
                        if ($dailyBounds[$key]['first'] === null || $checkIn->lt($dailyBounds[$key]['first'])) {
                        $dailyBounds[$key]['first'] = $checkIn;
                    }
                }

                if ($row->check_out_at) {
                    $checkOut = Carbon::parse((string) $row->check_out_at);
                    if ($dailyBounds[$key]['last'] === null || $checkOut->gt($dailyBounds[$key]['last'])) {
                        $dailyBounds[$key]['last'] = $checkOut;
                    }
                }
            }

              fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Log ID',
                'Date',
                'Employee',
                'Employee Code',
                'Check-in',
                'Check-out',
                'Duration',
                'Status',
                'Source',
                'Remarks',
                'Created At',
            ]);


            // Write each attendance log row to the CSV output, calculating duration based on check-in and check-out times.
            foreach ($rows as $row) {
                $checkIn = $row->check_in_at ? Carbon::parse((string) $row->check_in_at)->format('Y-m-d H:i:s') : '';
                $checkOut = $row->check_out_at ? Carbon::parse((string) $row->check_out_at)->format('Y-m-d H:i:s') : '';
                $employeeName = trim((string) $row->first_name . ' ' . (string) $row->last_name);
                $durationLabel = '';
                $key = (string) $row->employee_id . '|' . (string) $row->attendance_date;
                $first = $dailyBounds[$key]['first'] ?? null;
                $last = $dailyBounds[$key]['last'] ?? null;
                if ($first instanceof Carbon && $last instanceof Carbon) {
                    $minutes = max(0, $first->diffInMinutes($last, false));
                    $durationLabel = intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
                }

                fputcsv($output, [
                    (string) $row->id,
                    (string) $row->attendance_date,
                    $employeeName,
                    (string) $row->employee_code,
                    $checkIn,
                    $checkOut,
                    $durationLabel,
                    (string) $row->status,
                    (string) $row->source,
                    (string) ($row->remarks ?? ''),
                    $row->created_at ? Carbon::parse((string) $row->created_at)->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($output);
        };

        return response()->streamDownload($callback, $fileName, $headers);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $fileName = 'attendance_import_template.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = static function (): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['employee_code', 'attendance_date', 'entry_type', 'entry_time', 'remarks']);
            fputcsv($output, ['EMP0001', '2026-04-22', 'checkin', '09:01 AM', 'Morning entry']);
            fputcsv($output, ['EMP0001', '2026-04-22', 'checkout', '06:15 PM', 'Evening exit']);
            fclose($output);
        };

        return response()->streamDownload($callback, $fileName, $headers);
    }

    public function importCsv(ImportAttendanceRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $hasAllAccess = $this->hasAllAccess($user);
        $scopedEmployeeIds = $hasAllAccess ? null : $this->scopedEmployeeIds($user);
        $currentEmployeeId = (int) ($user->employee?->id ?? 0);

        $file = $request->file('attendance_file');
        if (! $file || ! $file->isValid()) {
            return back()->withErrors(['attendance_file' => 'Uploaded file is invalid.'])->withInput();
        }

        $handle = fopen((string) $file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['attendance_file' => 'Unable to read uploaded file.'])->withInput();
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return back()->withErrors(['attendance_file' => 'CSV header is missing.'])->withInput();
        }

        $columns = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $map = array_flip($columns);
        $requiredColumns = ['attendance_date', 'entry_type', 'entry_time'];
        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $map)) {
                fclose($handle);
                return back()->withErrors(['attendance_file' => "Missing required column: {$column}."])->withInput();
            }
        }

        $employeeCodeMap = Employee::query()
            ->select(['id', 'employee_code'])
            ->get()
            ->filter(fn (Employee $employee): bool => ! empty($employee->employee_code))
            ->mapWithKeys(fn (Employee $employee) => [strtolower((string) $employee->employee_code) => (int) $employee->id])
            ->all();

        $imported = 0;
        $skipped = 0;
        $line = 1;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $attendanceDate = trim((string) ($row[$map['attendance_date']] ?? ''));
            $entryTypeRaw = trim((string) ($row[$map['entry_type']] ?? ''));
            $entryTime = trim((string) ($row[$map['entry_time']] ?? ''));
            $remarks = trim((string) ($map['remarks'] ?? null) !== null ? ($row[$map['remarks']] ?? '') : '');
            $employeeCode = trim((string) (($map['employee_code'] ?? null) !== null ? ($row[$map['employee_code']] ?? '') : ''));
            $employeeIdRaw = trim((string) (($map['employee_id'] ?? null) !== null ? ($row[$map['employee_id']] ?? '') : ''));
            $entryType = $this->normalizeEntryType($entryTypeRaw);

            $employeeId = 0;
            if ($hasAllAccess) {
                if ($employeeIdRaw !== '' && ctype_digit($employeeIdRaw)) {
                    $employeeId = (int) $employeeIdRaw;
                } elseif ($employeeCode !== '') {
                    $employeeId = (int) ($employeeCodeMap[strtolower($employeeCode)] ?? 0);
                }
            } else {
                $employeeId = $currentEmployeeId;
            }

            $validator = Validator::make([
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
                'entry_type' => $entryType,
                'entry_time' => $entryTime,
                'remarks' => $remarks,
            ], [
                'employee_id' => ['required', 'integer', 'exists:employees,id'],
                'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'entry_type' => ['required', 'in:checkin,checkout'],
                'entry_time' => ['required', 'string', 'max:20'],
                'remarks' => ['nullable', 'string', 'max:1000'],
            ], [
                'attendance_date.before_or_equal' => 'Only today or previous dates are allowed for attendance.',
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = "Line {$line}: " . $validator->errors()->first();
                continue;
            }

            if (! $this->isEntryTimeValid($attendanceDate, $entryTime)) {
                $skipped++;
                $errors[] = "Line {$line}: Invalid entry_time format ({$entryTime}).";
                continue;
            }

            if ($scopedEmployeeIds !== null && ! in_array($employeeId, $scopedEmployeeIds, true)) {
                $skipped++;
                $errors[] = "Line {$line}: You are not allowed to import data for employee_id {$employeeId}.";
                continue;
            }

            $this->attendanceService->addManualLog($employeeId, [
                'attendance_date' => $attendanceDate,
                'entry_type' => $entryType,
                'entry_time' => $entryTime,
                'remarks' => $remarks,
            ], $user->id);
            $imported++;
        }

        fclose($handle);

        if ($skipped > 0 && $imported === 0) {
            return back()
                ->withErrors(['attendance_file' => 'No rows imported. ' . implode(' | ', array_slice($errors, 0, 3))])
                ->withInput();
        }

        $message = "Import completed. Imported {$imported} row(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} row(s).";
        }

        if ($skipped > 0) {
            return redirect()->route('attendance.index')->with('warning', $message);
        }

        return redirect()->route('attendance.index')->with('success', $message);
    }

    public function apiIntegrationDocs(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('hr.attendance.api_integration', [
            'apiClients' => AttendanceApiClient::query()->orderByDesc('id')->get(),
            'latestPlainToken' => session('attendance_api_plain_token'),
            'canManageAttendance' => $user->hasPermission('attendance.manage'),
        ]);
    }

    public function createApiClient(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allowed_ips' => ['nullable', 'string', 'max:1000'],
        ]);

        $plainToken = Str::random(64);
        AttendanceApiClient::query()->create([
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $plainToken),
            'is_active' => true,
            'allowed_ips' => $validated['allowed_ips'] ?: null,
            'created_by' => $user->id,
        ]);

        return redirect()->route('attendance.api-docs')
            ->with('success', __('API client created successfully. Copy the token now.'))
            ->with('attendance_api_plain_token', $plainToken);
    }

    public function toggleApiClient(Request $request, AttendanceApiClient $apiClient): RedirectResponse
    {
        $apiClient->update(['is_active' => ! $apiClient->is_active]);

        return redirect()->route('attendance.api-docs')->with('success', __('API client status updated.'));
    }

    /**
     * @return array<int, int>|null
     */
    private function scopedEmployeeIds(User $user): ?array
    {
        $employee = $user->employee;
        if (! $employee) {
            return [];
        }

        $ids = [$employee->id];
        $subordinateIds = $employee->subordinates()->pluck('id')->all();

        return array_values(array_unique(array_merge($ids, $subordinateIds)));
    }
    private function hasAllAccess(User $user): bool
    {
        return $user->hasAnyPermission(['attendance.manage', 'attendance.report', 'attendance.import']);
    }

    private function normalizeEntryType(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['checkin', 'check-in', 'in'], true)) {
            return 'checkin';
        }

        if (in_array($normalized, ['checkout', 'check-out', 'out'], true)) {
            return 'checkout';
        }

        return $normalized;
    }

    private function isEntryTimeValid(string $attendanceDate, string $entryTime): bool
    {
        $formats = ['Y-m-d H:i', 'Y-m-d h:i A', 'Y-m-d h:i a'];
        $value = $attendanceDate . ' ' . trim($entryTime);

        foreach ($formats as $format) {
            try {
                Carbon::createFromFormat($format, $value);
                return true;
            } catch (\Throwable) {
                // try next format
            }
        }

        return false;
    }
}
