<!DOCTYPE html>
<html>
<head>
	<title>Catchbox</title>
	<link rel="stylesheet" href="/style.css">
</head>
<body>
<?php

srand(time());

$sites = array(
    "home"     =>  array ( "name" => "Home"  ),
    "login"    =>  array ( "name" => "Login" ),
    "register" =>  array ( "name" => "Register" ),
    "files"    =>  array ( "name" => "Files" ),
    "users"    =>  array ( "name" => "Users" ),
    "about"    =>  array ( "name" => "About" )
);

$db = null;
$login = "";

function load() {
	global $db;
	if ($db === null)
		$db = new SQLite3("db.sqlite");
	return $db;
}

function banner($msg) {
	echo "<div class=error>" . $msg . "</div>";
}

function serv_post() {
	global $db, $login;
	if ($_POST["action"] == "register") {
		if (!isset($_POST["username"]) || !isset($_POST["password"])) {
			banner("Missing username / password");
			return "home";
		}

		$db = load();
		$q = $db->prepare("SELECT user FROM users WHERE user = :user");
		$q->bindValue(":user", $_POST["username"]);
		$res = $q->execute();
		if ($res !== false && $res->fetchArray() !== false) {
			banner("User already exists");
			return "home";
		}

		$auth = md5($_POST["username"] . strval(rand()));
		$q = $db->prepare("INSERT INTO users (user, pass, creat, auth) "
			. "VALUES (:user, :pass, :creat, :auth)");
		$q->bindValue(":user", $_POST["username"], SQLITE3_TEXT);
		$q->bindValue(":pass", $_POST["password"], SQLITE3_TEXT);
		$q->bindValue(":creat", time(), SQLITE3_INTEGER);
		$q->bindValue(":auth", $auth, SQLITE3_TEXT);
		$res = $q->execute();
		if ($res == false) {
			banner("Failed to create user");
			return "home";
		}

		$login = $_POST["username"];
		setcookie("session", $auth);

		return "files";
	} else if ($_POST["action"] == "login") {
		if (!isset($_POST["username"]) || !isset($_POST["password"])) {
			banner("Missing username / password");
			return "home";
		}

		$db = load();
		$q = $db->prepare("SELECT auth FROM users WHERE user = :user AND pass = :pass");
		$q->bindValue(":user", $_POST["username"], SQLITE3_TEXT);
		$q->bindValue(":pass", $_POST["password"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res == false || ($row = $res->fetchArray()) === false) {
			banner("Invalid username / password");
			return "home";
		}
		$auth = $row[0];

		$login = $_POST["username"];
		setcookie("session", $auth);

		return "files";
	} else if ($_POST["action"] == "upload") {
		if (!isset($_COOKIE["session"]))  {
			banner("Not authenticated");
			return "files";
		}

		if (!isset($_POST["filename"]) || !isset($_POST["content"])) {
			banner("Missing content or filename");
			return "files";
		}

		if (strlen($_POST["filename"]) > 20) {
			banner("Filename to long");
			return "files";
		}

		if (strlen($_POST["content"]) > 2**10) {
			banner("Content too long");
			return "files";
		}

		$db = load();
		$q = $db->prepare("SELECT uid, user, auth from users WHERE auth = :auth");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			banner("Invalid session");
			return "files";
		}
		$uid = $row[0];
		$login = $row[1];
		$auth = $row[2];

		$filename = explode("/", $_POST["filename"], 2)[0];
		if (strpos($filename, "..") !== false || $filename === "") {
			banner("Invalid file name");
			return "files";
		}

		$dirpath = "files/" . $auth;
		if (!is_dir($dirpath) && mkdir($dirpath) === false) {
			banner("User directory create failed (fs)");
			return "files";
		}

		$filepath = $dirpath . "/" . $filename;
		if (file_exists($filepath)) {
			banner("File already exists");
			return "files";
		}

		$f = fopen($filepath, "w+");
		if ($f === false) {
			banner("File upload failed (fs)");
			return "files";
		}
		fwrite($f, $_POST["content"]);
		fclose($f);

		$q = $db->prepare("INSERT INTO files (uid, file, content, creat) "
			. "VALUES (:uid, :file, :content, :creat)");
		$q->bindValue(":uid", $uid, SQLITE3_INTEGER);
		$q->bindValue(":file", $_POST["filename"], SQLITE3_TEXT);
		$q->bindValue(":content", $_POST["content"], SQLITE3_TEXT);
		$q->bindValue(":creat", time(), SQLITE3_INTEGER);
		$res = $q->execute();
		if ($res === false) {
			banner("File upload failed (db)");
			return "files";
		}

		return "files";
	}

	return "home";
}

