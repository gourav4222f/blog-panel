<?php
// Generates image fixtures for tests: php tests/fixtures/make.php
$dir = __DIR__ . '/out';
@mkdir($dir, 0777, true);

// Large landscape photo-like JPEG (3200x2000) with gradient + shapes.
$im = imagecreatetruecolor(3200, 2000);
for ($y = 0; $y < 2000; $y += 4) {
    $c = imagecolorallocate($im, (int) (40 + $y / 12), (int) (90 + $y / 20), (int) (160 - $y / 25));
    imagefilledrectangle($im, 0, $y, 3199, $y + 3, $c);
}
imagefilledellipse($im, 2300, 600, 700, 700, imagecolorallocate($im, 250, 210, 120));
imagefilledrectangle($im, 0, 1500, 3199, 1999, imagecolorallocate($im, 30, 60, 40));
imagejpeg($im, $dir . '/beach-sunset.jpg', 95);

// Small JPEG with an EXIF Orientation=6 tag (needs 90° clockwise rotation): 300x200 stored.
$im = imagecreatetruecolor(300, 200);
imagefilledrectangle($im, 0, 0, 149, 199, imagecolorallocate($im, 220, 40, 40));
imagefilledrectangle($im, 150, 0, 299, 199, imagecolorallocate($im, 40, 40, 220));
ob_start();
imagejpeg($im, null, 90);
$jpeg = ob_get_clean();
// Minimal little-endian TIFF/EXIF block with one IFD entry: Orientation (0x0112) SHORT = 6.
$tiff = 'II' . pack('v', 42) . pack('V', 8) . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, 6) . "\0\0" . pack('V', 0);
$app1 = "Exif\0\0" . $tiff;
$segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
file_put_contents($dir . '/rotated-phone-photo.jpg', substr($jpeg, 0, 2) . $segment . substr($jpeg, 2));

// PNG with transparency.
$im = imagecreatetruecolor(800, 600);
imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagefilledellipse($im, 400, 300, 500, 400, imagecolorallocatealpha($im, 180, 120, 80, 20));
imagepng($im, $dir . '/logo-mark.png');

// Square avatar.
$im = imagecreatetruecolor(600, 600);
imagefill($im, 0, 0, imagecolorallocate($im, 200, 170, 140));
imagefilledellipse($im, 300, 240, 220, 220, imagecolorallocate($im, 120, 90, 70));
imagejpeg($im, $dir . '/author-photo.jpg', 90);

// Malicious upload disguised as an image.
file_put_contents($dir . '/shell.php.jpg', "<?php echo 'pwned'; ?>");
file_put_contents($dir . '/fake.jpg', "GIF89a<?php system(\$_GET['c']); ?>");
echo "Fixtures written to $dir\n";
