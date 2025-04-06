<?php
// Check if session hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "BrightMind";

// Create connection and select database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // If database doesn't exist, create it
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname";
    if ($conn->query($sql) === TRUE) {
        $conn->select_db($dbname);
    } else {
        die("Error creating database: " . $conn->error);
    }
}

// Function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Create roles table first (since it will be referenced by other tables)
$sql_roles = "CREATE TABLE IF NOT EXISTS tb_roles (
    role_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_roles) === FALSE) {
    echo "Error creating roles table: " . $conn->error;
} else {
    // Insert default roles if they don't exist
    $check_roles = $conn->query("SELECT COUNT(*) as count FROM tb_roles");
    $row = $check_roles->fetch_assoc();
    
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO tb_roles (role_name, description) VALUES 
            ('admin', 'System administrator with full access'),
            ('student', 'Student user with limited access'),
            ('counselor', 'Counselor with student management access')
        ");
    }
}

// Create students table
$sql_students = "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    grade INT NOT NULL,
    dob DATE,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    emergency_contact TEXT,
    support_plan VARCHAR(100),
    next_session DATETIME,
    last_session DATETIME,
    session_frequency VARCHAR(50),
    recent_notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_students) === FALSE) {
    echo "Error creating students table: " . $conn->error;
} else {
    // Check if there are any students
    $check_students = $conn->query("SELECT COUNT(*) as count FROM students");
    $row = $check_students->fetch_assoc();
    
    // Insert sample students if table is empty
    if ($row['count'] == 0) {
        $sample_students = "INSERT INTO students (student_id, name, grade, dob, email, phone, address, emergency_contact, support_plan, next_session, last_session, session_frequency, recent_notes) VALUES 
        ('S10057829', 'John Smith', 10, '2010-04-15', 'john.smith@brightmind.edu', '(555) 123-4567', '123 Main St, Anytown, CA 90210', 'Mary Smith (Mother) - (555) 987-6543', 'Career Guidance', '2025-03-29 10:00:00', '2025-03-15 10:00:00', 'Bi-weekly', 'John expressed interest in computer science programs...'),
        ('S10045632', 'Emma Johnson', 11, '2009-06-12', 'emma.johnson@brightmind.edu', '(555) 234-5678', '456 Oak Ave, Anytown, CA 90210', 'Robert Johnson (Father) - (555) 876-5432', 'Academic Planning', '2025-03-27 14:30:00', '2025-03-20 14:30:00', 'Weekly', 'Emma is preparing for SAT next month...'),
        ('S10089345', 'Michael Brown', 9, '2011-09-03', 'michael.brown@brightmind.edu', '(555) 345-6789', '789 Pine St, Anytown, CA 90210', 'Patricia Brown (Mother) - (555) 765-4321', 'Personal Support', '2025-03-25 15:15:00', '2025-03-18 15:15:00', 'Weekly', 'Michael is adjusting well to high school...'),
        ('S10032198', 'Sarah Williams', 12, '2008-01-28', 'sarah.williams@brightmind.edu', '(555) 456-7890', '321 Maple Dr, Anytown, CA 90210', 'David Williams (Father) - (555) 654-3210', 'College Applications', '2025-03-26 11:00:00', '2025-03-22 11:00:00', 'Bi-weekly', 'Reviewed college acceptance letters...')";
        
        if ($conn->query($sample_students) === FALSE) {
            echo "Error inserting sample students: " . $conn->error;
        }
    }
}

// Create registration table with role reference
$sql_register = "CREATE TABLE IF NOT EXISTS tb_register (
    reg_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(50) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT(11) NOT NULL,
    fullname VARCHAR(100) NULL,
    address TEXT NULL,
    gender VARCHAR(10) NULL,
    birthdate DATE NULL,
    education TEXT NULL,
    bio TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES tb_roles(role_id)
)";

