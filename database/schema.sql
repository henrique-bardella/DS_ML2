-- Ticket Registration System Database Schema
-- SQL Server Database Schema

-- Users table
CREATE TABLE Users (
    user_id INT IDENTITY(1,1) PRIMARY KEY,
    username NVARCHAR(50) UNIQUE NOT NULL,
    email NVARCHAR(100) UNIQUE NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    first_name NVARCHAR(50) NOT NULL,
    last_name NVARCHAR(50) NOT NULL,
    role NVARCHAR(20) NOT NULL CHECK (role IN ('Requester', 'Analyst', 'Admin')),
    phone NVARCHAR(20),
    department NVARCHAR(100),
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE(),
    is_active BIT DEFAULT 1
);

-- Tickets table
CREATE TABLE Tickets (
    ticket_id INT IDENTITY(1,1) PRIMARY KEY,
    solicitation_number NVARCHAR(50) NOT NULL,
    category NVARCHAR(50) NOT NULL CHECK (category IN ('PADE', 'META', 'ENCARTEIRAMENTO')),
    agency NVARCHAR(100) NOT NULL,
    account_number NVARCHAR(50) NOT NULL,
    subject NVARCHAR(255) NOT NULL,
    description NTEXT NOT NULL,
    status NVARCHAR(20) DEFAULT 'Open' CHECK (status IN ('Open', 'In Progress', 'Pending', 'Resolved', 'Closed', 'Cancelled')),
    priority NVARCHAR(20) DEFAULT 'Normal' CHECK (priority IN ('Normal', 'High', 'Critical', 'Urgent', 'Emergency')),
    requester_id INT NOT NULL,
    assigned_analyst_id INT,
    created_at DATETIME2 DEFAULT GETDATE(),
    updated_at DATETIME2 DEFAULT GETDATE(),
    due_date DATETIME2,
    resolved_at DATETIME2,
    resolution_notes NTEXT,
    FOREIGN KEY (requester_id) REFERENCES Users(user_id),
    FOREIGN KEY (assigned_analyst_id) REFERENCES Users(user_id)
);

-- Ticket Form Data table (for storing category-specific form data)
CREATE TABLE TicketFormData (
    form_data_id INT IDENTITY(1,1) PRIMARY KEY,
    ticket_id INT NOT NULL,
    field_name NVARCHAR(100) NOT NULL,
    field_value NTEXT,
    created_at DATETIME2 DEFAULT GETDATE(),
    FOREIGN KEY (ticket_id) REFERENCES Tickets(ticket_id) ON DELETE CASCADE
);

