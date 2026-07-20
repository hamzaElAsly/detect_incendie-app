<?php
namespace App\Http\Controllers\Web\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class WebSettingsRequest extends FormRequest
{
    public function authorize(): bool { $f=$this->attributes->get('factory'); return $f&&$this->user()->hasPermission('settings.update',$f); }
    public function rules(): array { return ['timezone'=>['required','timezone'],'default_fire_threshold'=>['required','numeric','between:0,1'],'default_smoke_threshold'=>['required','numeric','between:0,1'],'alert_cooldown'=>['required','integer','min:0','max:86400'],'required_positive_frames'=>['required','integer','min:1','max:1000'],'notification_channels'=>['nullable','array'],'notification_channels.*'=>[Rule::in(['email','sms','push','webhook'])],'retention_days'=>['required','integer','min:1','max:3650']]; }
}
