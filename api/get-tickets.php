<?php
require_once '../config/database.php';

// For demo purposes, we'll simulate being logged in as a user
// In production, this would come from session
$_SESSION['user_id'] = 3; // Default user ID
$_SESSION['user_role'] = 'Requester'; // Default role

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed', 405);
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get parameters
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['pageSize'] ?? 20);
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $category = $_GET['category'] ?? '';
    $priority = $_GET['priority'] ?? '';
    
    // Get user info from session
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // Build where clause based on user role
    $whereConditions = [];
    $params = [];
    
    // Role-based filtering
    if ($userRole === 'Requester') {
        $whereConditions[] = "t.requester_id = ?";
        $params[] = $userId;
    } elseif ($userRole === 'Analyst') {
        $whereConditions[] = "t.assigned_analyst_id = ?";
        $params[] = $userId;
    }
    // Admin can see all tickets
    
    // Search filter
    if (!empty($search)) {
        $whereConditions[] = "(t.solicitation_number LIKE ? OR t.subject LIKE ? OR t.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Status filter
    if (!empty($status)) {
        $whereConditions[] = "t.status = ?";
        $params[] = $status;
    }
    
    // Category filter
    if (!empty($category)) {
        $whereConditions[] = "t.category = ?";
        $params[] = $category;
    }
    
    // Priority filter
    if (!empty($priority)) {
        $whereConditions[] = "t.priority = ?";
        $params[] = $priority;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Count total tickets
    $countQuery = "
        SELECT COUNT(*) as total
        FROM v_TicketSummary t
        $whereClause
    ";
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalTickets = $stmt->fetch()['total'];
    
    // Calculate pagination
    $totalPages = ceil($totalTickets / $pageSize);
    $offset = ($page - 1) * $pageSize;
    
    // Get tickets
    $ticketsQuery = "
        SELECT 
            t.ticket_id,
            t.solicitation_number,
            t.category,
            t.agency,
            t.account_number,
            t.subject,
            t.status,
            t.priority,
            t.created_at,
            t.updated_at,
            t.due_date,
            t.requester_name,
            t.requester_email,
            t.analyst_name,
            t.analyst_email,
            t.interaction_count,
            t.last_interaction
        FROM v_TicketSummary t
        $whereClause
        ORDER BY t.created_at DESC
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
    ";
    
    $ticketParams = array_merge($params, [$offset, $pageSize]);
    $stmt = $db->prepare($ticketsQuery);
    $stmt->execute($ticketParams);
    $tickets = $stmt->fetchAll();
    
    // Format response
    $response = [
        'tickets' => $tickets,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalTickets,
            'pageSize' => $pageSize,
            'hasNext' => $page < $totalPages,
            'hasPrevious' => $page > 1
        ]
    ];
    
    sendSuccessResponse($response);
    
} catch (Exception $e) {
    error_log("Get tickets error: " . $e->getMessage());
    sendErrorResponse('Failed to retrieve tickets', 500);
}
?>