if ($conn->query($sql_register) === FALSE) {
    echo "Error creating registration table: " . $conn->error;
}

// Create login sessions table
$sql_sessions = "CREATE TABLE IF NOT EXISTS tb_login_sessions (
    session_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reg_id INT(11) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    status ENUM('active', 'expired') DEFAULT 'active',
    FOREIGN KEY (reg_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE
)";

if ($conn->query($sql_sessions) === FALSE) {
    echo "Error creating sessions table: " . $conn->error;
}

// Check if subjects table exists first
$table_exists = $conn->query("SHOW TABLES LIKE 'tb_subjects'")->num_rows > 0;

// If table exists but status column doesn't, we need to add it
if ($table_exists && !columnExists($conn, 'tb_subjects', 'status')) {
    $alter_query = "ALTER TABLE tb_subjects 
                    ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active',
                    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    $conn->query($alter_query);
}

// Create or update subjects table
$sql_subjects = "CREATE TABLE IF NOT EXISTS tb_subjects (
    subject_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql_subjects) === FALSE) {
    echo "Error creating subjects table: " . $conn->error;
}

// Create counseling sessions table
$sql_counseling = "CREATE TABLE IF NOT EXISTS tb_counseling_sessions (
    session_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    counselor_id INT(11) NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    duration INT NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES tb_register(reg_id),
    FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id)
)";

if ($conn->query($sql_counseling) === FALSE) {
    echo "Error creating counseling sessions table: " . $conn->error;
}

// Create booking table
$sql_booking = "CREATE TABLE IF NOT EXISTS tb_bookings (
    booking_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    counselor_id INT(11) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    duration INT NOT NULL DEFAULT 60,
    reason TEXT,
    session_type ENUM('video', 'text') NOT NULL DEFAULT 'text',
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    notification_sent TINYINT(1) DEFAULT 0,
    meeting_link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE
)";

if ($conn->query($sql_booking) === FALSE) {
    echo "Error creating booking table: " . $conn->error;
}

// ... existing code ...

// Create indexes for messages table
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_sender ON tb_messages(sender_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_booking ON tb_messages(booking_id)");

// ... rest of the code ...
// Create messages table
// Create messages table
// Create messages table
$sql_messages = "CREATE TABLE IF NOT EXISTS tb_messages (
    message_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES tb_bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE
)";

if ($conn->query($sql_messages) === FALSE) {
    echo "Error creating messages table: " . $conn->error;
}

// Now create indexes for messages table after the table exists
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_sender ON tb_messages(sender_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_booking ON tb_messages(booking_id)");

// Create classes table
$sql_classes = "CREATE TABLE IF NOT EXISTS tb_classes (
    class_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    counselor_id INT(11) NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    description TEXT,
    session_type ENUM('video', 'text', 'in-person') NOT NULL,
    meeting_link TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE
)";

if ($conn->query($sql_classes) === FALSE) {
    echo "Error creating classes table: " . $conn->error;
}

// Create index for classes table
$conn->query("CREATE INDEX IF NOT EXISTS idx_classes_counselor ON tb_classes(counselor_id)");
// Create counselor availability tabl
// Create index for classes table
// Create counselor availability table
$sql_counselor_availability = "CREATE TABLE IF NOT EXISTS tb_counselor_availability (
   id INT PRIMARY KEY AUTO_INCREMENT,
    counselor_id INT(11) NOT NULL,
    day_of_week INT NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE,
    UNIQUE KEY unique_counselor_day (counselor_id, day_of_week)
)";

