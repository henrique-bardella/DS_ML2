<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendErrorResponse('Invalid JSON input');
    }
    
    $formType = $input['formType'] ?? '';
    $formData = $input['data'] ?? [];
    
    if (empty($formType) || empty($formData)) {
        sendErrorResponse('Missing required data');
    }
    
    // Validate required fields
    $requiredFields = ['solicitationNumber', 'agency', 'accountNumber'];
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            sendErrorResponse("Missing required field: $field");
        }
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->connect();
    
    // For demo purposes, we'll use a default user (in production, get from session)
    $requesterId = 3; // This would be the logged-in user's ID
    
    // Prepare ticket data
    $ticketData = [
        'solicitation_number' => sanitizeInput($formData['solicitationNumber']),
        'category' => $formType,
        'agency' => sanitizeInput($formData['agency']),
        'account_number' => sanitizeInput($formData['accountNumber']),
        'subject' => sanitizeInput($formData['subject'] ?? $formData['goalTitle'] ?? $formData['exceptionReason'] ?? 'No subject provided'),
        'description' => sanitizeInput($formData['description'] ?? $formData['goalDescription'] ?? $formData['exceptionReason'] ?? 'No description provided'),
        'priority' => sanitizeInput($formData['priority'] ?? 'Normal'),
        'requester_id' => $requesterId
    ];
    
    // Handle due date if provided
    if (!empty($formData['deadline']) || !empty($formData['endDate']) || !empty($formData['expectedResolution'])) {
        $dueDate = $formData['deadline'] ?? $formData['endDate'] ?? $formData['expectedResolution'];
        $ticketData['due_date'] = $dueDate;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert ticket using stored procedure
        $stmt = $db->prepare("EXEC sp_CreateTicket ?, ?, ?, ?, ?, ?, ?, ?, ?");
        $stmt->execute([
            $ticketData['solicitation_number'],
            $ticketData['category'],
            $ticketData['agency'],
            $ticketData['account_number'],
            $ticketData['subject'],
            $ticketData['description'],
            $ticketData['priority'],
            $ticketData['requester_id'],
            $ticketData['due_date'] ?? null
        ]);
        
        $result = $stmt->fetch();
        $ticketId = $result['ticket_id'];
        
        // Store additional form data
        foreach ($formData as $fieldName => $fieldValue) {
            if (!in_array($fieldName, ['solicitationNumber', 'agency', 'accountNumber', 'subject', 'description', 'priority', 'deadline', 'endDate', 'expectedResolution'])) {
                if (!empty($fieldValue)) {
                    $stmt = $db->prepare("INSERT INTO TicketFormData (ticket_id, field_name, field_value) VALUES (?, ?, ?)");
                    $stmt->execute([$ticketId, $fieldName, is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue]);
                }
            }
        }
        
        // Handle file uploads if present
        if (!empty($_FILES)) {
            $uploadDir = createUploadDirectory();
            
            foreach ($_FILES as $fieldName => $fileInfo) {
                if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                    $originalName = $fileInfo['name'];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Validate file type
                    if (in_array($extension, Config::ALLOWED_FILE_TYPES)) {
                        $storedName = $ticketId . '_' . uniqid() . '.' . $extension;
                        $storedPath = $uploadDir . $storedName;
                        
                        if (move_uploaded_file($fileInfo['tmp_name'], $storedPath)) {
                            $stmt = $db->prepare("INSERT INTO FileAttachments (ticket_id, original_filename, stored_filename, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $ticketId,
                                $originalName,
                                $storedName,
                                $fileInfo['size'],
                                $fileInfo['type'],
                                $requesterId
                            ]);
                            
                            // Add file upload interaction
                            $stmt = $db->prepare("INSERT INTO TicketInteractions (ticket_id, user_id, interaction_type, description) VALUES (?, ?, ?, ?)");
                            $stmt->execute([
                                $ticketId,
                                $requesterId,
                                'File Uploaded',
                                "File uploaded: $originalName"
                            ]);
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $db->commit();
        
        sendSuccessResponse([
            'ticketId' => $ticketId,
            'solicitationNumber' => $ticketData['solicitation_number'],
            'category' => $ticketData['category']
        ], 'Ticket submitted successfully');
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Ticket submission failed: " . $e->getMessage());
        sendErrorResponse('Failed to submit ticket');
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendErrorResponse('Internal server error', 500);
}

// Helper function to map form fields to database fields based on category
function mapFormFields($category, $formData) {
    $mappedData = [];
    
    switch ($category) {
        case 'PADE':
            $mappedData = [
                'subject' => $formData['subject'] ?? '',
                'description' => $formData['description'] ?? '',
                'process_type' => $formData['processType'] ?? '',
                'requested_by' => $formData['requestedBy'] ?? '',
                'department' => $formData['department'] ?? '',
                'contact_email' => $formData['contactEmail'] ?? '',
                'contact_phone' => $formData['contactPhone'] ?? '',
                'additional_notes' => $formData['additionalNotes'] ?? ''
            ];
            break;
            
        case 'META':
            $mappedData = [
                'subject' => $formData['goalTitle'] ?? '',
                'description' => $formData['goalDescription'] ?? '',
                'goal_type' => $formData['goalType'] ?? '',
                'target_period' => $formData['targetPeriod'] ?? '',
                'target_value' => $formData['targetValue'] ?? '',
                'measurement_unit' => $formData['measurementUnit'] ?? '',
                'start_date' => $formData['startDate'] ?? '',
                'end_date' => $formData['endDate'] ?? '',
                'key_activities' => $formData['keyActivities'] ?? '',
                'responsible_person' => $formData['responsiblePerson'] ?? '',
                'stakeholders' => $formData['stakeholders'] ?? '',
                'budget' => $formData['budget'] ?? '',
                'resources' => $formData['resources'] ?? '',
                'contact_email' => $formData['contactEmail'] ?? '',
                'contact_phone' => $formData['contactPhone'] ?? '',
                'risk_assessment' => $formData['riskAssessment'] ?? '',
                'success_criteria' => $formData['successCriteria'] ?? ''
            ];
            break;
            
        case 'ENCARTEIRAMENTO':
            $mappedData = [
                'subject' => 'Exception Request: ' . ($formData['exceptionType'] ?? 'Unknown'),
                'description' => $formData['exceptionReason'] ?? '',
                'exception_type' => $formData['exceptionType'] ?? '',
                'original_process' => $formData['originalProcess'] ?? '',
                'urgency_level' => $formData['urgencyLevel'] ?? '',
                'legal_basis' => $formData['legalBasis'] ?? '',
                'court_order' => $formData['courtOrder'] ?? '',
                'required_action' => $formData['requiredAction'] ?? '',
                'expected_resolution' => $formData['expectedResolution'] ?? '',
                'affected_parties' => $formData['affectedParties'] ?? '',
                'requested_by' => $formData['requestedBy'] ?? '',
                'authorized_by' => $formData['authorizedBy'] ?? '',
                'department' => $formData['department'] ?? '',
                'region' => $formData['region'] ?? '',
                'contact_email' => $formData['contactEmail'] ?? '',
                'contact_phone' => $formData['contactPhone'] ?? '',
                'risk_assessment' => $formData['riskAssessment'] ?? '',
                'previous_attempts' => $formData['previousAttempts'] ?? '',
                'additional_notes' => $formData['additionalNotes'] ?? ''
            ];
            break;
    }
    
    return $mappedData;
}
?>