<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Photos\PhotoLibraryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPhotoVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $photoId) {}

    public function handle(PhotoLibraryService $photos): void
    {
        $photo = Photo::query()->find($this->photoId);

        if (! $photo) {
            return;
        }

        $photos->process($photo);
    }
}
