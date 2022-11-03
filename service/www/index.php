<?php

$sites = array(
	"home"     =>  array ( "name" => "Home"  ),
	"login"    =>  array ( "name" => "Login" ),
	"register" =>  array ( "name" => "Register" ),
	"files"    =>  array ( "name" => "Files" ),
	"users"    =>  array ( "name" => "Users" ),
	"report"   =>  array ( "name" => "Contact" ),
	"about"    =>  array ( "name" => "About" )
);

$db = null;
$login = "";

$wrotehead = false;
$head = <<<EOF
<!DOCTYPE html>
<html>
<head>
	<title>Catchbox</title>
	<link rel="stylesheet" href="/style.css">
</head>
<body>
EOF;

srand(time());

function load() {
	global $db;
	if ($db === null) {
		/* https://phiresky.github.io/blog/2020/sqlite-performance-tuning/ */
		$db = new SQLite3("db.sqlite");
		$db->busyTimeout(15000);
		$db->exec("PRAGMA journal_mode = WAL;");
		$db->exec("PRAGMA synchronous = normal;");
		$db->exec("PRAGMA temp_storage = memory;");
		$db->exec("PRAGMA mmap_size = 30000000000;");
		$db->exec("PRAGMA page_size = 32768;");
	}
	return $db;
}

function writehead() {
	global $head, $wrotehead;
	if (!$wrotehead) {
		echo $head;
	}
	$wrotehead = true;
}

function banner($msg) {
	http_response_code(400);
	writehead();
	echo "<div class=error>" . $msg . "</div>";
}

function alphok($text) {
	return preg_match("#^[a-zA-Z0-9\.\-_äöüÄÖÜ]*$#", $text);
}

function quit() {
	global $db;
	if ($db !== null)
		$db->close();
	exit();
}

function redirect($url) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: $url");
	quit();
}

function serv_file($path) {
	$path = realpath($path);
	if ($path === false || strpos($path, "/service/files/") !== 0
			&& strpos($path, "/service/reports/") !== 0) {
		header("HTTP/1.1 404 Not Found");
		quit();
	}

	$mime = mime_content_type($path);
	if ($mime !== false) {
		header("Content-Type: " . $mime);
	}
	echo file_get_contents($path);
	quit();
}

