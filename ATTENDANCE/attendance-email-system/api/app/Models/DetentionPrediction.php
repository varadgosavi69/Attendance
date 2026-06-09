<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetentionPrediction extends Model
{
    protected $table = 'detention_predictions';

    public $timestamps = false; // table has only predicted_at

    protected $fillable = [
        'student_id',
        'predicted_at',
        'risk_score',
        'predicted_detention',
        'features_snapshot',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'predicted_at'        => 'datetime',
            'risk_score'          => 'decimal:3',
            'predicted_detention' => 'boolean',
            'features_snapshot'   => 'array',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
