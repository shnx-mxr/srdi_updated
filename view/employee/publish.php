<?php
session_start();
require_once "../../controller/Main.php";

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();

// Research type mapping
$typeNames = [1 => 'Mulberry', 2 => 'Post Cocoon', 3 => 'Silkworm'];

// Get all research for the user based on type
$researchList = $db->getResearchForUser($user_id, $type_id);

// Filter only Published research (status_id = 5)
$researchList = array_filter($researchList, function ($r) {
    return $r['status_id'] == 5;
});

// Apply type filter if requested
$filterType = $_GET['type_id'] ?? '';
if ($filterType && in_array($filterType, [1, 2, 3])) {
    $researchList = array_filter($researchList, function ($r) use ($filterType) {
        return $r['type_id'] == $filterType;
    });
}

// Add team leader, decided by names, and type name
foreach ($researchList as $key => $research) {
    $researchList[$key]['team_leader'] = $db->getEmployeeName($research['user_id']);
    $researchList[$key]['decided_by'] = $research['desisyon_id'] ? $db->getEmployeeName($research['desisyon_id']) : '-';
    $researchList[$key]['type_name'] = $typeNames[$research['type_id']] ?? 'Unknown';
}
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
                <h1 class="mt-4">Published Research</h1>

                <!-- Type Filter -->
                <form method="GET" class="mb-3">
                    <label>Filter by Type:</label>
                    <select name="type_id" class="form-select w-auto d-inline-block">
                        <option value="">All</option>
                        <?php foreach ($typeNames as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $filterType == $id ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </form>

                <?php if ($researchList): ?>
                    <div class="card mb-4">
                        <div class="card-header"><i class="fas fa-list"></i> Published Research</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Team Leader</th>
                                        <th>Members</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Research File</th>
                                        <th>Compliance File</th>
                                        <th>Status</th>
                                        <th>Decided By</th>
                                        <th>Type</th>
                                        <th>Publication</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($researchList as $research): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($research['title']) ?></td>
                                            <td><?= htmlspecialchars($research['team_leader']) ?></td>
                                            <td><?= htmlspecialchars($research['member']) ?></td>
                                            <td><?= htmlspecialchars($research['startDate']) ?></td>
                                            <td><?= htmlspecialchars($research['endDate']) ?></td>
                                            <td>
                                                <?php if (!empty($research['filePath'])): ?>
                                                    <a href="research/<?= htmlspecialchars($research['filePath']) ?>" target="_blank">View Research</a>
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($research['compliance'])): ?>
                                                    <a href="compliance/<?= htmlspecialchars($research['compliance']) ?>" target="_blank">View Compliance</a>
                                                <?php else: ?> N/A <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-primary">Published</span></td>
                                            <td><?= htmlspecialchars($research['decided_by']) ?></td>
                                            <td><?= htmlspecialchars($research['type_name']) ?></td>
                                            <td>
                                                <?php if (empty($research['publication_link'])): ?>
                                                    <!-- 👇 UPDATED: Only researcher (type_id=1) who owns this research can add publication -->
                                                    <?php if ($type_id == 1 && $research['user_id'] == $user_id): ?>
                                                        <button class="btn btn-success btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#addPubModal"
                                                            data-id="<?= $research['id'] ?>"
                                                            data-title="<?= htmlspecialchars($research['title']) ?>"
                                                            data-members="<?= htmlspecialchars($research['member']) ?>">
                                                            <i class="fas fa-plus"></i> Add Publication
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not yet published</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- View Publication Link (everyone can see) -->
                                                    <strong><?= htmlspecialchars($research['publication_title']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($research['publisher']) ?> | 
                                                        <?= date('M d, Y', strtotime($research['publication_date'])) ?>
                                                    </small><br>
                                                    <a href="<?= htmlspecialchars($research['publication_link']) ?>"
                                                        target="_blank"
                                                        class="btn btn-primary btn-sm mt-1">
                                                        <i class="fas fa-external-link-alt"></i> View Publication
                                                    </a>
                                                    
                                                    <!-- 👇 UPDATED: Only researcher who owns this research can edit -->
                                                    <?php if ($type_id == 1 && $research['user_id'] == $user_id): ?>
                                                        <button class="btn btn-warning btn-sm mt-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editPubModal"
                                                            data-id="<?= $research['id'] ?>"
                                                            data-pub-title="<?= htmlspecialchars($research['publication_title']) ?>"
                                                            data-pub-date="<?= htmlspecialchars($research['publication_date']) ?>"
                                                            data-pub-link="<?= htmlspecialchars($research['publication_link']) ?>"
                                                            data-publisher="<?= htmlspecialchars($research['publisher']) ?>"
                                                            data-title="<?= htmlspecialchars($research['title']) ?>"
                                                            data-members="<?= htmlspecialchars($research['member']) ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No published research found.</p>
                <?php endif; ?>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <!-- Add Publication Modal -->
    <div class="modal fade" id="addPubModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Publication Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addPubForm">
                    <div class="modal-body">
                        <input type="hidden" name="research_id" id="add_research_id">

                        <div class="mb-3">
                            <label class="form-label">Publication Title</label>
                            <input type="text" name="publication_title" id="add_pub_title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Members</label>
                            <input type="text" name="members_display" id="add_members" class="form-control" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Publication</label>
                            <input type="date" name="publication_date" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Publication Link <span class="text-danger">*</span></label>
                            <input type="url" name="publication_link" class="form-control" placeholder="https://example.com" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Publisher</label>
                            <input type="text" name="publisher" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Publication</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Publication Modal -->
    <div class="modal fade" id="editPubModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Publication Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPubForm">
                    <div class="modal-body">
                        <input type="hidden" name="research_id" id="edit_research_id">

                        <div class="mb-3">
                            <label class="form-label">Publication Title</label>
                            <input type="text" name="publication_title" id="edit_pub_title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Members</label>
                            <input type="text" name="members_display" id="edit_members" class="form-control" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Publication</label>
                            <input type="date" name="publication_date" id="edit_pub_date" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Publication Link <span class="text-danger">*</span></label>
                            <input type="url" name="publication_link" id="edit_pub_link" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Publisher</label>
                            <input type="text" name="publisher" id="edit_publisher" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Publication</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Add Publication Modal - Populate fields
        document.getElementById('addPubModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('add_research_id').value = button.getAttribute('data-id');
            document.getElementById('add_pub_title').value = button.getAttribute('data-title');
            document.getElementById('add_members').value = button.getAttribute('data-members');
        });

        // Edit Publication Modal - Populate fields
        document.getElementById('editPubModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_research_id').value = button.getAttribute('data-id');
            document.getElementById('edit_pub_title').value = button.getAttribute('data-pub-title');
            document.getElementById('edit_members').value = button.getAttribute('data-members');
            document.getElementById('edit_pub_date').value = button.getAttribute('data-pub-date');
            document.getElementById('edit_pub_link').value = button.getAttribute('data-pub-link');
            document.getElementById('edit_publisher').value = button.getAttribute('data-publisher');
        });

        // Add Publication Form Submit
        document.getElementById('addPubForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('add_publication.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops!',
                            text: data.message,
                            confirmButtonColor: '#3085d6'
                        });
                    }
                });
        });

        // Edit Publication Form Submit
        document.getElementById('editPubForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('add_publication.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Publication details updated successfully!',
                            confirmButtonColor: '#3085d6'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops!',
                            text: data.message,
                            confirmButtonColor: '#3085d6'
                        });
                    }
                });
        });
    </script>
</body>

</html>