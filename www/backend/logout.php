<?php
/**
 * Logout-Handler
 */

require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();
$auth->logout();

Helpers::redirect('/index.php');
