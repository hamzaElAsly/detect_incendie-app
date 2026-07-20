<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class MemberRequest extends FormRequest
{
 public function authorize():bool{$f=$this->attributes->get('factory');return $f&&$this->user()->hasPermission($this->route('member')?'users.update':'users.invite',$f);}
 public function rules():array{return ['name'=>[$this->route('member')?'sometimes':'required','string','max:255'],'email'=>[$this->route('member')?'sometimes':'required','email',Rule::unique('users')->ignore($this->route('member'))],'phone'=>['nullable','string','max:40'],'role'=>['required',Rule::exists('roles','name')->where('is_platform',false)],'is_active'=>['nullable','boolean']];}
}
