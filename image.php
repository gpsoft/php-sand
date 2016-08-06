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
    $top = 55;
    $width = 284;
    $width = 142;
    /* $width = 568; */
    $angle = 90;

    $angle = ($angle * -1 + 360) % 360;
    $editZoom = $width/$srcW;

    $rotOffX = 0;
    $rotOffY = 0;
    if ( $angle > 0 ) {
        $srcImage = imagerotate($srcImage, $angle, 0);
        $rotOffX = (int)((imagesx($srcImage) - $srcW) / 2);
        $rotOffY = (int)((imagesy($srcImage) - $srcH) / 2);
        $srcW = imagesx($srcImage);
        $srcH = imagesy($srcImage);
    }
    print_r(compact(
        'rotOffX', 'rotOffY',
        'srcW', 'srcH'
    ));

    list($dstX, $dstY, $dstW, $dstH,
        $cropX, $cropY, $cropW, $cropH) =
        calcCopyParam(284, 384, $srcW, $srcH,
            $left - $rotOffX, $top - $rotOffY, $editZoom, $angle);

    imagecopyresampled($dstImage, $srcImage,
        $dstX, $dstY, $cropX, $cropY,
        $dstW, $dstH, $cropW, $cropH);

    /* frameImage($dstImage, 284, 384); */

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

function frameImage($img, $w, $h) {
    $frameImage = imagecreate($w, $h);
    $cidWhite = imagecolorallocate($frameImage, 255, 255, 255);
    $cidRed = imagecolorallocate($frameImage, 255, 0, 0);
    imagecolortransparent($frameImage, $cidRed);
    imagefilledellipse($frameImage, $w/2, $h/2, $w, $h, $cidRed);
    imagecopyresampled($img, $frameImage, 0, 0, 0, 0,
        imagesx($img), imagesy($img), $w, $h);
}

function debug($res) {
    echo "({$res[0]},{$res[1]}) {$res[2]}x{$res[3]}"
        ."←({$res[4]},{$res[5]}) {$res[6]}x{$res[7]}";
    echo '<br />'.PHP_EOL;
}
