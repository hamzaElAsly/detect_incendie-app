<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Requests\CameraRequest;
use App\Models\Camera;
use App\Services\FactoryContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CameraController extends Controller
{
    public function index(Request $request, FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless($request->user()->hasPermission('cameras.view', $factory), 403);
        $cameras = Camera::forFactory($factory)
            ->with('zone:id,name')
            ->when($request->q, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%$value%")->orWhere('reference', 'like', "%$value%")))
            ->when($request->status, fn ($query, $value) => $query->where('status', $value))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('cameras.index', compact('cameras'));
    }

    public function create(FactoryContext $context): View
    {
        $factory = $context->get();
        abort_unless(request()->user()->hasPermission('cameras.create', $factory), 403);

        return view('cameras.form', ['camera' => new Camera, 'zones' => $factory->zones()->orderBy('name')->get()]);
    }

    public function store(CameraRequest $request, FactoryContext $context): RedirectResponse
    {
        $factory = $context->get();
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(fn () => $factory->cameras()->create($data));

        return redirect()->route('cameras.index')->with('success', 'Camera created.');
    }

    public function edit(Camera $camera, FactoryContext $context): View
    {
        $this->guard($camera, $context, 'cameras.update');
        $zones = $context->get()->zones()->orderBy('name')->get();

        return view('cameras.form', compact('camera', 'zones'));
    }

    public function update(CameraRequest $request, Camera $camera, FactoryContext $context): RedirectResponse
    {
        $this->guard($camera, $context, 'cameras.update');
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        if (blank($data['stream_url'] ?? null)) {
            unset($data['stream_url']);
        }
        DB::transaction(fn () => $camera->update($data));

        return redirect()->route('cameras.index')->with('success', 'Camera updated.');
    }

    public function destroy(Camera $camera, FactoryContext $context): RedirectResponse
    {
        $this->guard($camera, $context, 'cameras.delete');
        abort_if($camera->fireEvents()->exists(), 422, 'This camera has events.');
        DB::transaction(fn () => $camera->delete());

        return back()->with('success', 'Camera deleted.');
    }

    private function guard(Camera $camera, FactoryContext $context, string $permission): void
    {
        $factory = $context->get();
        abort_unless($camera->factory_id === $factory->id && request()->user()->hasPermission($permission, $factory), 403);
    }
}
