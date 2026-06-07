<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $table = 'faculty';
    protected $primaryKey = 'faculty_id';

    public $timestamps = false; // no timestamp columns in this table

    protected $fillable = [
        'faculty_name',
        'email',
        'department',
    ];

    // Relationships
    public function user()
    {
        return $this->hasOne(User::class, 'faculty_id', 'faculty_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(
            Subject::class,
            'faculty_subjects',
            'faculty_id',
            'subject_id'
        );
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'faculty_id', 'faculty_id');
    }
}
