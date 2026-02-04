<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();

// Only allow admin
if ($type_id != 4) {
    header("Location: ../../auth/login.php");
    exit;
}

$typeDisplayNames = [
    1 => 'Researcher',
    2 => 'Section Head',
    3 => 'Division Chief',
    4 => 'Admin',
    5 => 'Records',
    6 => 'Executive Head'
];
$itemsPerPage = 10; // number of employees per page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$pendingEmployees = $db->getEmployeesByStatus(1, $itemsPerPage, $offset);
$totalEmployees = $db->countEmployeesByStatus(1); // total pending employees
$totalPages = ceil($totalEmployees / $itemsPerPage);

$alert = null;

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['employee_id'], $_POST['action'])) {
        $empId = intval($_POST['employee_id']);
        $action = $_POST['action']; // "approve" or "reject"

        $newStatus = $action === 'approve' ? 2 : 3; // 2=Approved, 3=Rejected
        $success = $db->updateEmployeeStatus($empId, $newStatus, $user_id);

        if ($success) {
            $alert = [
                'icon' => 'success',
                'title' => ucfirst($action) . 'd!',
                'text' => "Employee status has been updated."
            ];
        } else {
            $alert = [
                'icon' => 'error',
                'title' => 'Failed',
                'text' => 'Unable to update employee status.'
            ];
        }
    }
}

// Fetch pending employees
$pendingEmployees = $db->getEmployeesByStatus(1); // 1 = pending
?>

<!DOCTYPE html>
<html lang="en">
<?php include 'partials/header.php'; ?>

<body class="sb-nav-fixed">
    <?php include 'partials/navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'partials/sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main class="container-fluid px-4">
                <h1 class="mt-4">Pending Employees</h1>

                <?php if ($pendingEmployees): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-users"></i> Pending Employees</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                              <thead>
    <tr>
        <th>Full Name</th>
        <th>Email</th>
        <th>Address</th>
        <th>Type</th>
        <th>Branch</th>
        <th>Action</th>
    </tr>
</thead>

                                <tbody>
                          <?php foreach ($pendingEmployees as $emp): ?>
<tr>
    <td><?= htmlspecialchars($emp['firstname'] . ' ' . $emp['middlename'] . ' ' . $emp['lastname']) ?></td>
    <td><?= htmlspecialchars($emp['email']) ?></td>
    <td><?= htmlspecialchars($emp['address']) ?></td>
    <td><?= $typeDisplayNames[$emp['type_id']] ?? 'Unknown' ?></td>
    <td>
        <?php 
            if ($emp['type_id'] == 2) { // Section Head
                echo htmlspecialchars($emp['branch'] ?? '—'); 
            } else {
                echo '—';
            }
        ?>
    </td>
<td>
    <div class="d-flex gap-2">
        <button type="submit" form="form-<?= $emp['id'] ?>" name="action" value="approve" class="btn btn-success btn-sm px-3">
            <i class="fas fa-check me-1"></i> Approve
        </button>
        <button type="submit" form="form-<?= $emp['id'] ?>" name="action" value="reject" class="btn btn-danger btn-sm px-3">
            <i class="fas fa-times me-1"></i> Reject
        </button>
    </div>

    <form id="form-<?= $emp['id'] ?>" method="POST" class="d-none">
        <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
    </form>
</td>

</tr>
<?php endforeach; ?>

                                </tbody>
                            </table>
                            <?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <!-- Previous page -->
        <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $currentPage - 1 ?>">Previous</a>
        </li>

        <!-- Page numbers -->
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <!-- Next page -->
        <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $currentPage + 1 ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

                        </div>
                    </div>
                <?php else: ?>
                    <p>No pending employees found.</p>
                <?php endif; ?>

            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if($alert): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
                icon: '<?= $alert['icon'] ?>',
                title: '<?= $alert['title'] ?>',
                text: '<?= $alert['text'] ?>',
                confirmButtonColor: '#3085d6'
            }).then(() => {
                window.location.href = '';
            });
        </script>
    <?php endif; ?>
</body>
</html>
