<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ReportSignatureImage
{
    public static function store(UploadedFile $file, string $directory = 'billing-signatures'): string
    {
        $image = self::imageFromUpload($file);

        if (! $image) {
            throw new RuntimeException('No se pudo procesar la firma.');
        }

        $signature = self::cropAndClean($image);
        $path = trim($directory, '/').'/firma-'.Str::uuid().'.png';

        ob_start();
        imagepng($signature, null, 9);
        $contents = ob_get_clean();

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('No se pudo guardar la firma.');
        }

        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private static function imageFromUpload(UploadedFile $file): mixed
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            return self::imageFromPdf($file->getRealPath());
        }

        $contents = file_get_contents($file->getRealPath());

        return is_string($contents) ? @imagecreatefromstring($contents) : false;
    }

    private static function imageFromPdf(string $path): mixed
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        $imagick = new \Imagick();
        $imagick->setResolution(180, 180);
        $imagick->readImage($path.'[0]');
        $imagick->setImageBackgroundColor('white');
        $flattened = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $flattened->setImageFormat('png');

        return @imagecreatefromstring($flattened->getImageBlob());
    }

    private static function cropAndClean(mixed $image): mixed
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $bounds = self::inkBounds($image, true) ?? self::inkBounds($image, false);

        if ($bounds === null) {
            return $image;
        }

        [$minX, $minY, $maxX, $maxY] = $bounds;
        $padding = 12;
        $minX = max(0, $minX - $padding);
        $minY = max(0, $minY - $padding);
        $maxX = min($width - 1, $maxX + $padding);
        $maxY = min($height - 1, $maxY + $padding);
        $cropWidth = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;
        $crop = imagecreatetruecolor($cropWidth, $cropHeight);

        imagealphablending($crop, false);
        imagesavealpha($crop, true);

        $transparent = imagecolorallocatealpha($crop, 255, 255, 255, 127);
        imagefilledrectangle($crop, 0, 0, $cropWidth, $cropHeight, $transparent);

        for ($y = 0; $y < $cropHeight; $y++) {
            for ($x = 0; $x < $cropWidth; $x++) {
                $rgba = imagecolorat($image, $minX + $x, $minY + $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $red = ($rgba >> 16) & 255;
                $green = ($rgba >> 8) & 255;
                $blue = $rgba & 255;

                if ($alpha > 100 || self::isBackground($red, $green, $blue)) {
                    imagesetpixel($crop, $x, $y, $transparent);

                    continue;
                }

                $color = imagecolorallocatealpha($crop, $red, $green, $blue, 0);
                imagesetpixel($crop, $x, $y, $color);
            }
        }

        return $crop;
    }

    private static function inkBounds(mixed $image, bool $preferColoredInk): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;
        $matches = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $red = ($rgba >> 16) & 255;
                $green = ($rgba >> 8) & 255;
                $blue = $rgba & 255;

                if ($alpha > 100 || ! self::isInk($red, $green, $blue, $preferColoredInk)) {
                    continue;
                }

                $matches++;
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        return $matches > 20 ? [$minX, $minY, $maxX, $maxY] : null;
    }

    private static function isInk(int $red, int $green, int $blue, bool $preferColoredInk): bool
    {
        if ($preferColoredInk) {
            return $blue > 70
                && $blue > $red + 10
                && $blue >= $green
                && $red < 220
                && $green < 230;
        }

        return ! self::isBackground($red, $green, $blue)
            && ($red + $green + $blue) < 660;
    }

    private static function isBackground(int $red, int $green, int $blue): bool
    {
        return $red > 232 && $green > 232 && $blue > 232;
    }
}
