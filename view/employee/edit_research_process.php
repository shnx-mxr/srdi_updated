<?php
session_start();
require_once "../../controller/Main.php";

$db = new db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $research_id = $_POST['research_id'] ?? 0;
    $title       = $_POST['title'] ?? '';
    $member      = $_POST['member'] ?? '';
    $startDate   = $_POST['startDate'] ?? null;
    $endDate     = $_POST['endDate'] ?? null;
    $user_id     = $_SESSION['user_id'] ?? 0;

    if (!$research_id) die("Invalid research ID.");

    /* ===== PDF UPLOAD ===== */
    $revised_pdf_filename = null;
    if (!empty($_FILES['revised_pdf']['name'])) {
        $ext = strtolower(pathinfo($_FILES['revised_pdf']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') die("Only PDF files are allowed.");

        $baseName = preg_replace("/[^a-zA-Z0-9_-]/", "_", pathinfo($_FILES['revised_pdf']['name'], PATHINFO_FILENAME));
        $revised_pdf_filename = $baseName . '_' . date('Ymd_His') . '.pdf';
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/srdi_system_v1/view/employee/research/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        move_uploaded_file($_FILES['revised_pdf']['tmp_name'], $uploadDir . $revised_pdf_filename);
    }

    /* ===== UPDATE DATA ===== */
    // Status = 1 (Pending) - goes back to Section Head
    // Clear rejected/cancelled flags
    $updateData = [
        'title'     => $title,
        'member'    => $member,
        'startDate' => $startDate,
        'endDate'   => $endDate,
        'status_id' => 1,
        'rejected_by_exec' => 0,
        'cancelled_by_exec' => 0,
        'processed_by_records' => 0,
        'desisyon_id' => null,
        'decided_by_role' => null,
        'comment' => null,
        'compliance' => null
    ];
    if ($revised_pdf_filename) $updateData['revised_pdf'] = $revised_pdf_filename;

    $db->updateResearch($research_id, $updateData);

    /* ===== NOTIFICATION ===== */
    $fullName = $_SESSION['fullname'] ?? 'Researcher';
    $research = $db->getResearchById($research_id);
    $researchTitle = $research['title'];
    
    // Notify ONLY Section Head (type_id = 2)
    $result = $db->getConnection()->query("SELECT id FROM employee WHERE type_id = 2");
    if ($result) {
        while ($secHead = $result->fetch_assoc()) {
            $message = "Research '{$researchTitle}' has been resubmitted by {$fullName} after revision.";
            $db->insertNotification($secHead['id'], $message, $research_id, 'pending');
        }
    }
    
    // Notify researcher (confirmation)
    $message = "Your research '{$researchTitle}' has been resubmitted to Section Head for review.";
    $db->insertNotification($user_id, $message, $research_id, 'pending');
    
    // Log activity
    $db->insertLog($user_id, "Resubmitted revised research '{$researchTitle}'");

    header("Location: revised.php?success=1");
    exit;
}