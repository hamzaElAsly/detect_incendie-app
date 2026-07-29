<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\FactoryContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $r, FactoryContext $c): View
    {
        $f = $c->get();
        abort_unless($r->user()->hasPermission('audit_logs.view', $f), 403);
        $logs = AuditLog::forFactory($f)->with('user:id,name,email')->when($r->q, fn ($q, $v) => $q->where(fn ($x) => $x->where('action', 'like', "%$v%")->orWhere('entity_type', 'like', "%$v%")))->when($r->action, fn ($q, $v) => $q->where('action', $v))->latest()->paginate(25)->withQueryString();

        return view('audit-logs.index', compact('logs'));
    }
}
