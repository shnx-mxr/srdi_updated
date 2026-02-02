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

                <div class="sb-sidenav-menu-heading">Research</div>

                <!-- Research Dropdown -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseResearch" aria-expanded="false" aria-controls="collapseResearch">
                    <div class="sb-nav-link-icon"><i class="fas fa-flask"></i></div>
                    Research
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
            <div class="collapse" id="collapseResearch" data-bs-parent="#sidenavAccordion">
    <nav class="sb-sidenav-menu-nested nav">
        <?php if (in_array($type_id, [1, 2, 3, 4, 5, 6])): ?>
            <a class="nav-link" href="pending.php">
                <i class="fas fa-clock me-1"></i> Pending
                <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
            </a>
            <a class="nav-link" href="approved.php">
                <i class="fas fa-check-circle me-1"></i> Approved
                <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
            </a>
            <a class="nav-link" href="revised.php">
                <i class="fas fa-edit me-1"></i> Revision
                <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
            </a>
            <a class="nav-link" href="cancel.php">
                <i class="fas fa-times-circle me-1"></i> Cancelled
                <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
            </a>
            <a class="nav-link" href="publish.php">
                <i class="fas fa-globe me-1"></i> Published
                <?php if ($type_id == 4): ?><span class="badge bg-secondary ms-1">View Only</span><?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if ($type_id == 1): ?>
            <a class="nav-link" href="upload.php">
                <i class="fas fa-file-upload me-1"></i> Upload Research
            </a>
        <?php endif; ?>
    </nav>
</div>

                <?php if ($type_id == 4): ?>
                    <div class="sb-sidenav-menu-heading">Administration</div>
                    <a class="nav-link" href="employeepending.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                        Employee Pending
                    </a>
                    <a class="nav-link" href="backup.php">
                        <div class="sb-nav-link-icon"><i class="fas fa-database"></i></div>
                        Backup & Restore
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="sb-sidenav-footer">
            <div class="small">Logged in as:</div>
            <?= htmlspecialchars($fullname) ?>
        </div>
    </nav>
</div>

<style>
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

    .sb-sidenav-menu-nested .nav-link {
        padding-left: 2rem;
    }
    
    .badge.bg-secondary {
        font-size: 0.65rem;
        padding: 0.25em 0.5em;
    }
</style>