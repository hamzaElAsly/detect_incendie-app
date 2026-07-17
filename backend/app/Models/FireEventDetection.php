<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUlids; use Illuminate\Database\Eloquent\Model;
class FireEventDetection extends Model { use HasUlids; public $timestamps=false; protected $fillable=['class_name','confidence','x1','y1','x2','y2','frame_timestamp','metadata']; protected function casts():array{return ['metadata'=>'array'];} public function fireEvent(){return $this->belongsTo(FireEvent::class);} }
