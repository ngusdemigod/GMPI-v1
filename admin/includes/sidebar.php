<?php
/**
 * Admin Sidebar
 * Bright Light Ministry Int'l Partners Portal
 */

// Get current page for active highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<aside class="w-64 bg-deep text-cream flex flex-col">

<!-- Logo -->
<div class="p-6 border-b border-white/10">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-gold flex items-center justify-center">
      <svg class="w-6 h-6 text-deep" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M6 8h12"/></svg>
    </div>
    <div>
      <div class="font-semibold text-sm">Admin Dashboard</div>
      <div class="text-xs text-cream/60">Bright Light Ministry</div>
    </div>
  </div>
</div>

<!-- Navigation -->
<nav class="flex-1 p-4">
  <ul class="space-y-1">
    
    <!-- Dashboard -->
    <li>
      <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'index' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
    </li>

    <!-- Users -->
    <li>
      <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'users' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span class="text-sm font-medium">Users</span>
      </a>
    </li>

    <!-- Partners -->
    <li>
      <a href="partners.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'partners' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span class="text-sm font-medium">Partners</span>
      </a>
    </li>

    <!-- Projects -->
    <li>
      <a href="projects.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'projects' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        <span class="text-sm font-medium">Projects</span>
      </a>
    </li>

    <!-- Transactions -->
    <li>
      <a href="transactions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'transactions' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-medium">Transactions</span>
      </a>
    </li>

    <!-- Plans -->
    <li>
      <a href="plans.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'plans' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
        <span class="text-sm font-medium">Plans</span>
      </a>
    </li>

    <!-- Support Tickets -->
    <li>
      <a href="support.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'support' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        <span class="text-sm font-medium">Support Tickets</span>
      </a>
    </li>

    <!-- Admin Members -->
    <li>
      <a href="admin-members.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'admin-members' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <span class="text-sm font-medium">Admin Members</span>
      </a>
    </li>

    <!-- Audit Logs -->
    <li>
      <a href="audit-logs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition <?php echo $currentPage === 'audit-logs' ? 'bg-gold text-deep' : 'text-cream/70 hover:bg-white/5 hover:text-cream'; ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span class="text-sm font-medium">Audit Logs</span>
      </a>
    </li>

  </ul>
</nav>

<!-- Footer -->
<div class="p-4 border-t border-white/10">
  <a href="settings.php" class="flex items-center gap-3 px-4 py-2 rounded-xl transition text-cream/70 hover:bg-white/5 hover:text-cream text-sm">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    <span class="text-sm font-medium">Settings</span>
  </a>
</div>

</aside>