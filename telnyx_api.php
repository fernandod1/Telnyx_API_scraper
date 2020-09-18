<? 

# Copyright (c) 2020 Fernando
# Url: https://github.com/dlfernando/
# License: MIT

define("MYSQLHOST", "YOUR_DB_HOST");
define("DBNAME", "YOUR_DB_NAME");
define("USERNAME", "YOUR_DB_USERNAME");
define("PASSWORD", "YOUR_DB_PASSWORD");
define("API_KEY"," Bearer YOUR_CUSTOM_API_KEY_VALUE");
define("API_URL", "https://api.telnyx.com/v2/available_phone_numbers?filter[limit]=100000&filter[phone_number][starts_with]=");
define("LOOKUP_TABLE", "lookup_table");
define("RESULTS_TABLE", "results_table");
define("MAX_AREACODE_SIMULTANEOUS_PROCESSING", "2"); // Set value between 1 and 3 to avoid browser script loading timeouts

function connect_to_mysqli(){
	$connect = mysqli_connect(MYSQLHOST, USERNAME, PASSWORD, DBNAME);
	if (!$connect) {
		  die("Connection failed mysql: " . mysqli_connect_error());
	}
	return $connect;	
}

function update_database($connection, $areacode){
    $sql = "UPDATE ".LOOKUP_TABLE." SET active='0' WHERE areacode='$areacode'";
    if($result = mysqli_query($connection, $sql)){
        //echo LOOKUP_TABLE." table updated. Set active=0 done.";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($connection);
    }
}

function active_total_rows($connection){
    $totalactive = 0;
    $sql = "SELECT COUNT(*) FROM ".LOOKUP_TABLE." WHERE active='1'";
    if($result = mysqli_query($connection, $sql)){
        $row = mysqli_fetch_row($result);
        $totalactive = $row[0];
    }
    return $totalactive;
}

function read_database($connection){
    $sql = "SELECT * FROM ".LOOKUP_TABLE." WHERE active='1' ORDER by areacode ASC LIMIT 0, ".MAX_AREACODE_SIMULTANEOUS_PROCESSING."";
    if($result = mysqli_query($connection, $sql)){
        //$GLOBALS['totalrows'] = mysqli_num_rows($result);
        if(mysqli_num_rows($result) > 0){
            while($row = mysqli_fetch_array($result)){
                $areacodes[] = $row['areacode'];
            }
            mysqli_free_result($result);
        } else{
            echo "No records matching criterial were found in ".LOOKUP_TABLE." table.";
        }
    } else{
        echo "ERROR: Could not execute $sql. " . mysqli_error($connection);
    }
    return $areacodes;
}

function get_from_api($areacode){
    $cConnection = curl_init();
    curl_setopt($cConnection, CURLOPT_URL, API_URL."$areacode");
    curl_setopt($cConnection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cConnection, CURLOPT_HTTPHEADER, array(
        'Authorization: '.API_KEY.'',
        'Content-Type: application/json',
        "Accept: application/json"
        ));
    $phoneList = curl_exec($cConnection);
    $jsonArrayResponse = json_decode($phoneList, true);
    curl_close($cConnection);
    return $jsonArrayResponse;
}

function insert_database($connection, $data){
    $iii = $data['phonenumber'];
    $sql = "INSERT INTO ".RESULTS_TABLE." (`areacode`, `phonenumber`, `state`, `ratecenter`, `date_added`) 
    VALUES ('{$data['areacode']}', '{$data['phonenumber']}', '{$data['state']}', '{$data['ratecenter']}', '{$data['date_added']}')";
   
    if (mysqli_query($connection, $sql)) {
       //echo "New record added to ".RESULTS_TABLE." table.";
    } else {
       echo "Error: " . $sql . "<br>" . mysqli_error($connection);
    }
}

function insert_data($connection, $onedata, $areacode){
    if($onedata['record_type']=='available_phone_number'){
        $savedata['areacode'] = $areacode;
        $savedata['phonenumber'] = str_replace("+", "", $onedata['phone_number']);
        $savedata['state'] = $onedata['region_information'][2]['region_name'];
        $savedata['ratecenter'] = $onedata['region_information'][0]['region_name'];
        $savedata['date_added'] = gmdate("Y-m-d H:i:s");
        insert_database($connection, $savedata);
    }   
}

# ------------------------------ Main program -------------------------------- #


$connection = connect_to_mysqli();
$activetotal = active_total_rows($connection);
if ($activetotal > 0){
    $areacodes = read_database($connection);
    echo "<b>Left: ".$activetotal."</b> active area codes to process...(<b>wait</b>)<br><br>";
    foreach($areacodes as $areacode)
    {
        $json_data = get_from_api($areacode);
        foreach($json_data["data"] as $onedata)
        {
            insert_data($connection, $onedata, $areacode);     
        }
        update_database($connection, $areacode);
        echo "Processed active area code: $areacode <br>";
        echo "<meta http-equiv=\"refresh\" content=\"5; url=".basename(__FILE__)."\">";
    }
}
else{echo "<br><br><b>DONE</b>. All active area codes have been processed.";}
mysqli_close($connection);




?>