<?php

namespace App\Domain\Dashboard;

use App\Models\Camera;
use App\Models\Factory;
use App\Models\FireEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(Factory $factory): array
    {
        $today = Carbon::now($factory->timezone ?: 'UTC')->startOfDay()->utc();
        $c = Camera::forFactory($factory)->selectRaw('COUNT(*) total_cameras')->selectRaw("SUM(CASE WHEN status='online' THEN 1 ELSE 0 END) online_cameras")->selectRaw("SUM(CASE WHEN status<>'online' THEN 1 ELSE 0 END) offline_cameras")->first();
        $e = FireEvent::forFactory($factory)->selectRaw('SUM(CASE WHEN detected_at >= ? THEN 1 ELSE 0 END) events_today', [$today])->selectRaw("SUM(CASE WHEN detected_at >= ? AND type='fire' THEN 1 ELSE 0 END) fire_events_today", [$today])->selectRaw("SUM(CASE WHEN detected_at >= ? AND type='smoke' THEN 1 ELSE 0 END) smoke_events_today", [$today])->selectRaw("SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) confirmed_events")->selectRaw("SUM(CASE WHEN status='false_alarm' THEN 1 ELSE 0 END) false_alarms")->selectRaw("SUM(CASE WHEN status IN ('detected','confirmed') THEN 1 ELSE 0 END) unresolved_events")->selectRaw('AVG(CASE WHEN confirmed_at IS NOT NULL THEN '.(DB::getDriverName() === 'sqlite' ? '(julianday(confirmed_at)-julianday(detected_at))*86400' : 'TIMESTAMPDIFF(SECOND, detected_at, confirmed_at)').' END) average_response_time')->first();

        return array_merge($c->toArray(), $e->toArray());
    }

    public function eventsChart(Factory $factory, int $days = 30): array
    {
        return FireEvent::forFactory($factory)->where('detected_at', '>=', now()->subDays($days))->selectRaw('DATE(detected_at) day, COUNT(*) total')->groupByRaw('DATE(detected_at)')->orderBy('day')->get()->toArray();
    }

    public function cameraStatus(Factory $factory): array
    {
        return Camera::forFactory($factory)->select('status')->selectRaw('COUNT(*) total')->groupBy('status')->pluck('total', 'status')->toArray();
    }

    public function recentEvents(Factory $factory, int $limit = 10)
    {
        return FireEvent::forFactory($factory)->with(['camera:id,name,reference', 'zone:id,name'])->latest('detected_at')->limit(min($limit, 50))->get();
    }

    public function metrics(Factory $factory): array
    {
        $base = FireEvent::forFactory($factory);
        $byZone = (clone $base)->leftJoin('zones', 'zones.id', '=', 'fire_events.zone_id')->selectRaw("COALESCE(zones.name,'Unassigned') label, COUNT(*) total")->groupBy('zones.name')->pluck('total', 'label');
        $bySeverity = (clone $base)->selectRaw('severity, COUNT(*) total')->groupBy('severity')->pluck('total', 'severity');
        $uptime = Camera::forFactory($factory)->selectRaw("AVG(CASE WHEN status='online' THEN 100 ELSE 0 END) value")->value('value') ?? 0;

        return ['camera_uptime' => (float) $uptime, 'events_by_zone' => $byZone, 'events_by_severity' => $bySeverity];
    }
}
