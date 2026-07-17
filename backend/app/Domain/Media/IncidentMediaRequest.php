<?php
namespace App\Domain\Media;
use Illuminate\Foundation\Http\FormRequest;
class IncidentMediaRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['image'=>['nullable','file','mimes:jpg,jpeg,png,webp','max:10240','required_without:video'],'video'=>['nullable','file','mimes:mp4,webm,mov','max:51200','required_without:image']];} }
