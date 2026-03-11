<?php
// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$currentModule = basename(dirname($_SERVER['PHP_SELF']));

// Get user info
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userInitial = strtoupper(substr($userName, 0, 1));
?>

<nav class="top-nav">
    <a href="<?php echo BASE_URL; ?>/modules/dashboard/index.php" class="logo">⚡ PlacementCode</a>
    
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
    
    <div class="nav-menu" id="navMenu">
        <a href="<?php echo BASE_URL; ?>/modules/dashboard/index.php" 
           class="<?php echo ($currentModule === 'dashboard') ? 'active' : ''; ?>">
            Dashboard
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/coding/problems.php" 
           class="<?php echo ($currentModule === 'coding') ? 'active' : ''; ?>">
            Problems
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/aptitude/tests.php" 
           class="<?php echo ($currentModule === 'aptitude') ? 'active' : ''; ?>">
            Aptitude
        </a>

        <a href="<?php echo BASE_URL; ?>/modules/companies/list.php" 
           class="<?php echo ($currentModule === 'companies') ? 'active' : ''; ?>">
            Companies
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/leaderboard/index.php" 
           class="<?php echo ($currentModule === 'leaderboard') ? 'active' : ''; ?>">
            Leaderboard
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/community/index.php" 
           class="<?php echo ($currentModule === 'community') ? 'active' : ''; ?>">
            Community
        </a>
        
        <a href="<?php echo BASE_URL; ?>/modules/focus/analytics.php" 
           class="<?php echo ($currentModule === 'focus') ? 'active' : ''; ?>">
            Analytics
        </a>
        
        <div class="user-menu">
            <div class="user-btn" onclick="toggleUserDropdown()">
                <div class="user-avatar"><?php echo $userInitial; ?></div>
                <span><?php echo htmlspecialchars($userName); ?></span>
                <span style="font-size: 0.7rem; margin-left: 4px;">▼</span>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="<?php echo BASE_URL; ?>/modules/profile/profile.php" class="dropdown-item">
                    <span>👤</span> My Profile
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/toolkit/resume-builder.php" class="dropdown-item">
                    <span>📄</span> Resume Builder
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/toolkit/documents.php" class="dropdown-item">
                    <span>📁</span> Documents
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/support/support.php" class="dropdown-item">
                    <span>🎫</span> Support
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php" class="dropdown-item logout">
                    <span>🚪</span> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('navMenu');
    menu.classList.toggle('active');
}

function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.querySelector('.user-menu');
    const dropdown = document.getElementById('userDropdown');
    
    if (dropdown && !userMenu.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});
</script>