if ($conn->query($sql_counselor_availability) === FALSE) {
    echo "Error creating counselor availability table: " . $conn->error;
} else {
    // Insert sample counselor availability if table is empty
    $check_availability = $conn->query("SELECT COUNT(*) as count FROM tb_counselor_availability");
    $row = $check_availability->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert default availability (Monday to Friday)
        $sample_availability = "INSERT INTO tb_counselor_availability (counselor_id, day_of_week, available) VALUES 
            (3, 1, 1),  -- Monday
            (3, 2, 1),  -- Tuesday
            (3, 3, 1),  -- Wednesday
            (3, 4, 1),  -- Thursday
            (3, 5, 1),  -- Friday
            (4, 1, 1),  -- Monday
            (4, 2, 1),  -- Tuesday
            (4, 3, 1),  -- Wednesday
            (4, 4, 1),  -- Thursday
            (4, 5, 1)   -- Friday
        ";
        
        if ($conn->query($sample_availability) === FALSE) {
            echo "Error inserting sample availability: " . $conn->error;
        }
    }
}

// Create view for counselor availability
$sql_availability_view = "CREATE OR REPLACE VIEW vw_counselor_availability AS
    SELECT 
        ca.counselor_id,
        r.fullname AS counselor_name,
        CASE ca.day_of_week 
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END AS day_name,
        ca.day_of_week,
        ca.available,
        r.status AS counselor_status
    FROM tb_counselor_availability ca
    JOIN tb_register r ON ca.counselor_id = r.reg_id
    WHERE r.role_id = (SELECT role_id FROM tb_roles WHERE role_name = 'counselor')
    ORDER BY ca.counselor_id, ca.day_of_week";
// Create indexes for better performance
// ... continue with existing indexes ...

// Create indexes for better performance
$conn->query("CREATE INDEX IF NOT EXISTS idx_login_regid ON tb_login_sessions(reg_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_login_status ON tb_login_sessions(status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_counseling_student ON tb_counseling_sessions(student_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_counseling_counselor ON tb_counseling_sessions(counselor_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_sender ON tb_messages(sender_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_messages_receiver ON tb_messages(receiver_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_booking_student ON tb_bookings(student_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_booking_counselor ON tb_bookings(counselor_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_booking_status ON tb_bookings(status)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_booking_date ON tb_bookings(booking_date)");

