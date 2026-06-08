<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HodAttendanceSummary extends Model
{
    protected $table = 'hod_attendance_summary';

    public $timestamps = false; // table uses uploaded_at, not created_at/updated_at

    protected $fillable = [
        'department',
        'semester',
        'year',
        'date',
        'total_students',
        'present_count',
        'attendance_percentage',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'date'                  => 'date',
            'semester'              => 'integer',
            'year'                  => 'integer',
            'total_students'        => 'integer',
            'present_count'         => 'integer',
            'attendance_percentage' => 'decimal:2',
            'uploaded_at'           => 'datetime',
        ];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
