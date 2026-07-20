<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\PasswordRequest; use App\Http\Controllers\Web\Requests\ProfileRequest; use Illuminate\Http\RedirectResponse; use Illuminate\Support\Facades\Hash; use Illuminate\View\View;
class ProfileController extends Controller
{
 public function edit():View{return view('profile.edit',['user'=>request()->user()]);}
 public function update(ProfileRequest $r):RedirectResponse{$u=$r->user();$emailChanged=$u->email!==$r->validated('email');$u->fill($r->validated());if($emailChanged)$u->email_verified_at=null;$u->save();if($emailChanged){$u->sendEmailVerificationNotification();return redirect()->route('verification.notice')->with('success','Profil mis à jour. Vérifiez votre nouvelle adresse.');}return back()->with('success','Profil mis à jour.');}
 public function password(PasswordRequest $r):RedirectResponse{$r->user()->update(['password'=>Hash::make($r->validated('password'))]);return back()->with('success','Mot de passe mis à jour.');}
}
