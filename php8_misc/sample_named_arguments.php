<?php declare(strict_types=1);

ini_set('display_errors', '1');

class foo {
    public int $id;
    public string $message;
}

/*

CREATE TABLE `foo` (
  `id` int(10) UNSIGNED NOT NULL,
  `message` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `foo` (`id`, `message`) VALUES
(1, 'foo'),
(2, 'bar');

*/

$db_config = [
    'dsn' => 'mysql:host=localhost;dbname=sandbox',
    'username' => 'sandbox_user',
    'password' => 'sample_insecure_password',
    'options' => [
        \PDO::ATTR_PERSISTENT => true,
    ],
];

$dbh = new \PDO(
    dsn: $db_config['dsn'],
    username: $db_config['username'],
    password: $db_config['password'],
    options: $db_config['options'] ?? [],
);

$sth = $dbh->prepare('SELECT * FROM foo');
$sth->execute();
$result = $sth->fetchAll(\PDO::FETCH_CLASS, foo::CLASS);

echo '<pre>';
var_dump($result);

/*

array(2) {
  [0]=>
  object(foo)#3 (2) {
    ["id"]=>
    int(1)
    ["message"]=>
    string(3) "foo"
  }
  [1]=>
  object(foo)#4 (2) {
    ["id"]=>
    int(2)
    ["message"]=>
    string(3) "bar"
  }
}

*/