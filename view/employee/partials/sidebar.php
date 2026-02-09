<?php
$type_id = $_SESSION['type_id'] ?? 0;
?>
<div id="layoutSidenav_nav"> 
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link" href="dashboard.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>

                <div class="sb-sidenav-menu-heading">Research Management</div>

                <?php if ($type_id == 1): ?>
                    <a class="nav-link" href="upload.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-upload text-primary"></i></div>
                        Upload Research
                    </a>
                <?php endif; ?>

                <?php if (in_array($type_id, [1, 2, 3, 4])): ?>
                    <a class="nav-link" href="pending.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-clock text-warning"></i></div>
                        Pending
                        <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
                    </a>
                <?php endif; ?>

                <a class="nav-link" href="approved.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-check-circle text-success"></i></div>
                    Approved
                    <?php if (in_array($type_id, [1, 2, 4])): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
                </a>
<!-- My Decisions (Exec Dir only - Tracking) -->
<?php if ($type_id == 6): ?>
    <a class="nav-link" href="my_decisions.php">
        <div class="sb-nav-link-icon"><i class="fas fa-clipboard-list text-warning"></i></div>
        My Decisions
        <span class="badge bg-info ms-1">Tracking</span>
    </a>
<?php endif; ?>
        <!-- Forwarded (Researcher, Section Head & Div Chief) -->
<?php if (in_array($type_id, [1, 2, 3])): ?>
    <a class="nav-link" href="forwarded.php">
        <div class="sb-nav-link-icon"><i class="fas fa-history"></i></div>
        Forwarded
        <span class="badge bg-info ms-1">Tracking</span>
    </a>
<?php endif; ?>

                <?php if ($type_id == 5): ?>
                    <a class="nav-link" href="processed.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-history text-info"></i></div>
                        Processed
                    </a>
                <?php endif; ?>

                <?php if (in_array($type_id, [1, 2, 3, 4, 6])): ?>
                    <a class="nav-link" href="revised.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-edit text-info"></i></div>
                        Revision
                        <?php if (in_array($type_id, [2, 3, 4, 6])): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if (in_array($type_id, [1, 2, 3, 4, 6])): ?>
                    <a class="nav-link" href="cancel.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-times-circle text-danger"></i></div>
                        Cancelled
                    </a>
                <?php endif; ?>

                <a class="nav-link" href="publish.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-globe text-primary"></i></div>
                    Published
                </a>

                <?php if ($type_id == 4): ?>
                    <div class="sb-sidenav-menu-heading">Administration</div>
                    
                    <a class="nav-link" href="employeepending.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-user-clock"></i></div>
                        Pending Employees
                    </a>
                    <a class="nav-link" href="employee_approved.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-user-check"></i></div>
                        Approved Employees
                    </a>
                    <a class="nav-link" href="backup.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-database"></i></div>
                        Backup & Restore
                    </a>
                <?php endif; ?>

                <div class="sb-sidenav-menu-heading">Account</div>
                <a class="nav-link" href="profile.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
                    Profile
                </a>
            </div>
        </div>

 <div class="sb-sidenav-footer">
    <div class="small">Logged in as:</div>
    <strong>
    <?php 
        $typeNames = [1=>'Researcher', 2=>'Section Head', 3=>'Division Chief', 4=>'Admin', 5=>'Records', 6=>'Exec. Director'];
        $roleName = $typeNames[$type_id] ?? 'User';
        
        // If Section Head, show their branch
        if ($type_id == 2) {
            $branch = $_SESSION['branch'] ?? 'Not Assigned';
            echo $roleName . '<br><small class="text-white">(' . htmlspecialchars($branch) . ')</small>';
        } else {
            echo $roleName;
        }
    ?>
    </strong>
</div>
    </nav>
</div>
<style>
    /* 1. Ensure the sidebar container takes full height and uses Flexbox */
    .sb-sidenav {
        display: flex !important;
        flex-direction: column !important;
        height: 100vh !important;
    }

    /* 2. Make the menu area scrollable and let it grow to fill space */
    .sb-sidenav-menu {
        flex-grow: 1 !important;
        overflow-y: auto !important; /* This adds the scrollbar only here */
    }

    /* 3. Keep the footer strictly at the bottom */
    .sb-sidenav-footer {
        flex-shrink: 0 !important; /* Prevents the footer from squishing */
        background-color: #343a40 !important; /* Matches dark theme */
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* 4. Scrollbar styling (Optional - makes it look cleaner) */
    .sb-sidenav-menu::-webkit-scrollbar {
        width: 5px;
    }
    .sb-sidenav-menu::-webkit-scrollbar-track {
        background: transparent;
    }
    .sb-sidenav-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }

    /* Your existing styles */
    #sidenavAccordion .nav-link {
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
    }
    #sidenavAccordion .sb-nav-link-icon {
        margin-right: 0.5rem;
        min-width: 20px;
        text-align: center;
    }
</style>