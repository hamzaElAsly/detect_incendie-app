<?php

namespace App\Http\Controllers\Web;

use App\Domain\Health\CameraStatusService;
use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Services\FactoryContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CameraHealthController extends Controller
{
    public function index(Request $r, FactoryContext $c, CameraStatusService $statuses): View
    {
        $f = $c->get();
        abort_unless($r->user()->hasPermission('cameras.view', $f), 403);
        $cameras = $f->cameras()->with(['zone', 'lastApiClient'])->when($r->camera_id, fn ($q, $v) => $q->whereKey($v))->when($r->zone_id, fn ($q, $v) => $q->where('zone_id', $v))->when($r->date_from, fn ($q, $v) => $q->whereDate('last_heartbeat_at', '>=', $v))->orderBy('name')->get()->filter(fn ($camera) => ! $r->status || $statuses->status($camera) === $r->status);

        return view('camera-health.index', compact('cameras', 'statuses'));
    }

    public function show(Camera $camera, FactoryContext $c): View
    {
        $f = $c->get();
        abort_unless($camera->factory_id === $f->id && request()->user()->hasPermission('cameras.view', $f), 403);
        $camera->load('lastApiClient');
        $logs = $camera->healthLogs()->latest('recorded_at')->paginate(50);
        $chart = $camera->healthLogs()->latest('recorded_at')->limit(30)->get()->reverse()->values();

        return view('camera-health.show',compact('camera','logs','chart'));
    }
}
