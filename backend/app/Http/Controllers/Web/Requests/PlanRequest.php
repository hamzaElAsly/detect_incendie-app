<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class PlanRequest extends FormRequest
{
 public function authorize():bool{return $this->user()->hasPermission('plans.manage');}
 public function rules():array{return ['name'=>['required','string','max:255'],'code'=>['required','alpha_dash','max:100',Rule::unique('plans')->ignore($this->route('plan'))],'max_cameras'=>['nullable','integer','min:1'],'max_users'=>['nullable','integer','min:1'],'features_text'=>['nullable','string','max:5000'],'is_active'=>['nullable','boolean']];}
 public function data():array{return ['name'=>$this->validated('name'),'code'=>$this->validated('code'),'max_cameras'=>$this->validated('max_cameras'),'max_users'=>$this->validated('max_users'),'features'=>collect(preg_split('/\r?\n/',(string)$this->validated('features_text')))->map(fn($x)=>trim($x))->filter()->values()->all(),'is_active'=>$this->boolean('is_active')];}
}