-- Ticket Interactions table (for timeline/audit trail)
CREATE TABLE TicketInteractions (
    interaction_id INT IDENTITY(1,1) PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    interaction_type NVARCHAR(50) NOT NULL CHECK (interaction_type IN ('Created', 'Updated', 'Status Changed', 'Comment Added', 'Assigned', 'Resolved', 'Reopened', 'File Uploaded')),
    description NTEXT NOT NULL,
    old_value NVARCHAR(255),
    new_value NVARCHAR(255),
    created_at DATETIME2 DEFAULT GETDATE(),
    FOREIGN KEY (ticket_id) REFERENCES Tickets(ticket_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- File Attachments table
CREATE TABLE FileAttachments (
    attachment_id INT IDENTITY(1,1) PRIMARY KEY,
    ticket_id INT NOT NULL,
    original_filename NVARCHAR(255) NOT NULL,
    stored_filename NVARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    file_type NVARCHAR(100) NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at DATETIME2 DEFAULT GETDATE(),
    FOREIGN KEY (ticket_id) REFERENCES Tickets(ticket_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES Users(user_id)
);

-- User Sessions table (for session management)
CREATE TABLE UserSessions (
    session_id NVARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address NVARCHAR(45),
    user_agent NVARCHAR(500),
    created_at DATETIME2 DEFAULT GETDATE(),
    expires_at DATETIME2 NOT NULL,
    is_active BIT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Create indexes for better performance
CREATE INDEX IX_Tickets_Status ON Tickets(status);
CREATE INDEX IX_Tickets_Category ON Tickets(category);
CREATE INDEX IX_Tickets_RequesterID ON Tickets(requester_id);
CREATE INDEX IX_Tickets_AssignedAnalystID ON Tickets(assigned_analyst_id);
CREATE INDEX IX_Tickets_CreatedAt ON Tickets(created_at);
CREATE INDEX IX_TicketInteractions_TicketID ON TicketInteractions(ticket_id);
CREATE INDEX IX_TicketInteractions_CreatedAt ON TicketInteractions(created_at);
CREATE INDEX IX_TicketFormData_TicketID ON TicketFormData(ticket_id);
CREATE INDEX IX_FileAttachments_TicketID ON FileAttachments(ticket_id);
CREATE INDEX IX_UserSessions_UserID ON UserSessions(user_id);
CREATE INDEX IX_UserSessions_ExpiresAt ON UserSessions(expires_at);

-- Create triggers for updating timestamp
CREATE TRIGGER TR_Users_UpdateTimestamp
ON Users
AFTER UPDATE
AS
BEGIN
    UPDATE Users 
    SET updated_at = GETDATE() 
    WHERE user_id IN (SELECT user_id FROM inserted);
END;

CREATE TRIGGER TR_Tickets_UpdateTimestamp
ON Tickets
AFTER UPDATE
AS
BEGIN
    UPDATE Tickets 
    SET updated_at = GETDATE() 
    WHERE ticket_id IN (SELECT ticket_id FROM inserted);
END;

-- Insert default admin user (password: admin123)
INSERT INTO Users (username, email, password_hash, first_name, last_name, role, department) 
VALUES ('admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'Admin', 'IT');

-- Insert sample analyst user (password: analyst123)
INSERT INTO Users (username, email, password_hash, first_name, last_name, role, department) 
VALUES ('analyst1', 'analyst1@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Analyst', 'Analyst', 'Operations');

-- Insert sample requester user (password: user123)
INSERT INTO Users (username, email, password_hash, first_name, last_name, role, department) 
VALUES ('user1', 'user1@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'User', 'Requester', 'Finance');

-- Create views for common queries
CREATE VIEW v_TicketSummary AS
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
    CONCAT(u_req.first_name, ' ', u_req.last_name) as requester_name,
    u_req.email as requester_email,
    CONCAT(u_an.first_name, ' ', u_an.last_name) as analyst_name,
    u_an.email as analyst_email,
    (SELECT COUNT(*) FROM TicketInteractions WHERE ticket_id = t.ticket_id) as interaction_count,
    (SELECT TOP 1 created_at FROM TicketInteractions WHERE ticket_id = t.ticket_id ORDER BY created_at DESC) as last_interaction
FROM Tickets t
LEFT JOIN Users u_req ON t.requester_id = u_req.user_id
LEFT JOIN Users u_an ON t.assigned_analyst_id = u_an.user_id;

CREATE VIEW v_TicketStats AS
SELECT 
    category,
    status,
    COUNT(*) as ticket_count,
    AVG(DATEDIFF(hour, created_at, COALESCE(resolved_at, GETDATE()))) as avg_resolution_hours
FROM Tickets
GROUP BY category, status;

-- Create stored procedures for common operations
CREATE PROCEDURE sp_CreateTicket
    @solicitation_number NVARCHAR(50),
    @category NVARCHAR(50),
    @agency NVARCHAR(100),
    @account_number NVARCHAR(50),
    @subject NVARCHAR(255),
    @description NTEXT,
    @priority NVARCHAR(20),
    @requester_id INT,
    @due_date DATETIME2 = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @ticket_id INT;
    
    BEGIN TRANSACTION;
    
    TRY
        -- Insert ticket
        INSERT INTO Tickets (solicitation_number, category, agency, account_number, subject, description, priority, requester_id, due_date)
        VALUES (@solicitation_number, @category, @agency, @account_number, @subject, @description, @priority, @requester_id, @due_date);
        
        SET @ticket_id = SCOPE_IDENTITY();
        
        -- Add creation interaction
        INSERT INTO TicketInteractions (ticket_id, user_id, interaction_type, description)
        VALUES (@ticket_id, @requester_id, 'Created', 'Ticket created');
        
        COMMIT TRANSACTION;
        
        SELECT @ticket_id as ticket_id;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;

CREATE PROCEDURE sp_UpdateTicketStatus
    @ticket_id INT,
    @new_status NVARCHAR(20),
    @user_id INT,
    @notes NTEXT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @old_status NVARCHAR(20);
    
    BEGIN TRANSACTION;
    
    TRY
        -- Get current status
        SELECT @old_status = status FROM Tickets WHERE ticket_id = @ticket_id;
        
        -- Update ticket
        UPDATE Tickets 
        SET status = @new_status,
            resolved_at = CASE WHEN @new_status = 'Resolved' THEN GETDATE() ELSE resolved_at END,
            resolution_notes = CASE WHEN @new_status = 'Resolved' THEN @notes ELSE resolution_notes END
        WHERE ticket_id = @ticket_id;
        
        -- Add interaction
        INSERT INTO TicketInteractions (ticket_id, user_id, interaction_type, description, old_value, new_value)
        VALUES (@ticket_id, @user_id, 'Status Changed', 
                COALESCE(@notes, 'Status changed from ' + @old_status + ' to ' + @new_status),
                @old_status, @new_status);
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;