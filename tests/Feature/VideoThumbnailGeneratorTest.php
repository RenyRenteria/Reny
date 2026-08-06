<?php

namespace Tests\Feature;

use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use App\Services\Media\VideoThumbnailGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VideoThumbnailGeneratorTest extends TestCase
{
    public function test_it_extracts_a_real_jpeg_frame_from_a_portrait_video(): void
    {
        Storage::fake('public');
        Storage::disk('public')->makeDirectory('videos');
        $videoPath = Storage::disk('public')->path('videos/portrait.mp4');
        $binary = (string) config('media.video_thumbnails.ffmpeg_binary', 'ffmpeg');
        $fixture = new Process([
            $binary,
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'testsrc2=size=390x844:rate=30:duration=1',
            '-c:v',
            'mpeg4',
            $videoPath,
        ]);
        $fixture->setTimeout(15);
        $fixture->mustRun();

        $video = (new MediaAsset)->forceFill([
            'type' => MediaAssetType::Video->value,
            'disk' => 'public',
            'path' => 'videos/portrait.mp4',
            'original_filename' => 'portrait.mp4',
        ]);
        $thumbnailPath = app(VideoThumbnailGenerator::class)->generate($video);

        try {
            $dimensions = getimagesize($thumbnailPath);

            $this->assertIsArray($dimensions);
            $this->assertSame('image/jpeg', $dimensions['mime'] ?? null);
            $this->assertSame(390, $dimensions[0]);
            $this->assertSame(844, $dimensions[1]);
            $this->assertGreaterThan(1000, filesize($thumbnailPath));
        } finally {
            if (is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }
        }
    }
}
