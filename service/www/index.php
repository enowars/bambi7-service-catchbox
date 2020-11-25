<?php

$sites = array(
    "home"  =>  array ( "name" => "Home"  ),
    "login" =>  array ( "name" => "Login" ),
    "files" =>  array ( "name" => "Files" ),
    "users" =>  array ( "name" => "Users" ),
    "about" =>  array ( "name" => "About" )
);

$users = array(
    "admin"
);

function unsetcookie($name) {
    setcookie($name, "", 1);
}

function get_file_line($path) {
    $contents = file_get_contents($path);
    return str_replace(PHP_EOL, "", $contents);
}

function is_password($user, $pass) {
    $realpass = get_file_line("internal/pass_" . $user);
    return ($realpass == $pass);
}

$login = "";
$site = "";
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if ($_POST["action"] == "login") {
        if (isset($_POST["username"])) {
            $username = $_POST["username"];
            if (is_password($username, $_POST["password"])) {
                $ctime = time();
                srand($ctime);
                $sessionval = md5(strval(rand()));
                setcookie("session", $sessionval);
                /* only admin user atm anyways */
                file_put_contents("internal/session_admin", strval($sessionval));
                file_put_contents("internal/logintime_admin", $ctime);
                $login = $username;
            } else {
                echo "<script>alert('Invalid username and password!')</script>";
            }
        }
        $site = "home";
    } else if ($_POST["action"] == "upload") {
        $site = "files";
        $end = str_replace("..", "", $_POST["name"]);
        if (strpos($end, "/") === false && $end != "" && $end !== false) {
            $filename = "files/upload-" . $end;
            $f = fopen($filename, "w");
            if ($f !== false) {
                fwrite($f, $_POST["content"]);
                fclose($f);
            }
        }
    }

    if ($site == "") {
        $site = "home";
    }
} else {
    if ($login == "" && isset($_COOKIE["session"])) {
        $session = $_COOKIE["session"];
        foreach ($users as $username) {
            $path = "internal/session_" . $username;
            if (file_exists($path) && $session == get_file_line($path)) {
                $login = $username;
                break;
            }
        }
        if ($login == "") {
            unsetcookie("session");
        }
    }

    $site = "home";
    if (isset($_GET["q"])) {
        if ($_GET["q"] == "view" && $login == "admin") {
            $path = "files/" . str_replace("..", "", $_GET["f"]);
            if (file_exists($path)) {
                if (strpos(mime_content_type($path), "image") !== false) {
                    echo '<img src="data:' . mime_content_type($path) . ';base64,' . base64_encode(file_get_contents($path)) . '">';
                } else if (strpos(mime_content_type($path), "text") !== false) {
                    echo file_get_contents($path);
                } else { # generic download
                    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                    header("Cache-Control: public"); // needed for internet explorer
                    header("Content-Type: " . mime_content_type($path));
                    header("Content-Transfer-Encoding: Binary");
                    header("Content-Length:" . filesize($path));
                    header("Content-Disposition: attachment;");
                    readfile($path);
                }
            }
            exit();
        } else if ($_GET["q"] == "logout") {
            unsetcookie("session");
            $_COOKIE["session"] = "";
            $login = "";
        } else {
            foreach ($sites as $qname => $info) {
                if ($_GET["q"] == $qname) {
                    $site = $qname;
                    break;
                }
            }
        }
    } else {
        $site = "home";
    }
}

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Catchbox</title>
        <link rel="stylesheet" href="/style.css">
    </head>
    <body>
        <div class="navbar">
            <img src="/imgs/logo.png"></img>
            <ul>
<?php

foreach ($sites as $qname => $info) {
    if ($site == $qname) {
        echo '<li><a class="active" href="/?q=' . $qname . '">' . $info["name"] . '</a></li>';
    } else {
        if ($qname != "login" || $login == "") {
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
            <p>Host your files with us! Register now&#185 and get 5GB for free!!</p>
        <div>
        <div class="footer">
            &#185 registering is currently unavailable.
        </div>
';
} else if ($site == "login") {
    echo '
        <div class="text">
            <form action="index.php" method="post" class="login-form">
                <h2>Login:</h2>
                <input class="txtinput" name="username" type="text" placeholder="username"></input>
                <input class="txtinput" name="password" type="password" placeholder="password"></input>
                <input type="hidden" name="action" value="login">
                <input type="submit">
            </form>
        <div>
';
} else if ($site == "files") {
    echo '
        <div class="text">
            <h2>Currently hosted files:</h2>
            <ul class="mslist userlist">';
    exec("ls -l files | tail -n +2 | awk '{ print $9 }'", $files, $return);
    exec("ls -l files | tail -n +2 | awk '{ print $6 \" \" $7 \" \" $8 }'", $dates, $return);
    for ($i = 0; $i < sizeof($files); $i++) {
        if ($login == "admin") {
            echo '<li><p class="front"><a href="/?q=view&f=' . $files[$i] . '"' . $files[$i] . '</a></p><p class="back">' . $dates[$i] . '</p></li>';
        } else {
            echo '<li><p class="front">' . $files[$i] . '</p><p class="back">' . $dates[$i] . '</p></li>';
        }
    }
    echo'</ul>
        <form action="index.php" method="post" class="upload-form">
            <h2>Sample upload:</h2>
            <input type=text name="name" placeholder="name"></input> <br>
            <input type=text name="content" placeholder="content"></input> <br>
            <input type=hidden name="action" value="upload">
            <input type=submit>
        </form>';
    echo '<div>';
} else if ($site == "users") {
    echo '
        <div class="text">
            <h2>Currently registeried users:</h2>
            <ul class="mslist userlist">
                <li><p class="front">admin</p><p class="back"';
    $ltime = get_file_line("internal/logintime_admin");
    echo ' meta="' . $ltime . '">last active: ';
    echo date("Y-m-d H:i:s", $ltime);
    echo ' (UTC)</p></li>
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
