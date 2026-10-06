<?php
require __DIR__ . '/includes/app.php';

logout_user();
redirect('login.php');