function serv_post() {
	global $db, $login;
	if ($_POST["action"] == "register") {
		if (!isset($_POST["username"]) || !isset($_POST["password"])) {
			banner("Missing username / password");
			return "home";
		}

		if (!alphok($_POST["username"]) || strlen($_POST["username"]) > 100) {
			banner("Invalid username");
			return "home";
		}

		if (strlen($_POST["password"]) > 100) {
			banner("Invalid password");
			return "home";
		}

		$db = load();
		$q = $db->prepare("SELECT user FROM users WHERE user = :user");
		$q->bindValue(":user", $_POST["username"]);
		$res = $q->execute();
		if ($res !== false && $res->fetchArray() !== false) {
			$q->close();
			banner("User already exists");
			return "home";
		}
		$q->close();

		$auth = md5($_POST["username"] . $_POST["password"]);
		$q = $db->prepare("INSERT INTO users (user, pass, creat, auth) "
			. "VALUES (:user, :pass, :creat, :auth)");
		$q->bindValue(":user", $_POST["username"], SQLITE3_TEXT);
		$q->bindValue(":pass", $_POST["password"], SQLITE3_TEXT);
		$q->bindValue(":creat", time(), SQLITE3_INTEGER);
		$q->bindValue(":auth", $auth, SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false) {
			$q->close();
			banner("Failed to insert user: " . $db->lastErrorMsg());
			return "home";
		}
		$q->close();

		$login = $_POST["username"];
		setcookie("session", $auth);

		return "files";
	} else if ($_POST["action"] === "login") {
		if (!isset($_POST["username"]) || !isset($_POST["password"])) {
			banner("Missing username / password");
			return "home";
		}

		$db = load();
		$q = $db->prepare("SELECT auth FROM users WHERE user = :user AND pass = :pass");
		$q->bindValue(":user", $_POST["username"], SQLITE3_TEXT);
		$q->bindValue(":pass", $_POST["password"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("Invalid credentials");
			return "home";
		}
		$auth = $row[0];
		$q->close();

		$login = $_POST["username"];
		setcookie("session", $auth);

		return "files";
	} else if ($_POST["action"] === "upload") {
		if (!isset($_COOKIE["session"]))  {
			banner("Not authenticated");
			return "files";
		}

		if (!isset($_POST["filename"]) || !isset($_POST["content"])) {
			banner("Missing content or filename");
			return "files";
		}

		if (strlen($_POST["filename"]) > 100) {
			banner("Invalid filename");
			return "files";
		}

		if (strlen($_POST["content"]) > 1024) {
			banner("File too large");
			return "files";
		}

		$db = load();
		$q = $db->prepare("SELECT uid, user from users WHERE auth = :auth");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("Invalid session");
			setcookie("session", "", 1);
			return "files";
		}
		$uid = $row[0];
		$user = $row[1];
		$login = $user;
		$q->close();

		$parts = explode("/", $_POST["filename"]);
		$filename = end($parts);
		foreach ($parts as $part) {
			if (strpos($part, "..") != false) {
				banner("Invalid filename");
				return "files";
			}
		}

		$dir = md5($user . $filename . strval(rand()));
		$dirpath = "files/" . $dir;
		if (is_dir($dirpath) || mkdir($dirpath) === false) {
			banner("File directory already exists");
			return "files";
		}

		$filepath = $dirpath . "/" . $filename;
		if (is_file($filepath)) {
			banner("File already exists");
			return "files";
		}

		$f = fopen($filepath, "w+");
		if ($f === false) {
			banner("File create failed");
			return "files";
		}
		fwrite($f, $_POST["content"]);
		fclose($f);

		$q = $db->prepare("INSERT INTO files (uid, file, dir, creat) "
			. "VALUES (:uid, :file, :dir, :creat)");
		$q->bindValue(":uid", $uid, SQLITE3_INTEGER);
		$q->bindValue(":file", $_POST["filename"], SQLITE3_TEXT);
		$q->bindValue(":dir", $dir, SQLITE3_TEXT);
		$q->bindValue(":creat", time(), SQLITE3_INTEGER);
		$res = $q->execute();
		if ($res === false) {
			$q->close();
			banner("Failed to insert file: " . $db->lastErrorMsg());
			return "files";
		}
		$q->close();

		return "files";
	} else if ($_POST["action"] == "report") {
		if (!isset($_COOKIE["session"]))  {
			banner("Not authenticated");
			return "files";
		}

		if (!isset($_POST["content"])) {
			banner("Missing content or filename");
			return "files";
		}

		if (strlen($_POST["content"]) > 1024) {
			banner("Report too long");
			return "files";
		}

		$db = load();
		$q = $db->prepare("SELECT uid, user from users WHERE auth = :auth");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("Invalid session");
			setcookie("session", "", 1);
			return "report";
		}
		$uid = $row[0];
		$user = $row[1];
		$q->close();

		$login = $user;
		$file = md5($user);

		$q = $db->prepare("INSERT INTO reports (uid, file, creat) "
			. "VALUES (:uid, :file, :creat)");
		$q->bindValue(":uid", $uid, SQLITE3_INTEGER);
		$q->bindValue(":file", $file, SQLITE3_TEXT);
		$q->bindValue(":creat", time(), SQLITE3_INTEGER);
		$res = $q->execute();
		if ($res === false) {
			$q->close();
			banner("Failed to insert report: " . $db->lastErrorMsg());
			return "files";
		}
		$q->close();

		$filepath = "reports/" . $file;
		if (is_file($filepath)) {
			banner("Report already exists");
			return "report";
		}

		$f = fopen($filepath, "w+");
		if ($f === false) {
			banner("Report create failed");
			return "files";
		}
		fwrite($f, $_POST["content"]);
		fclose($f);

		return "report";
	}

	return "home";
}

