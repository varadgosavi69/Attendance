<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';
    protected $primaryKey = 'attendance_id';

    public $timestamps = false; // table uses marked_at, not created_at/updated_at

    protected $fillable = [
        'student_id',
        'subject_id',
        'faculty_id',
        'attendance_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'marked_at'       => 'datetime',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id', 'faculty_id');
    }
}
