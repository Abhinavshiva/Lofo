<?php
// LOFO - Smart Automated Matching Engine
// Compares attributes between Lost and Found reports and calculates confidence scores

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notifications.php';

class SmartMatcher {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Compute similarity score and reasons between a lost and found item
     */
    public function calculateMatch($lostItem, $foundItem) {
        $score = 0;
        $reasons = [];

        // 1. Category check (40 points)
        if ($lostItem['category_id'] == $foundItem['category_id']) {
            $score += 40;
            $reasons[] = "Exact category match: " . ($lostItem['category_name'] ?? 'Identical Category');
        }

        // 2. Title & Keywords similarity (up to 35 points)
        $lostWords = preg_split('/[\s,\-_]+/', strtolower($lostItem['item_name'] . ' ' . $lostItem['description']));
        $foundWords = preg_split('/[\s,\-_]+/', strtolower($foundItem['item_name'] . ' ' . $foundItem['description']));
        
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'in', 'on', 'at', 'to', 'for', 'with', 'of', 'has', 'have', 'is', 'was', 'my', 'left', 'near', 'found', 'lost'];
        $lostKeywords = array_filter(array_unique($lostWords), function($w) use ($stopWords) {
            return strlen($w) > 2 && !in_array($w, $stopWords);
        });
        $foundKeywords = array_filter(array_unique($foundWords), function($w) use ($stopWords) {
            return strlen($w) > 2 && !in_array($w, $stopWords);
        });

        $matchingKeywords = array_intersect($lostKeywords, $foundKeywords);
        if (!empty($matchingKeywords)) {
            $overlapCount = count($matchingKeywords);
            $keywordScore = min(35, $overlapCount * 12);
            $score += $keywordScore;
            $reasons[] = "Matching keywords: " . implode(', ', array_slice($matchingKeywords, 0, 4));
        } else {
            // Check direct title similarity
            similar_text(strtolower($lostItem['item_name']), strtolower($foundItem['item_name']), $titlePerc);
            if ($titlePerc > 50) {
                $score += (int)($titlePerc * 0.3);
                $reasons[] = "Title similarity: " . round($titlePerc) . "%";
            }
        }

        // 3. Location check (up to 15 points)
        if (!empty($lostItem['location_lost']) && !empty($foundItem['location_found'])) {
            if (strcasecmp(trim($lostItem['location_lost']), trim($foundItem['location_found'])) === 0) {
                $score += 15;
                $reasons[] = "Same campus location: " . $lostItem['location_lost'];
            } else {
                similar_text(strtolower($lostItem['location_lost']), strtolower($foundItem['location_found']), $locPerc);
                if ($locPerc > 40) {
                    $score += 10;
                    $reasons[] = "Close campus proximity";
                }
            }
        }

        // 4. Date Proximity (up to 10 points)
        if (!empty($lostItem['date_lost']) && !empty($foundItem['date_found'])) {
            $lostTime = strtotime($lostItem['date_lost']);
            $foundTime = strtotime($foundItem['date_found']);
            $diffDays = ($foundTime - $lostTime) / 86400;

            // Item found on the same day or within 7 days after losing
            if ($diffDays >= 0 && $diffDays <= 7) {
                $score += 10;
                $reasons[] = "Dates align within " . round($diffDays) . " day(s)";
            } elseif ($diffDays >= -1 && $diffDays <= 14) {
                $score += 5;
                $reasons[] = "Dates align within 2 weeks";
            }
        }

        // Cap at 100
        $score = min(100, $score);

        return [
            'score' => $score,
            'reasons' => implode(' | ', $reasons)
        ];
    }

    /**
     * Run matching for a newly created or updated lost item against all available found items
     */
    public function matchLostItem($lostId) {
        $lostStmt = $this->pdo->prepare("
            SELECT l.*, c.name as category_name 
            FROM lost_items l 
            JOIN categories c ON l.category_id = c.category_id 
            WHERE l.lost_id = ?
        ");
        $lostStmt->execute([$lostId]);
        $lostItem = $lostStmt->fetch();
        if (!$lostItem) return 0;

        $foundStmt = $this->pdo->query("
            SELECT f.*, c.name as category_name 
            FROM found_items f 
            JOIN categories c ON f.category_id = c.category_id 
            WHERE f.status = 'Available'
        ");
        $foundItems = $foundStmt->fetchAll();

        $matchesCreated = 0;
        foreach ($foundItems as $foundItem) {
            $matchResult = $this->calculateMatch($lostItem, $foundItem);
            if ($matchResult['score'] >= 50) {
                $this->recordMatch($lostItem['lost_id'], $foundItem['found_id'], $matchResult['score'], $matchResult['reasons'], $lostItem['user_id'], $foundItem['item_name']);
                $matchesCreated++;
            }
        }
        return $matchesCreated;
    }

    /**
     * Run matching for a newly created or updated found item against all active lost items
     */
    public function matchFoundItem($foundId) {
        $foundStmt = $this->pdo->prepare("
            SELECT f.*, c.name as category_name 
            FROM found_items f 
            JOIN categories c ON f.category_id = c.category_id 
            WHERE f.found_id = ?
        ");
        $foundStmt->execute([$foundId]);
        $foundItem = $foundStmt->fetch();
        if (!$foundItem) return 0;

        $lostStmt = $this->pdo->query("
            SELECT l.*, c.name as category_name 
            FROM lost_items l 
            JOIN categories c ON l.category_id = c.category_id 
            WHERE l.status IN ('Lost', 'Under Review')
        ");
        $lostItems = $lostStmt->fetchAll();

        $matchesCreated = 0;
        foreach ($lostItems as $lostItem) {
            $matchResult = $this->calculateMatch($lostItem, $foundItem);
            if ($matchResult['score'] >= 50) {
                $this->recordMatch($lostItem['lost_id'], $foundItem['found_id'], $matchResult['score'], $matchResult['reasons'], $lostItem['user_id'], $foundItem['item_name']);
                $matchesCreated++;
            }
        }
        return $matchesCreated;
    }

    private function recordMatch($lostId, $foundId, $score, $reasons, $lostOwnerId, $foundName) {
        // Check if match already logged
        $checkStmt = $this->pdo->prepare("SELECT match_id FROM item_matches WHERE lost_id = ? AND found_id = ?");
        $checkStmt->execute([$lostId, $foundId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $updateStmt = $this->pdo->prepare("UPDATE item_matches SET confidence_score = ?, match_reasons = ? WHERE match_id = ?");
            $updateStmt->execute([$score, $reasons, $existing['match_id']]);
        } else {
            $insertStmt = $this->pdo->prepare("INSERT INTO item_matches (lost_id, found_id, confidence_score, match_reasons, status) VALUES (?, ?, ?, ?, 'Pending Review')");
            $insertStmt->execute([$lostId, $foundId, $score, $reasons]);

            // Notify owner of lost item
            $title = "Potential Match Found ({$score}%)!";
            $msg = "A found item '{$foundName}' has been matched with your lost report ({$score}% confidence). Check details to submit a claim!";
            $link = "item-details.php?type=found&id={$foundId}";
            createNotification($this->pdo, $lostOwnerId, $title, $msg, 'Match Found', $link);

            // Also update lost item status to 'Matched' if it was 'Lost'
            $this->pdo->prepare("UPDATE lost_items SET status = 'Matched' WHERE lost_id = ? AND status = 'Lost'")->execute([$lostId]);
        }
    }
}
