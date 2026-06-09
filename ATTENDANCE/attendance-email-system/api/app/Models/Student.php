<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'student_id';

    public $timestamps = false; // table has only created_at, no updated_at

    protected $fillable = [
        'roll_number',
        'student_name',
        'email',
        'parent_email',
        'department',
        'semester',
    ];

    protected function casts(): array
    {
        return [
            'semester'   => 'integer',
            'created_at' => 'datetime',
        ];
    }

    // Relationships
    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    public function detentions()
    {
        return $this->hasMany(Detention::class, 'student_id', 'student_id');
    }
}
