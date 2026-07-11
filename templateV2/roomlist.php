<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/setup/start.php');
require_once (DOCUMENT_ROOT.'/template/Master.php');
require_once (DOCUMENT_ROOT.'/template/room.php');
require_once (DOCUMENT_ROOT.'/template/roomicon.php');
class roomlist extends content_block
{
	protected $DB = NULL;
	protected $userOb = NULL;
	public $roomlist = array();

	protected function fetch_list()
	{
		$this->roomlist = array ('id_room','title','vis_title','description','background','foreground','is_public','order');
		if(is_null($this->userOb) || !$this->userOb->logged_in)
		{
			$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public,rooms.order '.
				'FROM rooms '.
				'WHERE rooms.is_public=1 '.
				'ORDER BY rooms.order',
				array(),
				$this->roomlist
				);

		} else {
			if($this->userOb->user_details['is_admin'])
			{
				$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public,rooms.order '.
				'FROM rooms '.
				'ORDER BY rooms.order',
				array(''),
				$this->roomlist
				);
			} else {
			$this->DB->sql(
				'SELECT rooms.id_room,rooms.room_title,rooms.vis_room_title,rooms.room_description,rooms.background,rooms.foreground,rooms.is_public,rooms.order '.
				'FROM rooms '.
				'LEFT JOIN room_permissions '.
				'ON room_permissions.id_room = rooms.id_room AND room_permissions.id_user=? '.
				'WHERE rooms.is_public=1 OR (room_permissions.can_read=1 AND room_permissions.id_user=? AND room_permissions.expires>NOW()) '.
				'ORDER BY rooms.order',
				array('ss',$this->userOb->user_details['id_user'],$this->userOb->user_details['id_user']),
				$this->roomlist
				);
		}
			
		}
	}
	public function __construct($userOb = NULL,$DB = NULL)
	{

        
		if(is_null($DB) || !is_a($DB,'databaseI'))
		{
			exit("Invalid constructor for roomlist, missing database interface");
		}
		$this->userOb = &$userOb;
		$this->DB = &$DB;
		$this->fetch_list();
		
		// Create main content wrapper
		$mainContent = new content_block(NULL, 'div', array('class' => 'inner-content'));
		$contentGrid = new content_block(NULL, 'div', array('class' => count($this->roomlist) > 0 ? 'video-grid' : 'empty-content-grid', 'id' => 'initialContentGrid'));
		
		// Add "Add Room" button if user is admin
		if(!is_null($this->userOb) && $this->userOb->logged_in && $this->userOb->user_details['is_admin']) {
			$flexEnd = new content_block(NULL, 'div', array('class' => 'flex-start'));
			$flexEnd->push(new anchor('Add Room', array('class' => 'primary_button', 'href' => 'create_room.php')));
			$contentGrid->push($flexEnd);
		}
        
		// $contentGrid = new content_block(NULL, 'div', array('class' => 'video-grid', 'id' => 'initialContentGrid'));
		
		// Dynamically create content blocks from database rooms
		if(!empty($this->roomlist)) {
			foreach($this->roomlist as $room) {
				// Create clickable room block with drag-and-drop for admins
				$blockAttrs = array(
					'class' => 'video-block',
					'data-room-id' => $room['id_room'],
					'data-order' => $room['order']
				);
				
				// Add draggable attribute for admin users
				if(!is_null($this->userOb) && $this->userOb->logged_in && $this->userOb->user_details['is_admin']) {
					$blockAttrs['draggable'] = 'true';
					$blockAttrs['class'] = 'video-block draggable-room';
				}
                
				$block = new content_block(NULL, 'div', $blockAttrs);
				
				// Add anchor wrapper for main content
				$roomLink = new content_block(NULL, 'a', array('href' => 'resource_detail.php?id=' . htmlspecialchars($room['id_room']), 'class' => 'anchor_button'));

				$roomLink->push(new content_block(strtoupper($room['title']), 'h3',['title' => htmlspecialchars($room['description'], ENT_QUOTES, 'UTF-8')]));
				
				$block->push($roomLink);
				
				// Hover icons for admin users
				if(!is_null($this->userOb) && $this->userOb->logged_in && $this->userOb->user_details['is_admin'])
				{
					$hoverIcons = new content_block(NULL, 'div', array('class' => 'hover-icons'));
					

					$trashIcon = new content_block(NULL, 'div', array('class' => 'icon-btn trash-icon'));
				
					// Create form for delete action
					$deleteForm = new content_block(NULL, 'form', array(
						'method' => 'POST',
						'action' => '',
						'style' => 'display: inline; margin: 0;',
					));
					
				
					
					// Hidden input for room id
					$deleteForm->push(new content_block(NULL, 'input', array(
						'type' => 'hidden',
						'name' => 'id_room',
						'value' => $room['id_room']
					)));
						
					$deleteForm->push(new content_block(NULL, 'input', array(
						'type' => 'hidden',
						'name' => 'action',
						'value' => 'delete_room'
					)));
						
					// Submit button with SVG
					$deleteButton = new content_block(NULL, 'button', array(
						'type' => 'submit',
						'class' => 'icon-submit-btn',
						'title' => 'Delete',
						'onclick' => "return confirm('Delete this file?')"
					));
					$deleteButton->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>', 'raw'));
					
					$deleteForm->push($deleteButton);
					$trashIcon->push($deleteForm);
					$hoverIcons->push($trashIcon);
					// // Delete icon
					// $trashIcon = new content_block(NULL, 'div', array('class' => 'icon-btn trash-icon'));
					// $trashLink = new content_block(NULL, 'a', array('href' => 'delete_room.php?id=' . $room['id_room'], 'title' => 'Delete', 'onclick' => "return confirm('Delete this room?')", 'class' => 'anchor_button'));
					// $trashLink->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>', 'raw'));
					// $trashIcon->push($trashLink);
					// $hoverIcons->push($trashIcon);
					
					// Edit icon
					$editIcon = new content_block(NULL, 'div', array('class' => 'icon-btn pencil-icon'));
					$editLink = new content_block(NULL, 'a', array('href' => 'edit_room.php?id=' . $room['id_room'], 'title' => 'Edit', 'class' => 'anchor_button'));
					$editLink->push(new content_block('<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>', 'raw'));
					$editIcon->push($editLink);
					$hoverIcons->push($editIcon);
					
					$block->push($hoverIcons);
				}
				
				$contentGrid->push($block);
			}
		} else {
			// No rooms found message
			$emptyBlock = new content_block(NULL, 'div', array('class' => 'empty-state'));
			$emptyBlock->push(new content_block('No rooms available', 'h3'));
			$emptyBlock->push(new content_block('Please check back later or contact support.', 'p'));
			$contentGrid->push($emptyBlock);
		}
        
		$mainContent->push($contentGrid);
		
		// Add drag-and-drop JavaScript
		$sortScript = new content_block("
		document.addEventListener('DOMContentLoaded', function() {
			const grid = document.getElementById('initialContentGrid');
			if (!grid) return;
			
			let draggedElement = null;
			let draggedOver = null;
			
			// Get all draggable room blocks
			function getDraggableRooms() {
				return Array.from(grid.querySelectorAll('.draggable-room'));
			}
			
			// Update order in the DOM and optionally save to database
			function updateOrder(saveToDb = false) {
				const blocks = getDraggableRooms();
				blocks.forEach((block, index) => {
					const newOrder = index + 1;
					block.dataset.order = newOrder;
				});
				
				// Prepare order data
				const orderData = blocks.map(b => ({
					id: b.dataset.roomId,
					order: b.dataset.order
				}));
				
				console.log('Room order updated:', orderData);
				
				// Save to database via AJAX only if requested
				if(saveToDb) {
					saveOrderToDatabase(orderData);
				}
			}
			
			// Save order to database
			function saveOrderToDatabase(orderData) {
				const formData = new FormData();
				formData.append('rooms', JSON.stringify(orderData));
				
				// Show saving indicator
				// showSaveIndicator('Saving...');
				
				fetch('/admin/update_room_order.php', {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(response => response.json())
				.then(data => {
					if(data.success) {
						console.log('Order saved successfully:', data);
						// showSaveIndicator('Order saved!', true);
					} else {
						console.error('Error saving order:', data.message);
						showSaveIndicator('Error saving', false);
					}
				})
				.catch(error => {
					console.error('Network error:', error);
					showSaveIndicator('Error saving', false);
				});
			}
			
			// Show save indicator
			function showSaveIndicator(message, success = null) {
				// Remove existing indicator if any
				const existing = document.getElementById('save-indicator');
				if(existing) existing.remove();
				
				// Create new indicator
				const indicator = document.createElement('div');
				indicator.id = 'save-indicator';
				indicator.textContent = message;
				indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 12px 20px; ' +
					'background-color: ' + (success === true ? '#4CAF50' : success === false ? '#f44336' : '#ff6600') + '; ' +
					'color: white; border-radius: 6px; font-weight: 600; font-size: 14px; ' +
					'box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; transition: opacity 0.3s;';
				
				document.body.appendChild(indicator);
				
				// Auto remove after delay
				if(success !== null) {
					setTimeout(() => {
						indicator.style.opacity = '0';
						setTimeout(() => indicator.remove(), 300);
					}, 2000);
				}
			}
			
			// Drag event handlers
			function handleDragStart(e) {
				draggedElement = this;
				this.style.opacity = '0.5';
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/html', this.innerHTML);
			}
			
			function handleDragOver(e) {
				if (e.preventDefault) {
					e.preventDefault();
				}
				e.dataTransfer.dropEffect = 'move';
				return false;
			}
			
			function handleDragEnter(e) {
				if (this.classList.contains('draggable-room')) {
					this.classList.add('drag-over');
					draggedOver = this;
				}
			}
			
			function handleDragLeave(e) {
				if (this.classList.contains('draggable-room')) {
					this.classList.remove('drag-over');
				}
			}
			
			function handleDrop(e) {
				if (e.stopPropagation) {
					e.stopPropagation();
				}
				
				if (draggedElement !== this && this.classList.contains('draggable-room')) {
					// Get all draggable rooms
					const rooms = getDraggableRooms();
					const draggedIndex = rooms.indexOf(draggedElement);
					const targetIndex = rooms.indexOf(this);
					
					// Reorder the elements
					if (draggedIndex < targetIndex) {
						this.parentNode.insertBefore(draggedElement, this.nextSibling);
					} else {
						this.parentNode.insertBefore(draggedElement, this);
					}
					
					// Update order and save to database
					updateOrder(true);
				}
				
				this.classList.remove('drag-over');
				return false;
			}
			
			function handleDragEnd(e) {
				this.style.opacity = '1';
				
				// Remove drag-over class from all elements
				getDraggableRooms().forEach(room => {
					room.classList.remove('drag-over');
				});
			}
			
			// Attach drag event listeners to all draggable rooms
			function initDragAndDrop() {
				const draggableRooms = getDraggableRooms();
				
				draggableRooms.forEach(room => {
					room.addEventListener('dragstart', handleDragStart, false);
					room.addEventListener('dragenter', handleDragEnter, false);
					room.addEventListener('dragover', handleDragOver, false);
					room.addEventListener('dragleave', handleDragLeave, false);
					room.addEventListener('drop', handleDrop, false);
					room.addEventListener('dragend', handleDragEnd, false);
				});
			}
			
			// Initialize
			initDragAndDrop();
			updateOrder();
		});
		", 'script', array('type' => 'text/javascript'));
		
		$mainContent->push($sortScript);
		
		parent::__construct($mainContent,'raw');
	}
}
?>