<?php
// Define security constant before any includes
define('VOTING_SYSTEM_SECURITY', true);

// Include security functions
require_once 'includes/security.php';

// Initialize secure session and set security headers
Security::setSecurityHeaders();
if (!Security::initSecureSession()) {
    header('Location: simple_login.php?error=session_expired');
    exit();
}

// Database connection
$conn = Security::getSecureConnection();

// Check if user is logged in and is admin
if (!Security::isLoggedIn() || !Security::isAdmin()) {
    Security::logEvent('unauthorized_admin_access', $_SERVER['REQUEST_URI'], $_SESSION['user_id'] ?? null);
    header('Location: simple_login.php?error=admin_required');
    exit();
}

$message = '';
$error = '';

// Generate CSRF token
$csrfToken = Security::generateCSRFToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token for all POST requests
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
        Security::logEvent('csrf_token_invalid', 'Admin panel form submission', $_SESSION['user_id']);
    } else {
        // Handle different form submissions
        if (isset($_POST['create_election'])) {
            // Create new election
            $title = Security::sanitizeInput($_POST['title']);
            $description = Security::sanitizeInput($_POST['description']);
            $start_date = Security::sanitizeInput($_POST['start_date']);
            $end_date = Security::sanitizeInput($_POST['end_date']);
            $status = Security::sanitizeInput($_POST['status']);
            
            if (!empty($title) && !empty($start_date) && !empty($end_date)) {
                if (!in_array($status, ['active', 'inactive', 'completed'])) {
                    $error = 'Invalid status selected.';
                } elseif (strtotime($start_date) >= strtotime($end_date)) {
                    $error = 'End date must be after start date.';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO elections (title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$title, $description, $start_date, $end_date, $status])) {
                            $message = "Election created successfully!";
                            Security::logEvent('election_created', $title, $_SESSION['user_id']);
                        } else {
                            $error = "Failed to create election.";
                            Security::logEvent('election_creation_failed', 'Database error', $_SESSION['user_id']);
                        }
                    } catch (Exception $e) {
                        $error = "Database error occurred.";
                        Security::logEvent('election_creation_exception', $e->getMessage(), $_SESSION['user_id']);
                    }
                }
            } else {
                $error = "Please fill in all required fields.";
            }
            
        } elseif (isset($_POST['add_candidate'])) {
            // Add new candidate
            $election_id = Security::validateInt($_POST['election_id']);
            $full_name = Security::sanitizeInput($_POST['full_name']);
            $position = Security::sanitizeInput($_POST['position']);
            $bio = Security::sanitizeInput($_POST['bio'] ?? '');
            
            if ($election_id && !empty($full_name) && !empty($position)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO candidates (election_id, full_name, position, bio) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$election_id, $full_name, $position, $bio])) {
                        $message = "Candidate added successfully!";
                        Security::logEvent('candidate_added', "$full_name for election $election_id", $_SESSION['user_id']);
                    } else {
                        $error = "Failed to add candidate.";
                        Security::logEvent('candidate_addition_failed', 'Database error', $_SESSION['user_id']);
                    }
                } catch (Exception $e) {
                    $error = "Database error occurred.";
                    Security::logEvent('candidate_addition_exception', $e->getMessage(), $_SESSION['user_id']);
                }
            } else {
                $error = "Please fill in all required fields.";
            }
            
        } elseif (isset($_POST['delete_election'])) {
            // Delete election
            $election_id = Security::validateInt($_POST['election_id']);
            if ($election_id) {
                try {
                    $stmt = $conn->prepare("DELETE FROM elections WHERE election_id = ?");
                    if ($stmt->execute([$election_id])) {
                        $message = "Election deleted successfully!";
                        Security::logEvent('election_deleted', "Election ID: $election_id", $_SESSION['user_id']);
                    } else {
                        $error = "Failed to delete election.";
                        Security::logEvent('election_deletion_failed', 'Database error', $_SESSION['user_id']);
                    }
                } catch (Exception $e) {
                    $error = "Database error occurred.";
                    Security::logEvent('election_deletion_exception', $e->getMessage(), $_SESSION['user_id']);
                }
            } else {
                $error = "Invalid election ID.";
            }
            
        } elseif (isset($_POST['delete_candidate'])) {
            // Delete candidate
            $candidate_id = Security::validateInt($_POST['candidate_id']);
            if ($candidate_id) {
                try {
                    $stmt = $conn->prepare("DELETE FROM candidates WHERE candidate_id = ?");
                    if ($stmt->execute([$candidate_id])) {
                        $message = "Candidate deleted successfully!";
                        Security::logEvent('candidate_deleted', "Candidate ID: $candidate_id", $_SESSION['user_id']);
                    } else {
                        $error = "Failed to delete candidate.";
                        Security::logEvent('candidate_deletion_failed', 'Database error', $_SESSION['user_id']);
                    }
                } catch (Exception $e) {
                    $error = "Database error occurred.";
                    Security::logEvent('candidate_deletion_exception', $e->getMessage(), $_SESSION['user_id']);
                }
            } else {
                $error = "Invalid candidate ID.";
            }
        }
    }
}

