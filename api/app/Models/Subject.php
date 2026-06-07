<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects';
    protected $primaryKey = 'subject_id';

    public $timestamps = false; // no timestamp columns in this table

    protected $fillable = [
        'subject_name',
        'subject_code',
        'department',
        'semester',
    ];

    protected function casts(): array
    {
        return [
            'semester' => 'integer',
        ];
    }

    // Relationships
    public function faculty()
    {
        return $this->belongsToMany(
            Faculty::class,
            'faculty_subjects',
            'subject_id',
            'faculty_id'
        );
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'subject_id', 'subject_id');
    }
}
