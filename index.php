<?php
require __DIR__ . '/includes/app.php';

$u = current_user();
redirect($u ? home_for($u) : 'login.php');
