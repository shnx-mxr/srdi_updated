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

// Handle submission (same as before)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'project_title' => $_POST['project_title'] ?? '',
        'project_leader' => $_POST['project_leader'] ?? 0,
        'sdg' => $_POST['sdg'] ?? [],
        'hnrda_area' => $_POST['hnrda_area'] ?? '',
        'hnrda_sector' => $_POST['hnrda_sector'] ?? '',
        'focus_rdi' => $_POST['focus_rdi'] ?? [],
        'created_by' => $user_id,
        'studies' => []
    ];
    
    if (isset($_POST['studies']) && is_array($_POST['studies'])) {
        foreach ($_POST['studies'] as $studyKey => $study) {
            $fileKey = "study_{$studyKey}_attachment";
            $studyFile = isset($_FILES[$fileKey]) ? $_FILES[$fileKey] : null;
            
            $data['studies'][] = [
                'title' => $study['title'] ?? '',
                'leader' => $study['leader'] ?? 0,
                'section' => $study['section'] ?? '',
                'members' => $study['members'] ?? [],
                'funding' => $study['funding'] ?? '',
                'duration' => $study['duration'] ?? '',
                'proposed_budget' => $study['proposed_budget'] ?? '',
                'attachment' => $studyFile
            ];
        }
    }
    
    $files = [];
    $file_types = ['proposal_form', 'cv', 'data_gathering', 'compliance_matrix', 'ethics_request', 'ethics_application', 'informed_consent'];
    foreach ($file_types as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === 0) {
            $files[$type] = $_FILES[$type];
        }
    }
    
    if ($db->uploadProject($data, $files)) {
        header("Location: upload_project.php?success=1");
        exit;
    } else {
        $message = "Failed to upload project.";
        $messageType = 'error';
    }
}

if (isset($_GET['success'])) {
    $message = "Project uploaded successfully!";
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
                <h1 class="mt-4">Upload Research Project Proposal</h1>
                <p class="text-muted">A Project contains multiple Studies</p>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-project-diagram"></i> Research Project Proposal Form
                    </div>
                    <div class="card-body">
                     <form method="POST" enctype="multipart/form-data">

    <!-- STUDIES SECTION -->
    <div id="studies-container"></div>

    <button type="button" class="btn btn-primary mb-4" id="addStudyBtn">
        <i class="fas fa-plus"></i> Add Study
    </button>

    <hr class="my-4">
<!-- Project Details Card -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Project Details</h5>
    </div>
    <div class="card-body">

        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">
                    Project Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="project_title" 
                       class="form-control" 
                       placeholder="Enter Project Title" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">
                    Project Leader <span class="text-danger">*</span>
                </label>
                <select name="project_leader" class="form-select" required>
                    <option value="">-- Select Leader --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>">
                            <?= htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

    </div>
</div>


<hr class="my-4">
    <!-- SDGs -->
    <div class="mb-4">




                       
                            <!-- SDGs -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">SUSTAINABLE DEVELOPMENT GOALS <span class="text-danger">*</span></label>
                                <p class="text-muted small">Please select all that apply:</p>
                                <div class="row">
                                    <?php 
                                    $sdgs = ['SDG 1. No poverty', 'SDG 2. Zero hunger', 'SDG 3. Good health and well-being', 'SDG 4. Quality education', 'SDG 5. Gender equality', 'SDG 6. Clean water and sanitation', 'SDG 7. Affordable and clean energy', 'SDG 8. Decent work and economic growth', 'SDG 9. Industry, innovation and infrastructure', 'SDG 10. Reduced inequalities', 'SDG 11. Sustainable cities and communities', 'SDG 12. Responsible consumption and production', 'SDG 13. Climate action', 'SDG 14. Life below water', 'SDG 15. Life on land', 'SDG 16. Peace, justice, and strong institutions', 'SDG 17. Partnerships for the goals'];
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

                            <!-- HNRDA Area -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA 2022-2028) <span class="text-danger">*</span></label>
                                <?php 
                                $hnrda = ['Basic Research', 'Health', 'Agriculture, Aquatic and Natural Resources', 'Industry, Energy and Emerging Technology', 'Disaster Risk Reduction and Climate Change'];
                                foreach ($hnrda as $h): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_area" value="<?= $h ?>" required>
                                    <label class="form-check-label"><?= $h ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
     

                            <!-- HNRDA Sector -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">PRIORITY R&D AGENDA (HNRDA Sector) <span class="text-danger">*</span></label>
                                <?php 
                                $sectors = ['Drug Discovery and Development', 'Functional Food', 'Nutrition and Food Safety', 'Re-emerging and Emerging Diseases', 'Diagnostics', 'Omic Technologies for Health', 'Biomedical Devices Engineering for Health', 'Digital and Frontier Health Technologies', 'Disaster Risk Reduction and Climate Change Adaptation in Health', 'Mental Health', 'Intellectual Property and Technology Management Program', 'Capacity Building Programs', 'Network Institution Development'];
                                foreach ($sectors as $s): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="hnrda_sector" value="<?= $s ?>" required>
                                    <label class="form-check-label"><?= $s ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Focus RDI -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">FOCUS RDI AGENDA (DMMSU 2025-2030) <span class="text-danger">*</span></label>
                                <?php 
                                $focus = ['Agriculture, Aquatic, and Natural Resources', 'Education', 'Economics and Social Studies', 'Governance', 'Health, Food and Nutrition', 'Information and Communications Technology', 'Industrial Technology, Agricultural, and Biosystems Engineering', 'Gender and Development Studies', 'Mathematics and Statistics'];
                                foreach ($focus as $f): 
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="focus_rdi[]" value="<?= $f ?>">
                                    <label class="form-check-label"><?= $f ?></label>
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
<?php
// Encode employees array safely for JS
$employees_json = json_encode($employees);
?>
<script>
const employees = <?= $employees_json ?>;
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
 <script>
