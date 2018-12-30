<?php
//todo
//Friedhof als Ort.. Rollen?
//Ortauswahl möglich
//Impressum mit testern
//proportionale Rollen

//setup database
$servername = "localhost";
$username = <username>;
$password = <password>;
$database = "spyfall";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

//set up the Bot API token
$botToken = <telegram bot api token>;
$website = "https://api.telegram.org/bot".$botToken;

//ip whitelist
$ip_allowed = false;
$whitelist = array('127.0.0.1');
$whitelist_range = array(array('149.154.167', 197, 233)); //current telegram ip range 149.154.167.197-233

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
	$fullhttp = $website."/sendmessage?chat_id=406199388&text=".urlencode($debugAnswer);
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
$language = "english";
$status = "status";
$languages = json_decode(file_get_contents('spyf4ll_language.json'), true);

//functions
function check() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $status;
	$sql = "SELECT * FROM users;";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	    while($row = mysqli_fetch_object($result)) {
	        if( strcmp($row->id, $userId) === 0 ) {
	            $gameId = $row->game;
	            $language = $row->language;
	            $status = $row->status;
	            break;
	        }
	    }
	}
}

function register() {
	global $conn;
	global $userId;
	global $language;

	if($stmt = $conn->prepare("INSERT INTO users VALUES(?, \"noGame\", ?, \"subscribed\")")) {
		$stmt->bind_param("ss", $userId, $language);
		$stmt->execute();
		$stmt->close();
	}

	return "Welcome to the Spyfall bot!";
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
	        $gameId .= $characters[mt_rand(0, $charactersLength - 1)];
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

	if($stmt = $conn->prepare("UPDATE users SET game=? WHERE id=?")) {
		$stmt->bind_param("ss", $gameId, $userId);
		$stmt->execute();
		$stmt->close();
	}

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
				if($stmt = $conn->prepare("UPDATE users SET game=? WHERE id=?")) {
					$stmt->bind_param("ss", $id, $userId);
					$stmt->execute();
					$stmt->close();
				}
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
	if($stmt = $conn->prepare("UPDATE users SET game =\"noGame\" WHERE id=?")) {
		$stmt->bind_param("s", $userId);
		$stmt->execute();
		$stmt->close();
	}
	$gameExists = $languages[$language][responses][leave_game];
	return $gameExists;
}

function delete() {
	global $conn;
	global $userId;
	global $language;
	global $languages;
	if($stmt = $conn->prepare("DELETE FROM users WHERE id=?")) {
		$stmt->bind_param("s", $userId);
		$stmt->execute();
		$stmt->close();
	}
	$gameExists = $languages[$language][responses][leave_game];
	return $gameExists;
}

