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
    $ticketId = $_GET['ticket_id'] ?? '';
    
    if (empty($ticketId)) {
        sendErrorResponse('Ticket ID is required');
    }
    
    $database = new Database();
    $db = $database->connect();
    
    // Get user info from session
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // First, verify user has access to this ticket
    $accessQuery = "
        SELECT 
            t.ticket_id,
            t.requester_id,
            t.assigned_analyst_id
        FROM Tickets t
        WHERE t.ticket_id = ?
    ";
    
    $stmt = $db->prepare($accessQuery);
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        sendErrorResponse('Ticket not found', 404);
    }
    
    // Check access permissions
    $hasAccess = false;
    if ($userRole === 'Admin') {
        $hasAccess = true;
    } elseif ($userRole === 'Requester' && $ticket['requester_id'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'Analyst' && $ticket['assigned_analyst_id'] == $userId) {
        $hasAccess = true;
    }
    
    if (!$hasAccess) {
        sendErrorResponse('Access denied', 403);
    }
    
    // Get timeline/interactions
    $timelineQuery = "
        SELECT 
            ti.interaction_id,
            ti.ticket_id,
            ti.user_id,
            ti.interaction_type,
            ti.description,
            ti.old_value,
            ti.new_value,
            ti.created_at,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.role as user_role
        FROM TicketInteractions ti
        LEFT JOIN Users u ON ti.user_id = u.user_id
        WHERE ti.ticket_id = ?
        ORDER BY ti.created_at ASC
    ";
    
    $stmt = $db->prepare($timelineQuery);
    $stmt->execute([$ticketId]);
    $interactions = $stmt->fetchAll();
    
    // Format interactions for better display
    $formattedInteractions = [];
    foreach ($interactions as $interaction) {
        $formattedInteraction = [
            'interaction_id' => $interaction['interaction_id'],
            'ticket_id' => $interaction['ticket_id'],
            'user_id' => $interaction['user_id'],
            'user_name' => $interaction['user_name'],
            'user_role' => $interaction['user_role'],
            'interaction_type' => $interaction['interaction_type'],
            'description' => $interaction['description'],
            'old_value' => $interaction['old_value'],
            'new_value' => $interaction['new_value'],
            'created_at' => $interaction['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($interaction['created_at'])),
            'icon' => $this->getInteractionIcon($interaction['interaction_type']),
            'color' => $this->getInteractionColor($interaction['interaction_type'])
        ];
        
        $formattedInteractions[] = $formattedInteraction;
    }
    
    sendSuccessResponse($formattedInteractions);
    
} catch (Exception $e) {
    error_log("Get timeline error: " . $e->getMessage());
    sendErrorResponse('Failed to retrieve timeline', 500);
}

function getInteractionIcon($type) {
    $icons = [
        'Created' => 'fas fa-plus-circle',
        'Updated' => 'fas fa-edit',
        'Status Changed' => 'fas fa-exchange-alt',
        'Comment Added' => 'fas fa-comment',
        'Assigned' => 'fas fa-user-plus',
        'Resolved' => 'fas fa-check-circle',
        'Reopened' => 'fas fa-redo',
        'File Uploaded' => 'fas fa-file-upload'
    ];
    
    return $icons[$type] ?? 'fas fa-circle';
}

function getInteractionColor($type) {
    $colors = [
        'Created' => 'success',
        'Updated' => 'info',
        'Status Changed' => 'warning',
        'Comment Added' => 'primary',
        'Assigned' => 'info',
        'Resolved' => 'success',
        'Reopened' => 'warning',
        'File Uploaded' => 'secondary'
    ];
    
    return $colors[$type] ?? 'secondary';
}
?>