let studyCount = 3; // default: 3 studies on load
const studiesContainer = document.getElementById('studies-container');

// Function to create Study card
function createStudyCard(num) {
    let membersOptions = employees.map(emp => `<option value="${emp.firstname} ${emp.lastname}">${emp.firstname} ${emp.lastname}</option>`).join('');

    const html = `
    <div class="card mb-3 study-card" data-study="${num}">
        <div class="card-header bg-light d-flex justify-content-between">
            <h5 class="mb-0">Study ${num}</h5>
            ${num > 3 ? `<button type="button" class="btn btn-sm btn-danger remove-study">Remove</button>` : ''}
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label>Study Title <span class="text-danger">*</span></label>
                    <input type="text" name="studies[${num}][title]" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Section <span class="text-danger">*</span></label>
                    <select name="studies[${num}][section]" class="form-select" required>
                        <option value="">Select Section</option>
                        <option value="Mulberry">Mulberry</option>
                        <option value="Post Cocoon">Post Cocoon</option>
                        <option value="Silkworm">Silkworm</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Study Leader <span class="text-danger">*</span></label>
                    <select name="studies[${num}][leader]" class="form-select" required>
                        <option value="">Select Leader</option>
                        ${membersOptions}
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Study Members <span class="text-danger">*</span></label>
                    <div class="member-container">
                        <div class="input-group mb-2">
                            <select name="studies[${num}][members][]" class="form-select" required>
                                <option value="">Select Member</option>
                                ${membersOptions}
                            </select>
                            <button type="button" class="btn btn-success add-member">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Funding Source</label>
                    <input type="text" name="studies[${num}][funding]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Proposed Budget (PHP) <span class="text-danger">*</span></label>
                    <input type="number" name="studies[${num}][proposed_budget]" class="form-control" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Proposed Duration (months)</label>
                    <input type="number" name="studies[${num}][duration]" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Attachment (PDF only) <span class="text-danger">*</span></label>
                    <input type="file" name="study_${num}_attachment" class="form-control" accept="application/pdf" required>
                </div>
            </div>
        </div>
    </div>
    `;

    studiesContainer.insertAdjacentHTML('beforeend', html);
}


document.addEventListener('DOMContentLoaded', () => {
    for (let i = 1; i <= 3; i++) {
        createStudyCard(i);
    }
});


document.getElementById('addStudyBtn').addEventListener('click', () => {
    studyCount++;
    createStudyCard(studyCount);
});

document.addEventListener('click', function(e) {
    // Remove study
    if (e.target.classList.contains('remove-study')) {
        if (confirm('Remove this study?')) e.target.closest('.study-card').remove();
    }

    // Add member
    if (e.target.classList.contains('add-member')) {
        const container = e.target.closest('.member-container');
        const studyNum = e.target.closest('.study-card').dataset.study;
        const html = `
            <div class="input-group mb-2">
                <select name="studies[${studyNum}][members][]" class="form-select" required>
                    <option value="">Select Member</option>
                    ${employees.map(emp => `<option value="${emp.firstname} ${emp.lastname}">${emp.firstname} ${emp.lastname}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-danger remove-member">-</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    // Remove member
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