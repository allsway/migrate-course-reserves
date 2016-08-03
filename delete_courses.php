<?php

/* 
	Deletes courses created by the Alma API
*/


/*
	GET course information request
*/
function getjson ($url)
{
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
	if(isset($result))
	{
		$json = json_decode($result,true);
		return $json;
	}
	else
	{
			return -1;
	}
}

/*
	DELETE request
*/
function delete_course($url)
{

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	$response = curl_exec($ch);
	curl_close($ch);
}



/*
	Deletes all course records with the creator 'exl_api'	
	
	Should be used for testing ONLY
*/

for ($i=0; $i<100; $i+=10)
	{
	$url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/courses?apikey='.$key.'&format=json&offset='.$i;
	
	//echo $url;
	$json = getjson($url);
	
	foreach($json['course'] as $course)
	{
	      if (isset($course['note'][0]))
	      {
	                if($course['note'][0]['created_by'] == 'exl_api')
	                {
	                        echo $course['id'] . " " . $course['note'][0]['created_by'] . PHP_EOL;
	                        $url2 = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/courses/'.$course['id'].'?apikey='.$key;
	                        $result = delete_course($url2);
	                        var_dump($result);
	                }
	      }
	}


}
?>



?>























