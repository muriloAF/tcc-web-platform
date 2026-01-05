<?php
	$pdo = new PDO('mysql: host=localhost; dbname=chat', 'root', '');
	$pdo -> exec("SET NAMES 'UTF8'");
?>