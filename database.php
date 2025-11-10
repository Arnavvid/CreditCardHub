<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cardhub";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

function generateUniqueId() {
    return bin2hex(random_bytes(16));
}

// --- 3. TABLE SETUP ---
function setupTables($conn) {
    try {
        $sqlAuthUsers = "
        CREATE TABLE IF NOT EXISTS auth_users (
            user_id VARCHAR(255) PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            hashed_password VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlAuthUsers);

        $sqlUsers = "
        CREATE TABLE IF NOT EXISTS users (
            user_id VARCHAR(255) PRIMARY KEY,
            total_clicks INT DEFAULT 0 NOT NULL,
            FOREIGN KEY (user_id) REFERENCES auth_users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlUsers);

        $sqlUserCardClicks = "
        CREATE TABLE IF NOT EXISTS user_card_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            card_id VARCHAR(255) NOT NULL,
            clicks INT DEFAULT 0 NOT NULL,
            UNIQUE KEY user_card (user_id, card_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlUserCardClicks);

        $sqlCardScores = "
        CREATE TABLE IF NOT EXISTS card_scores (
            card_id VARCHAR(255) PRIMARY KEY,
            score DECIMAL(20, 10) DEFAULT 0.0 NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlCardScores);

        $sqlSearchLog = "
        CREATE TABLE IF NOT EXISTS search_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            search_term VARCHAR(255) NOT NULL,
            search_count INT DEFAULT 1 NOT NULL,
            last_searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (search_term)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlSearchLog);

        $sqlComparisonLog = "
        CREATE TABLE IF NOT EXISTS comparison_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id VARCHAR(255) NOT NULL,
            comparison_count INT DEFAULT 1 NOT NULL,
            last_compared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (card_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlComparisonLog);

        $sqlCreditCards = "
        CREATE TABLE IF NOT EXISTS credit_cards (
            card_id VARCHAR(255) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            bank VARCHAR(100) NOT NULL,
            bankName VARCHAR(255) NOT NULL,
            category JSON,
            annualFee VARCHAR(255),
            joinBonus TEXT,
            rewardRate VARCHAR(255),
            minIncome INT,
            features JSON,
            pros JSON,
            cons JSON,
            rating DECIMAL(3, 1),
            applyLink TEXT,
            image_url VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->query($sqlCreditCards);

        echo json_encode(['success' => true, 'message' => 'All tables created successfully.']);
    
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Setup failed: ' . $e->getMessage()]);
    }
}

