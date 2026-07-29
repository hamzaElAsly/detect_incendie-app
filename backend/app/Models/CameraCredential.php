<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CameraCredential extends Model
{
    use HasUlids;

    protected $fillable = ['username_encrypted', 'password_encrypted'];

    protected $hidden = ['username_encrypted', 'password_encrypted'];

    protected function casts(): array
    {
        return ['username_encrypted' => 'encrypted', 'password_encrypted' => 'encrypted'];
    }

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }
}
