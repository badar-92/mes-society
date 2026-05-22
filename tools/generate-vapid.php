<?php
require_once '../vendor-push/autoload.php';
use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
echo "Public Key: " . $keys['publicKey'] . "<br>";
echo "Private Key: " . $keys['privateKey'] . "<br>";
?>