function start_game($lan, $arg) {
	global $conn;
	global $userId;
	global $gameId;
	global $website;
	global $language;
	global $languages;
	$locations;
	$playerIds=array();
	$playerRole=array();
	$roles_enabled = true;
	$games_enabled = false;
	$mode=mt_rand(0,999);

	$validLanguage = false;
	if(empty($lan)) {
		$validLanguage = true;
	}
	elseif(strcmp($lan, "noRole") === 0) {
		$roles_enabled = false;
		$validLanguage = true;
	}
	elseif(strcmp($lan, "games") === 0) {
		$roles_enabled = false;
		$games_enabled = true;
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
	else {
		if(strcmp($arg, "noRole") === 0) {
			$roles_enabled = false;
		}

		if(strcmp($arg, "games") === 0) {
			$roles_enabled = false;
			$games_enabled = true;
		}
	}

	if($games_enabled) {
		$locations = json_decode(file_get_contents("spyf4ll_games.json"), true);
	}
	else {
		$locations = json_decode(file_get_contents($languages[$language][locations_file]), true);
	}
	$location = mt_rand(0, sizeof($locations)-1);

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

	for($i = 0; $i < mt_rand(1, 10); $i++) {
		shuffle($playerRole);
	}

	for($i = 0; $i < sizeof($playerIds); $i++) {
		$message = "";
		if($mode === 0) {
			$message = $languages[$language][responses][start_game][location].": ???\n".$languages[$language][responses][start_game][role].": ".$languages[$language][responses][start_game][spy];
		}
		elseif($mode === 1) {
			$message = $languages[$language][responses][start_game][location].": ".$locations[$location][name];
			if($roles_enabled) {
				if($playerRole[$i] === 0) {
					$playerRole[$i] = 1;
				}
				$message.="\n".$languages[$language][responses][start_game][role].": ".$locations[$location][roles][$playerRole[$i]-1];
			}
		}
		elseif($mode === 2) {
			if($playerRole[$i] === 0 || $playerRole[$i] === 1) {
				$message = $languages[$language][responses][start_game][location].": ???\n".$languages[$language][responses][start_game][role].": ".$languages[$language][responses][start_game][spy];
			} else {
				$message = $languages[$language][responses][start_game][location].": ".$locations[$location][name];
				if($roles_enabled) {
					$message.="\n".$languages[$language][responses][start_game][role].": ".$locations[$location][roles][$playerRole[$i]-1];
				}
			}
		}
		elseif($mode === 3) {
			if($playerRole[$i] === 0 || $playerRole[$i] === 1) {
				$message = $languages[$language][responses][start_game][location].": ???\n".$languages[$language][responses][start_game][role].": ".$languages[$language][responses][start_game][spy]." ".($playerRole[$i]+1)."/2";
			} else {
				$message = $languages[$language][responses][start_game][location].": ".$locations[$location][name];
				if($roles_enabled) {
					$message.="\n".$languages[$language][responses][start_game][role].": ".$locations[$location][roles][$playerRole[$i]-1];
				}
			}
		}
		else {
			if($playerRole[$i] === 0) {
				$message = $languages[$language][responses][start_game][location].": ???\n".$languages[$language][responses][start_game][role].": ".$languages[$language][responses][start_game][spy];
			} else {
				$message = $languages[$language][responses][start_game][location].": ".$locations[$location][name];
				if($roles_enabled) {
					$message.="\n".$languages[$language][responses][start_game][role].": ".$locations[$location][roles][$playerRole[$i]-1];
				}
			}
		}
		$message.="\n".$languages[$language][responses][start_game][nrofplayers].": ".sizeof($playerIds);
		file_get_contents($website."/sendmessage?chat_id=".$playerIds[$i]."&text=".urlencode($message));
	}

	file_get_contents($website."/sendmessage?chat_id=".$playerIds[mt_rand(0, sizeof($playerIds)-1)]."&text=".urlencode($languages[$language][responses][start_game][firstTurn]));

	return "";
}

function list_locations($lan) {
	global $language;
	global $languages;

	$ticktock = true;

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
		$list_locations_return.=$location[name];
		if($key < sizeof($locations)-1) {
			if($ticktock) {
				$list_locations_return.=", ";
				$ticktock = false;
			}
			else {
				$list_locations_return.="\n";
				$ticktock = true;
			}
		}
	}

	return $list_locations_return;
}

function list_group($lan) {
	global $conn;
	global $gameId;
	global $language;
	global $languages;

	$playerIds=array();

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

	$locations = json_decode(file_get_contents($languages[$language][locations_file]), true);
	$list_group_return = "ID: ".$gameId."\n".$languages[$language][responses][start_game][nrofplayers].": ".sizeof($playerIds);

	return $list_group_return;
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
		if($stmt = $conn->prepare("UPDATE users SET language=? WHERE id=?")) {
			$stmt->bind_param("ss", $language, $userId);
			$stmt->execute();
			$stmt->close();
		}
		return $languages[$language][responses][language][changed];
	}
}

