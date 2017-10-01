<?php
include 'porter.php';
include 'stop.php';

$start = microtime(true);
$remove = array("<", ">", "&nbsp;");
$docs = ""; // stores all stemmed words for writing to test.html or train.html
$dirs = ""; // for storing current directory for identifying purposes of words
$outputname =""; // file name for storing stemmed words. either test.html or train.html
$numofwords = 0; // for storing SQL results

set_time_limit(1000);

// Connect to database
$link = mysqli_connect('localhost','root','');
if (!$link) {
	die('Connection Error('.mysqli_connect_errno().')) '. ') '. mysqli_connect_error());
}

// Create database
$sql = "CREATE DATABASE db4305";
if ($link->query($sql) === TRUE) {
    echo "Database db4305 created successfully<br/>";
} else {
	echo "Database db4305 Exists<br/>";
}
mysqli_select_db($link, "db4305");

// Create table allwords that will hold the inverted index
$query = "CREATE Table allwords(word varchar(60) NOT NULL, doctype varchar(40) NOT NULL, docid varchar(10) NOT NULL) Engine=MyISAM";
$stmt = $link->prepare($query);
$result = $stmt->execute();
if ($result) {
	echo "table allwords created<br/><br/>";
} else {
	echo "table allwords exists<br/><br/>";
}

// Removes all rows from table so it doesn't store redundant data
$stmt = $link->prepare("TRUNCATE Table allwords");
$result = $stmt->execute();

// function for storing words into test.html or train.html and also general processing
function storefiles($dir) { 
    $handle = opendir($dir);
	global $docs;
	global $dirs;
	global $link;
	global $outputname;
	$docno = 0;
	$docid = "";

    while (($file = readdir($handle)) !== false) {
        if ($file == '.' || $file == '..') { 
            continue; 
        } 
		$filepath = $dir."/".$file;
        if (is_link($filepath)) 
            continue; 
        if (is_file($filepath)) {
			$docno++;
			$document = removeHeaders(file_get_contents($filepath));
			$document = str_replace("\n"," ",$document);
			$words = getWords($document);
			$docid = $dirs."_".$docno;
			$docs .= "<".$docid.">\r\n";
			$link->query("START TRANSACTION"); // starts transaction
			for ($i=0; $i<count($words); $i++) {
				$docs .= $words[$i]." ";
				if (strlen($words[$i]) > 1) { // checks if word is a space or not before inserting into db
					storeDB($words[$i],$dirs,$docno);
				}	
			}
			$link->query("COMMIT"); // commits queries to database
			$docs .= "\r\n</".$docid.">\r\n\r\n";
        } else if (is_dir($filepath)) {
			$dirs = $filepath;
            storefiles($filepath); 
		}
    } 
    closedir($handle);
	file_put_contents($outputname, $docs);
} 

// function for removing headers from a document 
function removeHeaders($document) {
	$document = str_replace("<html>","<title>",$document);
	preg_match_all("'(.*?)<<title>>'s", $document, $headers, PREG_PATTERN_ORDER); // get all text before <title> tag
	for ($i = 0; $i < count($headers[0]); $i++) {
		$document = str_replace($headers[0][$i],'',$document);
	}
	return $document;
}

// function for processing words
function getWords($document) {
	$processed_text = "";
	global $remove;
	// get all text from web documents between ">" and "<" tags
	preg_match_all("'>(.*?)<'s", $document, $matches, PREG_PATTERN_ORDER);
	for ($i = 0; $i < count($matches[0]); $i++) {
		$matches[0][$i] = str_replace($remove,"",$matches[0][$i]);
		$matches[0][$i] = preg_replace("#[[:punct:]]#", " ", $matches[0][$i]); // replace all punctuation with a space
		$processed_text .= $matches[0][$i]; // store text in a string 
	}	
	
	// split string into individual words that are at least 2 characters long by spaces and store in an array
	preg_match_all("/(\S{2,})/i", $processed_text, $words, PREG_PATTERN_ORDER);
	for ($i=0; $i<count($words[0]); $i++) {
		$words[0][$i] = process($words[0][$i]);
	}
	return $words[0];
}

// function for removing stopwords and stemming and other processing
function process($word) {
	$word = removeCommonWords($word); // remove stopwords
	$word = preg_replace('/[0-9]+/',"",$word); // remove numbers
	$word = str_replace(" ","",$word); // remove unnecessary spaces in the word
	$word = PorterStemmer::Stem($word); // stem word	
	$word = strtolower($word); // convert word to lowercase
	return $word;
}

// function for storing words into the db
function storeDB($word, $doctype, $docid) {
	global $link;
	$stmt = $link->prepare("INSERT DELAYED INTO allwords (word,doctype,docid) VALUES (?,?,?)");
	$stmt->bind_param("sss",$word,$doctype,$docid);
	$stmt->execute();
}

// function for writing SQL query results to csv files
function storecsv ($query, $filename) {
	global $link;
	$result = $link->query($query);
    $fp = fopen($filename.".csv", 'w');
    while($row = mysqli_fetch_assoc($result))
    {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

// function for finding the number of words depending on the query provided
function findNum($stmt) {
	global $link;
	$stmt = $link->prepare($stmt);
	$stmt->execute();
	$stmt->bind_result($num);
	$stmt->store_result();
	$stmt->fetch();
	return $num;
}


$pth = "train"; // name of folder to begin file searching for processing
$outputname = "train.html";
storefiles($pth); // calls function to process documents in the $pth folder, write words to a file, and store words into the db
echo "train.html created<br/>";
$pth = "test";
$outputname = "test.html";
storefiles($pth);
echo "test.html created<br/><br/>";


/* Get Top 5 words from each folder and store in individual arrays (only need words not counts?) */

$limit = 2000;

// store word frequencies of course, faculty, student in individual csv files
$stmt = "select word, count(distinct docid) from allwords where doctype like 
'%train/course%' group by word order by count(distinct docid) desc limit ".$limit;
storecsv ($stmt, "train_course"); //store word frequencies of the train set 115
$stmt = "select word, count(distinct docid) from allwords where doctype like 
'%train/faculty%' group by word order by count(distinct docid) desc limit ".$limit;
storecsv ($stmt, "train_faculty"); //store word frequencies of the train set 77
$stmt = "select word, count(distinct docid) from allwords where doctype like 
'%train/student%' group by word order by count(distinct docid) desc limit ".$limit;
storecsv ($stmt, "train_student"); //store word frequencies of the train set 274

// put csv into array 
$course = array_map('str_getcsv', file('train_course.csv'));
$faculty = array_map('str_getcsv', file('train_faculty.csv'));
$student = array_map('str_getcsv', file('train_student.csv'));

?>