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

    if (!$research_id) die("Invalid research ID.");

    /* ===== PDF UPLOAD ===== */
    $revised_pdf_filename = null;
    if (!empty($_FILES['revised_pdf']['name'])) {
        $ext = strtolower(pathinfo($_FILES['revised_pdf']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') die("Only PDF files are allowed.");

        $baseName = preg_replace("/[^a-zA-Z0-9_-]/", "_", pathinfo($_FILES['revised_pdf']['name'], PATHINFO_FILENAME));
        $revised_pdf_filename = $baseName . '_' . date('Ymd_His') . '.pdf';
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/srdi_system_v1/view/employee/research/';
        move_uploaded_file($_FILES['revised_pdf']['tmp_name'], $uploadDir . $revised_pdf_filename);
    }

    /* ===== UPDATE DATA ===== */
    $updateData = [
        'title'     => $title,
        'member'    => $member,
        'startDate' => $startDate,
        'endDate'   => $endDate,
        'status_id' => 1
    ];
    if ($revised_pdf_filename) $updateData['revised_pdf'] = $revised_pdf_filename;

    $db->updateResearch($research_id, $updateData);

    /* ===== NOTIFICATION ===== */
    $fullName = $_SESSION['fullname'];
    $research = $db->getResearchById($research_id);
    $message = "The research \"{$research['title']}\" was updated by $fullName.";

    $type_ids_to_notify = [2, 3, 4, 5, 6];

    // Get all users with these type IDs
    $users_to_notify = $db->getUsersByTypes($type_ids_to_notify);

    foreach ($users_to_notify as $user) {
        // Optionally skip the person who updated
        if ($user['id'] != $_SESSION['user_id']) {
            $db->insertNotification($user['id'], $message);
        }
    }

    header("Location: revised.php?success=1");
    exit;
}
