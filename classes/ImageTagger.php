<?php
// classes/ImageTagger.php

/**
 * Class ImageTagger
 *
 * Automatically generates tags for an image based on:
 *   - Orientation: horizontal or vertical
 *   - Resolution: 'hq' if > 1920×1080, otherwise 'sq'
 *   - Background color: one of redBg, orangeBg, yellowBg, greenBg, blueBg,
 *     whiteBg, blackBg, grayBg, or mixedBg
 */
class ImageTagger {
    /** Full HD threshold */
    private $fullhdWidth  = 1920;
    private $fullhdHeight = 1080;

    /**
     * Returns an array of automatic tags for the given image file.
     *
     * @param string $filePath Path to the image file
     * @return string[] Array of tags
     * @throws ImagickException
     */
    public function getTags(string $filePath): array {
        $imagick = new Imagick($filePath);
        $width  = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        // 1) Orientation
        $orientation = ($width >= $height) ? 'horizontal' : 'vertical';

        // 2) Resolution
        $resolutionTag = (
            $width * $height >
            $this->fullhdWidth * $this->fullhdHeight
        ) ? 'hq' : 'sq';

        // 3) Background color
        $bgTag = $this->detectBackground($imagick);

        $imagick->destroy();
        return [$orientation, $resolutionTag, $bgTag];
    }

    /**
     * Detects the dominant background color and returns the appropriate tag.
     *
     * @param Imagick $img
     * @return string
     * @throws ImagickException
     */
    private function detectBackground(Imagick $img): string {
        // Clone and scale down to 1×1 to get average color
        $clone = clone $img;
        $clone->resizeImage(1, 1, Imagick::FILTER_BOX, 1);
        $pixel = $clone->getImagePixelColor(20, 20);
        $clone->destroy();

        $rgba = $pixel->getColor();
        $r = $rgba['r'];
        $g = $rgba['g'];
        $b = $rgba['b'];

        list($h, $s, $v) = $this->rgbToHsv($r, $g, $b);

        // White / Black detection
        if ($v > 0.85) {
            return 'whiteBg';
        }
        if ($v < 0.05) {
            return 'blackBg';
        }
        // Gray detection
        if ($s < 0.1) {
            return 'grayBg';
        }
        // Hue-based color mapping
        if ($h < 15 || $h >= 345) {
            return 'redBg';
        }
        if ($h < 45) {
            return 'orangeBg';
        }
        if ($h < 65) {
            return 'yellowBg';
        }
        if ($h < 170) {
            return 'greenBg';
        }
        if ($h < 260) {
            return 'blueBg';
        }
        return 'mixedBg';
    }

    /**
     * Converts RGB (0–255) to HSV (H: 0–360, S: 0–1, V: 0–1)
     *
     * @param float $r
     * @param float $g
     * @param float $b
     * @return array [h, s, v]
     */
    private function rgbToHsv(float $r, float $g, float $b): array {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $d   = $max - $min;
        $v   = $max;
        if ($d < 1e-6) {
            return [0, 0, $v];
        }
        $s = $d / $max;
        switch ($max) {
            case $r:
                $h = 60 * ((($g - $b) / $d) % 6);
                break;
            case $g:
                $h = 60 * ((($b - $r) / $d) + 2);
                break;
            case $b:
                $h = 60 * ((($r - $g) / $d) + 4);
                break;
        }
        if ($h < 0) {
            $h += 360;
        }
        return [$h, $s, $v];
    }
}
