<aside class="sidebar" aria-label="Admin navigation">
    <a class="brand" href="./" aria-label="GDG Bacolod Admin Console home">
        <img class="brand-mark" src="assets/images/gdg-logo.svg" alt="">
        <span class="brand-copy">
            <strong>GDG Bacolod</strong>
            <span>Admin Console</span>
        </span>
    </a>

    <?php $sidebarActivePage = $sidebarActivePage ?? 'dashboard'; ?>
    <nav class="primary-nav" aria-label="Main navigation">
        <a class="nav-link<?= $sidebarActivePage === 'dashboard' ? ' is-active' : '' ?>" href="index.php"<?= $sidebarActivePage === 'dashboard' ? ' aria-current="page"' : '' ?> data-dashboard-link>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4" width="7" height="7" rx="1.2"></rect><rect x="13.5" y="4" width="7" height="4" rx="1.2"></rect><rect x="13.5" y="10" width="7" height="10" rx="1.2"></rect><rect x="3.5" y="13" width="7" height="7" rx="1.2"></rect></svg>
            <span>Dashboard</span>
        </a>
        <a class="nav-link<?= $sidebarActivePage === 'settings' ? ' is-active' : '' ?>" href="settings.php"<?= $sidebarActivePage === 'settings' ? ' aria-current="page"' : '' ?> data-settings-link>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2Z"></path><path d="m19.2 13.5 1.1.9-1.1 1.9-1.4-.5a7.8 7.8 0 0 1-1.4.8l-.2 1.5h-2.2l-.4-1.4a7.7 7.7 0 0 1-1.6 0l-.8 1.2-1.9-1.1.5-1.4a7.8 7.8 0 0 1-.8-1.4l-1.5-.2v-2.2l1.4-.4a7.7 7.7 0 0 1 0-1.6l-1.2-.8 1.1-1.9 1.4.5a7.8 7.8 0 0 1 1.4-.8l.2-1.5h2.2l.4 1.4a7.7 7.7 0 0 1 1.6 0l.8-1.2 1.9 1.1-.5 1.4a7.8 7.8 0 0 1 .8 1.4l1.5.2v2.2l-1.4.4a7.7 7.7 0 0 1 0 1.6Z"></path></svg>
            <span>Settings</span>
        </a>
    </nav>

    <?php $sidebarUser = authenticated_user(); ?>
    <div class="sidebar-account">
        <span class="account-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.4c0 4.3-2.9 7.4-7 9.1-4.1-1.7-7-4.8-7-9.1V6l7-2.5Z"></path><path d="m9.2 11.9 1.8 1.8 3.9-4"></path></svg>
        </span>
        <span class="account-copy">
            <strong><?= escape_html($sidebarUser['display_name'] ?? 'Admin') ?></strong>
            <span><?= escape_html(ucfirst($sidebarUser['role'] ?? 'admin')) ?></span>
        </span>
        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <button class="account-exit" type="submit" aria-label="Sign out">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5.5A1.5 1.5 0 0 0 4 6.5v11A1.5 1.5 0 0 0 5.5 19H10"></path><path d="M14 8l4 4-4 4M18 12H9"></path></svg>
            </button>
        </form>
    </div>
</aside>
