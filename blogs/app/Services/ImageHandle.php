<?php

declare(strict_types=1);

namespace BlogFlow\Services;

/**
 * Wraps a GD or Imagick image resource with its dimensions.
 */
final class ImageHandle
{
    public function __construct(
        public readonly string $driver,
        public readonly mixed $image,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $alpha,
    ) {
    }

    public function copy(): self
    {
        if ($this->driver === 'imagick') {
            return new self('imagick', clone $this->image, $this->width, $this->height, $this->alpha);
        }
        $dst = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopy($dst, $this->image, 0, 0, 0, 0, $this->width, $this->height);
        return new self('gd', $dst, $this->width, $this->height, $this->alpha);
    }
}
