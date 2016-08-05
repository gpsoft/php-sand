<?php

function doit() {
    ini_set('memory_limit', -1);

    $preSrcImage = imagecreatefromjpeg('./org.jpg');
    $srcW = imagesx($preSrcImage);
    $srcH = imagesy($preSrcImage);

    $srcImage = imagecreate($srcW, $srcH);
    imagecolorallocate($srcImage, 255, 255, 255);
    imagecopyresampled($srcImage, $preSrcImage, 0, 0, 0, 0,
        $srcW, $srcH, $srcW, $srcH);
    $dstImage = imagecreate(284, 384);
    imagecolorallocate($dstImage, 255, 255, 255);

    $left = 0;
    $top = 0;
    $width = 284;
    /* $width = 142; */
    /* $width = 568; */
    $angle = 10;

    $angle = ($angle * -1 + 360) % 360;
    $editZoom = $width/$srcW;

    list($dstX, $dstY, $dstW, $dstH,
        $cropX, $cropY, $cropW, $cropH) =
        calcCopyParam(284, 384, $srcW, $srcH, $left, $top, $editZoom, $angle);

    $rotOffX = 0;
    $rotOffY = 0;
    if ( $angle > 0 ) {
        $srcImage = imagerotate($srcImage, $angle, 0);
        $rotOffX = (imagesx($srcImage) - $srcW) / 2 / $editZoom;
        $rotOffY = (imagesy($srcImage) - $srcH) / 2 / $editZoom;
    }

    imagecopyresampled($dstImage, $srcImage,
        $dstX, $dstY, $cropX + $rotOffX, $cropY + $rotOffY,
        $dstW, $dstH, $cropW, $cropH);
    imagejpeg($dstImage, './dest.jpg');
}

function calcCopyParam(
    $finalW, $finalH, $orgW, $orgH,
    $editLeft, $editTop, $editZoom, $editAngle) {

    $blnFitToWidth = true;
    if ( $orgW * $finalH / $finalW > $orgH ) $blnFitToWidth = false;

    $ratioF2TH = 1;

    $cropX = (int)(-1 * $editLeft / $editZoom);
    $cropY = (int)(-1 * $editTop / $editZoom);

    $cropW = (int)($finalW * $ratioF2TH / $editZoom);
    $cropH = (int)($finalH * $ratioF2TH / $editZoom);

    $dstX = 0;
    $dstY = 0;
    $dstW = $finalW;
    $dstH = $finalH;

    $res = [$dstX, $dstY, $dstW, $dstH, $cropX, $cropY, $cropW, $cropH];
    debug($res);

    if ( $cropX < 0 ) {
        $cropW += $cropX;
        $dstX -= (int)($cropX * $editZoom);
        $dstW += (int)($cropX * $editZoom);
        $cropX = 0;
    }
    if ( $cropY < 0 ) {
        $cropH += $cropY;
        $dstY -= (int)($cropY * $editZoom);
        $dstH += (int)($cropY * $editZoom);
        $cropY = 0;
    }

    $res = [$dstX, $dstY, $dstW, $dstH, $cropX, $cropY, $cropW, $cropH];
    debug($res);

    if ( $cropX + $cropW > $orgW ) {
        $delta = $cropX + $cropW - $orgW;
        $cropW -= $delta;
        $dstW -= (int)($delta * $editZoom);
    }
    if ( $cropY + $cropH > $orgH ) {
        $delta = $cropY + $cropH - $orgH;
        $cropH -= $delta;
        $dstH -= (int)($delta * $editZoom);
    }

    $res = [$dstX, $dstY, $dstW, $dstH, $cropX, $cropY, $cropW, $cropH];
    debug($res);

    return $res;
}

function debug($res) {
    echo "({$res[0]},{$res[1]}) {$res[2]}x{$res[3]}"
        ."←({$res[4]},{$res[5]}) {$res[6]}x{$res[7]}";
    echo '<br />';
}
