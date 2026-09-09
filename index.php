<?php require_once 'includes/auth.php';
redirect(is_logged_in() ? (user()['role'] === 'admin' ? 'dashboard_admin.php' : 'catalog.php') : 'catalog.php');
