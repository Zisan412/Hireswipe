<?php
require __DIR__ . '/config.php';
$u = current_user();
redirect($u ? next_screen($u) : 'signin.php');
