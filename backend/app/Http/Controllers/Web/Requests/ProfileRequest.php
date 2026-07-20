<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class ProfileRequest extends FormRequest
{
 public function authorize():bool{return true;}
 public function rules():array{return ['name'=>['required','string','max:255'],'email'=>['required','email',Rule::unique('users')->ignore($this->user())],'phone'=>['nullable','string','max:40']];}
}
