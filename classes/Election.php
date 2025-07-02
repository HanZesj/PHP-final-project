<?php
/**
 * Election Management Class
 * Handles election creation, management, and administration
 */

class Election {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Create new election (Admin only)
     */
    public function createElection($data) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            // Validate required fields
            $requiredFields = ['title', 'description', 'start_date', 'end_date'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("All fields are required.");
                }
            }
            
            // Sanitize inputs
            $title = Security::sanitizeInput($data['title']);
            $description = Security::sanitizeInput($data['description']);
            $startDate = Security::sanitizeInput($data['start_date']);
            $endDate = Security::sanitizeInput($data['end_date']);
            
            // Validate dates
            $startDateTime = DateTime::createFromFormat('Y-m-d H:i', $startDate);
            $endDateTime = DateTime::createFromFormat('Y-m-d H:i', $endDate);
            
            if (!$startDateTime || !$endDateTime) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD HH:MM format.");
            }
            
            if ($startDateTime >= $endDateTime) {
                throw new Exception("End date must be after start date.");
            }
            
            if ($startDateTime <= new DateTime()) {
                throw new Exception("Start date must be in the future.");
            }
            
            // Insert election
            $stmt = $this->db->prepare("INSERT INTO elections (title, description, start_date, end_date, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $description,
                $startDateTime->format('Y-m-d H:i:s'),
                $endDateTime->format('Y-m-d H:i:s'),
                $_SESSION['user_id']
            ]);
            
            $electionId = $this->db->lastInsertId();
            
            Security::logEvent('ELECTION_CREATED', "Election created: {$title}", $_SESSION['user_id']);
            
            return [
                'success' => true,
                'message' => 'Election created successfully.',
                'election_id' => $electionId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all elections
     */
    public function getAllElections() {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, u.full_name as created_by_name,
                       COUNT(c.id) as candidate_count,
                       COUNT(v.id) as vote_count
                FROM elections e 
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN candidates c ON e.id = c.election_id
                LEFT JOIN votes v ON e.id = v.election_id
                GROUP BY e.id
                ORDER BY e.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting elections: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active elections
     */
    public function getActiveElections() {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, COUNT(c.id) as candidate_count
                FROM elections e 
                LEFT JOIN candidates c ON e.id = c.election_id
                WHERE e.start_date <= NOW() AND e.end_date > NOW()
                GROUP BY e.id
                ORDER BY e.end_date ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting active elections: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get election by ID
     */
    public function getElectionById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, u.full_name as created_by_name
                FROM elections e 
                LEFT JOIN users u ON e.created_by = u.id
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting election: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update election status
     */
    public function updateElectionStatus($id, $status) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            $validStatuses = ['pending', 'active', 'completed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status.");
            }
            
            $stmt = $this->db->prepare("UPDATE elections SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            Security::logEvent('ELECTION_STATUS_UPDATED', "Election {$id} status changed to {$status}");
            
            return [
                'success' => true,
                'message' => 'Election status updated successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete election (Admin only)
     */
    public function deleteElection($id) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            // Check if election has votes
            $stmt = $this->db->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE election_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['vote_count'] > 0) {
                throw new Exception("Cannot delete election with existing votes.");
            }
            
            $stmt = $this->db->prepare("DELETE FROM elections WHERE id = ?");
            $stmt->execute([$id]);
            
            Security::logEvent('ELECTION_DELETED', "Election {$id} deleted");
            
            return [
                'success' => true,
                'message' => 'Election deleted successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get election results
     */
    public function getElectionResults($id) {
        try {
            $election = $this->getElectionById($id);
            if (!$election) {
                throw new Exception("Election not found.");
            }
            
            // Only show results if election is completed or user is admin
            if ($election['end_date'] > date('Y-m-d H:i:s') && !Security::isAdmin()) {
                throw new Exception("Results not available until election ends.");
            }
            
            $stmt = $this->db->prepare("
                SELECT c.*, COUNT(v.id) as vote_count,
                       ROUND((COUNT(v.id) / (SELECT COUNT(*) FROM votes WHERE election_id = ?)) * 100, 2) as percentage
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id
                WHERE c.election_id = ?
                GROUP BY c.id
                ORDER BY vote_count DESC
            ");
            $stmt->execute([$id, $id]);
            $candidates = $stmt->fetchAll();
            
            // Get total votes
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $stmt->execute([$id]);
            $totalVotes = $stmt->fetch()['total_votes'];
            
            return [
                'success' => true,
                'election' => $election,
                'candidates' => $candidates,
                'total_votes' => $totalVotes
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>
