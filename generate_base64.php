<?php
$im = imagecreatetruecolor(1,1);
$white = imagecolorallocate($im, 255,255,255);
imagefilledrectangle($im,0,0,1,1,$white);
ob_start();
imagejpeg($im);
$data = ob_get_clean();
echo base64_encode($data);
?>