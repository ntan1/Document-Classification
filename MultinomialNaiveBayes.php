<?php

// $vocab_size = 6;// the size of the vocabulary, used for add one/laplacian smoothing

// function to get count of a doctype
function getMaxDocCount($doctype) {
	global $link;
	$max;
	$stmt = $link->prepare("select count(distinct docid) from allwords where doctype like '%".$doctype."%'");
	$stmt->execute();
	$stmt->bind_result($max);
	$stmt->store_result();
	$stmt->fetch();
	return $max;
}

// function to calculate prior probabilities
function calcIndivPrior($class) {
	global $total;
	$prior = $class/$total;
	return $prior;
}

// function to calculate conditional probabilities
function calcConditionalProb($word, $doctype) {
	global $link; // MySQL connection
	global $vocab_size; 
	global $prior_course;
	global $prior_faculty;
	global $prior_student;
	$count_wc; // numerator
	$count_c; // denominator
	$prob;
	
	// calculate numerator which is count of word given class plus 1
	$query = "select count(word) from allwords where doctype='".$doctype."' and word='".$word."'";
	$stmt = $link->prepare($query);
	$stmt->execute();
	$stmt->bind_result($count_wc);
	$stmt->store_result();
	$stmt->fetch();
	$count_wc++; // add one smoothing
	
	
	// calculate denominator which is count of total words from class plus vocab size
	$query = "select count(word) from allwords where doctype='".$doctype."'";
	$stmt = $link->prepare($query);
	$stmt->execute();
	$stmt->bind_result($count_c);
	$stmt->store_result();
	$stmt->fetch();
	$count_c += $vocab_size; // add one smoothing
	
	
	// divide numerator and denominator to get prob
	$prob = $count_wc/$count_c;
	
	return $prob;
}

// function to classify documents
function classify($words) {
	global $link; // MySQL connection
	
	global $word_probs_course;
	global $word_probs_faculty;
	global $word_probs_student;
	
	global $prior_course;
	global $prior_faculty;
	global $prior_student;
	
	$course_prob = $prior_course;
	$faculty_prob = $prior_student;
	$student_prob = $prior_faculty;	
	
	// Get probabilities of each class by multiplying conditional probabilities and comparing with each
	for ($i=0; $i<count($words); $i++) {
		if (isset($word_probs_course[$words[$i]])) {
			$course_prob *= $word_probs_course[$words[$i]];
			$faculty_prob *= $word_probs_faculty[$words[$i]];
			$student_prob *= $word_probs_student[$words[$i]];
		}
	}	

	$max = max($course_prob, $faculty_prob, $student_prob);
	if ($max == $course_prob) {
		$class = "course";
	} else if ($max == $faculty_prob) {
		$class = "faculty";
	} else if ($max == $student_prob) {
		$class = "student";
	} 
	return $class;
}

function getWordsCount($doctype, $docid, $words) {
	$words = $words; // the array of words 
	$count = 0;
	$probs = array();
	
	for ($i=0; $i<count($words); $i++) {
		// get count of relevant words for this document
		$query = "select count(word) from allwords where doctype='".$doctype."' and docid='".$docid."' and word='".$words[$i]."'";
		$stmt = $link->prepare($query);
		$stmt->execute();
		$stmt->bind_result($count);
		$stmt->store_result();
		$stmt->fetch();
		
		// calculate probability of that words
		$probs[$i] = $count*calcConditionalProb($words[$i], $doctype);
	}
}
?>