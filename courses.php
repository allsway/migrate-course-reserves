<?php


/* 
	Writes to the Alma course API and creates the following based on course data in CSV format and item data in CSV format:
	(1) course records
	(2) reading lists  
	(3) citation lists	
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
	if ($date != '  -  -  ')
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
	$searchterm = urldecode($searchterm);
	$campuscode = "01CALS_SJO";
	$baseurl = "https://na03.alma.exlibrisgroup.com/view/sru/". $campuscode ."?version=1.2&operation=searchRetrieve&recordSchema=marcxml&query=alma.all_for_ui=" . $searchterm;
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL, $baseurl);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);	
	if(isset($result))
	{
		return $result;
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
*/
function matchitems($items,$file2)
{
	  $bib_ids = array();
  	  $c = 0;
	  foreach($items as $item)
  	  {
		$item = trimitems($item);		
		$result =  shell_exec("grep " .$item. " " . $file2 . " | cut -d, -f3" );
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
			$xml = new SimpleXMLElement($xml);
			if(($xml->numberOfRecords > 0) && ($xml->numberOfRecords < 2))
			{
				$ids = $xml->xpath('//record/controlfield[@tag=001]');
				$title1 = $xml->xpath('//record/datafield[@tag=245]/subfield[@code="a"]'); //Gets the 245$a field
				$title2 = $xml->xpath('//record/datafield[@tag=245]/subfield[@code="b"]'); // Gets the 245$b field
				
				if(!empty($title2))
				{
					$bib_ids[$c]['title'] = $title1[0].'' . ' ' . rtrim(($title2[0].''),'/');
				}
				else
				{
					$bib_ids[$c]['title'] = $title1[0].'';

				}
				$bib_ids[$c]['mms_id'] = $ids[0].'';
				echo $bib_ids[$c]['title'] . PHP_EOL;
				$c++;
			}
			else if ($xml->numberOfRecords > 0)
			{
				shell_exec("echo `date` More than one record found for ".$item." >> course_errors.log" );
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

$record_num_pos = 0;
$begin_date_pos = 0;
$end_date_pos = 0;
$created_date_pos = 0;
$updated_date_pos = 0;
$prof_pos = 0;
$course_field_pos = 0;
$items_list_pos = 0;
$ccode3_pos = 0;

//argv[1] is the file of course records. 
//Reads through course records
$file = fopen($argv[1],"r");
$matches = array();
$name = '';

while (($line = fgetcsv($file)) !== FALSE) {

 if ($line != NULL)
 {
  //$line is an array of the csv elements
  //Parse header
	  if (strpos(implode(",",$line),"RECORD #") !== false)
	  {
		for ($i=0; $i<count($line); $i++)
		{
			switch($line[$i])
			{
				case "RECORD #(COURSE)":
				$record_num_pos = $i;
				break;
				case "BEGIN DATE":
				$begin_date_pos = $i;
				break;
				case "END DATE":
				$end_date_pos = $i;
				break;
				case "CREATED(COURSE)":
				$created_date_pos = $i;
				break;
				case "UPDATED(COURSE)":
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
				case "CCODE3":
				$ccode3_pos = $i;
				break;
				default:
				shell_exec("echo `date` Field ".$line[$i]." not found in file >> course_errors.log");
			
			}
		}
	  }
	  else
	  {  	 
		  $start = getdates($line[$begin_date_pos]);
		  $end = getdates($line[$end_date_pos]);
		
	  	  $instructors = explode(';',$line[$prof_pos]);
			 if ($line[$prof_pos] != '')
			 {
				  $notecontent = array(array(
					  'content' => "Instructor: " . $line[$prof_pos]
				  ));
			 }
			 else
			 {
				 $notecontent = array(array(
					 'content' => ''
				 ));
			 }
	 
		 // Why can't we just throw the other name into the searchable_id field?  or is it better to create a separate course?
		 // Will iterate through names and do something 
		  $names = explode(';', $line[$course_field_pos]);
		  var_dump($names);
		  $shortestname = $names[0];
		  $longestname = $names[0];
		  foreach($names as $name)
		  {
				if(strlen($name) < strlen($shortestname))
				{
					$shortestname = $name;
				}
				if(strlen($name) > strlen($longestname))
				{
					$longestname = $name;
				}
		  }
		  
		  $shortestname = trim($shortestname,'"');
		  $longestname = trim($longestname,'"');
		  $course_fields = array (
				'code' => $shortestname,
				'name' => $longestname,
				'academic_department' => array('value' => ''),
				'processing_department' =>  array('value' => 'TestRes'), //Not sure where to get this from - maybe the config API?	
				'status' => 'ACTIVE',
				'start_date' => $start,
				'end_date' => $end,
				'searchable_id' => array($line[$record_num_pos]),
				'note'=> $notecontent		
		  );

		  $body = json_encode($course_fields);	  
		  $output = curljson($url,$body);
	  	  
	  	  // Check and make sure that course code is unique.  If it's not, we receive an error and iterate to get the unique value of the course code. 
	  	  $n = 0;
	  	  $course_xml = new SimpleXMLElement($output);

	  	  if($course_xml->errorsExist == "true")
	  	  {
	  	  	while(($course_xml->errorList->error->errorCode == '401006') && ($n < 20))
	  	  	{
	  	  		$n++;
	  	  		$shortestname = $shortestname . '-' . $n;
	  	  		//redo the above, with a unique ID
	  	  		 $course_fields = array (
					'code' => $shortestname,
					'name' => $longestname,
					'academic_department' => array('value' => ''),
					'processing_department' =>  array('value' => 'TestRes'), //Not sure where to get this from - maybe the config API?	
					'status' => 'ACTIVE',
					'start_date' => $start,
					'end_date' => $end,
					'searchable_id' => array($line[$record_num_pos]),
					'note'=> $notecontent		
			  );
			  $body = json_encode($course_fields);	  
			  $output = curljson($url,$body);
			  $course_xml = new SimpleXMLElement($output);
	  	  		
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

		  // I'm not sure what is the best way to make the reading list code unique
		  // Right now, using former system record number (unique) in combination with course code
		   $reading_list = array (
				'code' => $shortestname,
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
					$citation_output  = curljson($citation_url,$citation_body);
				}
			}
		}
	}
}
fclose($file);


?>























