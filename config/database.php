<?php
// Database configuration for SQL Server
class Database {
    private $host;
    private $database;
    private $username;
    private $password;
    private $connection;
    
    public function __construct() {
        // Database configuration - update these values for your environment
        $this->host = 'localhost'; // or your SQL Server hostname
        $this->database = 'TicketRegistrationDB';
        $this->username = 'sa'; // or your SQL Server username
        $this->password = 'your_password'; // update with your password
    }
    
    public function connect() {
        $this->connection = null;
        
        try {
            // SQL Server connection string
            $dsn = "sqlsrv:Server={$this->host};Database={$this->database}";
            
            $this->connection = new PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
        
        return $this->connection;
    }
    
    public function disconnect() {
        $this->connection = null;
    }
    
    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage());
            throw new Exception("Query execution failed");
        }
    }
    
    public function fetchAll($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->fetch();
    }
    
    public function getLastInsertId() {
        return $this->connection->lastInsertId();
    }
}

// Application configuration
class Config {
    const APP_NAME = 'Ticket Registration System';
    const APP_VERSION = '1.0.0';
    const UPLOAD_PATH = 'uploads/';
    const MAX_FILE_SIZE = 10485760; // 10MB in bytes
    const ALLOWED_FILE_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt'];
    const SESSION_TIMEOUT = 3600; // 1 hour in seconds
    const PAGINATION_LIMIT = 20;
    
    // Security settings
    const BCRYPT_COST = 12;
    const CSRF_TOKEN_LENGTH = 32;
    const SESSION_REGENERATE_INTERVAL = 300; // 5 minutes
    
    // Email settings (if needed for notifications)
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'your_email@gmail.com';
    const SMTP_PASSWORD = 'your_app_password';
    const FROM_EMAIL = 'noreply@ticketsystem.com';
    const FROM_NAME = 'Ticket Registration System';
    
    // User roles
    const ROLE_REQUESTER = 'Requester';
    const ROLE_ANALYST = 'Analyst';
    const ROLE_ADMIN = 'Admin';
    
    // Ticket statuses
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_PENDING = 'Pending';
    const STATUS_RESOLVED = 'Resolved';
    const STATUS_CLOSED = 'Closed';
    const STATUS_CANCELLED = 'Cancelled';
    
    // Priority levels
    const PRIORITY_NORMAL = 'Normal';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_CRITICAL = 'Critical';
    const PRIORITY_URGENT = 'Urgent';
    const PRIORITY_EMERGENCY = 'Emergency';
    
    // Categories
    const CATEGORY_PADE = 'PADE';
    const CATEGORY_META = 'META';
    const CATEGORY_ENCARTEIRAMENTO = 'ENCARTEIRAMENTO';
    
    public static function getStatusOptions() {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_CANCELLED
        ];
    }
    
    public static function getPriorityOptions() {
        return [
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
            self::PRIORITY_URGENT,
            self::PRIORITY_EMERGENCY
        ];
    }
    
    public static function getCategoryOptions() {
        return [
            self::CATEGORY_PADE,
            self::CATEGORY_META,
            self::CATEGORY_ENCARTEIRAMENTO
        ];
    }
    
    public static function getRoleOptions() {
        return [
            self::ROLE_REQUESTER,
            self::ROLE_ANALYST,
            self::ROLE_ADMIN
        ];
    }
    
    public static function getStatusColor($status) {
        switch($status) {
            case self::STATUS_OPEN:
                return 'status-open';
            case self::STATUS_IN_PROGRESS:
                return 'status-progress';
            case self::STATUS_PENDING:
                return 'status-progress';
            case self::STATUS_RESOLVED:
                return 'status-resolved';
            case self::STATUS_CLOSED:
                return 'status-resolved';
            case self::STATUS_CANCELLED:
                return 'status-cancelled';
            default:
                return 'status-open';
        }
    }
    
    public static function getPriorityColor($priority) {
        switch($priority) {
            case self::PRIORITY_NORMAL:
                return 'priority-normal';
            case self::PRIORITY_HIGH:
                return 'priority-high';
            case self::PRIORITY_CRITICAL:
                return 'priority-critical';
            case self::PRIORITY_URGENT:
                return 'priority-urgent';
            case self::PRIORITY_EMERGENCY:
                return 'priority-emergency';
            default:
                return 'priority-normal';
        }
    }
}

// Utility functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    return bin2hex(random_bytes(Config::CSRF_TOKEN_LENGTH));
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function createUploadDirectory() {
    $uploadPath = Config::UPLOAD_PATH;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    return $uploadPath;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendErrorResponse($message, $status = 400) {
    sendJsonResponse(['success' => false, 'message' => $message], $status);
}

function sendSuccessResponse($data = [], $message = 'Success') {
    sendJsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

// Error handling
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr in $errfile on line $errline");
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler('handleError');

// Session management
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > Config::SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Location: login.html');
        exit;
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        http_response_code(403);
        sendErrorResponse('Access denied', 403);
    }
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

// Initialize session
startSecureSession();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCSRFToken();
}
?>