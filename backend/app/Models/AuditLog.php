<?php
namespace App\Models;
use App\Models\Concerns\BelongsToFactory; use Illuminate\Database\Eloquent\Concerns\HasUlids; use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model { use HasUlids,BelongsToFactory; public $timestamps=false; protected $guarded=['id']; protected function casts():array{return ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime'];} public function user(){return $this->belongsTo(User::class);} }
