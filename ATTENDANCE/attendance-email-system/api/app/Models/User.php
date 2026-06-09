<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';

    public $timestamps = false; // table has created_at + last_login, not updated_at

    protected $fillable = [
        'username',
        'password_hash',
        'email',
        'full_name',
        'role',
        'faculty_id',
        'department',
        'last_login',
        'last_login_at',
        'remember_token',
        'failed_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'created_at'      => 'datetime',
            'last_login'      => 'datetime',
            'last_login_at'   => 'datetime',
            'locked_until'    => 'datetime',
            'failed_attempts' => 'integer',
        ];
    }

    // tymon/jwt-auth requires these two methods
    public function getJWTIdentifier()
    {
        return $this->getKey(); // returns user_id
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role'       => $this->role,
            'username'   => $this->username,
            'full_name'  => $this->full_name,
            'faculty_id' => $this->faculty_id,
            'department' => $this->department,
        ];
    }

    // Relationships
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id', 'faculty_id');
    }

    public function hodSummaries()
    {
        return $this->hasMany(HodAttendanceSummary::class, 'uploaded_by', 'user_id');
    }
}
