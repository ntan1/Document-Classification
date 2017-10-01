<?php
function calcEntropy($word,$type) {
	$entropy = -1*(calcIndivEntropy($word,$type."/course",$type) + calcIndivEntropy($word,$type."/faculty",$type) + calcIndivEntropy($word,$type."/student",$type));
	return $entropy;
}

function calcIndivEntropy($word,$doctype,$type) {
	$numerator = findNum("select count(word) from allwords where doctype='".$doctype."' and word='".$word."'");
	$denominator = findNum("select count(word) from allwords where word='".$word."' and doctype like '%".$type."%'");
	$fraction = $numerator/$denominator;

	if ($fraction != 0) {
		$indiv_entropy = $fraction*log($fraction,3);
	} else {
		$indiv_entropy = 0;
	}
	return $indiv_entropy;
}
?>