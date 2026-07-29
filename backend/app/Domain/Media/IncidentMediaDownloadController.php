<?php

namespace App\Domain\Media;

use App\Models\FireEvent;
use App\Services\FactoryContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentMediaDownloadController
{
    public function __invoke(Request $request, FireEvent $event, string $kind, FactoryContext $context): StreamedResponse
    {
        $factory = $context->get();
        abort_unless($event->factory_id === $factory->id && $request->user()->hasPermission('events.view', $factory), 403);
        abort_unless(in_array($kind, ['image', 'video'], true), 404);

        $path = $kind === 'image' ? $event->image_path : $event->video_path;
        abort_unless($path && Storage::disk(config('filesystems.default'))->exists($path), 404);

        return Storage::disk(config('filesystems.default'))->response($path);
    }
}
