<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table      = 'email_logs';
    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'recipient_email',
        'email_type',
        'status',
        'error_message',
        'attempts',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts'   => 'integer',
            'sent_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
