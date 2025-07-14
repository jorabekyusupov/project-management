<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'ticket_prefix',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function ticketStatuses(): HasMany
    {
        return $this->hasMany(TicketStatus::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withTimestamps();
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
    }

    public function getRemainingDaysAttribute()
    {
        if (!$this->end_date) {
            return null;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->end_date);

        if ($today->gt($endDate)) {
            return 0;
        }

        return $today->diffInDays($endDate);
    }
}
