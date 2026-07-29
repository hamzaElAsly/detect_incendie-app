<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\FireEvent;
use App\Services\FactoryContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $r, FactoryContext $c): View
    {
        $f = $c->get();
        abort_unless($r->user()->hasPermission('reports.view', $f), 403);
        $query = $this->query($r, $f->id);
        $events = (clone $query)->with(['camera:id,name', 'zone:id,name'])->latest('detected_at')->paginate(25)->withQueryString();
        $cameras = Camera::forFactory($f)->orderBy('name')->get(['id', 'name']);
        $zones = $f->zones()->orderBy('name')->get(['id', 'name']);

        return view('reports.index', compact('events', 'cameras', 'zones'));
    }

    public function export(Request $r, FactoryContext $c): StreamedResponse
    {
        $f = $c->get();
        abort_unless($r->user()->hasPermission('reports.view', $f), 403);
        $query = $this->query($r, $f->id)->with(['camera:id,name', 'zone:id,name'])->latest('detected_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Caméra', 'Zone', 'Type', 'Sévérité', 'Statut', 'Confiance']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $e) {
                    fputcsv($out, array_map($this->escapeCsvFormula(...), [$e->detected_at, $e->camera?->name, $e->zone?->name, $e->type->value, $e->severity->value, $e->status->value, $e->confidence]));
                }
            });
            fclose($out);
        }, 'rapport-evenements-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $r, string $factoryId): Builder
    {
        return FireEvent::query()->where('factory_id', $factoryId)->when($r->zone_id, fn ($q, $v) => $q->where('zone_id', $v))->when($r->camera_id, fn ($q, $v) => $q->where('camera_id', $v))->when($r->type, fn ($q, $v) => $q->where('type', $v))->when($r->status, fn ($q, $v) => $q->where('status', $v))->when($r->severity, fn ($q, $v) => $q->where('severity', $v))->when($r->date_from, fn ($q, $v) => $q->whereDate('detected_at', '>=', $v))->when($r->date_to, fn ($q, $v) => $q->whereDate('detected_at', '<=', $v));
    }

    private function escapeCsvFormula(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
