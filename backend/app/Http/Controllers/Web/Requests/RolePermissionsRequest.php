<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class RolePermissionsRequest extends FormRequest
{
 public function authorize():bool{return $this->user()->can('update',$this->route('role'));}
 public function rules():array{return ['permissions'=>['nullable','array'],'permissions.*'=>['integer',Rule::exists('permissions','id')]];}
}
