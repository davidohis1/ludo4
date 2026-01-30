<?php
function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    
    if (!isset($_SESSION['user']['isAdmin']) || $_SESSION['user']['isAdmin'] !== true) {
        header('Location: dashboard.php');
        exit();
    }
}
?>