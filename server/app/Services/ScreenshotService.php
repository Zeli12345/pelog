<?php

namespace App\Services;

use App\Enums\ScreenshotFormat;
use App\Models\Screenshot;
use App\Models\UsageSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScreenshotService
{
    public const THUMB_WIDTH = 320;

    public const THUMB_QUALITY = 75;

    public function store(UploadedFile $file, UsageSession $session, ?string $screenshotUuid = null): Screenshot
    {
        $mime = (string) $file->getMimeType();
        $format = $mime === 'image/webp' ? ScreenshotFormat::Webp : ScreenshotFormat::Jpeg;
        $extension = $format === ScreenshotFormat::Webp ? 'webp' : 'jpg';

        // Nama berkas unik per screenshot: sesi boleh punya banyak screenshot.
        $suffix = $screenshotUuid !== null && $screenshotUuid !== ''
            ? $screenshotUuid
            : (string) str()->uuid();
        $base = $session->session_uuid.'-'.$suffix;

        $directory = 'screenshots/'.now()->format('Y/m');
        $filename = $base.'.'.$extension;

        $path = $file->storeAs($directory, $filename, 'local');

        $absolutePath = Storage::disk('local')->path($path);
        $thumbPath = $this->makeThumbnail($absolutePath, $directory, $base);

        return Screenshot::query()->create([
            'screenshot_uuid' => (string) str()->uuid(),
            'usage_session_id' => $session->id,
            'format' => $format,
            'path' => $path,
            'thumb_path' => $thumbPath,
            'size_bytes' => (int) (filesize($absolutePath) ?: 0),
            'captured_at' => now(),
            'captured_at_client' => null,
        ]);
    }

    private function makeThumbnail(string $absolutePath, string $directory, string $base): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $info = @getimagesize($absolutePath);

            if ($info === false) {
                return null;
            }

            $source = match ($info[2]) {
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
                IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
                default => false,
            };

            if ($source === false) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);

            $thumbWidth = min(self::THUMB_WIDTH, $width);
            $thumbHeight = (int) max(1, round($height * $thumbWidth / max(1, $width)));

            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            $thumbRelative = $directory.'/'.$base.'-thumb.jpg';
            $thumbAbsolute = Storage::disk('local')->path($thumbRelative);

            imagejpeg($thumb, $thumbAbsolute, self::THUMB_QUALITY);

            imagedestroy($thumb);
            imagedestroy($source);

            return $thumbRelative;
        } catch (Throwable) {
            return null;
        }
    }
}
