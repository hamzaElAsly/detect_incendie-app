<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $r): View|RedirectResponse
    {
        return $r->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $r): RedirectResponse
    {
        if (! $r->user()->hasVerifiedEmail()) {
            $r->fulfill();
            event(new Verified($r->user()));
        }

return redirect()->route('dashboard')->with('success', 'Adresse e-mail vérifiée.');
    }

    public function send(Request $r): RedirectResponse
    {
        $r->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Lien de vérification envoyé.');
    }
}
