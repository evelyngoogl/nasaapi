<?php
require_once realpath(__DIR__ . '/env.php'); 
error_reporting(-1);
ini_set('display_errors', 'On');
header('Content-Type: application/json');

$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

$start_date_obj = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);

if ($start_date_obj > $end_date_obj) {
	echo json_encode(['error' => 'End date should be greater than start date.']);
	http_response_code(400);
	exit();
}

$days_between = $start_date_obj->diff($end_date_obj)->format("%a");
if ($days_between > 7) {
	echo json_encode(['error' => 'Interval between start and end date should be equal to or less than 7 days.']);
	http_response_code(400);
	exit();
}

$ch = curl_init();
$requestUri = "https://api.nasa.gov/neo/rest/v1/feed?start_date=".$start_date."&end_date=".$end_date."&api_key=" . $NASA_API_KEY;
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, $requestUri);
curl_setopt($ch, CURLOPT_SSH_COMPRESSION, true);
curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_URL => $requestUri
]);
$result = curl_exec($ch);

curl_close($ch);

$data = json_decode($result);

if ($data->code != 200) {
	echo json_encode(['error' => $data->error_message]);
	http_response_code(400);
	exit();
}

$near_earth_objects_dates = $data->near_earth_objects;
$near_earth_objects = [];

//echo $near_earth_objects;
foreach ($near_earth_objects_dates as $objects_in_day) {
	foreach ($objects_in_day as $o) {
		$close_approach_data_array = $o->close_approach_data;
		$close_approach_data = $close_approach_data_array[0];

		if ( count($close_approach_data_array) > 1 ) {
			for ($i = 1; $i < count($close_approach_data_array); $i++) {
				$curr_item = $close_approach_data_array[$i];
				if ((float) $close_approach_data->miss_distance->kilometers > (float) $curr_item->miss_distance->kilometers) {
					$close_approach_data = $curr_item;
				}
			}
		}
		
		$near_earth_objects[] = [
			'name' => $o->name,
			'time' => $close_approach_data->close_approach_date_full,
			'distance' => (float) $close_approach_data->miss_distance->kilometers,
			'estimated_size' => $o->estimated_diameter->kilometers
		];
	}
}

usort($near_earth_objects, function($first, $second) {
	if ($first['distance'] == $second['distance']) {
        return 0;
    }
    return ($first['distance'] < $second['distance']) ? -1 : 1;
});

echo json_encode($near_earth_objects);
exit();

?>