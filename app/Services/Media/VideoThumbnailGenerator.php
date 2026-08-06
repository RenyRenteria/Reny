<?php

namespace App\Services\Media;

use App\Enums\MediaAssetType;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class VideoThumbnailGenerator
{
    public function generate(MediaAsset $video): string
    {
        if ($video->type !== MediaAssetType::Video || ! $video->disk || ! $video->path) {
            throw new MediaUploadException('No se pudo generar la miniatura porque el video no es válido.');
        }

        try {
            $sourcePath = Storage::disk($video->disk)->path($video->path);
        } catch (Throwable $exception) {
            throw new MediaUploadException('No se pudo abrir el video para generar su miniatura.', previous: $exception);
        }

        if (! is_file($sourcePath)) {
            throw new MediaUploadException('No se pudo abrir el video para generar su miniatura.');
        }

        $thumbnailPath = tempnam(sys_get_temp_dir(), 'reny-video-thumbnail-');

        if (! is_string($thumbnailPath)) {
            throw new MediaUploadException('No se pudo preparar la miniatura del video.');
        }

        try {
            $process = new Process([
                (string) config('media.video_thumbnails.ffmpeg_binary', 'ffmpeg'),
                '-hide_banner',
                '-loglevel',
                'error',
                '-y',
                '-i',
                $sourcePath,
                '-vf',
                "thumbnail=30,scale='min(1280,iw)':-2",
                '-frames:v',
                '1',
                '-f',
                'image2',
                '-vcodec',
                'mjpeg',
                $thumbnailPath,
            ]);
            $process->setTimeout((float) config('media.video_thumbnails.timeout_seconds', 30));
            $process->run();

            if (! $process->isSuccessful() || ! is_file($thumbnailPath) || filesize($thumbnailPath) === 0) {
                throw new MediaUploadException('No se pudo extraer una imagen del video. Verifica que el archivo se pueda reproducir.');
            }

            return $thumbnailPath;
        } catch (MediaUploadException $exception) {
            $this->deleteTemporaryFile($thumbnailPath);

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteTemporaryFile($thumbnailPath);

            throw new MediaUploadException('No se pudo generar la miniatura del video.', previous: $exception);
        }
    }

    private function deleteTemporaryFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
