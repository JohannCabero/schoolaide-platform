<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'service_type_id',
        'status',
        'assigned_to',
        'requested_date',
        'remarks',
        'processed_by',
        'processing_notes',
        'processed_at',
        'version',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withoutGlobalScope(TenantScope::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class)->withoutGlobalScope(TenantScope::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withoutGlobalScope(TenantScope::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withoutGlobalScope(TenantScope::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('requested_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('requested_date', '<=', $to);
        }

        return $query;
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if the version hasn't changed since last read
     */
    public function isVersionFresh(int $expectedVersion): bool
    {
        return $this->version === $expectedVersion;
    }


}
