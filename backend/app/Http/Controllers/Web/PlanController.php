<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\PlanRequest; use App\Models\Plan; use Illuminate\Http\RedirectResponse; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class PlanController extends Controller
{
 public function index():View{abort_unless(request()->user()->hasPermission('plans.view'),403);$plans=Plan::withCount('subscriptions')->orderBy('name')->paginate(20);return view('plans.index',compact('plans'));}
 public function create():View{abort_unless(request()->user()->hasPermission('plans.manage'),403);return view('plans.form',['plan'=>new Plan]);}
 public function store(PlanRequest $r):RedirectResponse{$p=DB::transaction(fn()=>Plan::create($r->data()));return redirect()->route('plans.edit',$p)->with('success','Plan créé.');}
 public function edit(Plan $plan):View{abort_unless(request()->user()->hasPermission('plans.manage'),403);return view('plans.form',compact('plan'));}
 public function update(PlanRequest $r,Plan $plan):RedirectResponse{DB::transaction(fn()=> $plan->update($r->data()));return back()->with('success','Plan mis à jour.');}
 public function toggle(Plan $plan):RedirectResponse{abort_unless(request()->user()->hasPermission('plans.manage'),403);$plan->update(['is_active'=>!$plan->is_active]);return back()->with('success','Statut du plan mis à jour.');}
}
