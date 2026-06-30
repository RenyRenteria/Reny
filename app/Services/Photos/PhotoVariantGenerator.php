<?php

namespace App\Services\Photos;

use App\Enums\PhotoVisibility;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PhotoVariantGenerator
{
    /**
     * @return array{
     *     public_disk: string,
     *     public_path: string,
     *     blurred_disk: string,
     *     blurred_path: string,
     *     thumbnail_disk: string,
     *     thumbnail_path: string,
     *     width: int,
     *     height: int
     * }
     */
    public function generate(Photo $photo): array
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Photo processing requires the GD extension.');
        }

        if ((! $photo->original_disk || ! $photo->original_path) && ! $this->legacySourcePath($photo)) {
            throw new RuntimeException('Photo original file is missing.');
        }

        $sourcePath = $this->sourcePath($photo);
        $size = getimagesize($sourcePath);

        if ($size === false) {
            throw new RuntimeException('Photo original file is not a supported image.');
        }

        [$width, $height] = $size;
        $mime = (string) ($size['mime'] ?? '');
        $source = $this->loadImage($sourcePath, $mime);
        $uuid = (string) Str::uuid();
        $month = now()->format('Y/m');
        $publicDisk = $photo->visibility === PhotoVisibility::Public
            ? config('photos.public_disk', 'public')
            : config('photos.private_disk', 'local');
        $blurredDisk = config('photos.public_disk', 'public');
        $thumbnailDisk = $photo->visibility === PhotoVisibility::Public
            ? config('photos.public_disk', 'public')
            : config('photos.public_disk', 'public');

        $optimized = $this->resize($source, $width, $height, (int) config('photos.variants.optimized_max_width', 1800));
        $blurred = $this->blurredVariant($source, $width, $height);
        $thumbnail = $photo->visibility === PhotoVisibility::MemberOnly
            ? $this->resize($blurred, imagesx($blurred), imagesy($blurred), (int) config('photos.variants.thumbnail_max_width', 480))
            : $this->resize($source, $width, $height, (int) config('photos.variants.thumbnail_max_width', 480));

        $optimizedPath = "photos/optimized/{$month}/{$uuid}.jpg";
        $blurredPath = "photos/blurred/{$month}/{$uuid}.jpg";
        $thumbnailPath = "photos/thumbnails/{$month}/{$uuid}.jpg";

        $this->storeJpeg($optimized, $publicDisk, $optimizedPath, (int) config('photos.variants.jpeg_quality', 82));
        $this->storeJpeg($blurred, $blurredDisk, $blurredPath, (int) config('photos.variants.blur_quality', 62));
        $this->storeJpeg($thumbnail, $thumbnailDisk, $thumbnailPath, (int) config('photos.variants.thumbnail_quality', 76));

        imagedestroy($source);
        imagedestroy($optimized);
        imagedestroy($blurred);
        imagedestroy($thumbnail);

        return [
            'public_disk' => $publicDisk,
            'public_path' => $optimizedPath,
            'blurred_disk' => $blurredDisk,
            'blurred_path' => $blurredPath,
            'thumbnail_disk' => $thumbnailDisk,
            'thumbnail_path' => $thumbnailPath,
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }

    private function sourcePath(Photo $photo): string
    {
        if (! $photo->original_disk || ! $photo->original_path) {
            if ($legacy = $this->legacySourcePath($photo)) {
                return $legacy;
            }

            throw new RuntimeException('Photo original file is missing.');
        }

        $disk = Storage::disk($photo->original_disk);

        try {
            $path = $disk->path($photo->original_path);
        } catch (\Throwable) {
            $path = '';
        }

        if ($path !== '' && is_file($path)) {
            return $path;
        }

        $stream = $disk->readStream($photo->original_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Photo original file is unavailable.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'reny-photo-');
        $target = fopen($temporary, 'wb');

        if (! is_resource($target)) {
            fclose($stream);
            throw new RuntimeException('Photo processing could not create a temporary file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $temporary;
    }

    private function legacySourcePath(Photo $photo): ?string
    {
        $legacy = data_get($photo->metadata ?? [], 'legacy_asset_path');

        if (! is_string($legacy) || $legacy === '') {
            return null;
        }

        $legacy = str_replace('\\', '/', $legacy);

        if (! str_starts_with($legacy, 'images/photos/') || str_contains($legacy, '..')) {
            return null;
        }

        $path = public_path($legacy);

        return is_file($path) ? $path : null;
    }

    private function loadImage(string $path, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Photo original file is not a supported image.');
        }

        return $image;
    }

    private function resize(\GdImage $source, int $width, int $height, int $maxWidth): \GdImage
    {
        $targetWidth = $width > $maxWidth ? $maxWidth : $width;
        $targetHeight = (int) max(1, round($height * ($targetWidth / max(1, $width))));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function blurredVariant(\GdImage $source, int $width, int $height): \GdImage
    {
        $downsampleWidth = (int) config('photos.variants.blur_downsample_width', 72);
        $blurWidth = (int) config('photos.variants.blur_max_width', 900);
        $small = $this->resize($source, $width, $height, $downsampleWidth);

        for ($i = 0; $i < (int) config('photos.variants.blur_passes', 10); $i++) {
            imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $blurred = $this->resize($small, imagesx($small), imagesy($small), $blurWidth);
        imagedestroy($small);

        return $blurred;
    }

    private function storeJpeg(\GdImage $image, string $disk, string $path, int $quality): void
    {
        ob_start();
        imagejpeg($image, null, max(1, min(100, $quality)));
        $contents = ob_get_clean();

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Photo variant generation failed.');
        }

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('Photo variant could not be stored.');
        }
    }
}