function serv() {
	global $db, $login;
	if ($_SERVER["REQUEST_METHOD"] === "POST") {
		$site = serv_post();
	} else {
		if (isset($_GET["q"]))
			$site = $_GET['q'];
		else
			$site = "home";

		if ($site == "logout") {
			setcookie("session", "", 1);
			return "home";
		}

		if (!isset($_COOKIE["session"]))
			return $site;

		$db = load();
		$q = $db->prepare("SELECT user FROM users WHERE auth = :auth");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			banner("Invalid session");
			return $site;
		}
		$login = $row[0];
	}

	return $site;
}

$site = serv();

?>
	<div class="navbar">
		<img src="/imgs/logo.png"></img>
		<ul>
<?php

foreach ($sites as $qname => $info) {
	if ($site == $qname) {
		echo '<li><a class="active" href="/?q=' . $qname . '">' . $info["name"] . '</a></li>';
	} else {
		if ($qname != "login" && $qname != "register" || $login == "") {
			echo '<li><a href="/?q=' . $qname . '">' . $info["name"] . '</a></li>';
		}
	}
}

?>
		</ul>
	</div>
	<div class="main">
		<div class="login">
<?php

if ($login != "") {
	echo '<a href="/?q=logout">logged in (' . $login . ')</a>';
} else {
	echo '<a href="/?q=login">log in</a>';
}

?>
		</div>
<?php
if ($site == "home") {
	echo '
		<div class="text">
			<h1>Welcome to Catchbox&#8482</h1>
			<p>Host your files with us! Register now and get 5GB for free!&#185</p>
		<div>
		<div class="footer">
			&#185 Terms and conditions apply.
		</div>';
} else if ($site == "login") {
	echo '
		<div class="text">
			<form action="index.php" method="post" class="login-form">
				<h2>Login:</h2>
				<input class="txtinput" name="username" type="text" placeholder="username"></input>
				<input class="txtinput" name="password" type="password" placeholder="password"></input>
				<input type="hidden" name="action" value="login">
				<input type="submit">
				<a class=hint href=/?q=register>Need an account?</a>
			</form>
		<div>';
} else if ($site == "register") {
	echo '
		<div class="text">
			<form action="index.php" method="post" class="login-form">
				<h2>Register:</h2>
				<input class="txtinput" name="username" type="text" placeholder="username"></input>
				<input class="txtinput" name="password" type="password" placeholder="password"></input>
				<input type="hidden" name="action" value="register">
				<input type="submit">
			</form>
		<div>';
} else if ($site == "files") {
	echo '
		<div class="text">
			<h2>Currently hosted files:</h2>
			<ul class="filelist mslist userlist">';
	$db = load();
	$q = $db->prepare("SELECT file, creat FROM files WHERE "
		. "uid = (SELECT uid FROM users WHERE user = :user)");
	$q->bindValue(":user", $login, SQLITE3_TEXT);
	$res = $q->execute();
	while (($row = $res->fetchArray())) {
		$date = date("Y-m-d H:i:s", $row[1]);
		echo '<a href="/uploads/' . $_COOKIE["session"] . '/' . $row[0] . '" class=mfile>';
		echo '<li><p class="front">' . $row[0];
		echo '</p><p class="back">' . $date . '</p></li></a>';
	}
	echo '
			</ul>
			<form action="index.php" method="post" class="upload-form">
				<h2>Upload a file:</h2>
				<input type=text name="filename" placeholder="name"></input><br>
				<input type=text name="content" placeholder="content"></input><br>
				<input type=hidden name="action" value="upload">
				<input type=submit>
			</form>
		<div>';
} else if ($site == "users") {
	echo '
		<div class="text">
			<h2>Currently registered users:</h2>
			<ul class="mslist userlist">';
	$db = load();
	$res = $db->query("SELECT user, creat FROM users");
	while (($row = $res->fetchArray())) {
		$date = date("Y-m-d H:i:s", $row[1]);
		echo '<li><p class="front">' . $row[0] . '</p>';
		echo '<p class="back">' . $date . '</p></li>';
	}
	echo '
			</ul>
		<div>';
} else if ($site == "about") {
	echo '
		<div class="text">
			<h1>We value our employees:</h1>
			Become a member of our team today!
			<div class="employee-pics">
				<img width="100%" src="/imgs/work.jpg"/>
				<img width="100%" src="/imgs/work2.jpg"/>
			</div>
		</div>
';
}
?>
			</div>
	</body>
</html>
