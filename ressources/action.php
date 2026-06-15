<?php
require_once '../base/basePHP.php';

if (empty($_SESSION['logged_in']) || empty($_SESSION['controller'])) {
    header('Location: /' . $_SESSION['FOLDER'] . '/connection/loginForm.php');
    exit();
}

if (getConfig($gameReady, 'ressource_management') !== 'TRUE') {
    http_response_code(403);
    exit();
}

$folder = $_SESSION['FOLDER'];
$controller_id = (int)$_SESSION['controller']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['giftRessource'])) {
    header("Location: /{$folder}/ressources/view.php");
    exit();
}

$result = giftRessource($gameReady, $controller_id, $_POST);
$flag = $result['success'] ? 'success' : 'error';
$msg = urlencode($result['message']);
header("Location: /{$folder}/ressources/view.php?feedback={$flag}&msg={$msg}");
exit();
