<?php
session_start();
require_once "../../controller/Main.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new db();

    $research_id = $_POST['research_id'] ?? 0;
    $pub_title = $_POST['publication_title'] ?? '';
    $pub_date = $_POST['publication_date'] ?? '';
    $pub_link = $_POST['publication_link'] ?? '';
    $publisher = $_POST['publisher'] ?? '';

    if ($research_id && $pub_title && $pub_date && $pub_link && $publisher) {
        if ($db->addPublicationDetails($research_id, $pub_title, $pub_date, $pub_link, $publisher)) {
            echo json_encode(['success' => true, 'message' => 'Publication details added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save publication details.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
