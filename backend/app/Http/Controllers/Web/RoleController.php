<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\RolePermissionsRequest; use App\Models\Permission; use App\Models\Role; use Illuminate\Http\RedirectResponse; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class RoleController extends Controller
{
 public function index():View{$this->authorize('viewAny',Role::class);$roles=Role::withCount(['users','permissions'])->orderByDesc('is_platform')->get();$permissions=Permission::orderBy('name')->get()->groupBy(fn($p)=>str($p->name)->before('.')->toString());return view('roles.index',compact('roles','permissions'));}
 public function show(Role $role):View{$this->authorize('view',$role);$role->load('permissions');$permissions=Permission::orderBy('name')->get()->groupBy(fn($p)=>str($p->name)->before('.')->toString());return view('roles.show',compact('role','permissions'));}
 public function edit(Role $role):View{$this->authorize('update',$role);$role->load('permissions');$permissions=Permission::orderBy('name')->get()->groupBy(fn($p)=>str($p->name)->before('.')->toString());return view('roles.edit',compact('role','permissions'));}
 public function update(RolePermissionsRequest $r,Role $role):RedirectResponse{DB::transaction(fn()=> $role->permissions()->sync($r->validated('permissions',[])));return redirect()->route('roles.show',$role)->with('success','Permissions mises à jour.');}
}
