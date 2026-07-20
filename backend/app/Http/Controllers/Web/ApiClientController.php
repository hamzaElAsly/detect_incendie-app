<?php
namespace App\Http\Controllers\Web;
use App\Domain\ApiClients\ApiClientService; use App\Http\Controllers\Controller; use App\Http\Controllers\Web\Requests\ApiClientRequest; use App\Models\ApiClient; use App\Services\FactoryContext; use Illuminate\Http\RedirectResponse; use Illuminate\View\View;
class ApiClientController extends Controller
{
 public function index(FactoryContext $c):View{$f=$c->get();abort_unless(request()->user()->hasPermission('api_clients.view',$f),403);$clients=ApiClient::forFactory($f)->latest()->paginate(20);return view('api-clients.index',compact('clients'));}
 public function create(FactoryContext $c):View{$f=$c->get();abort_unless(request()->user()->hasPermission('api_clients.manage',$f),403);return view('api-clients.form');}
 public function store(ApiClientRequest $r,FactoryContext $c,ApiClientService $s):RedirectResponse{$credentials=$s->create($c->get(),$r->attributesData(),$r->user());return redirect()->route('api-clients.show',$credentials->client)->with('api_secret',$credentials->secret)->with('success','Client API créé. Copiez le secret maintenant.');}
 public function show(ApiClient $api_client,FactoryContext $c):View{$this->guard($api_client,$c,'api_clients.view');return view('api-clients.show',['client'=>$api_client]);}
 public function toggle(ApiClient $api_client,FactoryContext $c,ApiClientService $s):RedirectResponse{$this->guard($api_client,$c,'api_clients.manage');$s->setStatus($api_client,$api_client->status==='active'?'inactive':'active',request()->user());return back()->with('success','Statut mis à jour.');}
 public function regenerate(ApiClient $api_client,FactoryContext $c,ApiClientService $s):RedirectResponse{$this->guard($api_client,$c,'api_clients.manage');$credentials=$s->regenerateSecret($api_client,request()->user());return back()->with('api_secret',$credentials->secret)->with('success','Secret régénéré. Copiez-le maintenant.');}
 public function revoke(ApiClient $api_client,FactoryContext $c,ApiClientService $s):RedirectResponse{$this->guard($api_client,$c,'api_clients.manage');$s->setStatus($api_client,'revoked',request()->user());return back()->with('success','Client révoqué définitivement.');}
 private function guard(ApiClient $x,FactoryContext $c,string $p):void{$f=$c->get();abort_unless($x->factory_id===$f->id&&request()->user()->hasPermission($p,$f),403);}
}
