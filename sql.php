<?php

function pre_sqlCond($com, $mixCriteria, $strOpe='AND') {
	// leaf? let's terminate.
	if ( is_string($mixCriteria) ) return $mixCriteria;
	if ( !is_array($mixCriteria) ) throw new Exception();
	$aryCriteria = $mixCriteria;
	if ( empty($aryCriteria) ) return 'TRUE';
	if ( pre_isLeafArray($aryCriteria) ) {
		// eval a leaf array.
		$arySql = array();
		if ( is_string($aryCriteria[0]) ) $arySql[] = $aryCriteria[0];
		else $arySql[] = pre_sqlCol($com, $aryCriteria[0]);
		$arySql[] = $aryCriteria[1];
		if ( is_string($aryCriteria[2]) ) $arySql[] = $aryCriteria[2];
		else $arySql[] = pre_sqlCol($com, $aryCriteria[2]);
		return implode(' ', $arySql);
	}

	// it should be an array of leaves
	$arySql = array();
	foreach ( $aryCriteria as $k=>$v ) {
		if ( is_numeric($k) || $k == 'AND' ) {
			$arySql[] = pre_sqlCond($com, $v);
			continue;
		}
		if ( $k == 'OR' ) {
			$arySql[] = pre_sqlCond($com, $v, $k);
			continue;
		}
		throw new Exception();
	}

	if ( count($arySql) <= 1 ) return $arySql[0];
	return '('.implode(' '.$strOpe.' ', $arySql).')';
}

function pre_isLeafArray($mixCriteria) {
	if ( !is_array($mixCriteria) ) return false;

	$aryCriteria = $mixCriteria;
	if ( count($aryCriteria) != 3 ) return false;
	$aryKeys = array_keys($aryCriteria);
	if ( array_keys($aryKeys) !== $aryKeys ) return false;

	if ( !is_string($aryCriteria[0]) &&
		(!is_array($aryCriteria[0]) ||
		(count($aryCriteria[0]) != 2) ||
		($aryCriteria[0][0] != 'blob')) ) return false;

	if ( !is_string($aryCriteria[1]) ) return false;

	if ( !is_string($aryCriteria[2]) &&
		(!is_array($aryCriteria[2]) ||
		(count($aryCriteria[2]) != 2) ||
		(in_array($aryCriteria[2][0], array('blob','str','num')))) ) return false;
	return true;
}

function pre_sqlCol($com, $aryColValSpec) {
	return print_r($aryColValSpec, true);
}

function sql($mixCriteria) {
	$sql = pre_sqlCond(null, $mixCriteria);
	echo $sql.PHP_EOL;
}

function sqlo($mixCriteria, $strOpe) {
	$sql = pre_sqlCond(null, $mixCriteria, $strOpe);
	echo $sql.PHP_EOL;
}

sql("fuga=fuga");
sql([]);
sql(["fuga=fuga"]);
sql(["fuga=fuga", "piyo=piyo"]);
// NG, because it looks like a leaf array(but actually three leaves).
// sql(["fuga=fuga", "piyo=piyo", "piyopiyo=piyopiyo"]);
sql(["col", "=", "'000'"]);
sql(["fuga=fuga", ["col2", "IS NOT", "NULL"]]);
sql([["col", "=", "'000'"], ["col2", "IS NOT", "NULL"]]);
sql([[["col", "=", "'000'"], ["col2", "IS NOT", "NULL"]], "fuga=fuga"]);

sqlo(["fuga=fuga", "piyo=piyo"], 'OR');
sqlo([["col", "=", "'000'"], ["col2", "IS NOT", "NULL"]], 'OR');

sql(['OR'=>'TRUE']);
sql(['OR'=>[]]);
sql(['OR'=>["fuga=fuga"]]);
sql(['OR'=>["fuga=fuga", "piyo=piyo"]]);
sql(["fuga=fuga", "piyo=piyo", 'OR'=>['a=a', 'b=b', ['c', '=', 'c']]]);
sql([['OR'=>["fuga=fuga", "piyo=piyo"]], ['OR'=>['a=a', 'b=b']]]);

sql(['AND'=>'TRUE']);
sql(['AND'=>[]]);
sql(['AND'=>["fuga=fuga"]]);
sql(['AND'=>["fuga=fuga", "piyo=piyo"]]);
sql(["fuga=fuga", "piyo=piyo", 'AND'=>['a=a', 'b=b', ['c', '=', 'c']]]);
sql([['AND'=>["fuga=fuga", "piyo=piyo"]], ['AND'=>['a=a', 'b=b']]]);
