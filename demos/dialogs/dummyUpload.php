<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uploadedFiles = $_FILES["uploadedfile"];
    $uploadDir = trim($_POST['path']);
    // Loop through each uploaded file
    for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
        $fileName = basename($uploadedFiles['name'][$i]);
        $targetPath = $uploadDir . $fileName;
        // Check if the file was successfully uploaded
        if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
            echo "File '$fileName' uploaded successfully.<br>";
        } else {
            echo "Error uploading file '$fileName'.<br>";
        }
    }
}
?>
