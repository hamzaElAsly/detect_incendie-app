<?php
namespace App\Domain\Audit;
use App\Models\AuditLog; use App\Models\Factory; use Illuminate\Http\Request;
class AuditLogController { public function index(Request $request,Factory $factory){$logs=AuditLog::forFactory($factory)->with('user:id,name,email')->when($request->filled('action'),fn($q)=>$q->where('action',$request->string('action')))->latest('created_at')->paginate(min($request->integer('per_page',20),100));return response()->json(['success'=>true,'message'=>'Audit logs retrieved successfully','data'=>$logs->items(),'meta'=>['current_page'=>$logs->currentPage(),'last_page'=>$logs->lastPage(),'total'=>$logs->total()]]);} }
