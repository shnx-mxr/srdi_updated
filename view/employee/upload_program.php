<?php
session_start();
require_once "../../controller/Main.php";

// Access control: only researchers can upload
if (!isset($_SESSION['user_id']) || $_SESSION['type_id'] != 1) {
    header("Location: ../../auth/login.php");
    exit;
}

$fullname = $_SESSION['fullname'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$type_id = $_SESSION['type_id'] ?? 0;

$db = new db();
$message = '';
$messageType = '';

// Fetch all employees for dropdowns
$employees = $db->getEmployees();

// Handle form submission
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Prepare data
    $data = [
        'program_title' => $_POST['program_title'] ?? '',
        'program_leader' => $_POST['program_leader'] ?? 0,
        'sdg' => $_POST['sdg'] ?? [],
        'hnrda_area' => $_POST['hnrda_area'] ?? '',
        'hnrda_sector' => $_POST['hnrda_sector'] ?? '',
        'focus_rdi' => $_POST['focus_rdi'] ?? [],
        'created_by' => $user_id,
        'projects' => []
    ];
    
    // Process projects
    if (isset($_POST['projects']) && is_array($_POST['projects'])) {
        foreach ($_POST['projects'] as $projectKey => $project) {
            $projectData = [
                'title' => $project['title'] ?? '',
                'leader' => $project['leader'] ?? 0,
                'studies' => []
            ];
            
            // Process studies
            if (isset($project['studies']) && is_array($project['studies'])) {
                foreach ($project['studies'] as $studyKey => $study) {
                    // Get file for this study
                    $studyFile = null;
                    $fileKey = "projects_{$projectKey}_studies_{$studyKey}_attachment";
                    if (isset($_FILES[$fileKey])) {
                        $studyFile = $_FILES[$fileKey];
                    }
                    
                    $projectData['studies'][] = [
                        'title' => $study['title'] ?? '',
                        'leader' => $study['leader'] ?? 0,
                        'section' => $study['section'] ?? '',
                        'members' => $study['members'] ?? [],
                        'funding' => $study['funding'] ?? '',
                        'start_date' => $study['start_date'] ?? '',
                        'end_date' => $study['end_date'] ?? '',
                        'description' => $study['description'] ?? '',
                        'attachment' => $studyFile
                    ];
                }
            }
            
            $data['projects'][] = $projectData;
        }
    }
    
    // Prepare files array
    $files = [];
    $file_types = [
        'proposal_form', 'cv', 'data_gathering', 'compliance_matrix',
        'ethics_request', 'ethics_application', 'informed_consent'
    ];
    
    foreach ($file_types as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === 0) {
            $files[$type] = $_FILES[$type];
        }
    }
    
    // Upload program
    if ($db->uploadProgram($data, $files)) {
        $message = "Research program uploaded successfully!";
        $messageType = 'success';
        // Redirect after success
        header("Location: upload_program.php?success=1");
        exit;
    } else {
        $message = "Failed to upload research program. Please try again.";
        $messageType = 'error';
    }
}

