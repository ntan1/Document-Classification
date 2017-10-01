<html>
	<head>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
		<script type="text/javascript" src="result.js"></script>
	</head>
<?php

include 'preprocess.php';
include 'MultinomialNaiveBayes.php';
include 'entropy.php';

$type; // train or test

$max_course;
$max_faculty;
$max_student;
$total;

$prior_course;
$prior_faculty;
$prior_student;

$topwords = array();
$theOne = array();
$word_probs_course = array();
$word_probs_faculty = array();
$word_probs_student = array();

$entrop = 0.49;


$type = "train";
// training set calcs
getMax($type); 
calcPriors(); // independent should not change or be called again
getTopWords($course); // independent
getTopWords($faculty); // independent
getTopWords($student); // independent
$topwords = array_values(array_unique($topwords)); // remove duplicates in array // independent
$vocab_size = count($topwords); // independent

// store the word probs in their respective arrays
// $word_probs_course = getWordProbs("train/course", $word_probs_course); // independent
// $word_probs_faculty = getWordProbs("train/faculty", $word_probs_faculty); // independent
// $word_probs_student = getWordProbs("train/student", $word_probs_student); // independent

for ($i=0; $i<count($topwords); $i++) {
	$entropy = calcEntropy($topwords[$i], "train");
	if ($entropy < $entrop) {
		array_push($theOne, $topwords[$i]);
	}
}

echo "<br/> Word Count: ".count($theOne)."<br/>";

$vocab_size = count($theOne); // independent
$word_probs_course = getWordProbs("train/course"); // independent
$word_probs_faculty = getWordProbs("train/faculty"); // independent
$word_probs_student = getWordProbs("train/student"); // independent

echo "Word limit per doc: ".$limit."<br/>";
echo "Entropy limit: ".$entrop."<br/>";

?>

<!--display results in a table of TRAIN-->
<table style="width:100%;">
	<tr><th>Course</th><th>Faculty</th><th>Student</th>
	</tr>
	<tr>	
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"course"); ?></td>
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"faculty"); ?></td>
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"student"); ?></td>
	</tr>
</table>

<?php


// TEST
$type = "test";
getMax($type); 

?>

<table style="width:100%;">
	<tr><th>Course</th><th>Faculty</th><th>Student</th>
	</tr>
	<tr>	
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"course"); ?></td>
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"faculty"); ?></td>
		<td style="display: table-cell; vertical-align:top;"><?php display($type,"student"); ?></td>
	</tr>
</table>


<?php
// Calculate probabilities of each class with prior prob and prob of each word times count


// Return highest prob class and print with actual doctype to compare


// Calculate probabilities of each word


// Get max count of training documents
function getMax($type) {
	global $max_course;
	global $max_faculty;
	global $max_student;
	global $total;
	
	$max_course = getMaxDocCount($type."/course");
	$max_faculty = getMaxDocCount($type."/faculty");
	$max_student = getMaxDocCount($type."/student");
	$total = $max_course + $max_faculty + $max_student; // add up count of training documents to get total training documents
	echo "number of ".$type." course documents: ".$max_course."<br/>";
	echo "number of ".$type." faculty documents: ".$max_faculty."<br/>";
	echo "number of ".$type." student documents: ".$max_student."<br/><br/>";
}

// Calculate prior probabilities of each class
function calcPriors() {
	global$max_course;
	global $max_faculty;
	global $max_student;
	global $prior_course;
	global $prior_faculty;
	global $prior_student;
	
	$prior_course = calcIndivPrior($max_course);
	$prior_faculty = calcIndivPrior($max_faculty);
	$prior_student = calcIndivPrior($max_student);
	echo "prior_course: ".$prior_course."<br/>";
	echo "prior_faculty: ".$prior_faculty."<br/>";
	echo "prior_student: ".$prior_student."<br/>";
}

// Insert the chosen words into array for easier access and use
function getTopWords($class) {
	global $topwords;
	for ($i=0; $i<count($class); $i++) {
		array_push($topwords, $class[$i][0]);
	}
}

// Function to store word probabilities of the different classes
function getWordProbs($doctype) {
	global $theOne;
	
	for ($i=0; $i<count($theOne); $i++) {
		$word_probs[$theOne[$i]] = calcConditionalProb($theOne[$i],$doctype);
	}
	return $word_probs;
}

// store relevant words from this document into an array
function getDocWords($doctype, $docid) {
	global $link;
	$words = array();
	
	$query = "select word from allwords where doctype='".$doctype."'
	and docid ='".$docid."'";
	$result = $link->query($query);
    while($row = mysqli_fetch_assoc($result))
    {
        array_push($words, $row["word"]);
    }
	return $words;
}

// display results as a table
function display($type, $doctype) {
	global $max_course;
	global $max_faculty;
	global $max_student;
	$correct = 0;
	
	if ($doctype == "course") {
		$maxdoc = $max_course;
	} else if($doctype == "faculty") {
		$maxdoc = $max_faculty;
	} else if($doctype == "student") {
		$maxdoc = $max_student;
	}
	
	echo 
	"<table border='1' class='table' style='margin: 0 auto;'>
		<tr><th>docid</th><th>type</th><th>doctype</th><th>class</th>
		</tr>
	";
	for ($i=0; $i<$maxdoc; $i++) {
		$docid = $i+1;
		$class = classify(getDocWords($type."/".$doctype, $docid));
		if ($class == $doctype) {
			$correct++;
		}
		echo 
		"<tr>
			<td>".$docid."</td>
			<td>".$type."</td>
			<td class='actual'>".$doctype."</td>
			<td class='given'>".$class."</td>
		</tr>
		";
	}
	echo "</table>";
	$precision = $correct/$docid;
	echo "<div style='text-align: center;'><br/><strong>Precision: </strong>".$precision."</div>";
}

//close db connection
mysqli_close($link);

$end = microtime(true);
$total = $end - $start;
echo "<br/><br/>Total time: ".$total." sec";

?>
</html>
