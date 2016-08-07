<?php
include_once('./image.php');

$editParam = [
    'left'=>0,
    'top'=>0,
    'zoom'=>1.0,
    'angle'=>0,
];

$org = './org.jpg';
$dst = './dest.jpg';

editImage($editParam, $org, $dst);
?>

<img src="<?= $org ?>" />
<br />
<img src="<?= $dst ?>" />
