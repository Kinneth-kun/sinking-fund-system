<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
</head>
<body>

<div class="layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="brand-icon">💰</span>
      <div>
        <div class="brand-name">Staycation</div>
        <div class="brand-sub">Savings Fund</div>
      </div>
    </div>

    <!-- Clock -->
    <div class="sidebar-clock">
      <div class="clock-time" id="sb-time">--:--:-- --</div>
      <div class="clock-date" id="sb-date">Loading...</div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/pages/dashboard.php"  class="nav-item <?= $currentPage==='dashboard'  ? 'active' : '' ?>"><span>📊</span> Dashboard</a>
      <a href="<?= APP_URL ?>/pages/calendar.php"   class="nav-item <?= $currentPage==='calendar'   ? 'active' : '' ?>"><span>📅</span> Calendar</a>
      <a href="<?= APP_URL ?>/pages/members.php"    class="nav-item <?= $currentPage==='members'    ? 'active' : '' ?>"><span>👥</span> Members</a>
      <a href="<?= APP_URL ?>/pages/payments.php"   class="nav-item <?= $currentPage==='payments'   ? 'active' : '' ?>"><span>💵</span> Payments</a>
      <a href="<?= APP_URL ?>/pages/reports.php"    class="nav-item <?= $currentPage==='reports'    ? 'active' : '' ?>"><span>📈</span> Reports</a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-chip">
        <span class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="user-role">Admin</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">⏻</a>
    </div>
  </aside>

  <!-- Sidebar overlay (mobile tap-to-close) -->
  <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <button class="menu-toggle" id="menu-toggle" onclick="toggleSidebar()">☰</button>
      <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
      <div class="topbar-clock">
        <span id="top-time" class="tc-time">--:--:-- --</span>
        <span id="top-date" class="tc-date">Loading...</span>
      </div>
    </div>
    <div class="content-body">