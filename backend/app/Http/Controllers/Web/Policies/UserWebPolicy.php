<?php
namespace App\Http\Controllers\Web\Policies;
use App\Models\User;
class UserWebPolicy
{
 public function before(User $u):?bool{return $u->is_super_admin?true:null;}
 public function viewAny(User $u):bool{return $u->roles()->where('roles.is_platform',true)->exists()&&$u->hasPermission('users.view');}
 public function view(User $u,User $t):bool{return $this->viewAny($u)&&!$t->is_super_admin;}
 public function update(User $u,User $t):bool{return $u->hasPermission('users.update')&&!$t->is_super_admin;}
 public function delete(User $u,User $t):bool{return $u->hasPermission('users.remove')&&!$t->is_super_admin&&$u->id!==$t->id;}
}
