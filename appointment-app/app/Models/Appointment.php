<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'first_name', 'last_name', 'company', 'job_title', 'email', 'phone',
        'subject', 'date', 'time', 'duration', 'attendees', 'companions', 'message',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'date' => 'date:Y-m-d',
            'duration' => 'integer',
            'attendees' => 'integer',
            'decided_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            $appointment->reference ??= 'RDV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            $appointment->status ??= AppointmentStatus::Pending;
        });
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function arrivalRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arrival_recorded_by');
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Approved);
    }

    public function fullName(): string
    {
        return collect([$this->title, $this->first_name, $this->last_name])->filter()->implode(' ');
    }

    /** Début du rendez-vous dans le fuseau de l'application. */
    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->time);
    }

    public function endsAt(): Carbon
    {
        return $this->startsAt()->addMinutes($this->duration);
    }

    public function isToday(): bool
    {
        return $this->date->isSameDay(today());
    }

    /**
     * Rendez-vous validés qui chevauchent le créneau de celui-ci.
     *
     * @return Collection<int, Appointment>
     */
    public function overlappingApproved(?string $date = null, ?string $time = null, ?int $duration = null)
    {
        $start = Carbon::parse(($date ?? $this->date->format('Y-m-d')).' '.($time ?? $this->time));
        $end = $start->copy()->addMinutes($duration ?? $this->duration);

        return static::approved()
            ->whereDate('date', $start->toDateString())
            ->whereKeyNot($this->getKey())
            ->get()
            ->filter(fn (Appointment $other) => $other->startsAt() < $end && $other->endsAt() > $start)
            ->values();
    }
}
