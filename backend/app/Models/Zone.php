<?php
namespace App\Models;
use App\Enums\RiskLevel; use App\Enums\RecordStatus; use App\Models\Concerns\BelongsToFactory; use Illuminate\Database\Eloquent\Concerns\HasUlids; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes;
class Zone extends Model { use HasUlids,SoftDeletes,BelongsToFactory; protected $fillable=['name','reference','description','risk_level','floor','status']; protected function casts():array{return ['risk_level'=>RiskLevel::class,'status'=>RecordStatus::class];} public function cameras(){return $this->hasMany(Camera::class);} public function fireEvents(){return $this->hasMany(FireEvent::class);} }
