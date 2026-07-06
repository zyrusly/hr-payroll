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
        $checkInAt = $entryType === 'checkin' ? $entryAt : null;
        $checkOutAt = $entryType === 'checkout' ? $entryAt : null;
        $source = substr($sourcePrefix . '-' . $entryType, 0, 40);

        return DB::transaction(function () use ($employeeId, $attendanceDate, $checkInAt, $checkOutAt, $source, $payload, $approvedBy): AttendanceLog {
            return $this->attendanceRepository->create([
                'employee_id' => $employeeId,
                'attendance_date' => $attendanceDate,
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'worked_minutes' => 0,
                'status' => 'present',
                'source' => $source,
                'remarks' => $payload['remarks'] ?? null,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedBy ? now() : null,
            ]);
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
