<?php
$width = 800;
$height = 600;
$img = imagecreatetruecolor($width, $height);

// Colors
$bg = imagecolorallocate($img, 255, 255, 255);
$gold = imagecolorallocate($img, 197, 160, 89);
$green = imagecolorallocate($img, 0, 229, 153);
$dark = imagecolorallocate($img, 30, 41, 59);
$grey = imagecolorallocate($img, 100, 116, 139);

imagefill($img, 0, 0, $bg);

// Borders
imagesetthickness($img, 10);
imagerectangle($img, 20, 20, $width - 20, $height - 20, $gold);
imagesetthickness($img, 2);
imagerectangle($img, 30, 30, $width - 30, $height - 30, $gold);

// Text
$font = 5; // Built-in font
$title = "CERTIFICATE OF COMPLETION";
$tw = strlen($title) * imagefontwidth($font);
imagestring($img, $font, ($width - $tw) / 2, 80, $title, $dark);

$sub = "This is to certify that";
$sw = strlen($sub) * imagefontwidth($font);
imagestring($img, $font, ($width - $sw) / 2, 180, $sub, $grey);

$name = "STUDENT NAME";
$nw = strlen($name) * imagefontwidth($font);
imagestring($img, $font, ($width - $nw) / 2, 220, $name, $green);

$msg = "has successfully completed the course";
$mw = strlen($msg) * imagefontwidth($font);
imagestring($img, $font, ($width - $mw) / 2, 300, $msg, $grey);

$course = "\"Advanced Web Development\"";
$cw = strlen($course) * imagefontwidth($font);
imagestring($img, $font, ($width - $cw) / 2, 340, $course, $dark);

header("Content-Type: image/png");
imagepng($img);
imagedestroy($img);
?>
