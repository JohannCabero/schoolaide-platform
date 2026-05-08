<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'student_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'status',
        'program',
        'year_level',
        'user_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function getFullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function searchActive($query)
    {
        return $query->where('status', 'active');
    }

    public function searchByStudentNumber($query, string $studentNumber)
    {
        return $query->where('student_number', $studentNumber);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
