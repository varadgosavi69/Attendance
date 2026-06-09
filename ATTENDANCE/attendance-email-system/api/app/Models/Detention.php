<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detention extends Model
{
    protected $table = 'detention';
    protected $primaryKey = 'detention_id';

    public $timestamps = false; // table uses generated_at, not created_at/updated_at

    protected $fillable = [
        'student_id',
        'month',
        'total_classes',
        'attended_classes',
        'attendance_percentage',
        'is_detained',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'month'                => 'date',
            'total_classes'        => 'integer',
            'attended_classes'     => 'integer',
            'attendance_percentage'=> 'decimal:2',
            'is_detained'          => 'boolean',
            'notified_at'          => 'datetime',
            'generated_at'         => 'datetime',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
