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
    
    // Get user info from session
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // Build where clause based on user role
    $whereCondition = '';
    $params = [];
    
    if ($userRole === 'Requester') {
        $whereCondition = 'WHERE requester_id = ?';
        $params[] = $userId;
    } elseif ($userRole === 'Analyst') {
        $whereCondition = 'WHERE assigned_analyst_id = ?';
        $params[] = $userId;
    }
    // Admin can see all tickets
    
    // Get overall statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'Open' THEN 1 END) as open,
            COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as inProgress,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved,
            COUNT(CASE WHEN status = 'Closed' THEN 1 END) as closed,
            COUNT(CASE WHEN status = 'Cancelled' THEN 1 END) as cancelled,
            COUNT(CASE WHEN priority = 'High' THEN 1 END) as highPriority,
            COUNT(CASE WHEN priority = 'Critical' THEN 1 END) as criticalPriority,
            COUNT(CASE WHEN priority = 'Urgent' THEN 1 END) as urgentPriority,
            COUNT(CASE WHEN priority = 'Emergency' THEN 1 END) as emergencyPriority,
            COUNT(CASE WHEN due_date < GETDATE() AND status NOT IN ('Resolved', 'Closed') THEN 1 END) as overdue
        FROM Tickets
        $whereCondition
    ";
    
    $stmt = $db->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
    // Get category breakdown
    $categoryQuery = "
        SELECT 
            category,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'Open' THEN 1 END) as open,
            COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as inProgress,
            COUNT(CASE WHEN status = 'Resolved' THEN 1 END) as resolved
        FROM Tickets
        $whereCondition
        GROUP BY category
    ";
    
    $stmt = $db->prepare($categoryQuery);
    $stmt->execute($params);
    $categoryStats = $stmt->fetchAll();
    
    // Get recent activity (last 30 days)
    $recentActivityQuery = "
        SELECT 
            CAST(created_at AS DATE) as date,
            COUNT(*) as tickets_created
        FROM Tickets
        WHERE created_at >= DATEADD(day, -30, GETDATE())
        $whereCondition
        GROUP BY CAST(created_at AS DATE)
        ORDER BY date DESC
    ";
    
    $stmt = $db->prepare($recentActivityQuery);
    $stmt->execute($params);
    $recentActivity = $stmt->fetchAll();
    
    // Calculate average resolution time
    $avgResolutionQuery = "
        SELECT 
            AVG(DATEDIFF(hour, created_at, resolved_at)) as avg_resolution_hours
        FROM Tickets
        WHERE resolved_at IS NOT NULL
        $whereCondition
    ";
    
    $stmt = $db->prepare($avgResolutionQuery);
    $stmt->execute($params);
    $avgResolution = $stmt->fetch();
    
    // Get tickets by priority
    $priorityQuery = "
        SELECT 
            priority,
            COUNT(*) as count
        FROM Tickets
        $whereCondition
        GROUP BY priority
        ORDER BY 
            CASE priority
                WHEN 'Emergency' THEN 1
                WHEN 'Urgent' THEN 2
                WHEN 'Critical' THEN 3
                WHEN 'High' THEN 4
                WHEN 'Normal' THEN 5
                ELSE 6
            END
    ";
    
    $stmt = $db->prepare($priorityQuery);
    $stmt->execute($params);
    $priorityStats = $stmt->fetchAll();
    
    // Get unassigned tickets (for admin/analyst view)
    $unassignedCount = 0;
    if ($userRole === 'Admin' || $userRole === 'Analyst') {
        $unassignedQuery = "
            SELECT COUNT(*) as count
            FROM Tickets
            WHERE assigned_analyst_id IS NULL
            AND status NOT IN ('Resolved', 'Closed', 'Cancelled')
        ";
        
        $stmt = $db->prepare($unassignedQuery);
        $stmt->execute();
        $unassignedCount = $stmt->fetch()['count'];
    }
    
    // Format response
    $response = [
        'total' => (int)$stats['total'],
        'open' => (int)$stats['open'],
        'inProgress' => (int)$stats['inProgress'],
        'pending' => (int)$stats['pending'],
        'resolved' => (int)$stats['resolved'],
        'closed' => (int)$stats['closed'],
        'cancelled' => (int)$stats['cancelled'],
        'highPriority' => (int)$stats['highPriority'],
        'criticalPriority' => (int)$stats['criticalPriority'],
        'urgentPriority' => (int)$stats['urgentPriority'],
        'emergencyPriority' => (int)$stats['emergencyPriority'],
        'overdue' => (int)$stats['overdue'],
        'unassigned' => (int)$unassignedCount,
        'avgResolutionHours' => round($avgResolution['avg_resolution_hours'] ?? 0, 2),
        'categoryBreakdown' => $categoryStats,
        'priorityBreakdown' => $priorityStats,
        'recentActivity' => $recentActivity
    ];
    
    sendSuccessResponse($response);
    
} catch (Exception $e) {
    error_log("Get stats error: " . $e->getMessage());
    sendErrorResponse('Failed to retrieve statistics', 500);
}
?>