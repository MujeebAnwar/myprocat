<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');

header('Content-Type: application/json');

// Check if the request is POST and has the required data
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rooms'])) {
    try {
        // Decode the rooms array
        $rooms = json_decode($_POST['rooms'], true);
        
        if(!is_array($rooms) || empty($rooms)) {
            echo json_encode(['success' => false, 'message' => 'Invalid rooms data']);
            exit;
        }
        
        // Begin transaction for data consistency
        $DB->sql('START TRANSACTION');
        
        // Update each room's order
        foreach($rooms as $room) {
            if(isset($room['id']) && isset($room['order']) && is_numeric($room['id']) && is_numeric($room['order'])) {
                $DB->sql(
                    'UPDATE rooms SET `order` = ? WHERE id_room = ?',
                    array('ii', intval($room['order']), intval($room['id']))
                );
            }
        }
        
        // Commit transaction
        $DB->sql('COMMIT');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Room order updated successfully',
            'rooms' => $rooms
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $DB->sql('ROLLBACK');
        echo json_encode([
            'success' => false, 
            'message' => 'Error updating room order: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request'
    ]);
}
?>

