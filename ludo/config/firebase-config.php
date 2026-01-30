<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

$serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/service-account-key.json');

$firebase = (new Factory)
    ->withServiceAccount($serviceAccount)
    ->withDatabaseUri('https://your-project.firebaseio.com') // Realtime DB URL
    ->create();

$auth = $firebase->createAuth();
$firestore = $firebase->createFirestore();
$database = $firestore->database();
$realtimeDB = $firebase->getDatabase();

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>