// Show success message if redirected
if (isset($_GET['success'])) {
    $message = "Research program uploaded successfully!";
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
                <h1 class="mt-4">Upload Research Program</h1>
                <p class="text-muted">A Program contains multiple Projects, each with multiple Studies</p>

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-folder-open me-1"></i> Research Program Form
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="programForm">
                            
               

                            <!-- SUSTAINABLE DEVELOPMENT GOALS -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">SUSTAINABLE DEVELOPMENT GOALS <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select all that apply:</p>
                                <div class="row">
                                    <?php 
                                    $sdgs = [
                                        'SDG 1. No poverty',
                                        'SDG 2. Zero hunger',
                                        'SDG 3. Good health and well-being',
                                        'SDG 4. Quality education',
                                        'SDG 5. Gender equality',
                                        'SDG 6. Clean water and sanitation',
                                        'SDG 7. Affordable and clean energy',
                                        'SDG 8. Decent work and economic growth',
                                        'SDG 9. Industry, innovation and infrastructure',
                                        'SDG 10. Reduced inequalities',
                                        'SDG 11. Sustainable cities and communities',
                                        'SDG 12. Responsible consumption and production',
                                        'SDG 13. Climate action',
                                        'SDG 14. Life below water',
                                        'SDG 15. Life on land',
                                        'SDG 16. Peace, justice, and strong institutions',
                                        'SDG 17. Partnerships for the goals'
                                    ];
                                    foreach ($sdgs as $index => $sdg): 
                                    ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="sdg[]" value="<?= $index + 1 ?>" id="sdg<?= $index + 1 ?>">
                                            <label class="form-check-label" for="sdg<?= $index + 1 ?>">
                                                <?= htmlspecialchars($sdg) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- PRIORITY R&D AGENDA (HNRDA 2022-2028) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA 2022-2028) <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select HNRDA Area:</p>
                                <?php 
                                $hnrda_areas = [
                                    'Basic Research',
                                    'Health',
                                    'Agriculture, Aquatic and Natural Resources',
                                    'Industry, Energy and Emerging Technology',
                                    'Disaster Risk Reduction and Climate Change'
                                ];
                                foreach ($hnrda_areas as $area): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_area" value="<?= htmlspecialchars($area) ?>" id="area_<?= md5($area) ?>" required>
                                    <label class="form-check-label" for="area_<?= md5($area) ?>">
                                        <?= htmlspecialchars($area) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <!-- PRIORITY R&D AGENDA (HNRDA Sector) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA Sector) <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select sector:</p>
                                <?php 
                                $hnrda_sectors = [
                                    'Drug Discovery and Development',
                                    'Functional Food',
                                    'Nutrition and Food Safety',
                                    'Re-emerging and Emerging Diseases',
                                    'Diagnostics',
                                    'Omic Technologies for Health',
                                    'Biomedical Devices Engineering for Health',
                                    'Digital and Frontier Health Technologies',
                                    'Disaster Risk Reduction and Climate Change Adaptation in Health',
                                    'Mental Health',
                                    'Intellectual Property and Technology Management Program',
                                    'Capacity Building Programs',
                                    'Network Institution Development'
                                ];
                                foreach ($hnrda_sectors as $sector): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_sector" value="<?= htmlspecialchars($sector) ?>" id="sector_<?= md5($sector) ?>" required>
                                    <label class="form-check-label" for="sector_<?= md5($sector) ?>">
                                        <?= htmlspecialchars($sector) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <!-- FOCUS RDI AGENDA (DMMSU 2025-2030) -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">FOCUS RDI AGENDA (DMMSU 2025-2030) <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select all that apply:</p>
                                <?php 
                                $focus_rdi = [
                                    'Agriculture, Aquatic, and Natural Resources',
                                    'Education',
                                    'Economics and Social Studies',
                                    'Governance',
                                    'Health, Food and Nutrition',
                                    'Information and Communications Technology',
                                    'Industrial Technology, Agricultural, and Biosystems Engineering',
                                    'Gender and Development Studies',
                                    'Mathematics and Statistics'
                                ];
                                foreach ($focus_rdi as $index => $rdi): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="focus_rdi[]" value="<?= htmlspecialchars($rdi) ?>" id="rdi<?= $index ?>">
                                    <label class="form-check-label" for="rdi<?= $index ?>">
                                        <?= htmlspecialchars($rdi) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                

                            <hr class="my-4">

                            <!-- FILE ATTACHMENTS -->
                            <h4 class="mb-3">Required Documents</h4>
                            
                            <div class="mb-3">
                                <label class="form-label">Proposal Form <span class="text-danger">*</span></label>
                                <input type="file" name="proposal_form" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Curriculum Vitae of Proponent/s <span class="text-danger">*</span></label>
                                <input type="file" name="cv" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Proposed Data Gathering Instruments (if applicable)</label>
                                <input type="file" name="data_gathering" class="form-control" accept="application/pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Compliance Matrix (if applicable)</label>
                                <input type="file" name="compliance_matrix" class="form-control" accept="application/pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Request Letter for Ethics Review <span class="text-danger">*</span></label>
                                <input type="file" name="ethics_request" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Application for Ethics Review <span class="text-danger">*</span></label>
                                <input type="file" name="ethics_application" class="form-control" accept="application/pdf" required>
                            </div>

                            <div class="mb-5">
                                <label class="form-label">Informed Consent Form (if applicable)</label>
                                <input type="file" name="informed_consent" class="form-control" accept="application/pdf">
                            </div>

                            <!-- <hr class="my-4"> -->



             <!-- Program Title -->
<?php
$totalProjects = 3;
$studiesPerProject = 3;
?>

<?php for ($projNum = 1; $projNum <= $totalProjects; $projNum++): ?>
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Project <?= $projNum ?></h4>
    </div>
    <div class="card-body">

        <!-- Project Title + Leader -->
        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Program Title <span class="text-danger">*</span></label>
                <input type="text" name="projects[<?= $projNum ?>][title]" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Program Leader <span class="text-danger">*</span></label>
                <select name="projects[<?= $projNum ?>][leader]" class="form-select" required>
                    <option value="">-- Select Program Leader --</option>
                    <?php foreach ($employees as $emp): 
                        $fullName = $emp['firstname'] . ' ' . $emp['lastname'];
                    ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($fullName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Studies -->
        <?php for ($studyNum = 1; $studyNum <= $studiesPerProject; $studyNum++): ?>
        <div class="card mb-3 border-secondary">
            <div class="card-header bg-light">
                <h5 class="mb-0">Study <?= $studyNum ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold fs-6">Study Title <span class="text-danger">*</span></label>
                        <input type="text" name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][title]" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold fs-6">Section <span class="text-danger">*</span></label>
                        <select name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][section]" class="form-select" required>
                            <option value="">Select Section</option>
                            <option value="Mulberry">Mulberry</option>
                            <option value="Post Cocoon">Post Cocoon</option>
                            <option value="Silkworm">Silkworm</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
<div class="col-md-6">
    <label class="form-label fw-semibold fs-6">Study Leader <span class="text-danger">*</span></label>
    <select name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][leader]" class="form-select" required>
        <option value="">Select Leader</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?></option>
        <?php endforeach; ?>
    </select>
</div>        
<div class="col-md-6">
    <label class="form-label fw-semibold fs-6">Study Members <span class="text-danger">*</span></label>
    <select name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][members][]" class="form-select" required>
        <option value="">Select Member</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>">
                <?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>        </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold fs-6">Funding Source</label>
                        <input type="text" name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][funding]" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold fs-6">Start Date</label>
                        <input type="date" name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][start_date]" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold fs-6">End Date</label>
                        <input type="date" name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][end_date]" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold fs-6">Description</label>
                    <textarea name="projects[<?= $projNum ?>][studies][<?= $studyNum ?>][description]" class="form-control" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold fs-6">Attachment (PDF only)</label>
<input type="file" name="projects_<?= $projNum ?>_studies_<?= $studyNum ?>_attachment" class="form-control">
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>
<?php endfor; ?>




                            <button type="submit" class="btn btn-primary btn-md">
                                <i class="fas fa-upload"></i> Submit Program
                            </button>
                        </form>
                    </div>
                </div>
            </main>
            <?php include 'partials/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- PART 2: JavaScript for dynamic Projects/Studies will go here -->
    
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