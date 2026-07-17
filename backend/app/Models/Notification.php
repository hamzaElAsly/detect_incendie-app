<?php
namespace App\Models;
use App\Enums\NotificationChannel; use App\Enums\NotificationStatus; use App\Models\Concerns\BelongsToFactory; use Illuminate\Database\Eloquent\Concerns\HasUlids; use Illuminate\Database\Eloquent\Model;
class Notification extends Model { use HasUlids,BelongsToFactory; protected $fillable=['fire_event_id','channel','recipient','subject','message','status','sent_at','failed_at','error_message','metadata']; protected function casts():array{return ['channel'=>NotificationChannel::class,'status'=>NotificationStatus::class,'sent_at'=>'datetime','failed_at'=>'datetime','metadata'=>'array'];} public function fireEvent(){return $this->belongsTo(FireEvent::class);} public function logs(){return $this->hasMany(NotificationLog::class);} }
