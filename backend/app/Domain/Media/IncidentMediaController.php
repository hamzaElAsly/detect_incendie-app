<?php

namespace App\Domain\Media;

use App\Models\FireEvent;
use App\Services\FactoryContext;

class IncidentMediaController
{
    public function __construct(private IncidentMediaService $media) {}

    public function store(IncidentMediaRequest $request, FactoryContext $context, FireEvent $event)
    {
        $factory = $context->get();
        abort_unless($event->factory_id === $factory->id && $request->user()->hasPermission('events.create', $factory), 403);
        $event = $this->media->store($event, $request->file('image'), $request->file('video'));

        return response()->json(['success' => true, 'message' => 'Incident media uploaded successfully', 'data' => ['event_id' => $event->id, 'has_image' => (bool) $event->image_path, 'has_video' => (bool) $event->video_path], 'meta' => (object) []]);
    }
}
