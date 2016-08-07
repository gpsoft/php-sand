<?php

function doit() {
    $editParam = [
        'left'=>-20,
        'top'=>0,
        'zoom'=>0.8,
        'angle'=>80,
    ];

    $systemInfo = [
        'TH2SCREEN'=>0.5,
        'TH2FINAL'=>1.0,
        'FINALW'=>284,
        'FINALH'=>384,
        'THUMBW'=>284,
        'THUMBH'=>384,
    ];

    applyEditParam($editParam, $systemInfo, './orgh.jpg', './dest.jpg');
}

function getReadyOrgImage($path) {
    $preImage = imagecreatefromjpeg($path);
    $w = imagesx($preImage);
    $h = imagesy($preImage);

    $orgImage = imagecreateTrueColor($w, $h);
    $cidWhite = imagecolorallocate($orgImage, 255, 255, 255); //余白の色
    imagecopyresampled($orgImage, $preImage,
        0, 0, 0, 0,
        $w, $h, $w, $h);
    return [$orgImage, $w, $h, $cidWhite];
}

function getReadyFinalImage($si) {
    $dstImage = imagecreateTrueColor($si['FINALW'], $si['FINALH']);
    $cidWhite = imagecolorallocate($dstImage, 255, 255, 255); //余白の色
    imagefill($dstImage, 0, 0, $cidWhite);
    return $dstImage;
}

function calcRatioA2B($aw, $ah, $bw, $bh) {
    $w2h = $bh / $bw;
    if ( $aw <= $bw && $ah <= $bh ) return 1.0;
    if ( $ah < $aw * $w2h ) { // 幅フィット?
        return $bw / $aw;
    }
    return $bh / $ah;
}

function calcRatioOrg2Thumb($w, $h, $si) {
    return calcRatioA2B($w, $h, $si['THUMBW'], $si['THUMBH']);
}

function calcRatioOrg2Final($w, $h, $si) {
    return calcRatioA2B($w, $h, $si['FINALW'], $si['FINALH']);
}

function coordScreen2Org($ep, $si, $o2th) {
    $ep['left'] = $ep['left'] / $si['TH2SCREEN'] / $o2th;
    $ep['top'] = $ep['top'] / $si['TH2SCREEN'] / $o2th;
    return $ep;
}

function normalizeAngle($ep) {
    $ep['angle'] = ($ep['angle'] % 360 + 360) % 360;
    return $ep;
}

function calcWindowRect($ep, $si, $o2th) {
    return [
        'x'=> -1 * $ep['left'],
        'y'=> -1 * $ep['top'],
        'w'=> (int)($si['THUMBW'] / $o2th / $ep['zoom']),
        'h'=> (int)($si['THUMBH'] / $o2th / $ep['zoom']),
    ];
}

function updateWindowByRotation($win, $img, $orgW, $orgH) {
    $w = imagesx($img);
    $h = imagesy($img);
    $win['x'] += ($w - $orgW) / 2;
    $win['y'] += ($h - $orgH) / 2;
    return [$win, $w, $h];
}

function optimizeCopyParam($dst, $win, $si, $zoom, $orgW, $orgH, $o2f) {
    // left
    $l = $win['x'];
    if ( $l < 0 ) {
        $win['w'] += $l;
        $dst['x'] -= (int)($l * $zoom * $o2f);
        $dst['w'] += (int)($l * $zoom * $o2f);
        $win['x'] = 0;
    }

    // top
    $t = $win['y'];
    if ( $t < 0 ) {
        $win['h'] += $t;
        $dst['y'] -= (int)($t * $zoom * $o2f);
        $dst['h'] += (int)($t * $zoom * $o2f);
        $win['y'] = 0;
    }

    // right
    $r = $win['x'] + $win['w'];
    if ( $r > $orgW ) {
        $win['w'] -= $r - $orgW;
        $dst['w'] -= (int)(($r - $orgW) * $zoom * $o2f);
    }

    // bottom
    $b = $win['y'] + $win['h'];
    if ( $b > $orgH ) {
        $win['h'] -= $b - $orgH;
        $dst['h'] -= (int)(($b - $orgH) * $zoom * $o2f);
    }

    return [$dst, $win];
}

function frameImage($img) {
    $w = imagesx($img);
    $h = imagesy($img);
    $frameImage = imagecreate($w, $h);
    $cidWhite = imagecolorallocate($frameImage, 255, 255, 255);
    $cidRed = imagecolorallocate($frameImage, 255, 0, 0);

    imagecolortransparent($frameImage, $cidRed);

    imagefilledellipse($frameImage, $w/2, $h/2, $w, $h, $cidRed);

    imagecopyresampled($img, $frameImage,
        0, 0, 0, 0,
        $w, $h, $w, $h);
}

function applyEditParam($editParam, $systemInfo, $orgPath, $destPath) {

    ini_set('memory_limit', -1);

    list($orgImage, $orgW, $orgH, $cidWhite) = getReadyOrgImage($orgPath);
    $ratioOrg2Th = calcRatioOrg2Thumb($orgW, $orgH, $systemInfo);

    $editParam = coordScreen2Org($editParam, $systemInfo, $ratioOrg2Th);
    $editParam = normalizeAngle($editParam);
    d($editParam);

    $dest = ['x'=>0, 'y'=>0, 'w'=>$systemInfo['FINALW'], 'h'=>$systemInfo['FINALH']];
    $window = calcWindowRect($editParam, $systemInfo, $ratioOrg2Th);

    if ( $editParam['angle'] > 0 ) {
        $orgImage = imagerotate($orgImage, $editParam['angle'], $cidWhite);
        list($window, $orgW, $orgH) =
            updateWindowByRotation($window, $orgImage, $orgW, $orgH);
    }

    $ratioOrg2Fin = calcRatioOrg2Final($orgW, $orgH, $systemInfo);
    list($dest, $window) =
        optimizeCopyParam($dest, $window,
            $systemInfo, $editParam['zoom'], $orgW, $orgH, $ratioOrg2Fin);

    $dstImage = getReadyFinalImage($systemInfo);

    imagecopyresampled($dstImage, $orgImage,
        $dest['x'], $dest['y'],
        $window['x'], $window['y'],
        $dest['w'], $dest['h'],
        $window['w'], $window['h']
    );

    /* frameImage($dstImage); */

    imagejpeg($dstImage, $destPath);
}

function d($t) {
    print_r($t);
    echo '<br/>'.PHP_EOL;
}
