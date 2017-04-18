<?PHP
require 'vendor/autoload.php';

$db = new
\atk4\data\Persistence_SQL('mysql:dbname=agile_education;host=localhost','root','');

$sql = mysql_query("DELETE FROM `friends` WHERE `ID` = 7");