<?php
namespace App\Models;
use App\Enums\Severity; use App\Models\Concerns\BelongsToFactory; use Illuminate\Database\Eloquent\Concerns\HasUlids; use Illuminate\Database\Eloquent\Model;
class AlertRecipient extends Model { use HasUlids,BelongsToFactory; protected $fillable=['user_id','name','email','phone','channels','is_active','severity_min']; protected function casts():array{return ['channels'=>'array','is_active'=>'boolean','severity_min'=>Severity::class];} public function user(){return $this->belongsTo(User::class);} }
