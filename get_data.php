<?php
$conn = new mysqli("localhost", "root", "", "location_db");
if ($conn->connect_error) die("DB Connection Failed");

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$data = [];

switch ($type) {
    case 'district':
        $result = $conn->query("SELECT id, name FROM districts WHERE province_id = $id");
        break;
    case 'tehsil':
        $result = $conn->query("SELECT id, name FROM tehsils WHERE district_id = $id");
        break;
    case 'uc':
        $result = $conn->query("SELECT id, name FROM union_councils WHERE tehsil_id = $id");
        break;
    case 'school':
        $result = $conn->query("SELECT id, name FROM primary_schools WHERE uc_id = $id");
        break;
    default:
        $result = [];
}

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
?>
