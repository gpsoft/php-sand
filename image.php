<?php

const FINAL2SCREEN = 0.5;
const FINALW = 284;
const FINALH = 384;
const SCREENW = FINALW * FINAL2SCREEN;
const SCREENH = FINALH * FINAL2SCREEN;

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
function getReadyFinalImage() {
    $dstImage = imagecreateTrueColor(FINALW, FINALH);
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
    if ( $aw <= $bw && $ah <= $bh ) { // 縦横ともに十分小さい?
        return 1.0;  // 拡大はしない
    }
    if ( $ah < $aw * $w2h ) { // 幅フィット?
        return $bw / $aw;
    }
    // 高さフィット
    return $bh / $ah;
}

/**
 * オリジナルを何倍したら画面上のサイズになるか
 */
function calcRatioOrg2Screen($w, $h) {
    return calcRatioA2B($w, $h, SCREENW, SCREENH);
}

/**
 * 画面上の座標系から、オリジナル画像上の座標系へ変換
 */
function screen2Org($scrVal, $o2s) {
    return (int)($scrVal / $o2s);
}

/**
 * オリジナル画像上の座標系から最終画像上の座標系へ変換
 */
function org2Final($orgVal, $zoom, $o2f) {
    return (int)($orgVal * $zoom * $o2f);
}

/**
 * 編集パラメータのleftとtopを
 * 画面上の座標系から、オリジナル画像上の座標系へ変換
 */
function coordScreen2Org($ep, $o2s) {
    $ep['left'] = screen2Org($ep['left'], $o2s);
    $ep['top'] = screen2Org($ep['top'], $o2s);
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
function calcWindowRect($ep, $o2s) {
    return [
        'x'=> -1 * $ep['left'],
        'y'=> -1 * $ep['top'],
        'w'=> (int)(screen2Org(SCREENW, $o2s) / $ep['zoom']),
        'h'=> (int)(screen2Org(SCREENH, $o2s) / $ep['zoom']),
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
    // 回転後の新しいW&H
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
function optimizeCopyParam($dst, $win, $orgW, $orgH, $zoom, $o2f) {
    // left, top, right, bottomそれぞれについて、
    // オリジナル画像からはみでてないかチェック。
    // はみ出ている場合は、ウィンドウとコピー先矩形を小さくする。
    // ウィンドウ座標系とコピー先座標系では単位が異なる点に注意。

    // left
    $l = $win['x'];
    if ( $l < 0 ) {
        $win['w'] += $l;
        $dst['x'] -= org2Final($l, $zoom, $o2f);
        $dst['w'] += org2Final($l, $zoom, $o2f);
        $win['x'] = 0;
    }

    // top
    $t = $win['y'];
    if ( $t < 0 ) {
        $win['h'] += $t;
        $dst['y'] -= org2Final($t, $zoom, $o2f);
        $dst['h'] += org2Final($t, $zoom, $o2f);
        $win['y'] = 0;
    }

    // right
    $r = $win['x'] + $win['w'];
    if ( $r > $orgW ) {
        $win['w'] -= $r - $orgW;
        $dst['w'] -= org2Final(($r - $orgW), $zoom, $o2f);
    }

    // bottom
    $b = $win['y'] + $win['h'];
    if ( $b > $orgH ) {
        $win['h'] -= $b - $orgH;
        $dst['h'] -= org2Final(($b - $orgH), $zoom, $o2f);
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

function editImage($editParam, $orgPath, $destPath) {

    ini_set('memory_limit', -1);

    // 加工対象画像をロードして
    // 各種情報を整理
    list($orgImage, $orgW, $orgH, $cidWhite) = getReadyOrgImage($orgPath);
    $ratioOrg2Screen = calcRatioOrg2Screen($orgW, $orgH);
    $ratioOrg2Final = $ratioOrg2Screen / FINAL2SCREEN;
    $editParam = coordScreen2Org($editParam, $ratioOrg2Screen);
    $editParam = normalizeAngle($editParam);

    // コピー先矩形とウィンドウ矩形(切り抜き範囲)。
    $dest = ['x'=>0, 'y'=>0, 'w'=>FINALW, 'h'=>FINALH];
    $window = calcWindowRect($editParam, $ratioOrg2Screen);

    // 回転
    if ( $editParam['angle'] > 0 ) {
        $orgImage = imagerotate($orgImage, $editParam['angle'], $cidWhite);
        list($window, $orgW, $orgH) =
            updateWindowByRotation($window, $orgImage, $orgW, $orgH);
        // 回転したことによりオリジナル画像のサイズが変わったとしても
        // $ratioOrg2Xxxを再計算しない。
        // 見た目のサイズが変わったとはいえ、
        // 倍率が変わったわけではないので。
    }

    // コピー先矩形とウィンドウ矩形を最適化
    list($dest, $window) =
        optimizeCopyParam($dest, $window, $orgW, $orgH,
            $editParam['zoom'], $ratioOrg2Final);

    // コピー
    $dstImage = getReadyFinalImage();
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
