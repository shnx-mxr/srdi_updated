<?php
session_start();
require_once "../../controller/Main.php";

if (!isset($_SESSION['user_id']) || $_SESSION['type_id'] != 1) {
    header("Location: ../../auth/login.php");
    exit;
}

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$db = new db();
$message = '';
$messageType = '';

$employees = $db->getEmployees();
// Debug: Check if employees loaded
if (!$employees || !is_array($employees)) {
    die("Error: Could not load employees list. Check getEmployees() function.");
}
// Handle submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'study_title' => $_POST['study_title'] ?? '',
        'study_leader' => $_POST['study_leader'] ?? 0,
        'section' => $_POST['section'] ?? '',
        'members' => $_POST['members'] ?? [],
        'funding' => $_POST['funding'] ?? '',
        'duration' => $_POST['duration'] ?? '',
        'proposed_budget' => $_POST['proposed_budget'] ?? '',
        'sdg' => $_POST['sdg'] ?? [],
        'hnrda_area' => $_POST['hnrda_area'] ?? '',
        'hnrda_sector' => $_POST['hnrda_sector'] ?? '',
        'focus_rdi' => $_POST['focus_rdi'] ?? [],
        'created_by' => $user_id
    ];
    
    // Files
    $files = [];
    $file_types = ['proposal_form', 'attachment', 'cv', 'data_gathering', 'compliance_matrix', 'ethics_request', 'ethics_application', 'informed_consent'];
    foreach ($file_types as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === 0) {
            $files[$type] = $_FILES[$type];
        }
    }
    
    if ($db->uploadStudy($data, $files)) {
        header("Location: upload_study.php?success=1");
        exit;
    } else {
        $message = "Failed to upload study.";
        $messageType = 'error';
    }
}

