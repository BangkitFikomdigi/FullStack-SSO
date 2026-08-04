<?php
session_start();
if (!empty($_SESSION['sso_token'])) {
    header('Location: dashboard.php');
    exit;
}
header('Location: login.php');
exit;
