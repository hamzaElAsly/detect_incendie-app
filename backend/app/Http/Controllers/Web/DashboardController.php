<?php

namespace App\Http\Controllers\Web;

use App\Domain\Dashboard\DashboardService;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Services\FactoryContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FactoryContext $context, DashboardService $dashboard): View
    {
        $factory = $context->get();
        $user = request()->user();
        abort_unless($user->hasPermission('factories.view', $factory), 403);
        $summary = $dashboard->summary($factory);
        $metrics = $dashboard->metrics($factory);
        $eventsChart = collect($dashboard->eventsChart($factory, 7));
        $cameraStatus = $dashboard->cameraStatus($factory);
        $recentEvents = $dashboard->recentEvents($factory, 8);
        $criticalAlerts = Alert::forFactory($factory)->where('severity', 'critical')->whereIn('status', ['pending', 'sent'])->latest()->limit(5)->get();
        $recentActivity = AuditLog::forFactory($factory)->with('user:id,name')->latest()->limit(6)->get();
        $liveCameras = Camera::forFactory($factory)->with('zone:id,name')->with(['fireEvents' => fn ($query) => $query->latest('detected_at')->limit(1)])->orderByRaw("CASE WHEN status = 'online' THEN 0 ELSE 1 END")->limit(8)->get();
        $zones = $factory->zones()->withCount(['cameras', 'fireEvents'])->orderBy('name')->limit(12)->get();

        return view('dashboard.index', compact('factory', 'summary', 'metrics', 'eventsChart', 'cameraStatus', 'recentEvents', 'criticalAlerts', 'recentActivity', 'liveCameras', 'zones'));
    }
}