// Get all elections
try {
    $stmt = $conn->prepare("SELECT * FROM elections ORDER BY created_at DESC");
    $stmt->execute();
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $elections = [];
    $error = "Error loading elections.";
    Security::logEvent('elections_load_failed', $e->getMessage(), $_SESSION['user_id']);
}

// Get all candidates with election info
try {
    $stmt = $conn->prepare("
        SELECT c.*, e.title as election_title 
        FROM candidates c 
        JOIN elections e ON c.election_id = e.election_id 
        ORDER BY e.title, c.full_name
    ");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $candidates = [];
    Security::logEvent('candidates_load_failed', $e->getMessage(), $_SESSION['user_id']);
}

// Get system statistics
try {
    $stats = [];
    
    // Total elections
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM elections");
    $stmt->execute();
    $stats['total_elections'] = $stmt->fetch()['total'];
    
    // Active elections
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM elections WHERE status = 'active'");
    $stmt->execute();
    $stats['active_elections'] = $stmt->fetch()['total'];
    
    // Total candidates
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM candidates");
    $stmt->execute();
    $stats['total_candidates'] = $stmt->fetch()['total'];
    
    // Total votes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM votes");
    $stmt->execute();
    $stats['total_votes'] = $stmt->fetch()['total'];
    
    // Total users
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    $stats = ['total_elections' => 0, 'active_elections' => 0, 'total_candidates' => 0, 'total_votes' => 0, 'total_users' => 0];
    Security::logEvent('stats_load_failed', $e->getMessage(), $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Electronic Voting System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .admin-title {
            font-size: 28px;
            color: #333;
            font-weight: bold;
        }
        
        .user-info {
            color: #666;
        }
        
        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 16px;
            background: white;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .nav-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="admin-title">
                    <i class="fas fa-cog"></i> Admin Panel
                </div>
                <div class="user-info">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
                    <p><strong>Role:</strong> Administrator</p>
                </div>
            </div>
            <div class="nav-buttons">
                <a href="voting_system.php" class="btn btn-primary">
                    <i class="fas fa-vote-yea"></i> Voting System
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_elections']; ?></div>
                <div class="stat-label">Total Elections</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_elections']; ?></div>
                <div class="stat-label">Active Elections</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_candidates']; ?></div>
                <div class="stat-label">Total Candidates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_votes']; ?></div>
                <div class="stat-label">Total Votes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Registered Users</div>
            </div>
        </div>

        <!-- Create Election Form -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Create New Election</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title" class="form-label">Election Title</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select" required>
                                <option value="inactive">Inactive</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="start_date" class="form-label">Start Date & Time</label>
                            <input type="datetime-local" id="start_date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date" class="form-label">End Date & Time</label>
                            <input type="datetime-local" id="end_date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Enter election description..."></textarea>
                    </div>
                    <button type="submit" name="create_election" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Election
                    </button>
                </form>
            </div>
        </div>

        <!-- Add Candidate Form -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Add New Candidate</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="election_id" class="form-label">Election</label>
                            <select id="election_id" name="election_id" class="form-select" required>
                                <option value="">Select an election</option>
                                <?php foreach ($elections as $election): ?>
                                    <option value="<?php echo $election['election_id']; ?>">
                                        <?php echo htmlspecialchars($election['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="full_name" class="form-label">Candidate Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="position" class="form-label">Position</label>
                            <input type="text" id="position" name="position" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bio" class="form-label">Biography</label>
                        <textarea id="bio" name="bio" class="form-control" rows="3" placeholder="Enter candidate biography..."></textarea>
                    </div>
                    <button type="submit" name="add_candidate" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Add Candidate
                    </button>
                </form>
            </div>
        </div>

        <!-- Elections List -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Manage Elections</h2>
            </div>
            <div class="card-body">
                <?php if (empty($elections)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">
                        <i class="fas fa-info-circle"></i> No elections created yet.
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($elections as $election): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($election['title']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $election['status']; ?>">
                                            <?php echo htmlspecialchars($election['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($election['start_date'])); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($election['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this election? This will also delete all associated candidates and votes.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                            <button type="submit" name="delete_election" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Candidates List -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-users"></i> Manage Candidates</h2>
            </div>
            <div class="card-body">
                <?php if (empty($candidates)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">
                        <i class="fas fa-info-circle"></i> No candidates added yet.
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Election</th>
                                <th>Votes</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['position']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['election_title']); ?></td>
                                    <td><strong><?php echo $candidate['vote_count']; ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($candidate['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this candidate? This will also delete all votes for this candidate.')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['candidate_id']; ?>">
                                            <button type="submit" name="delete_candidate" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