function rules($lan) {
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

	return $languages[$language][responses][rules];
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

	$help_return = $languages[$language][responses][help][help].":\n/create - ".$languages[$language][responses][help][create]."\n/join <".$languages[$language][responses][help][join_code]."> - ".$languages[$language][responses][help][join]."\n/leave - ".$languages[$language][responses][help][leave]."\n/play <".$languages[$language][responses][help][play_language].">; 'noRole' - ".$languages[$language][responses][help][play]."\n/list <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][list_locations]."\n/group <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][list_group]."\n/language <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][language]."\n/rules <".$languages[$language][responses][help][play_language]."> - ".$languages[$language][responses][help][rules]."\n/help <".$languages[$language][responses][help][play_language].">- ".$languages[$language][responses][help][help2];

	return $help_return;
}

function unsubscribe() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	if($stmt = $conn->prepare("UPDATE users SET status =\"unsubscribed\" WHERE id=?")) {
		$stmt->bind_param("s", $userId);
		$stmt->execute();
		$stmt->close();
	}
	$gameExists = $languages[$language][responses][leave_game];
	return $gameExists;
}

function subscribe() {
	global $conn;
	global $userId;
	global $gameId;
	global $language;
	global $languages;
	if($stmt = $conn->prepare("UPDATE users SET status =\"subscribed\" WHERE id=?")) {
		$stmt->bind_param("s", $userId);
		$stmt->execute();
		$stmt->close();
	}
	$gameExists = $languages[$language][responses][leave_game];
	return $gameExists;
}

function broadcast($feed) {
	global $conn;
	global $userId;
	global $status;
	global $website;
	global $language;
	global $languages;

	if(strcmp($status, "admin") === 0) {
		$sql = "SELECT id FROM users WHERE status = \"subscribed\";";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		    while($row = mysqli_fetch_object($result)) {
		    	file_get_contents($website."/sendmessage?chat_id=".$row->id."&text=".urlencode($feed));
		    }
		}
	}
	else {
		return "You got no permission for this command!";
	}

	return "";
}

function feedback($feed) {
	global $conn;
	global $userId;
	global $website;
	global $language;
	global $languages;

	$sql = "SELECT id FROM users WHERE status = \"admin\";";
	$result = $conn->query($sql);

	if(strcmp($feed, "seks") === 0) {
		return "Wolle Rose kaufen?";
	}
	else {
		if ($result->num_rows > 0) {
		    while($row = mysqli_fetch_object($result)) {
		    	file_get_contents($website."/sendmessage?chat_id=".$row->id."&text=".urlencode($userId.": ".$feed));
		    }
		}
		return $languages[$language][responses][feedback][thanks];
	}
}

function respond($id, $feed) {
	global $status;
	global $website;
	// global $language;
	// global $languages;

	if(strcmp($status, "admin") === 0) {
		file_get_contents($website."/sendmessage?chat_id=".$id."&text=".urlencode($feed));
	}
	else {
		return "You got no permission for this command!";
	}

	return "";
}

function info() {
	//todo
	return "Impressum, Version etc. comming soon.";
}

//work
check();
switch(strtok($text, " ")) {
	case '/start':
		$answer = register();
		break;

	case '/create':
		$answer = create_game();
		break;

	case '/join':
		$answer = join_game(strtok(" "));
		break;

	case '/leave':
		$answer = leave_game(strtok(" "));
		break;

	case '/play':
		$answer = start_game(strtok(" "), strtok(" "));
		break;

	case '/li':
	case '/list':
		$answer = list_locations(strtok(" "));
		break;

	case '/group':
		$answer = list_group(strtok(" "));
		break;

	case '/language':
		$answer = language(strtok(" "));
		break;

	case '/rules':
		$answer = rules(strtok(" "));
		break;

	case '/help':
		$answer = help(strtok(" "));
		break;

	case '/unsubscribe':
		$answer = unsubscribe();
		break;

	case '/subscribe':
		$answer = subscribe();
		break;

	case '/broadcast':
		$answer = broadcast(substr(strstr($text, " "), 1));
		break;

	case '/feedback':
		$answer = feedback(substr(strstr($text, " "), 1));
		break;

	case '/respond':
		$answer = respond(strtok(" "), substr(strstr(substr(strstr($text, " "), 1), " "), 1));
		break;

	case '/info':
		$answer = info();
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