<?php
/**
 * Admin Top Bar
 * Bright Light Ministry Int'l Partners Portal
 */

// Get admin info
$adminId = $_SESSION['admin_id'] ?? null;
$admin = null;
$adminName = 'Admin';
$adminInitials = 'AD';

if ($adminId) {
    try {
        // Join with users table to get first_name and last_name
        $stmt = $db->getConnection()->prepare("
            SELECT au.*, u.first_name, u.last_name 
            FROM admin_users au
            LEFT JOIN users u ON au.user_id = u.user_id
            WHERE au.admin_id = :admin_id
        ");
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && !empty($admin['first_name']) && !empty($admin['last_name'])) {
            $adminName = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
            $firstInitial = !empty($admin['first_name']) ? substr($admin['first_name'], 0, 1) : 'A';
            $lastInitial = !empty($admin['last_name']) ? substr($admin['last_name'], 0, 1) : 'D';
            $adminInitials = strtoupper($firstInitial . $lastInitial);
        }
    } catch (Exception $e) {
        error_log("Admin fetch error: " . $e->getMessage());
    }
}
?>

<header class="bg-white border-b border-gray-200">
  <div class="flex items-center justify-between px-6 py-4">
    
    <!-- Page Title (can be overridden by child pages) -->
    <div class="flex-1">
      <?php if (!isset($customTitle)): ?>
      <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>
      <?php endif; ?>
    </div>

    <!-- Right Actions -->
    <div class="flex items-center gap-4">
      
      <!-- Notifications -->
      <button class="relative p-2 text-gray-500 hover:text-gray-700 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.4-1.4A7 7 0 0019 10V9a7 7 0 10-14 0v1a7 7 0 00.4 5.6L4 17h5m6 0a3 3 0 11-6 0"/></svg>
        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>

      <!-- Admin Profile -->
      <a href="account.php" class="flex items-center gap-3 pl-4 border-l border-gray-200 no-underline">
        <div class="text-right hidden md:block">
          <p class="text-sm font-medium text-gray-800"><?php echo $adminName; ?></p>
          <p class="text-xs text-gray-500">Account Management</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-gold flex items-center justify-center text-deep font-semibold">
          <?php echo $adminInitials; ?>
        </div>
      </a>

      <!-- Logout -->
      <a href="logout.php" class="p-2 text-gray-500 hover:text-red-600 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </a>
    </div>

  </div>
</header>
