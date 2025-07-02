<?php
/**
 * Vote Management Class
 * Handles secure voting process with integrity checks
 */

class Vote {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Cast a vote
     */
    public function castVote($electionId, $candidateId) {
        try {
            if (!Security::isLoggedIn()) {
                throw new Exception("You must be logged in to vote.");
            }
            
            $userId = $_SESSION['user_id'];
            
            // Validate election
            $stmt = $this->db->prepare("SELECT * FROM elections WHERE id = ? AND start_date <= NOW() AND end_date > NOW()");
            $stmt->execute([$electionId]);
            $election = $stmt->fetch();
            
            if (!$election) {
                throw new Exception("Election not found or not currently active.");
            }
            
            // Validate candidate
            $stmt = $this->db->prepare("SELECT * FROM candidates WHERE id = ? AND election_id = ?");
            $stmt->execute([$candidateId, $electionId]);
            $candidate = $stmt->fetch();
            
            if (!$candidate) {
                throw new Exception("Candidate not found in this election.");
            }
            
            // Generate anonymized voter hash
            $voterHash = Security::generateVoterHash($userId, $electionId);
            
            // Check if user has already voted
            $stmt = $this->db->prepare("SELECT id FROM votes WHERE election_id = ? AND voter_hash = ?");
            $stmt->execute([$electionId, $voterHash]);
            if ($stmt->fetch()) {
                throw new Exception("You have already voted in this election.");
            }
            
            // Begin transaction
            $this->db->beginTransaction();
            
            try {
                // Cast vote
                $stmt = $this->db->prepare("INSERT INTO votes (election_id, candidate_id, voter_hash, ip_address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$electionId, $candidateId, $voterHash, Security::getClientIP()]);
                
                // Update candidate vote count
                $stmt = $this->db->prepare("UPDATE candidates SET vote_count = vote_count + 1 WHERE id = ?");
                $stmt->execute([$candidateId]);
                
                // Mark user as voted (for UI purposes)
                $stmt = $this->db->prepare("UPDATE users SET has_voted = TRUE WHERE id = ?");
                $stmt->execute([$userId]);
                
                $this->db->commit();
                
                Security::logEvent('VOTE_CAST', "Vote cast in election {$electionId} for candidate {$candidateId}", $userId);
                
                return [
                    'success' => true,
                    'message' => 'Your vote has been cast successfully!'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if user has voted in an election
     */
    public function hasUserVoted($userId, $electionId) {
        try {
            $voterHash = Security::generateVoterHash($userId, $electionId);
            $stmt = $this->db->prepare("SELECT id FROM votes WHERE election_id = ? AND voter_hash = ?");
            $stmt->execute([$electionId, $voterHash]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Error checking vote status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get vote statistics for an election
     */
    public function getElectionStats($electionId) {
        try {
            $stats = [];
            
            // Total votes
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_votes FROM votes WHERE election_id = ?");
            $stmt->execute([$electionId]);
            $stats['total_votes'] = $stmt->fetch()['total_votes'];
            
            // Votes by candidate
            $stmt = $this->db->prepare("
                SELECT c.name, c.party, COUNT(v.id) as vote_count,
                       ROUND((COUNT(v.id) / ?) * 100, 2) as percentage
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ?
                WHERE c.election_id = ?
                GROUP BY c.id
                ORDER BY vote_count DESC
            ");
            $stmt->execute([$stats['total_votes'] ?: 1, $electionId, $electionId]);
            $stats['candidates'] = $stmt->fetchAll();
            
            // Votes over time (hourly breakdown)
            $stmt = $this->db->prepare("
                SELECT DATE_FORMAT(vote_timestamp, '%Y-%m-%d %H:00:00') as hour,
                       COUNT(*) as vote_count
                FROM votes 
                WHERE election_id = ?
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute([$electionId]);
            $stats['votes_by_hour'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting election stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get overall voting statistics (Admin only)
     */
    public function getOverallStats() {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            $stats = [];
            
            // Total elections
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_elections FROM elections");
            $stmt->execute();
            $stats['total_elections'] = $stmt->fetch()['total_elections'];
            
            // Total candidates
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_candidates FROM candidates");
            $stmt->execute();
            $stats['total_candidates'] = $stmt->fetch()['total_candidates'];
            
            // Total votes
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_votes FROM votes");
            $stmt->execute();
            $stats['total_votes'] = $stmt->fetch()['total_votes'];
            
            // Total users
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_users FROM users WHERE role = 'voter'");
            $stmt->execute();
            $stats['total_users'] = $stmt->fetch()['total_users'];
            
            // Active elections
            $stmt = $this->db->prepare("SELECT COUNT(*) as active_elections FROM elections WHERE start_date <= NOW() AND end_date > NOW()");
            $stmt->execute();
            $stats['active_elections'] = $stmt->fetch()['active_elections'];
            
            // Recent voting activity
            $stmt = $this->db->prepare("
                SELECT e.title, COUNT(v.id) as vote_count
                FROM elections e
                LEFT JOIN votes v ON e.id = v.election_id AND v.vote_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                WHERE e.start_date <= NOW() AND e.end_date > NOW()
                GROUP BY e.id
                ORDER BY vote_count DESC
                LIMIT 5
            ");
            $stmt->execute();
            $stats['recent_activity'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting overall stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verify vote integrity (Admin only)
     */
    public function verifyVoteIntegrity($electionId) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            $issues = [];
            
            // Check for duplicate votes by same voter
            $stmt = $this->db->prepare("
                SELECT voter_hash, COUNT(*) as vote_count
                FROM votes 
                WHERE election_id = ?
                GROUP BY voter_hash
                HAVING vote_count > 1
            ");
            $stmt->execute([$electionId]);
            $duplicates = $stmt->fetchAll();
            
            if (!empty($duplicates)) {
                $issues[] = count($duplicates) . " voters have multiple votes";
            }
            
            // Check candidate vote count consistency
            $stmt = $this->db->prepare("
                SELECT c.id, c.name, c.vote_count as stored_count, COUNT(v.id) as actual_count
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id
                WHERE c.election_id = ?
                GROUP BY c.id
                HAVING stored_count != actual_count
            ");
            $stmt->execute([$electionId]);
            $inconsistencies = $stmt->fetchAll();
            
            if (!empty($inconsistencies)) {
                foreach ($inconsistencies as $inc) {
                    $issues[] = "Candidate {$inc['name']}: stored count ({$inc['stored_count']}) != actual count ({$inc['actual_count']})";
                }
            }
            
            Security::logEvent('VOTE_INTEGRITY_CHECK', "Integrity check for election {$electionId}: " . (empty($issues) ? 'PASSED' : 'ISSUES FOUND'));
            
            return [
                'success' => true,
                'integrity_passed' => empty($issues),
                'issues' => $issues
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