// --- 4. SIGNUP FUNCTION (Unchanged) ---
function signup($conn, $data) {
    error_log("Signup input: " . json_encode($data));
    $full_name = $data['full_name'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    if (!$full_name || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing full name, email, or password.']);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        return;
    }
    $stmt = $conn->prepare("SELECT user_id FROM auth_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        return;
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_id = generateUniqueId();
    $conn->begin_transaction();
    try {
        $stmt_auth = $conn->prepare("INSERT INTO auth_users (user_id, full_name, email, hashed_password) VALUES (?, ?, ?, ?)");
        $stmt_auth->bind_param("ssss", $user_id, $full_name, $email, $hashed_password);
        $stmt_auth->execute();
        $stmt_user = $conn->prepare("INSERT INTO users (user_id, total_clicks) VALUES (?, 0)");
        $stmt_user->bind_param("s", $user_id);
        $stmt_user->execute();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Registration successful.', 'user_id' => $user_id]);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
}

// --- 5. LOGIN FUNCTION (Unchanged) ---
function login($conn, $data) {
    error_log("Login data received: " . json_encode($data));
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing email or password.']);
        return;
    }
    $stmt = $conn->prepare("SELECT user_id, full_name, hashed_password FROM auth_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials (user not found).']);
        return;
    }
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['hashed_password'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user_id' => $user['user_id'],
            'full_name' => $user['full_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password.']);
    }
}


// --- 6. CLICK CARD LOGIC (Unchanged) ---
function clickCard($conn) {
    $user_id = $_POST['user_id'] ?? null;
    $clicked_card_id = $_POST['card_id'] ?? null;
    if (!$user_id || !$clicked_card_id) {
        echo json_encode(['success' => false, 'message' => 'Missing user_id or card_id.']);
        return;
    }
    $conn->begin_transaction();
    try {
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt_check->bind_param("s", $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 0) {
            $dummy_email = $user_id . "@cardhub.anon"; 
            $dummy_name = "Anonymous User";
            $dummy_password = password_hash(generateUniqueId(), PASSWORD_DEFAULT);
            $stmt_auth = $conn->prepare("INSERT INTO auth_users (user_id, full_name, email, hashed_password) VALUES (?, ?, ?, ?)");
            $stmt_auth->bind_param("ssss", $user_id, $dummy_name, $dummy_email, $dummy_password);
            $stmt_auth->execute();
            $stmt_user_insert = $conn->prepare("INSERT INTO users (user_id, total_clicks) VALUES (?, 0)");
            $stmt_user_insert->bind_param("s", $user_id);
            $stmt_user_insert->execute();
        }
        $stmt_user = $conn->prepare("SELECT total_clicks FROM users WHERE user_id = ?");
        $stmt_user->bind_param("s", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $old_total_clicks = 0;
        if ($result_user->num_rows > 0) {
            $old_total_clicks = $result_user->fetch_assoc()['total_clicks'];
        } else {
            throw new Exception("User not found in 'users' table after initial check (ID: $user_id).");
        }
        $new_total_clicks = $old_total_clicks + 1;
        $stmt_history = $conn->prepare("SELECT card_id, clicks FROM user_card_clicks WHERE user_id = ?");
        $stmt_history->bind_param("s", $user_id);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        $old_clicks_map = [];
        $new_clicks_map = [];
        while ($row = $result_history->fetch_assoc()) {
            $old_clicks_map[$row['card_id']] = (int)$row['clicks'];
            $new_clicks_map[$row['card_id']] = (int)$row['clicks'];
        }
        if (isset($new_clicks_map[$clicked_card_id])) {
            $new_clicks_map[$clicked_card_id]++;
        } else {
            $new_clicks_map[$clicked_card_id] = 1;
        }
        $all_affected_cards = array_keys($new_clicks_map);
        $deltas = [];
        foreach ($all_affected_cards as $card_id) {
            $old_clicks_for_this_card = $old_clicks_map[$card_id] ?? 0;
            $old_ratio = ($old_total_clicks == 0) ? 0 : ($old_clicks_for_this_card / $old_total_clicks);
            $new_clicks_for_this_card = $new_clicks_map[$card_id];
            $new_ratio = $new_clicks_for_this_card / $new_total_clicks;
            $delta = $new_ratio - $old_ratio;
            $deltas[$card_id] = $delta;
            $stmt_update_score = $conn->prepare("
                INSERT INTO card_scores (card_id, score) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE score = score + ?
            ");
            $stmt_update_score->bind_param("sdd", $card_id, $delta, $delta);
            $stmt_update_score->execute();
        }
        $stmt_update_user = $conn->prepare("UPDATE users SET total_clicks = ? WHERE user_id = ?");
        $stmt_update_user->bind_param("is", $new_total_clicks, $user_id);
        $stmt_update_user->execute();
        $stmt_update_uc = $conn->prepare("
            INSERT INTO user_card_clicks (user_id, card_id, clicks) VALUES (?, ?, ?,)
            ON DUPLICATE KEY UPDATE clicks = ?
        ");
        $new_click_count = $new_clicks_map[$clicked_card_id];
        $stmt_update_uc->bind_param("ssii", $user_id, $clicked_card_id, $new_click_count, $new_click_count);
        $stmt_update_uc->execute();
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Click tracked and all weights recalculated.',
            'deltas_applied' => $deltas
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
}

// --- 7. GET CARD SCORE FUNCTION (Unchanged) ---
function getCardScore($conn) {
    $card_id = $_POST['card_id'] ?? $_GET['card_id'] ?? null;
    if (!$card_id) {
        echo json_encode(['success' => false, 'message' => 'Missing card_id.']);
        return;
    }
    $stmt = $conn->prepare("SELECT score FROM card_scores WHERE card_id = ?");
    $stmt->bind_param("s", $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $score = 0.0;
    if ($result->num_rows > 0) {
        $score = $result->fetch_assoc()['score'];
    }
    echo json_encode(['success' => true, 'card_id' => $card_id, 'score' => (float)$score]);
}

// --- 8. GET TOP CARDS FUNCTION (Unchanged) ---
function getTopCards($conn) {
    $n = $_POST['n'] ?? $_GET['n'] ?? 5;
    $n = (int)$n;
    if ($n <= 0) $n = 5;
    $stmt = $conn->prepare("SELECT card_id, score FROM card_scores ORDER BY score DESC LIMIT ?");
    $stmt->bind_param("i", $n);
    $stmt->execute();
    $result = $stmt->get_result();
    $top_cards = [];
    while ($row = $result->fetch_assoc()) {
        $row['score'] = (float)$row['score'];
        $top_cards[] = $row;
    }
    echo json_encode(['success' => true, 'count' => count($top_cards), 'top_cards' => $top_cards]);
}

// --- 9. LOG SEARCH FUNCTION (Unchanged) ---
function logSearch($conn, $data) {
    $term = $data['search_term'] ?? null;
    if (!$term || strlen(trim($term)) < 3) {
        echo json_encode(['success' => false, 'message' => 'Search term too short.']);
        return;
    }
    try {
        $stmt = $conn->prepare("
            INSERT INTO search_log (search_term, search_count) VALUES (?, 1)
            ON DUPLICATE KEY UPDATE search_count = search_count + 1
        ");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Search logged.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Logging failed: ' . $e->getMessage()]);
    }
}

// --- 10. LOG COMPARISON FUNCTION (Unchanged) ---
function logComparison($conn, $data) {
    $card_ids_json = $data['card_ids'] ?? null; 
    if (!$card_ids_json) {
        echo json_encode(['success' => false, 'message' => 'Missing card_ids.']);
        return;
    }
    $card_ids = json_decode($card_ids_json, true);
    if (!is_array($card_ids) || empty($card_ids)) {
         echo json_encode(['success' => false, 'message' => 'Invalid card_ids format.']);
        return;
    }
    try {
        $stmt = $conn->prepare("
            INSERT INTO comparison_log (card_id, comparison_count) VALUES (?, 1)
            ON DUPLICATE KEY UPDATE comparison_count = comparison_count + 1
        ");
        $conn->begin_transaction();
        foreach ($card_ids as $card_id) {
            $sanitized_id = htmlspecialchars(strip_tags($card_id));
            if(empty($sanitized_id)) continue;
            $stmt->bind_param("s", $sanitized_id);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Comparison logged.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Logging failed: ' . $e->getMessage()]);
    }
}

// --- ADDED: 11. GET ALL CARDS FUNCTION ---
function getAllCards($conn) {
    try {
        $result = $conn->query("SELECT * FROM credit_cards ORDER BY name ASC");
        $cards = [];
        while ($row = $result->fetch_assoc()) {
            $row['category'] = json_decode($row['category']);
            $row['features'] = json_decode($row['features']);
            $row['pros'] = json_decode($row['pros']);
            $row['cons'] = json_decode($row['cons']);
            $cards[] = $row;
        }
        echo json_encode(['success' => true, 'cards' => $cards]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch cards: ' . $e->getMessage()]);
    }
}

// --- ADDED: 12. HELPER FUNCTION FOR IMAGE UPLOAD ---
function handleImageUpload($card_id) {
    if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] == 0) {
        $target_dir = "cards/"; 
        $safe_card_id = preg_replace("/[^a-zA-Z0-9-]/", "", $card_id);
        $file_extension = pathinfo($_FILES['image_url']['name'], PATHINFO_EXTENSION);
        $target_file = $target_dir . $safe_card_id . "." . $file_extension;

        if (move_uploaded_file($_FILES['image_url']['tmp_name'], $target_file)) {
            return $target_file;
        }
    }
    return null;

// --- ADDED: 13. CREATE CARD FUNCTION ---
function createCard($conn) {
    $data = $_POST;
    
    $new_image_path = handleImageUpload($data['card_id']);
    if ($new_image_path === null) {
        $new_image_path = "cards/placeholder.jpg"; 
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO credit_cards (
                card_id, name, bank, bankName, category, annualFee, joinBonus, 
                rewardRate, minIncome, features, pros, cons, rating, applyLink, image_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $minIncome = !empty($data['minIncome']) ? (int)$data['minIncome'] : null;
        $rating = !empty($data['rating']) ? (float)$data['rating'] : null;

        $stmt->bind_param(
            "sssssssssisssss",
            $data['card_id'], $data['name'], $data['bank'], $data['bankName'],
            $data['category'], $data['annualFee'], $data['joinBonus'], $data['rewardRate'],
            $minIncome, $data['features'], $data['pros'], $data['cons'],
            $rating, $data['applyLink'], $new_image_path
        );
        
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Card created successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create card: ' . $e->getMessage()]);
    }
}

// --- ADDED: 14. UPDATE CARD FUNCTION ---
function updateCard($conn) {
    $data = $_POST;
    
    $new_image_path = handleImageUpload($data['card_id']);
    
    try {
        $sql = "
            UPDATE credit_cards SET 
                name = ?, bank = ?, bankName = ?, category = ?, annualFee = ?, joinBonus = ?, 
                rewardRate = ?, minIncome = ?, features = ?, pros = ?, cons = ?, 
                rating = ?, applyLink = ?
        ";
        
        $types = "sssssssisssss";
        
        $minIncome = !empty($data['minIncome']) ? (int)$data['minIncome'] : null;
        $rating = !empty($data['rating']) ? (float)$data['rating'] : null;
        
        $params = [
            $data['name'], $data['bank'], $data['bankName'], $data['category'],
            $data['annualFee'], $data['joinBonus'], $data['rewardRate'], $minIncome,
            $data['features'], $data['pros'], $data['cons'], $rating, $data['applyLink']
        ];

        if ($new_image_path) {
            $sql .= ", image_url = ?";
            $types .= "s";
            $params[] = $new_image_path;
        }

        $sql .= " WHERE card_id = ?";
        $types .= "s";
        $params[] = $data['card_id'];

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Card updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update card: ' . $e->getMessage()]);
    }
}

// --- ADDED: 15. DELETE CARD FUNCTION ---
function deleteCard($conn, $data) {
    $card_id = $data['card_id'] ?? null;
    if (!$card_id) {
        echo json_encode(['success' => false, 'message' => 'Missing card_id.']);
        return;
    }
    
    try {
        $stmt_get = $conn->prepare("SELECT image_url FROM credit_cards WHERE card_id = ?");
        $stmt_get->bind_param("s", $card_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        if ($result->num_rows > 0) {
            $image_path = $result->fetch_assoc()['image_url'];
            if ($image_path && file_exists($image_path) && $image_path !== 'cards/placeholder.jpg') {
                unlink($image_path);
            }
        }

        $stmt_del = $conn->prepare("DELETE FROM credit_cards WHERE card_id = ?");
        $stmt_del->bind_param("s", $card_id);
        $stmt_del->execute();
        
        echo json_encode(['success' => true, 'message' => 'Card deleted successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete card: ' . $e->getMessage()]);
    }
}

// --- ADDED: 16. GET USER PROFILE DATA ---
function getUserProfile($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Missing user_id.']);
        return;
    }

    try {
        $profile = [];

        $stmt_auth = $conn->prepare("SELECT full_name, email FROM auth_users WHERE user_id = ?");
        $stmt_auth->bind_param("s", $user_id);
        $stmt_auth->execute();
        $auth_result = $stmt_auth->get_result();
        if ($auth_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            return;
        }
        $profile = $auth_result->fetch_assoc();

        $stmt_users = $conn->prepare("SELECT total_clicks FROM users WHERE user_id = ?");
        $stmt_users->bind_param("s", $user_id);
        $stmt_users->execute();
        $users_result = $stmt_users->get_result();
        $profile['total_clicks'] = $users_result->num_rows > 0 ? $users_result->fetch_assoc()['total_clicks'] : 0;

        $stmt_fav = $conn->prepare("SELECT card_id FROM user_card_clicks WHERE user_id = ? ORDER BY clicks DESC LIMIT 1");
        $stmt_fav->bind_param("s", $user_id);
        $stmt_fav->execute();
        $fav_result = $stmt_fav->get_result();
        $profile['favorite_card'] = $fav_result->num_rows > 0 ? $fav_result->fetch_assoc()['card_id'] : 'None yet';

        $stmt_search = $conn->prepare("SELECT search_term FROM search_log ORDER BY last_searched_at DESC LIMIT 5");
        $stmt_search->execute();
        $search_result = $stmt_search->get_result();
        $recent_searches = [];
        while ($row = $search_result->fetch_assoc()) {
            $recent_searches[] = $row['search_term'];
        }
        $profile['recent_searches'] = $recent_searches;

        echo json_encode(['success' => true, 'data' => $profile]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch profile: ' . $e->getMessage()]);
    }
}

// --- ADDED: 17. UPDATE USER PROFILE (NAME) ---
function updateProfile($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $full_name = $data['full_name'] ?? null;

    if (!$user_id || !$full_name) {
        echo json_encode(['success' => false, 'message' => 'Missing user_id or full_name.']);
        return;
    }

    try {
        $stmt = $conn->prepare("UPDATE auth_users SET full_name = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $full_name, $user_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.', 'new_name' => $full_name]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or user not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
    }
}

// --- ADDED: 18. UPDATE USER PASSWORD ---
function updatePassword($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $current_password = $data['currentPassword'] ?? null;
    $new_password = $data['newPassword'] ?? null;

    if (!$user_id || !$current_password || !$new_password) {
        echo json_encode(['success' => false, 'message' => 'Missing fields.']);
        return;
    }

    try {
        $stmt_get = $conn->prepare("SELECT hashed_password FROM auth_users WHERE user_id = ?");
        $stmt_get->bind_param("s", $user_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            return;
        }
        
        $hashed_password = $result->fetch_assoc()['hashed_password'];

        if (password_verify($current_password, $hashed_password)) {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_set = $conn->prepare("UPDATE auth_users SET hashed_password = ? WHERE user_id = ?");
            $stmt_set->bind_param("ss", $new_hashed_password, $user_id);
            $stmt_set->execute();
            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Current password incorrect.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update password: ' . $e->getMessage()]);
    }
}

// --- ADDED: 19. DELETE USER ACCOUNT ---
function deleteAccount($conn, $data) {
    $user_id = $data['user_id'] ?? null;
    $password = $data['password'] ?? null;

    if (!$user_id || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing user_id or password.']);
        return;
    }

    try {
        $stmt_get = $conn->prepare("SELECT hashed_password FROM auth_users WHERE user_id = ?");
        $stmt_get->bind_param("s", $user_id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            return;
        }
        
        $hashed_password = $result->fetch_assoc()['hashed_password'];

        if (password_verify($password, $hashed_password)) {
            $stmt_del = $conn->prepare("DELETE FROM auth_users WHERE user_id = ?");
            $stmt_del->bind_param("s", $user_id);
            $stmt_del->execute();
            echo json_encode(['success' => true, 'message' => 'Account deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Password incorrect.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete account: ' . $e->getMessage()]);
    }
}

// --- UNIVERSAL ROUTER (Updated) ---
$rawInput = file_get_contents("php://input");
$decoded = json_decode($rawInput, true);

if ($decoded && is_array($decoded)) {
    $data = array_merge($_REQUEST, $decoded);
} else {
    $data = $_REQUEST;
}

$action = $data["action"] ?? $_GET["action"] ?? null;

if (!$action) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $data = $_POST;
    } else {
        echo json_encode(["success" => false, "message" => "No action provided."]);
        exit;
    }
}

// --- ROUTER ---
try {
    switch ($action) {
        case 'setup':
            setupTables($conn);
            break;
        case 'signup':
            signup($conn, $data);
            break;
        case 'login':
            login($conn, $data);
            break;
        case 'click':
            clickCard($conn);
            break;
        case 'getScore':
            // --- THIS IS THE FIX ---
            getCardScore($conn);
            break;
        case 'getTopCards':
            getTopCards($conn); // Uses $_REQUEST
            break;
        case 'logSearch':
            logSearch($conn, $data);
            break;
        case 'logComparison':
            logComparison($conn, $data); // Uses $_POST
            break;
        // --- ADDED: New Card Management Actions ---
        case 'getAllCards':
            getAllCards($conn);
            break;
        case 'createCard':
            createCard($conn); // Uses $_POST and $_FILES
            break;
        case 'updateCard':
            updateCard($conn); // Uses $_POST and $_FILES
            break;
        case 'deleteCard':
            deleteCard($conn, $data); // Uses $data (from POST or JSON)
            break;
        case 'getUserProfile':
            getUserProfile($conn, $data);
            break;
        case 'updateProfile':
            updateProfile($conn, $data);
            break;
        case 'updatePassword':
            updatePassword($conn, $data);
            break;
        case 'deleteAccount':
            deleteAccount($conn, $data);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>