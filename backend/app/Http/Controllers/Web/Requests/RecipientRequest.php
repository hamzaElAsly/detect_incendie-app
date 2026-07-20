<?php
namespace App\Http\Controllers\Web\Requests;
use App\Domain\Recipients\AlertRecipientValidator; use Illuminate\Foundation\Http\FormRequest;
class RecipientRequest extends FormRequest
{
 public function authorize():bool{$f=$this->attributes->get('factory');return $f&&$this->user()->hasPermission('recipients.manage',$f);}
 public function rules():array{return app(AlertRecipientValidator::class)->rules($this->attributes->get('factory'),$this->all());}
 protected function prepareForValidation():void{$this->merge(['channels'=>$this->input('channels',[]),'is_active'=>$this->boolean('is_active')]);}
}
