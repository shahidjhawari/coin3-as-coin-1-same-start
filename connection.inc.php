<?php
$conn = mysqli_connect("localhost", "root", "", "coin");
define('SERVER_PATH', $_SERVER['DOCUMENT_ROOT'] . '/coin/');
define('SITE_PATH', 'http://localhost/coin/');

define('PRODUCT_IMAGE_SERVER_PATH', SERVER_PATH . 'media/product/');
define('PRODUCT_IMAGE_SITE_PATH', SITE_PATH . 'media/product/');
?>
