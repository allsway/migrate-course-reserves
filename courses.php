<?php


/* 
	Writes to the course API and creates a:
	(1) course record
	(2) reading list  
	(3) citation list	
*/

function curljson ($url,$body)
{
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

	$response = curl_exec($curl);
	curl_close($curl);

	if(isset($response))
	{
		return $response;
	}
	else 
	{
		shell_exec('echo `date`  No response from API >> course_errors.log');
		return -1;
		
	}
}


/*
	Manipulates dates in the old format
	DD-MM-YYY to YYYY-MM-DD, which is the expected format by the courses API. 
*/
function getdates($date)
{
	if ($date != '  -  -  ' && $date != '  -  -    ')
	{
		$date = explode('-',$date);
		$date = $date[2].'-'.$date[0].'-'.$date[1];
	}
	else 
	{
		// Not sure what to do in case that the date is empty.  Ex Libris automatically sets an end date if it's not supplied. 
		// Select a default end date? 
		$date = '2016-12-12';
	}
	return $date;
}

/*
	Removes the "::a" from the item list from extracted csv file format
*/
function trimitems($item)
{
	$trimmed_item = ltrim($item,' "');
  	$trimmed_item = rtrim($trimmed_item,'::a"');
  	return $trimmed_item;
}


/*
	Searches for the item record based on the item barcode, through the SRU	
*/

function callsru($searchterm)
{
	$campuscode = "01CALS_SFR";
	$baseurl = "https://na03.alma.exlibrisgroup.com/view/sru/". $campuscode ."?version=1.2&operation=searchRetrieve&recordSchema=marcxml&query=alma.all_for_ui=" . $searchterm;
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL, $baseurl);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);	
	$xml = new SimpleXMLElement($result);
	if(isset($xml))
	{
		return $xml;
	}
	else
	{
		shell_exec('echo `date`  No response from SRU >> course_errors.log');
		result -1;
	}
	
}

/*
	Gets the matches for the item in the item csv file
	greps the item record number from the delivered items list (assumming order here, can alter if need be)
	returns the item barcode from the items file
	
	Calls the SRU using the item barcode
	With the SRU results, parses the bib record MMS ID and the bib record title (both necessary for the citation API call)
	
	Returns array of bib MMS IDs and record titles
	
	I'm not sure what to do with duplicate items (same bib record)
	How are these supposed to be treated in Alma?  I assume that we only want to add each bib record once, right?
	
	So I guess I have to check for other duplicates? ugh. 

*/
function matchitems($items,$file2)
{
	  $bib_ids = array();
  	  $c = 0;
	  foreach($items as $item)
  	  {
		$item = trimitems($item);
		$barcode_position = "2";
		$result =  shell_exec("grep " .$item. " " . $file2 . " | cut -d, -f" . $barcode_position );
		echo $result . PHP_EOL;
		if (strlen($result) < 4)
		{
			shell_exec("echo `date`  barcode for ".$item." not found in file >> course_errors.log");
		}
		else
		{
			// barcodes with spaces cause SRU response issue
			// should check with other characters too..
			$result = str_replace(' ', '', $result);
			$xml = callsru($result);
			if(($xml->numberOfRecords > 0) && ($xml->numberOfRecords < 2))
			{
				// Pulling the 245 $a, $b and $c fields (apparently necessary) for full title info
				$ids = $xml->xpath('//record/controlfield[@tag=001]');
				$title1 = 	$xml->xpath('//record/datafield[@tag=245]/subfield[@code="a"]'); //Gets the 245$a field
				$title2 = 	$xml->xpath('//record/datafield[@tag=245]/subfield[@code="b"]');

				$bib_ids[$c]['title'] = $title1[0].'';
				if(!empty($title2))
				{
					$bib_ids[$c]['title'] .=   ' ' . rtrim(($title2[0].''),'\/');
				}
				else 
				{
					$bib_ids[$c]['title'] = rtrim($bib_ids[$c]['title'],'/');
				}
				$bib_ids[$c]['mms_id'] = $ids[0].'';
				echo $bib_ids[$c]['title'] . PHP_EOL;

				$c++;
			}
			else if ($xml->numberOfRecords > 1)
			{
				shell_exec("echo `date` More than one result found for barcode ". $result . ", correct item record: ".$item." >> course_errors.log" );
			}
		 }
  	  }  	  
  	  return $bib_ids;
}