if (isset($_GET['success'])) {
    $message = "Study uploaded successfully!";
    $messageType = 'success';
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
                <h1 class="mt-4">Upload Research Study</h1>

                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-file-alt"></i> Research Study Proposal Form
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            
               <h5 class="mb-3 ">Study 1</h5>

<div class="row">
    <!-- Study Title (70%) -->
    <div class="col-md-8 mb-3">
        <label class="form-label fw-semibold fs-6">Study Title <span class="text-danger">*</span></label>
        <input type="text" name="study_title" class="form-control" placeholder="Enter" required>
    </div>

    <!-- Section (30%) -->
    <div class="col-md-4 mb-3">
        <label class="form-label fw-semibold fs-6">Section <span class="text-danger">*</span></label>
        <select name="section" class="form-select" required>
            <option value="">Select Section</option>
            <option value="Mulberry">Mulberry</option>
            <option value="Post Cocoon">Post Cocoon</option>
            <option value="Silkworm">Silkworm</option>
        </select>
    </div>
</div>

<div class="row">
    <!-- Study Leader (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Study Leader <span class="text-danger">*</span></label>
        <select name="study_leader" class="form-select" required>
            <option value="">Select Leader </option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $emp['id'] == $user_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Study Members (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Study Members <span class="text-danger">*</span></label>
        <div id="member-container">
            <div class="input-group mb-2">
                <select name="members[]" class="form-select" required>
                    <option value="">Select Member</option>
                    <?php foreach ($employees as $emp): 
                        if ($emp['id'] == $user_id) continue;
                    ?>
                        <option value="<?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>">
                            <?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-success add-member">+</button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Funding Source (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Funding Source<span class="text-danger">*</span></label>
        <input type="text" name="funding" class="form-control" placeholder="Enter source name/details">
    </div>

    <!-- Proposed Budget (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Proposed Budget (PHP) <span class="text-danger">*</span></label>
        <input type="number" name="proposed_budget" class="form-control" placeholder="Enter proposed budget" required>
    </div>
</div>

<div class="row">
    <!-- Proposed Duration (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Proposed Duration (months)<span class="text-danger">*</span></label>
        <input type="number" name="duration" class="form-control" placeholder="Enter duration in months">
    </div>

    <!-- Attachment (50%) -->
    <div class="col-md-6 mb-3">
        <label class="form-label fw-semibold fs-6">Attachment (PDF only) <span class="text-danger">*</span></label>
        <input type="file" name="attachment" class="form-control" accept="application/pdf" required>
    </div>
</div>
<hr>
                            <!-- SDGs -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">SUSTAINABLE DEVELOPMENT GOALS <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select all that apply:</p>
                                <div class="row">
                                    <?php 
                                   $sdgs = [
    'SDG 1. No Poverty',
    'SDG 10. Reduced Inequalities',

    'SDG 2. Zero Hunger',
    'SDG 11. Sustainable Cities and Communities',

    'SDG 3. Good Health and Well-being',
    'SDG 12. Responsible Consumption and Production',

    'SDG 4. Quality Education',
    'SDG 13. Climate Action',

    'SDG 5. Gender Equality',
    'SDG 14. Life Below Water',

    'SDG 6. Clean Water and Sanitation',
    'SDG 15. Life on Land',

    'SDG 7. Affordable and Clean Energy',
    'SDG 16. Peace, Justice and Strong Institutions',

    'SDG 8. Decent Work and Economic Growth',
    'SDG 17. Partnerships for the Goals',

    'SDG 9. Industry, Innovation and Infrastructure'
];

                                    foreach ($sdgs as $index => $sdg): 
                                    ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="sdg[]" value="<?= $index + 1 ?>" id="sdg<?= $index + 1 ?>">
                                            <label class="form-check-label" for="sdg<?= $index + 1 ?>"><?= $sdg ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- HNRDA Area -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA 2022-2028) <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select HNRDA Area:</p>
                                <?php 
                                $hnrda_areas = ['Basic Research', 'Health', 'Agriculture, Aquatic and Natural Resources', 'Industry, Energy and Emerging Technology', 'Disaster Risk Reduction and Climate Change'];
                                foreach ($hnrda_areas as $area): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_area" value="<?= htmlspecialchars($area) ?>" required>
                                    <label class="form-check-label"><?= htmlspecialchars($area) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
<hr>
                            <!-- HNRDA Sector -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA Sector) <span class="text-danger">*</span></label>
                                <?php 
                                $sectors = ['Drug Discovery and Development', 'Functional Food', 'Nutrition and Food Safety', 'Re-emerging and Emerging Diseases', 'Diagnostics', 'Omic Technologies for Health', 'Biomedical Devices Engineering for Health', 'Digital and Frontier Health Technologies', 'Disaster Risk Reduction and Climate Change Adaptation in Health', 'Mental Health', 'Intellectual Property and Technology Management Program', 'Capacity Building Programs', 'Network Institution Development'];
                                foreach ($sectors as $sector): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_sector" value="<?= htmlspecialchars($sector) ?>" required>
                                    <label class="form-check-label"><?= htmlspecialchars($sector) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
<hr>
                            <!-- Focus RDI -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">FOCUS RDI AGENDA (DMMSU 2025-2030) <span class="text-danger">*</span></label>
                                <?php 
                                $focus = ['Agriculture, Aquatic, and Natural Resources', 'Education', 'Economics and Social Studies', 'Governance', 'Health, Food and Nutrition', 'Information and Communications Technology', 'Industrial Technology, Agricultural, and Biosystems Engineering', 'Gender and Development Studies', 'Mathematics and Statistics'];
                                foreach ($focus as $f): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="focus_rdi[]" value="<?= htmlspecialchars($f) ?>">
                                    <label class="form-check-label"><?= htmlspecialchars($f) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <!-- Files -->
                            <h4>Required Documents</h4>
                            
                            <div class="mb-3">
                                <label>Proposal Form <span class="text-danger">*</span></label>
                                <input type="file" name="proposal_form" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label>Curriculum Vitae of Proponent/s <span class="text-danger">*</span></label>
                                <input type="file" name="cv" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label>Proposed Data Gathering Instruments (if applicable)</label>
                                <input type="file" name="data_gathering" class="form-control" accept="application/pdf">
                            </div>

                            <div class="mb-3">
                                <label>Compliance Matrix (if applicable)</label>
                                <input type="file" name="compliance_matrix" class="form-control" accept="application/pdf">
                            </div>

                            <div class="mb-3">
                                <label>Request Letter for Ethics Review <span class="text-danger">*</span></label>
                                <input type="file" name="ethics_request" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label>Application for Ethics Review <span class="text-danger">*</span></label>
                                <input type="file" name="ethics_application" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label>Informed Consent Form (if applicable)</label>
                                <input type="file" name="informed_consent" class="form-control" accept="application/pdf">
                            </div>

                            <button type="submit" class="btn btn-primary btn-md">
                                <i class="fas fa-upload"></i> Submit Proposal
                            </button>
                        </form>
                    </div>
                </div>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>
<script>
let employees = <?= json_encode($employees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-member')) {
            const container = document.getElementById('member-container');
            const html = `
                <div class="input-group mb-2">
                    <select name="members[]" class="form-select" required>
                        <option value="">Select Member</option>
                        <?php foreach ($employees as $emp): 
                            if ($emp['id'] == $user_id) continue;
                        ?>
                            <option value="<?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>">
                                <?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-danger remove-member">-</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        if (e.target.classList.contains('remove-member')) {
            e.target.closest('.input-group').remove();
        }
    });
    </script>
    
    <?php if ($message): ?>
        <script>
            Swal.fire({
                icon: '<?= $messageType ?>',
                title: '<?= $messageType === "success" ? "Success" : "Error" ?>',
                text: '<?= $message ?>',
                confirmButtonColor: '#3085d6',
            });
        </script>
    <?php endif; ?>
</body>
</html>