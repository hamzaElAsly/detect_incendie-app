<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rules\Password;
class PasswordRequest extends FormRequest
{
 public function authorize():bool{return true;}
 public function rules():array{return ['current_password'=>['required','current_password'],'password'=>['required','confirmed',Password::defaults()]];}
}
