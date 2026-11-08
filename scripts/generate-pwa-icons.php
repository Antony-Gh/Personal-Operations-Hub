<?php

// Zero-dependency PWA Icon Generator using PHP GD
$publicDir = __DIR__.'/../public';

function generateIcon(int $size, string $outputPath, bool $maskable = false): void
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    imagealphablending($img, true);

    // Color definitions
    // #4F46E5 -> R: 79, G: 70, B: 229
    $indigo = imagecolorallocate($img, 79, 70, 229);
    $white = imagecolorallocate($img, 255, 255, 255);
    $whiteAlpha = imagecolorallocatealpha($img, 255, 255, 255, 15); // ~90% opacity

    if ($maskable) {
        // Full bleed background for maskable icons (safe zone is inner 80%)
        imagefilledrectangle($img, 0, 0, $size, $size, $indigo);
    } else {
        // Rounded rectangle background
        $radius = (int) round($size * 0.22);
        imagefilledellipse($img, $radius, $radius, $radius * 2, $radius * 2, $indigo);
        imagefilledellipse($img, $size - $radius, $radius, $radius * 2, $radius * 2, $indigo);
        imagefilledellipse($img, $radius, $size - $radius, $radius * 2, $radius * 2, $indigo);
        imagefilledellipse($img, $size - $radius, $size - $radius, $radius * 2, $radius * 2, $indigo);
        imagefilledrectangle($img, $radius, 0, $size - $radius, $size, $indigo);
        imagefilledrectangle($img, 0, $radius, $size, $size - $radius, $indigo);
    }

    $cx = $size / 2;
    $cy = $size / 2;
    $scale = $size / 40.0;

    // Connecting lines (stroke width = 2 * scale)
    $strokeWidth = (int) max(2, round(2 * $scale));
    imagesetthickness($img, $strokeWidth);

    // M20 12.5V15 -> y from 12.5 to 15
    imageline($img, (int) round(20 * $scale), (int) round(12.5 * $scale), (int) round(20 * $scale), (int) round(15 * $scale), $white);
    // M20 25V27.5
    imageline($img, (int) round(20 * $scale), (int) round(25 * $scale), (int) round(20 * $scale), (int) round(27.5 * $scale), $white);
    // M12.5 20H15
    imageline($img, (int) round(12.5 * $scale), (int) round(20 * $scale), (int) round(15 * $scale), (int) round(20 * $scale), $white);
    // M25 20H27.5
    imageline($img, (int) round(25 * $scale), (int) round(20 * $scale), (int) round(27.5 * $scale), (int) round(20 * $scale), $white);

    // Central Node (r = 5 * scale)
    $cr = (int) round(5 * $scale);
    imagefilledellipse($img, (int) round($cx), (int) round($cy), $cr * 2, $cr * 2, $white);

    // Satellite Nodes (r = 2.5 * scale)
    $sr = (int) round(2.5 * $scale);
    imagefilledellipse($img, (int) round(20 * $scale), (int) round(10 * $scale), $sr * 2, $sr * 2, $whiteAlpha);
    imagefilledellipse($img, (int) round(20 * $scale), (int) round(30 * $scale), $sr * 2, $sr * 2, $whiteAlpha);
    imagefilledellipse($img, (int) round(10 * $scale), (int) round(20 * $scale), $sr * 2, $sr * 2, $whiteAlpha);
    imagefilledellipse($img, (int) round(30 * $scale), (int) round(20 * $scale), $sr * 2, $sr * 2, $whiteAlpha);

    imagepng($img, $outputPath);
    imagedestroy($img);
    echo "Generated {$outputPath} ({$size}x{$size})\n";
}

generateIcon(192, $publicDir.'/icon-192.png', false);
generateIcon(512, $publicDir.'/icon-512.png', false);
generateIcon(512, $publicDir.'/icon-maskable-512.png', true);
