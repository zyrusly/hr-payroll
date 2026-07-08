<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function branchHistories(): HasMany
    {
        return $this->hasMany(BranchHistory::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'default_branch_id');
    }

    public function scheduleDays(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }
}