/*
	Sets the API keys and URLS
	
	Opens the file of course records in CSV format 
	Parses the header (fields can be delivered in any order)
	
	Sends the date fields to the date format function
*/

$ini_array = parse_ini_file("courses.ini");

$key= $ini_array['apikey'];
$baseurl = $ini_array['baseurl'];
$url = $baseurl.'/almaws/v1/courses?apikey='.$key;
$delimiter = '|';


$record_num_pos = -1;
$begin_date_pos = -1;
$end_date_pos = -1;
$created_date_pos = -1;
$updated_date_pos = -1;
$prof_pos = -1;
$course_field_pos = -1;
$items_list_pos = -1;
$ccode3_pos = -1;
$note_pos = -1;
$url_pos = -1;


//argv[1] is the file of course records. 
//Reads through course records
$file = fopen($argv[1],"r");


// For '|'-delimited file
// Second parameter is longest length in file (set high)
while (($line = fgetcsv($file,10000,$delimiter)) !== FALSE) {

 if ($line != NULL)
 {
  //$line is an array of the csv elements
  //Parse header
	  if (strpos(implode($delimiter,$line),"RECORD #") !== false)
	  {
			for ($i=0; $i<count($line); $i++)
			{
				switch($line[$i])
				{
					case "RECORD #":
					$record_num_pos = $i;
					break;
					case "BEGIN DATE":
					$begin_date_pos = $i;
					break;
					case "END DATE":
					$end_date_pos = $i;
					break;
					case "CREATED":
					$created_date_pos = $i;
					break;
					case "UPDATED":
					$updated_date_pos = $i;
					break;
					case "PROF/TA":
					$prof_pos = $i;
					break;
					case "COURSE":
					$course_field_pos = $i;
					break;
					case "ITEM ID":
					$items_list_pos = $i;
					break;
					case "URL":
					$url_pos = $i;
					break;
					case "COUR NOTE":
					$note_pos = $i;
					break;
					default:
					shell_exec("echo `date` Field ".$line[$i]." not found in file >> course_errors.log");
				
				}
			}
	  }
	  else
	  {  	 	  
	  	/* 
	  		Call getdates to re-arrange the date fields so that they are accepted by the Alma APIs
		*/
		  $start = getdates($line[$begin_date_pos]);
		  $end = getdates($line[$end_date_pos]);
				 
		 /*
		 	Options:
		 	Add all additional names to searchable IDs
		 	Add a new course for each name
		 	Combine the shortest names together and the longest names together for viewing
		 */
		  $names = explode(';', $line[$course_field_pos]);
		  $temp_array = array();
		 
		  $searchable_ids = $line[$record_num_pos];
		  $searchable_ids = str_replace('"','',$searchable_ids);

		 /*
		 	Creates notes section of course record
		 	Each instructor is added into a separate note line
		 	Each note is added into a separate note line
		 	Any additional non-mappable fields can be added to a separate note line
		 */
		  $notes_array = array();
		  $instructors = explode(';',$line[$prof_pos]);
		  $urls = explode(';',$line[$url_pos]);
		  $notes = explode(';',$line[$note_pos]); 
		  $note_counter = 0;
		  
		  
		  foreach($instructors as $instructor)
		  {
		  		$instructor = trim(trim($instructor,' '),'"');
		  		$notes_array[$note_counter] = array('content' => "Instructor: " . $instructor);
		  		$note_counter++;
		  }
		  if($note_pos > 0 && strlen($notes[0])>2)
		  {
			  foreach($notes as $note)
			  {
			  		$note = trim(trim($note,' '),'"'); 
					$notes_array[$note_counter] = array('content' => "Note: " . $note   );
					$note_counter++;
			  }
		  }
	  	  if($url_pos > 0 && strlen($urls[0])>2)
	  	  {
	  	  	 foreach($urls as $url_note)
	  	  	 {
	  	  	 	$url_note = trim(trim($url_note,' '),'"'); 
	  	  	 	$notes_array[$note_counter] = array('content' => "URL: " . $url_note);
	  	  	 	$note_counter++;
	  	  	 }
	  	  }
	  	  
	  	  //$processing_dept = $ini_array['processing_dept'];

	  	  $processing_dept = 'Course Unit';
	  	 
	  	 /*
	  	 	Creates a separate course for each name that exists in the current course record
	  	 */	  	 
	  	  foreach($names as $name)
	  	  {
		  	  $shortestname = $name;
		  	  $longestname = $name;
		  	  $shortestname = trim(trim($shortestname,' '),'"');
			  $longestname = trim(trim($longestname,' '),'"');
			  $tempname = $shortestname;

			  /*
			  		Create possible exception case for campuses that tend to have a 
			  		Course Code + course name in the COURSE field. 
			  */
			  $course_fields = array (
					'code' => $shortestname,
					'name' => $longestname,
					'academic_department' => array('value' => ''),
					'processing_department' =>  array('value' => $processing_dept), 	
					'status' => 'ACTIVE',
					'start_date' => $start,
					'end_date' => $end,
					'searchable_id' => array($searchable_ids),
					'note'=> $notes_array		
			  );


			  $body = json_encode($course_fields);	
			  $output = curljson($url,$body);
			  // Check and make sure that course code is unique.  If it's not, we receive an error and iterate to get the unique value of the course code. 
			  $n = 1;
			  $course_xml = new SimpleXMLElement($output);
			  $continue = true;
		  
			  if($course_xml->errorsExist == "true" && $course_xml->errorList->error->errorCode = "401006")
			  {
					while(($continue == true) && ($n < 30))
					{
						$n++;
						$tempname = $shortestname . '-' . $n;
						//redo the above, with a unique ID
						 $course_fields = array (
							'code' => $tempname,
							'name' => $longestname,
							'academic_department' => array('value' => ''),
							'processing_department' =>  array('value' =>  $processing_dept), 
							'status' => 'ACTIVE',
							'start_date' => $start,
							'end_date' => $end,
							'searchable_id' => array($line[$record_num_pos]),
							'note'=> $notes_array		
					  );
					  $body = json_encode($course_fields);	  
					  $output = curljson($url,$body);
					  $course_xml = new SimpleXMLElement($output);
					  if($course_xml->errorsExist == "true" && $course_xml->errorList->error->errorCode = "401006")
					  {
							$continue = true;
					  }
					  else
					  {
							$continue = false;
					  }
					}
			  }
			  /*
				Gets the new course ID to send to the reading lists api
				Uses returned course response to get the course ID in Alma to send to the reading list API
			  */
			  $course_id = $course_xml->id.'';
  
			  /*	  
				Create reading list
				Create parameters for the reading list Create Reading List API
			  */
			  $readinglist_url = $baseurl.'/almaws/v1/courses/'.$course_id.'/reading-lists?apikey='.$key;

			  $reading_list = array (
					'code' => $tempname,
					'name' => $longestname,
					'status' => array('value' => 'Complete' )
			   );	

			   $list_body = json_encode($reading_list);
			   $reading_output = curljson($readinglist_url,$list_body);
			   $reading_xml = new SimpleXMLElement($reading_output);
				/*
					Create citations!
					Calls matchitems() to obtain the bib record mms ids for each attached item in the course
				*/	
	
				$reading_list_id = $reading_xml->id;
				$citation_url = $baseurl.'/almaws/v1/courses/'.$course_id.'/reading-lists/'.$reading_list_id.'/citations?apikey='.$key;  
	
				/*
					Gets the items attached to each course record and adds to citations
				*/
				if (strlen($line[$items_list_pos]) > 4)
				{	
					$items = explode(';',$line[$items_list_pos]);
					// Second file is barcodes.csv (item record numbers and corresponding barcodes)
					$bib_ids = matchitems($items,$argv[2]);
					// Removes duplicate bibs - Alma uses bibs instead of items, so we end up with dupes
					$bib_ids = array_map("unserialize", array_unique(array_map("serialize", $bib_ids)));
		

					// Add a citation for each bib record
					// Static values for complete and physical book seem ok here, they should always be the same	
					foreach($bib_ids as $bib_id)
					{
						$citations = array (
							'status' => array('value' => 'Complete',
								'desc' => 'Complete'
							),
							'type' => array('value' => 'BK',
								'desc' => 'Physical Book'
							),
							'metadata' => $bib_id
			
						);
						$citation_body = json_encode($citations);
						var_dump($citation_body);
						$citation_output  = curljson($citation_url,$citation_body);
					}
				}
			}
		}	
	}
}
fclose($file);






?>























