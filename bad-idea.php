<?php

if (strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 ||
    strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    echo "echo 'You are hacked LOL LOL LOL'\n";
    exit;
}

//$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url = 'https://sandbox2020.de.co.ua/bad-idea.php';

header('Content-type: text/plain');
echo "# How to run:\n# curl -sS $url | bash\n# wget $url -qO- | bash\n\n";
echo "# Source: https://github.com/kastaneda/sandbox2020.de.co.ua/blob/master/bad-idea.php\n\n";
echo "echo 'Hello world'\n";