// Function to register new user with role
function registerUser($conn, $username, $email, $phone, $password, $role_id = 2) {
    $conn->begin_transaction();
    
    try {
        $check_user = userExists($conn, $username, $email, $phone);
        if ($check_user['exists']) {
            return ['success' => false, 'message' => $check_user['message']];
        }
        
        // Verify role exists
        $stmt = $conn->prepare("SELECT role_id FROM tb_roles WHERE role_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed (role check): " . $conn->error);
        }
        
        $stmt->bind_param("i", $role_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed (role check): " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid role selected'];
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO tb_register (username, email, phone, password, role_id) VALUES (?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed (insert user): " . $conn->error);
        }
        
        $stmt->bind_param("ssssi", $username, $email, $phone, $hashed_password, $role_id);
        
        if ($stmt->execute()) {
            $reg_id = $conn->insert_id;
            
            // If user is a student (role_id = 2), add to students table with pending status
            if ($role_id == 2) {
                // Generate a student ID
                $year = date('y');
                $random = mt_rand(1000, 9999);
                $student_id = "S{$year}{$random}";
                
                // Add to students table with pending status
                $student_sql = "INSERT INTO students (student_id, name, grade, email, phone, status) 
                                VALUES (?, ?, 0, ?, ?, 'pending')";
                $stmt = $conn->prepare($student_sql);
                
                if (!$stmt) {
                    throw new Exception("Prepare statement failed (insert student): " . $conn->error);
                }
                
                $stmt->bind_param("ssss", $student_id, $username, $email, $phone);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding student record: " . $stmt->error);
                }
            }
            
            $conn->commit();
            return ['success' => true, 'reg_id' => $reg_id];
        } else {
            throw new Exception("Error registering user: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to check if user exists
function userExists($conn, $username, $email, $phone) {
    $stmt = $conn->prepare("SELECT username, email, phone FROM tb_register WHERE username = ? OR email = ? OR phone = ?");
    if (!$stmt) {
        return ['exists' => true, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param("sss", $username, $email, $phone);
    if (!$stmt->execute()) {
        return ['exists' => true, 'message' => 'Database error: ' . $stmt->error];
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['username'] === $username) {
            return ['exists' => true, 'message' => 'Username already exists'];
        }
        if ($row['email'] === $email) {
            return ['exists' => true, 'message' => 'Email already registered'];
        }
        if ($row['phone'] === $phone) {
            return ['exists' => true, 'message' => 'Phone number already registered'];
        }
    }
    
    return ['exists' => false, 'message' => ''];
}

function validateLogin($conn, $username_or_email, $password) {
    // Ensure database is selected
    if (!$conn->select_db("BrightMind")) {
        error_log("Failed to select database in validateLogin");
        return ['success' => false, 'message' => 'Database connection error'];
    }
    
    error_log("Login attempt - Username/Email: " . $username_or_email);
    
    $stmt = $conn->prepare("
        SELECT r.reg_id, r.username, r.password, r.status, r.role_id, ro.role_name
        FROM tb_register r
        JOIN tb_roles ro ON r.role_id = ro.role_id
        WHERE (r.username = ? OR r.email = ? OR r.phone = ?) 
        AND r.status = 'active'
    ");
    
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        return ['success' => false, 'message' => 'Database error'];
    }
    
    $stmt->bind_param("sss", $username_or_email, $username_or_email, $username_or_email);
    
    if (!$stmt->execute()) {
        error_log("Execute statement failed: " . $stmt->error);
        return ['success' => false, 'message' => 'Database error'];
    }
    
    $result = $stmt->get_result();
    error_log("Query result rows: " . $result->num_rows);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        error_log("User found: " . $user['username'] . ", Role: " . $user['role_name']);
        
        if (password_verify($password, $user['password'])) {
            // Create login session
            $session_created = createLoginSession($conn, $user['reg_id']);
            error_log("Login successful - Role: " . $user['role_name'] . ", Role ID: " . $user['role_id'] . ", Username: " . $user['username']);
            
            if (!$session_created) {
                error_log("Session creation failed for user ID: " . $user['reg_id']);
                return ['success' => false, 'message' => 'Failed to create login session'];
            }
            
            $_SESSION['reg_id'] = $user['reg_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['logged_in'] = true;
            
            // Add dashboard paths
            $dashboards = [
                'admin' => 'admin_dashboard.php',
                'counselor' => 'counselor_dashboard.php',
                'student' => 'student_dashboard.php'
            ];
            
            $role = strtolower($user['role_name']);
            if (isset($dashboards[$role])) {
                return [
                    'success' => true,
                    'reg_id' => $user['reg_id'],
                    'username' => $user['username'],
                    'role_name' => $user['role_name'],
                    'role_id' => $user['role_id'],
                    'redirect' => $dashboards[$role]
                ];
            } else {
                error_log("Invalid role detected - Role: " . $role . ", User ID: " . $user['reg_id']);
                return ['success' => false, 'message' => 'Invalid role configuration'];
            }
        }
        error_log("Password verification failed for user: " . $username_or_email);
        return ['success' => false, 'message' => 'Invalid password'];
    }
    error_log("User not found: " . $username_or_email);
    return ['success' => false, 'message' => 'User not found'];
}

// Function to create login session
function createLoginSession($conn, $reg_id) {
    try {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        
        // Close any existing active sessions
        $update = $conn->prepare("
            UPDATE tb_login_sessions 
            SET status = 'expired', logout_time = CURRENT_TIMESTAMP 
            WHERE reg_id = ? AND status = 'active'
        ");
        
        if (!$update || !$update->bind_param("i", $reg_id) || !$update->execute()) {
            return false;
        }
        
        // Create new session
        $stmt = $conn->prepare("INSERT INTO tb_login_sessions (reg_id, ip_address, user_agent) VALUES (?, ?, ?)");
        if (!$stmt || !$stmt->bind_param("iss", $reg_id, $ip, $user_agent) || !$stmt->execute()) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Exception in createLoginSession: " . $e->getMessage());
        return false;
    }
}

// Function to end login session
function endLoginSession($conn, $reg_id) {
    $stmt = $conn->prepare("
        UPDATE tb_login_sessions 
        SET status = 'expired', logout_time = CURRENT_TIMESTAMP 
        WHERE reg_id = ? AND status = 'active'
    ");
    
    if (!$stmt || !$stmt->bind_param("i", $reg_id) || !$stmt->execute()) {
        return false;
    }
    
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    return true;
}

// Function to get user details
function getUserDetails($conn, $reg_id) {
    $stmt = $conn->prepare("
        SELECT r.*, ro.role_name
        FROM tb_register r
        JOIN tb_roles ro ON r.role_id = ro.role_id
        WHERE r.reg_id = ?
    ");
    
    if (!$stmt || !$stmt->bind_param("i", $reg_id) || !$stmt->execute()) {
        return null;
    }
    
    $result = $stmt->get_result();
    return ($result->num_rows === 1) ? $result->fetch_assoc() : null;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Function to check if user has a specific role
function hasRole($role_name) {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === $role_name;
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Function to require specific role
function requireRole($required_role) {
    requireLogin();
    if (!hasRole($required_role)) {
        header("Location: unauthorized.php");
        exit;
    }
}

// Function to get all roles
function getAllRoles($conn) {
    $result = $conn->query("SELECT * FROM tb_roles ORDER BY role_id");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAllSubjects($conn, $status = null) {
    // First check if the table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'tb_subjects'");
    if ($table_check->num_rows == 0) {
        return []; // Table doesn't exist yet
    }
    
    // Always check if status column exists before trying to use it
    $column_check = $conn->query("SHOW COLUMNS FROM tb_subjects LIKE 'status'");
    $status_column_exists = $column_check->num_rows > 0;
    
    if ($status && !$status_column_exists) {
        // If status filtering is requested but column doesn't exist,
        // just get all subjects without filtering
        $sql = "SELECT * FROM tb_subjects ORDER BY subject_name";
        $result = $conn->query($sql);
    } else if ($status && $status_column_exists) {
        // If status column exists and filtering is requested
        $stmt = $conn->prepare("SELECT * FROM tb_subjects WHERE status = ? ORDER BY subject_name");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Get all subjects without filtering
        $sql = "SELECT * FROM tb_subjects ORDER BY subject_name";
        $result = $conn->query($sql);
    }
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to create a new booking
function createBooking($conn, $student_id, $counselor_id, $booking_date, $booking_time, $duration, $reason) {
    $stmt = $conn->prepare("
        INSERT INTO tb_bookings (student_id, counselor_id, booking_date, booking_time, duration, reason) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt || !$stmt->bind_param("iissss", $student_id, $counselor_id, $booking_date, $booking_time, $duration, $reason) || !$stmt->execute()) {
        return ['success' => false, 'message' => 'Error creating booking: ' . $conn->error];
    }
    
    return ['success' => true, 'booking_id' => $conn->insert_id];
}

// Function to update booking status
function updateBookingStatus($conn, $booking_id, $status) {
    $stmt = $conn->prepare("
        UPDATE tb_bookings
        SET status = ?
        WHERE booking_id = ?
    ");
    
    if (!$stmt || !$stmt->bind_param("si", $status, $booking_id) || !$stmt->execute()) {
        return ['success' => false, 'message' => 'Error updating booking: ' . $conn->error];
    }
    
    return ['success' => true];
}

// Function to get bookings by student
function getStudentBookings($conn, $student_id, $status = null) {
    $sql = "
        SELECT b.*, 
               r1.username as student_name,
               r2.username as counselor_name
        FROM tb_bookings b
        JOIN tb_register r1 ON b.student_id = r1.reg_id
        JOIN tb_register r2 ON b.counselor_id = r2.reg_id
        WHERE b.student_id = ?
    ";
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $student_id, $status);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
    }
    
    if (!$stmt || !$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get bookings by counselor
function getCounselorBookings($conn, $counselor_id, $status = null) {
    $sql = "
        SELECT b.*, 
               r1.username as student_name,
               r2.username as counselor_name
        FROM tb_bookings b
        JOIN tb_register r1 ON b.student_id = r1.reg_id
        JOIN tb_register r2 ON b.counselor_id = r2.reg_id
        WHERE b.counselor_id = ?
    ";
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $counselor_id, $status);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $counselor_id);
    }
    
    if (!$stmt || !$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
// After getCounselorBookings() function...

// Create resources table
$sql_resources = "CREATE TABLE IF NOT EXISTS tb_resources (
    resource_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    counselor_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    resource_type ENUM('document', 'video', 'link', 'other') NOT NULL,
    resource_url TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id) ON DELETE CASCADE
)";

if ($conn->query($sql_resources) === FALSE) {
    echo "Error creating resources table: " . $conn->error;
}

// Create index for resources table
$conn->query("CREATE INDEX IF NOT EXISTS idx_resources_counselor ON tb_resources(counselor_id)");
$conn->query("CREATE INDEX IF NOT EXISTS idx_resources_status ON tb_resources(status)");

// Function to add a new resource
function addResource($conn, $counselor_id, $title, $description, $resource_type, $resource_url = null, $file_path = null) {
    $stmt = $conn->prepare("INSERT INTO tb_resources (counselor_id, title, description, resource_type, resource_url, file_path) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt || !$stmt->bind_param("isssss", $counselor_id, $title, $description, $resource_type, $resource_url, $file_path) || !$stmt->execute()) {
        return ['success' => false, 'message' => 'Error adding resource: ' . $conn->error];
    }
    
    return ['success' => true, 'resource_id' => $conn->insert_id];
}

// Function to get resources by counselor
function getCounselorResources($conn, $counselor_id) {
    $sql = "SELECT r.*, reg.username as counselor_name 
            FROM tb_resources r
            JOIN tb_register reg ON r.counselor_id = reg.reg_id
            WHERE r.counselor_id = ? AND r.status = 'active'
            ORDER BY r.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt || !$stmt->bind_param("i", $counselor_id) || !$stmt->execute()) {
        return [];
    }
    
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all active resources for students
function getAllActiveResources($conn) {
    $sql = "SELECT r.*, reg.username as counselor_name 
            FROM tb_resources r
            JOIN tb_register reg ON r.counselor_id = reg.reg_id
            WHERE r.status = 'active'
            ORDER BY r.created_at DESC";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to update resource status
function updateResourceStatus($conn, $resource_id, $status) {
    $stmt = $conn->prepare("UPDATE tb_resources SET status = ? WHERE resource_id = ?");
    
    if (!$stmt || !$stmt->bind_param("si", $status, $resource_id) || !$stmt->execute()) {
        return ['success' => false, 'message' => 'Error updating resource status: ' . $conn->error];
    }
    
    return ['success' => true];
}

// Function to update resource details
function updateResource($conn, $resource_id, $title, $description, $resource_type, $resource_url = null, $file_path = null) {
    $stmt = $conn->prepare("UPDATE tb_resources SET title = ?, description = ?, resource_type = ?, resource_url = ?, file_path = ? WHERE resource_id = ?");
    
    if (!$stmt || !$stmt->bind_param("sssssi", $title, $description, $resource_type, $resource_url, $file_path, $resource_id) || !$stmt->execute()) {
        return ['success' => false, 'message' => 'Error updating resource: ' . $conn->error];
    }
    
    return ['success' => true];
}


?>