<?php

namespace App\Domain\Media;

use App\Models\FireEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IncidentMediaService
{
    public function store(FireEvent $event, ?UploadedFile $image, ?UploadedFile $video): FireEvent
    {
        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $base = 'incidents/'.$event->factory_id.'/'.$event->id;
        $oldImage = $event->image_path;
        $oldVideo = $event->video_path;
        $newPaths = [];

        try {
            if ($image) {
                $newPaths['image_path'] = $image->storeAs($base, Str::ulid().'.'.$image->guessExtension(), $diskName);
            }
            if ($video) {
                $newPaths['video_path'] = $video->storeAs($base, Str::ulid().'.'.$video->guessExtension(), $diskName);
            }
            $event = DB::transaction(function () use ($event, $newPaths): FireEvent {
                $event->forceFill($newPaths)->save();

                return $event->refresh();
            });
        } catch (\Throwable $exception) {
            $disk->delete(array_values(array_filter($newPaths)));
            throw $exception;
        }

        if (isset($newPaths['image_path']) && $oldImage && $oldImage !== $newPaths['image_path']) {
            $disk->delete($oldImage);
        }
        if (isset($newPaths['video_path']) && $oldVideo && $oldVideo !== $newPaths['video_path']) {
            $disk->delete($oldVideo);
        }

        return $event;
    }

    public function url(string $path): string
    {
        $disk = Storage::disk(config('filesystems.default'));

        return method_exists($disk, 'temporaryUrl') ? $disk->temporaryUrl($path, now()->addMinutes(10)) : $disk->url($path);
    }
}
