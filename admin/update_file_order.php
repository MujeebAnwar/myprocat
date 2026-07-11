<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/login.php');
require_once (DOCUMENT_ROOT.'/setup/force_admin.php');

header('Content-Type: application/json');

// Check if the request is POST and has the required data
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['files']) && isset($_POST['id_room'])) {
    try {
        // Decode the files array
        $files = json_decode($_POST['files'], true);
        $id_room = intval($_POST['id_room']);
        
        if(!is_array($files) || empty($files)) {
            echo json_encode(['success' => false, 'message' => 'Invalid files data']);
            exit;
        }
        
        if($id_room <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
            exit;
        }
        
        // Begin transaction for data consistency
        $DB->sql('START TRANSACTION');
        
        // Update each file's order
        foreach($files as $file) {
            if(isset($file['id']) && isset($file['order']) && is_numeric($file['id']) && is_numeric($file['order'])) {
                $DB->sql(
                    'UPDATE filelist SET `order` = ? WHERE id_file = ? AND id_room = ?',
                    array('iii', intval($file['order']), intval($file['id']), $id_room)
                );
            }
        }
        
        // Commit transaction
        $DB->sql('COMMIT');
        
        echo json_encode([
            'success' => true, 
            'message' => 'File order updated successfully',
            'files' => $files,
            'room_id' => $id_room
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $DB->sql('ROLLBACK');
        echo json_encode([
            'success' => false, 
            'message' => 'Error updating file order: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request'
    ]);
}
?>

