<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\UserRequest; use App\Models\User; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class UserController extends Controller
{
 public function index(Request $r):View{$this->authorize('viewAny',User::class);$users=User::with(['roles','factories'])->when($r->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('email','like',"%$v%")))->when($r->status==='active',fn($q)=>$q->where('is_active',true))->when($r->status==='inactive',fn($q)=>$q->where('is_active',false))->latest()->paginate(20)->withQueryString();return view('users.index',compact('users'));}
 public function show(User $user):View{$this->authorize('view',$user);$user->load(['roles.permissions','factories']);return view('users.show',compact('user'));}
 public function edit(User $user):View{$this->authorize('update',$user);return view('users.edit',compact('user'));}
 public function update(UserRequest $r,User $user):RedirectResponse{DB::transaction(fn()=> $user->update([...$r->validated(),'is_active'=>$r->boolean('is_active')]));return redirect()->route('users.show',$user)->with('success','Utilisateur mis à jour.');}
 public function destroy(User $user):RedirectResponse{$this->authorize('delete',$user);abort_if($user->factories()->wherePivot('role','factory_owner')->exists(),422,'Un propriétaire d’usine ne peut pas être supprimé directement.');DB::transaction(fn()=> $user->update(['is_active'=>false]));return back()->with('success','Utilisateur désactivé.');}
}
