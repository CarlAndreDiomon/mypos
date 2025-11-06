<?php
$conn = mysql_connect("localhost","root","","mypos");

//check connection
if (!$conn) {
    die ("connect failed:". mysqli_connect_error());
}
?>