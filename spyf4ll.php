<?php
//setup database
$servername = "localhost";
$username = "<username>";
$password = "<password>";
$database = "spyfall";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

//set up the Bot API token
$botToken = "<botToken>";
$website = "https://api.telegram.org/bot".$botToken;

//ip whitelist
$ip_allowed = false;
$whitelist = array('127.0.0.1');
$whitelist_range = array(array('149.154.167', 197, 233)); //149.154.167.197-233

if(in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
	$ip_allowed = true;
}
else {
	$intruder = explode('.', $_SERVER['REMOTE_ADDR']);
	foreach ($whitelist_range as $range_ip) {
		$ip_allowed = true;
		$test_ip = explode('.', $range_ip[0]);
		for ($i=0; $i < 3; $i++) { 
			if($test_ip[$i] != $intruder[$i]) {
				$ip_allowed = false;
				break;
			}
		}
		if($range_ip[1] > intval($intruder[3]) || intval($intruder[3]) > $range_ip[2]) {
			$ip_allowed = false;
		}
		else {
			break;
		}
	}
}

if(!$ip_allowed) {
	$debugAnswer = $_SERVER['REMOTE_ADDR']." tried to access your server.";
	$fullhttp = $website."/sendmessage?chat_id=<chat_id>&text=".urlencode($debugAnswer);
	file_get_contents($fullhttp);
	die("don't trust you..");
}

//Grab the info from the webhook, parse it and put it into $message
$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);
$message = $update["message"];

//Make some helpful variables
$userId = $message["from"]["id"];
$gameId = "gameId";
$text = $message["text"];
$answer = "";
$language = "language";
$languages = json_decode(file_get_contents('spyf4ll_language.json'), true);

//functions
function check() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	$sql = "SELECT * FROM users;";
	$result = $conn->query($sql);

	$user_exists = false;

	if ($result->num_rows > 0) {
	    while($row = mysqli_fetch_object($result)) {
	        if( strcmp($row->id, $userId) === 0 ) {
	            $user_exists = true;
	            $gameId = $row->game;
	            $language = $row->language;
	            break;
	        }
	    }
	}

	if(!$user_exists) {
		$language = "english";
		$sql = "INSERT INTO users VALUES($userId, \"noGame\", $language);";
		$conn->query($sql);
	}
}

function create_game() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	$sql = "SELECT * FROM users;";
	$result = $conn->query($sql);
	$gameExists = true;
	$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);

	while($gameExists) {
		$gameExists = false;
		$gameId = "";
		for ($i = 0; $i < 5; $i++) {
	        $gameId .= $characters[rand(0, $charactersLength - 1)];
	    }

		if ($result->num_rows > 0) {
		    while($row = mysqli_fetch_object($result)) {
		        if( strcmp($row->game, $gameId) === 0 ) {
		            $gameExists = true;
		            break;
		        }
		    }
		}
	}

	$sql = "UPDATE users SET game=\"$gameId\" WHERE id=\"$userId\"";
	$conn->query($sql);

	return $languages[$language][responses][create_game].": ".$gameId;
}

function join_game($id) {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	$sql = "SELECT * FROM users;";
	$result = $conn->query($sql);
	$gameExists = $languages[$language][responses][join_game][notexist];

	if(empty($id)) {
		return $languages[$language][responses][join_game][noGame];
	}

	if ($result->num_rows > 0) {
	    while($row = mysqli_fetch_object($result)) {
	        if( strcmp($row->game, $id) === 0 ) {
	            $gameExists = $languages[$language][responses][join_game][exists];
	            $sql = "UPDATE users SET game=\"$id\" WHERE id=\"$userId\"";
				$conn->query($sql);
				$gameId = $id;
	            break;
	        }
	    }
	}

	return $gameExists;
}

function leave_game() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	$sql = "DELETE FROM users WHERE id=\"$userId\"";
	$result = $conn->query($sql);
	$gameExists = $languages[$language][responses][leave_game];
	return $gameExists;
}

