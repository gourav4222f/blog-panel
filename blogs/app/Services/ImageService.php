<?php

declare(strict_types=1);

namespace BlogFlow\Services;

/**
 * Low-level image operations. Uses Imagick when available, otherwise GD.
 * Capabilities are detected at runtime; unsupported formats are never claimed.
 */
final class ImageService
{
    public const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    private static ?array $caps = null;

    public static function capabilities(): array
    {
        if (self::$caps !== null) {
            return self::$caps;
        }
        $caps = ['driver' => 'none', 'gd' => extension_loaded('gd'), 'imagick' => false, 'read' => [], 'write' => [], 'exif' => function_exists('exif_read_data')];
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            try {
                $formats = array_map('strtoupper', \Imagick::queryFormats());
                $caps['imagick'] = true;
                $caps['driver'] = 'imagick';
                foreach (['JPEG' => 'jpg', 'PNG' => 'png', 'WEBP' => 'webp', 'AVIF' => 'avif', 'GIF' => 'gif'] as $f => $ext) {
                    if (in_array($f, $formats, true)) {
                        $caps['read'][] = $ext;
                        $caps['write'][] = $ext;
                    }
                }
            } catch (\Throwable) {
                $caps['imagick'] = false;
            }
        }
        if ($caps['driver'] === 'none' && $caps['gd']) {
            $info = gd_info();
            $caps['driver'] = 'gd';
            if (!empty($info['JPEG Support'])) {
                $caps['read'][] = 'jpg';
                $caps['write'][] = 'jpg';
            }
            if (!empty($info['PNG Support'])) {
                $caps['read'][] = 'png';
                $caps['write'][] = 'png';
            }
            if (!empty($info['WebP Support']) && function_exists('imagewebp')) {
                $caps['read'][] = 'webp';
                $caps['write'][] = 'webp';
            }
            if (!empty($info['AVIF Support']) && function_exists('imageavif') && function_exists('imagecreatefromavif')) {
                $caps['read'][] = 'avif';
                $caps['write'][] = 'avif';
            }
            if (!empty($info['GIF Read Support'])) {
                $caps['read'][] = 'gif';
            }
        }
        return self::$caps = $caps;
    }

    public static function canWrite(string $ext): bool
    {
        return in_array($ext, self::capabilities()['write'], true);
    }

    public static function canRead(string $ext): bool
    {
        return in_array($ext, self::capabilities()['read'], true);
    }

    /**
     * Validates that a file is a real, decodable image of an allowed type.
     *
     * @return array{mime: string, ext: string, width: int, height: int}
     * @throws \RuntimeException with a user-facing message
     */
    public static function inspect(string $file, int $maxPixels): array
    {
        if (!is_file($file) || filesize($file) === 0) {
            throw new \RuntimeException('The uploaded file is empty.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file);
        if (!isset(self::MIME_EXT[$mime])) {
            throw new \RuntimeException('Only JPEG, PNG, WebP, AVIF and GIF images are allowed.');
        }
        $ext = self::MIME_EXT[$mime];
        if (!self::canRead($ext)) {
            throw new \RuntimeException(strtoupper($ext) . ' images are not supported by this server’s image library.');
        }
        $size = @getimagesize($file);
        if ($size === false || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            throw new \RuntimeException('The file is not a valid image.');
        }
        if (($size['mime'] ?? $mime) !== $mime && !($mime === 'image/avif' && ($size['mime'] ?? '') === '')) {
            throw new \RuntimeException('The image type does not match its contents.');
        }
        if ($size[0] * $size[1] > $maxPixels) {
            throw new \RuntimeException(sprintf('The image is too large (%d × %d px). The limit is %d megapixels.', $size[0], $size[1], (int) ($maxPixels / 1_000_000)));
        }
        return ['mime' => $mime, 'ext' => $ext, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    public static function isAnimatedGif(string $file): bool
    {
        $raw = @file_get_contents($file, false, null, 0, 1024 * 1024 * 8);
        return $raw !== false && preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $raw) > 1;
    }

    /**
     * Loads an image, applies EXIF orientation and returns a driver handle.
     */
    public static function load(string $file, string $ext): ImageHandle
    {
        @ini_set('memory_limit', '512M');
        $caps = self::capabilities();
        if ($caps['driver'] === 'imagick') {
            $im = new \Imagick($file);
            if ($im->getNumberImages() > 1) {
                $im = $im->coalesceImages();
                $im->setIteratorIndex(0);
                $im = $im->getImage();
            }
            if (method_exists($im, 'autoOrient')) {
                $im->autoOrient();
            } else {
                self::imagickOrient($im);
            }
            $im->setImagePage(0, 0, 0, 0);
            return new ImageHandle('imagick', $im, $im->getImageWidth(), $im->getImageHeight(), $im->getImageAlphaChannel() !== 0);
        }

        $img = match ($ext) {
            'jpg' => @imagecreatefromjpeg($file),
            'png' => @imagecreatefrompng($file),
            'webp' => @imagecreatefromwebp($file),
            'avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($file) : false,
            'gif' => @imagecreatefromgif($file),
            default => false,
        };
        if (!$img instanceof \GdImage) {
            throw new \RuntimeException('The image could not be decoded. It may be corrupt.');
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        if ($ext === 'jpg' && $caps['exif']) {
            $exif = @exif_read_data($file);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $img = self::gdOrient($img, $orientation);
        }
        $alpha = in_array($ext, ['png', 'webp', 'avif', 'gif'], true) && self::gdHasAlpha($img);
        return new ImageHandle('gd', $img, imagesx($img), imagesy($img), $alpha);
    }

    private static function gdOrient(\GdImage $img, int $o): \GdImage
    {
        $rotated = match ($o) {
            2 => (function () use ($img) { imageflip($img, IMG_FLIP_HORIZONTAL); return $img; })(),
            3 => imagerotate($img, 180, 0),
            4 => (function () use ($img) { imageflip($img, IMG_FLIP_VERTICAL); return $img; })(),
            5 => (function () use ($img) { $r = imagerotate($img, -90, 0); imageflip($r, IMG_FLIP_HORIZONTAL); return $r; })(),
            6 => imagerotate($img, -90, 0),
            7 => (function () use ($img) { $r = imagerotate($img, 90, 0); imageflip($r, IMG_FLIP_HORIZONTAL); return $r; })(),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
        return $rotated instanceof \GdImage ? $rotated : $img;
    }

    private static function imagickOrient(\Imagick $im): void
    {
        switch ($im->getImageOrientation()) {
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $im->rotateImage('#000', 180);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $im->rotateImage('#000', 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $im->rotateImage('#000', -90);
                break;
        }
        $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    private static function gdHasAlpha(\GdImage $img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(max($w, $h) / 64));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Returns a resized copy no wider than $maxWidth. Never upscales. */
    public static function resize(ImageHandle $src, int $maxWidth): ImageHandle
    {
        if ($src->width <= $maxWidth) {
            return $src->copy();
        }
        $w = $maxWidth;
        $h = max(1, (int) round($src->height * ($w / $src->width)));
        if ($src->driver === 'imagick') {
            $im = clone $src->image;
            $im->resizeImage($w, $h, \Imagick::FILTER_LANCZOS, 1);
            return new ImageHandle('imagick', $im, $w, $h, $src->alpha);
        }
        $dst = imagecreatetruecolor($w, $h);
        if ($src->alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src->image, 0, 0, 0, 0, $w, $h, $src->width, $src->height);
        return new ImageHandle('gd', $dst, $w, $h, $src->alpha);
    }

    /**
     * Encodes the image to $file in the given format. Metadata is not preserved.
     */
    public static function save(ImageHandle $img, string $file, string $ext, array $q): bool
    {
        if (!self::canWrite($ext)) {
            return false;
        }
        if ($img->driver === 'imagick') {
            $im = clone $img->image;
            $im->stripImage();
            $format = ['jpg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WEBP', 'avif' => 'AVIF'][$ext] ?? null;
            if ($format === null) {
                return false;
            }
            if ($ext === 'jpg' && $img->alpha) {
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            }
            $im->setImageFormat($format);
            match ($ext) {
                'jpg' => [$im->setImageCompressionQuality($q['jpg']), $im->setInterlaceScheme(\Imagick::INTERLACE_PLANE), $im->setSamplingFactors(['2x2', '1x1', '1x1'])],
                'png' => $im->setOption('png:compression-level', (string) $q['png']),
                'webp' => [$im->setImageCompressionQuality($q['webp']), $im->setOption('webp:method', '4')],
                'avif' => $im->setImageCompressionQuality($q['avif']),
            };
            $ok = $im->writeImage($file);
            $im->clear();
            return (bool) $ok && is_file($file) && filesize($file) > 0;
        }

        $gd = $img->image;
        if ($ext === 'jpg' && $img->alpha) {
            $flat = imagecreatetruecolor($img->width, $img->height);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $gd, 0, 0, 0, 0, $img->width, $img->height);
            $gd = $flat;
        }
        if ($ext !== 'jpg') {
            imagesavealpha($gd, true);
        }
        $ok = match ($ext) {
            'jpg' => (function () use ($gd, $file, $q) {
                imageinterlace($gd, true);
                return imagejpeg($gd, $file, $q['jpg']);
            })(),
            'png' => imagepng($gd, $file, $q['png']),
            'webp' => imagewebp($gd, $file, $q['webp']),
            'avif' => function_exists('imageavif') ? imageavif($gd, $file, $q['avif'], 6) : false,
            default => false,
        };
        return (bool) $ok && is_file($file) && filesize($file) > 0;
    }
}
