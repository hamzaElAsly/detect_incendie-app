<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class UserRequest extends FormRequest
{
 public function authorize():bool{return $this->user()->can('update',$this->route('user'));}
 public function rules():array{$u=$this->route('user');return ['name'=>['required','string','max:255'],'email'=>['required','email',Rule::unique('users')->ignore($u)],'phone'=>['nullable','string','max:40'],'is_active'=>['nullable','boolean']];}
}
