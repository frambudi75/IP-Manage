<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <header class="top-header">
                <div class="breadcrumb">
                    <span class="text-muted">Pages</span> / <span><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></span>
                </div>
                <div class="user-menu">
                    <span class="btn btn-secondary" style="font-size: 0.8rem; gap: 8px;">
                        <i data-lucide="user" style="width: 14px;"></i> 
                        <?php echo $_SESSION['username']; ?> 
                        <span style="opacity: 0.5; font-size: 0.7rem;">(<?php echo strtoupper($_SESSION['role']); ?>)</span>
                    </span>
                </div>
            </header>
            <div class="content-wrapper mt-4">
