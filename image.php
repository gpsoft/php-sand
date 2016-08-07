<?php

function doit() {
    $editParam = [
        'left'=>0,
        'top'=>0,
        'zoom'=>1.0,
        'angle'=>90,
    ];

    $systemInfo = [
        'TH2SCREEN'=>0.5,
        'TH2FINAL'=>1.0,
        'FINALW'=>284,
        'FINALH'=>384,
        'THUMBW'=>284,
        'THUMBH'=>384,
        'SCREENW'=>142,
        'SCREENH'=>192,
    ];

    editImage($editParam, $systemInfo, './orgh.jpg', './dest.jpg');
}

/**
 * 加工対象のオリジナル画像を準備
 */
function getReadyOrgImage($path) {
    // オリジナル画像をそのまま使うのでなく、
    // 同サイズで白背景のキャンバスにコピーして使う。
    // これなら回転により生じる余白が黒にならない。
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

/**
 * 最終画像用のキャンバスを準備
 */
function getReadyFinalImage($si) {
    $dstImage = imagecreateTrueColor($si['FINALW'], $si['FINALH']);
    $cidWhite = imagecolorallocate($dstImage, 255, 255, 255); //余白の色
    imagefill($dstImage, 0, 0, $cidWhite);
    return $dstImage;
}

/**
 * 画像Aを矩形Bへ、縦横比を維持したままフィットさせるときの
 * 倍率を求める。
 * 拡大はしない。
 */
function calcRatioA2B($aw, $ah, $bw, $bh) {
    $w2h = $bh / $bw;
    if ( $aw <= $bw && $ah <= $bh ) return 1.0;
    if ( $ah < $aw * $w2h ) { // 幅フィット?
        return $bw / $aw;
    }
    // 高さフィット
    return $bh / $ah;
}

/**
 * オリジナルを何倍したらサムネイルのサイズになるか
 */
function calcRatioOrg2Thumb($w, $h, $si) {
    return calcRatioA2B($w, $h, $si['THUMBW'], $si['THUMBH']);
}

/**
 * オリジナルを何倍したら最終画像のサイズになるか
 */
function calcRatioOrg2Final($w, $h, $si) {
    return calcRatioA2B($w, $h, $si['FINALW'], $si['FINALH']);
}

/**
 * 編集パラメータのleftとtopを
 * 画面上の座標系から、オロジナル画像上の座標系へ変換
 */
function screen2Org($screenVal, $si, $o2th) {
    return (int)($screenVal / $si['TH2SCREEN'] / $o2th);
}

/**
 * 編集パラメータのleftとtopを
 * 画面上の座標系から、オロジナル画像上の座標系へ変換
 */
function coordScreen2Org($ep, $si, $o2th) {
    $ep['left'] = screen2Org($ep['left'], $si, $o2th);
    $ep['top'] = screen2Org($ep['top'], $si, $o2th);
    return $ep;
}

/**
 * 角度を正規化
 *
 * 右回りの-720度や540度を、左回りの0〜359の範囲へ。
 */
function normalizeAngle($ep) {
    $ep['angle'] = (-1 * $ep['angle'] % 360 + 360) % 360;
    return $ep;
}

/**
 * 編集パラメータをもとに
 * ウィンドウ矩形(切り抜き範囲)を決める。
 * この時点では、回転は考慮しない。
 *
 *
 */
function calcWindowRect($ep, $si, $o2th) {
    return [
        'x'=> -1 * $ep['left'],
        'y'=> -1 * $ep['top'],
        'w'=> screen2Org($si['SCREENW'], $si, $o2th),
        'h'=> screen2Org($si['SCREENH'], $si, $o2th),
    ];
}

/**
 * 回転による、座標系の原点移動を考慮して、
 * ウィンドウ矩形を更新する。
 *
 * 回転は画像の中点を軸にして行われる。
 * その結果、画像のサイズが変わる。
 * 画像上での座標系は左上スミを原点とするので、
 * 相対的に原点が移動することになる。
 * よってウィンドウの位置が変わる。
 */
function updateWindowByRotation($win, $img, $orgW, $orgH) {
    $w = imagesx($img);
    $h = imagesy($img);
    $win['x'] += ($w - $orgW) / 2;
    $win['y'] += ($h - $orgH) / 2;
    return [$win, $w, $h];
}

/**
 * ウィンドウ矩形がオリジナル画像からはみ出さないよう、
 * ウィンドウ矩形(切り抜き範囲)とコピー先矩形を最適化する。
 *
 * オリジナル画像の外側をコピーしてしまうと、
 * コピー先の色が黒くなってしまう。
 */
function optimizeCopyParam($dst, $win, $si, $zoom, $orgW, $orgH, $o2f) {
    // left, top, right, bottomそれぞれについて、
    // オリジナル画像からはみでてないかチェック。
    // はみ出ている場合は、ウィンドウとコピー先矩形を小さくする。
    // ウィンドウ座標系とコピー先座標系では単位が異なる点に注意。
    //

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

/**
 * 画像を額縁に入れる
 */
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

function editImage($editParam, $systemInfo, $orgPath, $destPath) {

    ini_set('memory_limit', -1);

    // 加工対象画像をロードして
    // 編集パラメータを調整。
    list($orgImage, $orgW, $orgH, $cidWhite) = getReadyOrgImage($orgPath);
    $dstImage = getReadyFinalImage($systemInfo);
    $ratioOrg2Th = calcRatioOrg2Thumb($orgW, $orgH, $systemInfo);
    $ratioOrg2Fin = calcRatioOrg2Final($orgW, $orgH, $systemInfo);
    $editParam = coordScreen2Org($editParam, $systemInfo, $ratioOrg2Th);
    $editParam = normalizeAngle($editParam);

    // コピー先矩形とウィンドウ矩形(切り抜き範囲)。
    $dest = ['x'=>0, 'y'=>0, 'w'=>$systemInfo['FINALW'], 'h'=>$systemInfo['FINALH']];
    $window = calcWindowRect($editParam, $systemInfo, $ratioOrg2Th);

    // 回転
    if ( $editParam['angle'] > 0 ) {
        $orgImage = imagerotate($orgImage, $editParam['angle'], $cidWhite);
        list($window, $orgW, $orgH) =
            updateWindowByRotation($window, $orgImage, $orgW, $orgH);
    }

    // コピー先矩形とウィンドウ矩形を最適化
    list($dest, $window) =
        optimizeCopyParam($dest, $window,
            $systemInfo, $editParam['zoom'], $orgW, $orgH, $ratioOrg2Fin);

    // コピー
    imagecopyresampled($dstImage, $orgImage,
        $dest['x'], $dest['y'],
        $window['x'], $window['y'],
        $dest['w'], $dest['h'],
        $window['w'], $window['h']
    );

    // 額装
    frameImage($dstImage);

    imagejpeg($dstImage, $destPath);
}

function d($t) {
    print_r($t);
    echo '<br/>'.PHP_EOL;
}
