<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FactorySelectionController extends Controller
{
    public function __invoke(Request $request, Factory $factory): RedirectResponse
    {
        abort_unless($request->user()->canAccessFactory($factory), 403);
        $request->session()->put('factory_id', $factory->getKey());

        return back()->with('success', 'Usine active mise à jour.');
    }
}