function start_game($lan) {
	global $conn;
	global $userId;
	global $gameId;
	global $website;
	global $language;
	global $languages;
	$locations;
	$playerIds=array();
	$playerRole=array();

	$validLanguage = false;
	if(empty($lan)) {
		$validLanguage = true;
	}
	else{
		foreach ($languages[languages] as $lang) {
			if(in_array($lan, $languages[$lang][language_var])) {
				$language = $lang;
				$validLanguage = true;
			}
		}
	}
	if(!$validLanguage) {
		return $languages[$language][responses][language][invalid];
	}

	$locations = json_decode(file_get_contents($languages[$language][locations_file]), true);
	$location = rand(0, sizeof($locations)-1);

	if(strcmp($gameId, "noGame") === 0) {
		return $languages[$language][responses][start_game][noGame];
	}

	$sql = "SELECT * FROM users;";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	    while($row = mysqli_fetch_object($result)) {
	        if( strcmp($row->game, $gameId) === 0 ) {
	            array_push($playerIds, $row->id);
	        }
	    }
	}
	if(sizeof($playerIds) > 8) {
		return $languages[$language][responses][start_game][full];
	}
	elseif(sizeof($playerIds) < 3) {
		return $languages[$language][responses][start_game][less];
	}

	for($i = 0; $i < sizeof($playerIds); $i++) {
		array_push($playerRole, $i);
	}
	shuffle($playerRole);

	for($i = 0; $i < sizeof($playerIds); $i++) {
		$message = "";
		if($playerRole[$i] === 0) {
			$message = $languages[$language][responses][start_game][location].": ???\n".$languages[$language][responses][start_game][role].": ".$languages[$language][responses][start_game][spy];
		} else {
			$message = $languages[$language][responses][start_game][location].": ".$locations[$location][name]."\n".$languages[$language][responses][start_game][role].": ".$locations[$location][roles][$playerRole[$i]-1];
		}
		$message.="\n".$languages[$language][responses][start_game][nrofplayers].": ".sizeof($playerIds);
		if($playerIds[$i][0] != 't') {
			file_get_contents($website."/sendmessage?chat_id=".$playerIds[$i]."&text=".urlencode($message));
		}
	}

	return "";
}

function list_locations($lan) {
	global $language;
	global $languages;

	$validLanguage = false;
	if(empty($lan)) {
		$validLanguage = true;
	}
	else{
		foreach ($languages[languages] as $lang) {
			if(in_array($lan, $languages[$lang][language_var])) {
				$language = $lang;
				$validLanguage = true;
			}
		}
	}
	if(!$validLanguage) {
		return $languages[$language][responses][language][invalid];
	}

	$locations = json_decode(file_get_contents($languages[$language][locations_file]), true);
	$list_locations_return = "";
	foreach ($locations as $key => $location) {
		$list_locations_return.=$location[name];#
		if($key < sizeof($locations)-1) {
			$list_locations_return.=", ";
		}
	}

	return $list_locations_return;
}

function language($lan) {
	global $conn;
	global $userId;
	global $language;
	global $languages;

	if(empty($lan)) {
		return $languages[$language][responses][language][nolanguage];
	}

	$validLanguage = false;
	foreach ($languages[languages] as $lang) {
		if(in_array($lan, $languages[$lang][language_var])) {
			$language = $lang;
			$validLanguage = true;
		}
	}
	if(!$validLanguage) {
		return $languages[$language][responses][language][invalid];
	}
	else {
		$sql = "UPDATE users SET language=\"$language\" WHERE id=\"$userId\"";
		$conn->query($sql);
		return $languages[$language][responses][language][changed];
	}
}

function help($lan) {
	global $language;
	global $languages;

	$validLanguage = false;
	if(empty($lan)) {
		$validLanguage = true;
	}
	else{
		foreach ($languages[languages] as $lang) {
			if(in_array($lan, $languages[$lang][language_var])) {
				$language = $lang;
				$validLanguage = true;
			}
		}
	}
	if(!$validLanguage) {
		return $languages[$language][responses][language][invalid];
	}

	$help_return = $languages[$language][responses][help][help].":\n/create - ".$languages[$language][responses][help][create]."\n/join <".$languages[$language][responses][help][join_code]."> - ".$languages[$language][responses][help][join]."\n/leave - ".$languages[$language][responses][help][leave]."\n/play <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][play]."\n/list <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][list_locations]."\n/language <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][language]."\n/help <".$languages[$language][responses][help][play_language].">- ".$languages[$language][responses][help][help2];

	return $help_return;
}

//work
check();
switch(strtok($text, " ")) {
	case '/create':
		$answer = create_game();
		break;

	case '/join':
		$answer = join_game(strtok(" "));
		break;

	case '/leave':
		$answer = leave_game();
		break;

	case '/play':
		$answer = start_game(strtok(" "));
		break;

	case '/list':
		$answer = list_locations(strtok(" "));
		break;

	case '/language':
		$answer = language(strtok(" "));
		break;

	case '/help':
		$answer = help(strtok(" "));
		break;

	default:
		$answer = $languages[$language][responses][invalid];
		break;
}

//answer
$fullhttp = $website."/sendmessage?chat_id=".$userId."&text=".urlencode($answer);
file_get_contents($fullhttp);

//close connection
mysqli_close($conn);
?>
