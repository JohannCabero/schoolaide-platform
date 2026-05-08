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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function searchPending($query)
    {
        return $query->where('status', 'pending');
    }

    public function searchByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function searchByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    public function searchAssignedTo($query, int $userId)
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

    /**
     * Increment version for optimistic locking after each write
     */
    public function incrementVersion(): void
    {
        $this->increment('version');
        $this->refresh();
    }
}
