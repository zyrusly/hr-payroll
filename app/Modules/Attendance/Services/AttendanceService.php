<?php

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceLog;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private readonly AttendanceRepository $attendanceRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addManualLog(int $employeeId, array $payload, ?int $approvedBy = null, string $sourcePrefix = 'manual'): AttendanceLog
    {
        $attendanceDate = (string) $payload['attendance_date'];
        $entryType = (string) $payload['entry_type'];
        $entryTime = (string) $payload['entry_time'];
        $entryAt = $this->parseEntryDateTime($attendanceDate, $entryTime);
        $source = substr($sourcePrefix . '-' . $entryType, 0, 40);

        return DB::transaction(function () use ($employeeId, $attendanceDate, $entryType, $entryAt, $source, $payload, $approvedBy): AttendanceLog {
            $log = AttendanceLog::query()
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $attendanceDate)
                ->lockForUpdate()
                ->first();

            if (! $log) {
                $log = new AttendanceLog([
                    'employee_id' => $employeeId,
                    'attendance_date' => $attendanceDate,
                    'worked_minutes' => 0,
                    'status' => 'present',
                    'source' => $source,
                ]);
            }

            if ($entryType === 'checkin' && (! $log->check_in_at || $entryAt->lt($log->check_in_at))) {
                $log->check_in_at = $entryAt;
            }

            if ($entryType === 'checkout' && (! $log->check_out_at || $entryAt->gt($log->check_out_at))) {
                $log->check_out_at = $entryAt;
            }

            if (! empty($payload['remarks'])) {
                $log->remarks = $log->remarks
                    ? trim($log->remarks . ' | ' . $payload['remarks'])
                    : $payload['remarks'];
            }

            $log->source = $source;
            $log->approved_by = $approvedBy;
            $log->approved_at = $approvedBy ? now() : null;

            if ($log->check_in_at && $log->check_out_at) {
                $log->worked_minutes = max(0, $log->check_in_at->diffInMinutes($log->check_out_at, false));
            }

            $log->save();

            return $log;
        });
    }

    public function isEntryDateTimeValid(string $attendanceDate, string $entryTime): bool
    {
        $value = trim($attendanceDate . ' ' . trim($entryTime));
        $formats = ['Y-m-d H:i', 'Y-m-d h:i A', 'Y-m-d h:i a'];

        foreach ($formats as $format) {
            try {
                Carbon::createFromFormat($format, $value);
                return true;
            } catch (\Throwable) {
                // Try next format.
            }
        }

        return false;
    }
    // Parse the provided attendance date and entry time into a Carbon instance, trying multiple formats to accommodate different time input styles.
    private function parseEntryDateTime(string $attendanceDate, string $entryTime): Carbon
    {
        $value = trim($attendanceDate . ' ' . trim($entryTime));
        $formats = ['Y-m-d H:i', 'Y-m-d h:i A', 'Y-m-d h:i a'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
 
            }
        }

        return Carbon::parse($value);
    }
}
