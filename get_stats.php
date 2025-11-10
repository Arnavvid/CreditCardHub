<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cardhub";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    
    $sql_popularity = "SELECT card_id, score FROM card_scores ORDER BY score DESC LIMIT 15";
    $result_popularity = $conn->query($sql_popularity);
    $popularityData = [];
    while($row = $result_popularity->fetch_assoc()) {
        $row['score'] = (float)$row['score'];
        $popularityData[] = $row;
    }

    $sql_searches = "SELECT search_term, search_count FROM search_log ORDER BY search_count DESC LIMIT 10";
    $result_searches = $conn->query($sql_searches);
    $searchData = [];
    while($row = $result_searches->fetch_assoc()) {
        $searchData[] = $row;
    }

    $sql_comparisons = "SELECT card_id, comparison_count FROM comparison_log ORDER BY comparison_count DESC LIMIT 10";
    $result_comparisons = $conn->query($sql_comparisons);
    $comparisonData = [];
    while($row = $result_comparisons->fetch_assoc()) {
        $comparisonData[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'popularity' => $popularityData,
            'searches' => $searchData,
            'comparisons' => $comparisonData
        ]
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query failed: ' . $e->getMessage()]);
}
?>