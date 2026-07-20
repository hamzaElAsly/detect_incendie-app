<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\FactoryRequest; use App\Models\Factory; use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\View\View;
class FactoryController extends Controller
{
 public function index(Request $r):View{$this->authorize('viewAny',Factory::class);$factories=Factory::withCount(['users','zones','cameras','fireEvents'])->with('subscriptions.plan')->when($r->q,fn($q,$v)=>$q->where(fn($x)=>$x->where('name','like',"%$v%")->orWhere('reference','like',"%$v%")))->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest()->paginate(15)->withQueryString();return view('factories.index',compact('factories'));}
 public function create():View{$this->authorize('create',Factory::class);return view('factories.form',['factory'=>new Factory]);}
 public function store(FactoryRequest $r):RedirectResponse{$factory=DB::transaction(fn()=>Factory::create([...$r->validated(),'is_active'=>$r->boolean('is_active')]));return redirect()->route('factories.show',$factory)->with('success','Usine créée.');}
 public function show(Factory $factory):View{$this->authorize('view',$factory);$factory->loadCount(['users','zones','cameras','fireEvents'])->load(['users'=>fn($q)=>$q->limit(8),'subscriptions.plan']);$recentEvents=$factory->fireEvents()->with('camera')->latest('detected_at')->limit(6)->get();return view('factories.show',compact('factory','recentEvents'));}
 public function edit(Factory $factory):View{$this->authorize('update',$factory);return view('factories.form',compact('factory'));}
 public function update(FactoryRequest $r,Factory $factory):RedirectResponse{DB::transaction(fn()=> $factory->update([...$r->validated(),'is_active'=>$r->boolean('is_active')]));return redirect()->route('factories.show',$factory)->with('success','Usine mise à jour.');}
 public function toggle(Factory $factory):RedirectResponse{$this->authorize('update',$factory);DB::transaction(fn()=> $factory->update(['is_active'=>!$factory->is_active,'status'=>$factory->is_active?'inactive':'active']));return back()->with('success','Statut de l’usine mis à jour.');}
 public function destroy(Factory $factory):RedirectResponse{$this->authorize('delete',$factory);DB::transaction(fn()=> $factory->delete());return redirect()->route('factories.index')->with('success','Usine archivée.');}
}
