<?php

namespace App\Http\Controllers\Web;

use App\Domain\Audit\AuditService;
use App\Http\Controllers\Web\Requests\PasswordRequest;
use App\Http\Controllers\Web\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => request()->user()]);
    }

    public function update(ProfileRequest $r, AuditService $audit): RedirectResponse
    {
        $u = $r->user();
        $old = $u->only(['name', 'email', 'phone', 'avatar_path', 'timezone', 'preferences']);
        $emailChanged = $u->email !== $r->validated('email');
        $preferences = $r->validated('preferences', $u->preferences ?? []);
        if ($r->has('preferences')) {
            $preferences['compact_sidebar'] = $r->boolean('preferences.compact_sidebar');
            $preferences['email_notifications'] = $r->boolean('preferences.email_notifications');
        }$u->fill(['name' => $r->validated('name'), 'email' => mb_strtolower(trim($r->validated('email'))), 'phone' => $r->validated('phone'), 'timezone' => $r->validated('timezone', $u->timezone), 'preferences' => $preferences]);
        if ($r->hasFile('avatar')) {
            $oldAvatar = $u->avatar_path;
            $u->avatar_path = $r->file('avatar')->store('avatars', 'public');
            if ($oldAvatar) {
                Storage::disk('public')->delete($oldAvatar);
            }
        }if ($emailChanged) {
            $u->email_verified_at = null;
        }$u->save();
        $audit->record('profile.updated', $u, $old, $u->fresh()->only(['name', 'email', 'phone', 'avatar_path', 'timezone', 'preferences']), null, $u);
        if ($emailChanged) {
            $u->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', __('phase2.profile.updated_verify'));
        }

return back()->with('success', __('phase2.profile.updated'));
    }

    public function password(PasswordRequest $r): RedirectResponse
    {
        $r->user()->update(['password' => Hash::make($r->validated('password'))]);

        return back()->with('success','Mot de passe mis à jour.');
    }
}
