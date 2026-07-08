<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDay extends Model
{
    use HasFactory;

    public const STATUS_WORKING = 'working';
    public const STATUS_REST_DAY = 'rest_day';
    public const STATUS_DAY_OFF = 'day_off';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_HOLIDAY = 'holiday';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
