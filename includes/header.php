<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' stroke='%236366f1' stroke-width='3' stroke-linecap='round' stroke-linejoin='round' viewBox='0 0 24 24'><rect x='16' y='16' width='6' height='6' rx='1'/><rect x='2' y='16' width='6' height='6' rx='1'/><rect x='9' y='2' width='6' height='6' rx='1'/><path d='M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3'/><path d='M12 12V8'/></svg>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <header class="top-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button id="menu-toggle" class="btn" style="padding: 6px; display: none; background: rgba(59, 130, 246, 0.1); color: var(--primary);">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="breadcrumb">
                        <span class="text-muted">Pages</span> / <span><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></span>
                    </div>
                </div>
                <div class="user-menu">
                    <span class="btn btn-secondary" style="font-size: 0.8rem; gap: 8px;">
                        <i data-lucide="user" style="width: 14px;"></i> 
                        <?php echo $_SESSION['username']; ?> 
                        <span style="opacity: 0.5; font-size: 0.7rem;">(<?php echo strtoupper($_SESSION['role']); ?>)</span>
                    </span>
                </div>
            </header>
            <div class="print-only-header">
                <h1><?php echo APP_NAME; ?> - Network Report</h1>
                <p>Generated on: <?php echo date('d M Y H:i:s'); ?> | User: <?php echo $_SESSION['username']; ?></p>
            </div>
            <div class="content-wrapper mt-4">
