<?php
require_once 'config.php';

if (is_logged_in()) {
    header("Location: admin.php");
} else {
    header("Location: login.php");
}
exit;
