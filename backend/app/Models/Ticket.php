<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'subject',
        'description',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    protected $appends = ['sla_status'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function slaPolicy()
    {
        return $this->hasOne(SlaPolicy::class, 'priority', 'priority')
            ->where('organization_id', $this->organization_id);
    }

    public function getSlaStatusAttribute(): array
    {
        // Use eager-loaded relationship if available, otherwise query
        $policy = $this->relationLoaded('slaPolicy') ? $this->slaPolicy : SlaPolicy::where('organization_id', $this->organization_id)->where('priority', $this->priority)->first();

        if (! $policy) {
            return [
                'has_policy' => false,
                'is_breached' => false,
                'time_remaining' => null,
                'percent_remaining' => null,
                'status' => 'none',
            ];
        }

        $deadline = Carbon::parse($this->created_at)->addHours($policy->resolution_hours);
        $now = Carbon::now();
        $totalSeconds = $policy->resolution_hours * 3600;
        $remainingSeconds = $now->diffInSeconds($deadline, false); // positive = remaining

        $isBreached = $remainingSeconds <= 0;
        $percentRemaining = $totalSeconds > 0 ? max(0, min(100, ($remainingSeconds / $totalSeconds) * 100)) : 0;

        $status = 'ok';
        if ($isBreached) {
            $status = 'breached';
        } elseif ($percentRemaining < 20) {
            $status = 'warning';
        }

        return [
            'has_policy' => true,
            'is_breached' => $isBreached,
            'deadline' => $deadline->toIso8601String(),
            'time_remaining' => $isBreached ? null : $remainingSeconds,
            'percent_remaining' => round($percentRemaining, 1),
            'status' => $status,
        ];
    }
}