function serv() {
	global $db, $login;
	if ($_SERVER["REQUEST_METHOD"] === "POST") {
		$site = serv_post();
	} else if (isset($_GET["f"])) {
		if (!isset($_COOKIE["session"]))  {
			banner("Not authenticated");
			return "files";
		}

		$db = load();
		$q = $db->prepare("SELECT uid from users WHERE auth = :auth");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("Invalid session");
			setcookie("session", "", 1);
			return "files";
		}
		$uid = $row[0];
		$q->close();

		$q = $db->prepare("SELECT dir, file from files "
			. "WHERE uid = :uid and file = :file");
		$q->bindValue(":uid", $uid, SQLITE3_INTEGER);
		$q->bindValue(":file", $_GET["f"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("No such file");
			return "files";
		}
		$path = "files/" . $row[0] . "/" . $row[1];
		$q->close();

		serv_file($path);
	} else if (isset($_GET["r"])) {
		if (!isset($_COOKIE["session"]))  {
			banner("Not authenticated");
			return "files";
		}

		$db = load();
		$q = $db->prepare("SELECT file from reports WHERE "
			. "uid = (SELECT uid FROM users WHERE auth = :auth)");
		$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
		$res = $q->execute();
		if ($res === false || ($row = $res->fetchArray()) === false) {
			$q->close();
			banner("Invalid session");
			setcookie("session", "", 1);
			return "files";
		}
		$path = "reports/" . $row[0];
		$q->close();

		serv_file($path);
	} else {
		if (isset($_GET["q"]))
			$site = $_GET['q'];
		else
			$site = "home";
	}

	if ($site == "logout") {
		setcookie("session", "", 1);
		$login = "";
		return "home";
	}

	return $site;
}

$site = serv();

if (isset($_COOKIE["session"]) && $login === "") {
	$db = load();
	$q = $db->prepare("SELECT user FROM users WHERE auth = :auth");
	$q->bindValue(":auth", $_COOKIE["session"], SQLITE3_TEXT);
	$res = $q->execute();
	if ($res === false || ($row = $res->fetchArray()) === false) {
		$q->close();
		banner("Invalid session");
		setcookie("session", "", 1);
		return $site;
	}
	$login = $row[0];
	$q->close();
}

writehead();
?>
	<div class="navbar">
		<img src="/static/logo.png"></img>
		<ul>
<?php

foreach ($sites as $qname => $info) {
	if ($site == $qname) {
		echo '<li><a class="active" href="/?q=' . $qname . '">' . $info["name"] . '</a></li>';
	} else {
		if ($login != "" && $qname != "login" && $qname != "register"
				|| $login == "" && $qname != "files" && $qname != "report") {
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
			<h2>Your hosted files:</h2>
			<ul class="mslist filelist">';
	$db = load();
	$q = $db->prepare("SELECT file, dir FROM files WHERE "
		. "uid = (SELECT uid FROM users WHERE user = :user)");
	$q->bindValue(":user", $login, SQLITE3_TEXT);
	$res = $q->execute();
	while (($row = $res->fetchArray())) {
		$pub = "/uploads/" . $row[1] . "/" . $row[0];
		$priv = "/index.php?f=" . $row[0];
		echo '
				<li>
					<p class="front">
						<a class="mfile" href="' . $priv . '">' . $row[0] . '</a>
					</p>
					<p class="back">
						<a class="textref" href="' . $pub . '">share</a>
					</p>
				</li>';
	}
	$q->close();
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
			<h2>Registered users:</h2>
			<ul class="mslist userlist">';
	$db = load();
	$res = $db->query("SELECT user, creat FROM users ORDER BY creat DESC");
	while (($row = $res->fetchArray())) {
		$date = date("Y-m-d H:i:s", $row[1]);
		echo '<li><p class="front">' . $row[0] . '</p>';
		echo '<p class="back">' . $date . '</p></li>';
	}
	echo '
			</ul>
		<div>';
} else if ($site == "report") {
	echo '
		<div class="text">';

	$db = load();
	$q = $db->prepare("SELECT file FROM reports WHERE "
		. "uid = (SELECT uid FROM users WHERE user = :user)");
	$q->bindValue(":user", $login, SQLITE3_TEXT);
	$res = $q->execute();
	if ($res === false || ($row = $res->fetchArray()) === false) {
		echo'
			<h2>We would love to hear from you!</h2>
			<form action="index.php" method="post" class="upload-form">
				<h2>Submit feedback:</h2>
				<input type=text name="content"></input><br>
				<input type=hidden name="action" value="report">
				<input type=submit>
			</form>';
	} else {
		echo'
			<h2>Thank you for your feedback.</h2>
			You can view your feedback <a class=textref href="/?r">here</a>';
	}
	$q->close();
	echo '
		<div>';
} else if ($site == "about") {
	echo '
		<div class="text">
			<h1>We value our employees:</h1>
			Become a member of our team today!
			<div class="employee-pics">
				<img width="100%" src="/static/work.jpg"/>
				<img width="100%" src="/static/work2.jpg"/>
			</div>
		</div>
';
}
?>
			</div>
	</body>
</html>

<?php
	quit();
?>
