<?php
require_once('config.php');
require_once(DOCUMENT_ROOT.'/setup/login.php');
require_once(DOCUMENT_ROOT.'/setup/force_authorized.php');
require_once(DOCUMENT_ROOT.'/setup/force_admin.php');
require_once(DOCUMENT_ROOT.'/lib/adminactions.php');
require_once(DOCUMENT_ROOT.'/lib/keyless_lib.php');
require_once(DOCUMENT_ROOT.'/lib/nonce.php');
require_once(DOCUMENT_ROOT.'/template/Master.php');

function admin_users_h($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_users_notice(&$notices, $text, $type = 'info')
{
	$notices[] = array('text' => $text, 'type' => $type);
}

/** Convert DB datetime to HTML datetime-local value (Y-m-d\TH:i). */
function admin_users_datetime_local($value)
{
	if($value === NULL || $value === '')
	{
		return '';
	}
	$ts = strtotime((string)$value);
	if($ts === false)
	{
		return '';
	}
	return date('Y-m-d\TH:i', $ts);
}

/** Convert HTML datetime-local (or any parseable date) to MySQL datetime. */
function admin_users_datetime_mysql($value)
{
	if($value === NULL || $value === '')
	{
		return '';
	}
	$value = str_replace('T', ' ', (string)$value);
	$ts = strtotime($value);
	if($ts === false)
	{
		return $value;
	}
	return date('Y-m-d H:i:s', $ts);
}

$notices = array();
$id_selected = NULL;
$user_info = array();
$search_results = NULL;
$permissions = NULL;
$licenses_html = '';
$search_for = '';

if(!(isset($Session) && $Session->valid && isset($UserAccount) && $UserAccount->logged_in && !empty($UserAccount->user_details['is_admin'])))
{
	header('Location: /signup/login.php');
	exit;
}

if(is_array($_POST) && array_key_exists('id_selected', $_POST))
{
	$id_selected = $_POST['id_selected'];
}

if(is_array($_POST) && array_key_exists('Search_For', $_POST))
{
	$search_for = $_POST['Search_For'];
}

if(is_array($_POST) && array_key_exists('action', $_POST))
{
	switch($_POST['action'])
	{
		case 'create_user':
			if(!$DB->sql(
				'INSERT INTO accounts (`id_user`) VALUES (?)',
				array('s', $id_selected)
			))
			{
				admin_users_notice($notices, 'User already exists', 'error');
			}
			else
			{
				admin_users_notice($notices, 'User created.', 'success');
			}
			break;

		case 'search':
			if(array_key_exists('Search_For', $_POST))
			{
				$results = array(
					'id_user','email','first_name','last_name','account_expires',
					'id_perm','id_room','room_title','can_read','can_upload','can_remove',
					'room_expires','hardware_key','password_last_changed'
				);
				$searchstring = '%'.$_POST['Search_For'].'%';
				$DB->sql(
					'SELECT '.
					'accounts.id_user,accounts.email,accounts.first_name,'.
					'accounts.last_name,accounts.expires,'.
					'room_permissions.room_permissions_id,room_permissions.id_room,rooms.room_title,'.
					'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,'.
					'room_permissions.expires,hardware.id_hardware,'.
					'pw.time_stamp AS password_last_changed '.
					'FROM accounts '.
					'LEFT JOIN room_permissions ON accounts.id_user = room_permissions.id_user '.
					'LEFT JOIN rooms ON rooms.id_room = room_permissions.id_room '.
					'LEFT JOIN hardware ON accounts.id_user = hardware.id_user '.
					'LEFT JOIN (SELECT MAX(time_stamp) AS time_stamp, id_user FROM passwords GROUP BY id_user) AS pw ON accounts.id_user = pw.id_user '.
					'WHERE accounts.email LIKE ? OR accounts.id_user LIKE ? OR accounts.first_name LIKE ? OR accounts.last_name LIKE ?',
					array('ssss', $searchstring, $searchstring, $searchstring, $searchstring),
					$results
				);

				$duplicate = array();
				foreach($results as $res)
				{
					$duplicate[$res['id_user']] = 1;
				}
				if(count($duplicate) === 1)
				{
					$id_selected = $results[0]['id_user'];
					$user_info = array_intersect_key(
						$results[0],
						array_flip(array('email','first_name','last_name','account_expires','password_last_changed'))
					);
					$permissions = $results;
				}
				else
				{
					$search_results = $results;
					if(count($duplicate) === 0)
					{
						admin_users_notice($notices, 'No users matched your search.', 'info');
					}
				}
			}
			break;

		case 'update_user':
			if(!is_null($id_selected)
				&& array_key_exists('email', $_POST) && !is_null($_POST['email']) && strstr($_POST['email'], '@') !== false
				&& array_key_exists('first_name', $_POST) && !is_null($_POST['first_name']) && strlen($_POST['first_name']) > 0
				&& array_key_exists('last_name', $_POST) && !is_null($_POST['last_name']) && strlen($_POST['last_name']) > 0
				&& array_key_exists('account_expires', $_POST) && !is_null($_POST['account_expires']) && strlen($_POST['account_expires']) > 0
			)
			{
				$r = $DB->sql(
					'UPDATE accounts SET email=?,first_name=?,last_name=?,expires=? WHERE id_user=?',
					array('sssss', $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['account_expires'], $id_selected)
				);
				if(!$r)
				{
					admin_users_notice($notices, 'No user data changed', 'info');
				}
				else
				{
					admin_users_notice($notices, 'User info updated.', 'success');
				}
			}
			else
			{
				admin_users_notice($notices, 'Invalid input', 'error');
			}
			break;

		case 'Delete':
			$r = $DB->sql(
				'DELETE FROM room_permissions WHERE room_permissions_id=?',
				array('i', $_POST['id_perm'])
			);
			if(!$r)
			{
				admin_users_notice($notices, 'Room Permissions not changed', 'info');
			}
			else
			{
				admin_users_notice($notices, 'Room Access deleted for room \''.$_POST['room_title'].'\'.', 'success');
			}
			break;

		case 'Update':
			if(!is_null($_POST['id_perm']))
			{
				$can_read = array_key_exists('Read', $_POST) ? 1 : 0;
				$can_upload = array_key_exists('Upload', $_POST) ? 1 : 0;
				$can_remove = (array_key_exists('Remove', $_POST) || array_key_exists('Delete', $_POST)) ? 1 : 0;
				$expiration = admin_users_datetime_mysql(isset($_POST['Expiration']) ? $_POST['Expiration'] : '');

				$r = $DB->sql(
					'UPDATE room_permissions SET room_permissions.expires=?,can_read=?,can_upload=?,can_remove=? WHERE room_permissions_id=?',
					array('siiii', $expiration, $can_read, $can_upload, $can_remove, $_POST['id_perm'])
				);
				if(!$r)
				{
					admin_users_notice($notices, 'Room Permissions not changed', 'info');
				}
				else
				{
					admin_users_notice($notices, 'Room Access changed for room \''.$_POST['room_title'].'\'.', 'success');
				}
			}
			else
			{
				admin_users_notice($notices, 'Invalid input', 'error');
			}
			break;

		case 'add_room':
			if(array_key_exists('room_selected', $_POST) && !is_null($_POST['room_selected']) && $_POST['room_selected'] !== '')
			{
				$can_read = array_key_exists('Read', $_POST) ? 1 : 0;
				$can_upload = array_key_exists('Upload', $_POST) ? 1 : 0;
				$can_remove = (array_key_exists('Remove', $_POST) || array_key_exists('Delete', $_POST)) ? 1 : 0;
				$expiration = admin_users_datetime_mysql(isset($_POST['Expiration']) ? $_POST['Expiration'] : '');

				$r = $DB->sql(
					'INSERT INTO room_permissions (id_room,id_user,expires,can_read,can_upload,can_remove) VALUES (?,?,?,?,?,?)',
					array('issiii', $_POST['room_selected'], $id_selected, $expiration, $can_read, $can_upload, $can_remove)
				);
				if(!$r)
				{
					admin_users_notice($notices, 'Room Permissions not changed', 'info');
				}
				else
				{
					admin_users_notice($notices, 'Room Access added.', 'success');
				}
			}
			else
			{
				admin_users_notice($notices, 'Invalid input', 'error');
			}
			break;

		case 'reset_hardware':
			if(!is_null($id_selected))
			{
				if($DB->sql('DELETE FROM hardware WHERE id_user=?', array('s', $id_selected)))
				{
					admin_users_notice($notices, 'Hardware Key reset', 'success');
				}
				else
				{
					admin_users_notice($notices, 'Error resetting key (No existing key or bad ID?)', 'error');
				}
			}
			break;

		case 'set_key_destroyed':
			if(!is_null($id_selected) && array_key_exists('destroyed', $_POST) && !is_null($_POST['destroyed']))
			{
				if($DB->sql(
<<<SQL
INSERT INTO key_destroy_log
	(destroyed, id_user)
VALUES
	(?, ?)
ON DUPLICATE KEY UPDATE
	destroyed = ?,
	id_user = ?
SQL
					, array('isis', $_POST['destroyed'], $id_selected, $_POST['destroyed'], $id_selected)))
				{
					admin_users_notice($notices, 'Log Key as '.($_POST['destroyed'] ? 'D' : 'Und').'estroyed succeeded', 'success');
				}
				else
				{
					admin_users_notice($notices, 'Log Key as Destroyed failed (No existing key or bad ID?)', 'error');
				}
			}
			break;

		case 'enable_key_undestroy':
			if(!is_null($id_selected))
			{
				$results = array('id');
				if(!$DB->sql(
<<<SQL
SELECT id
FROM key_destroy_log
WHERE id_user = ?
SQL
					, array('s', $id_selected)
					, $results))
				{
					break;
				}
				try
				{
					$expires = new DateTime();
					$expires->setTimestamp(time() + (60 * 60));
					$nlib = new noncelib(new db_nonce_cache($DB, $id_selected));
					$anonce = $nlib->create(32, $expires, 'set_key_undestroyed', $results[0]['id']);
					admin_users_notice($notices, 'Enable Key Undestroy succeeded', 'success');
					admin_users_notice($notices, 'ID: '.$results[0]['id'], 'info');
					admin_users_notice($notices, 'Nonce: '.base64_encode($anonce->payload()), 'info');
				}
				catch(RuntimeException $e)
				{
					admin_users_notice($notices, 'Enable Key Undestroy failed (No existing key or bad ID?)', 'error');
				}
			}
			break;

		case 'release_license':
			if(!is_null($id_selected))
			{
				if(false === $DB->sql(
<<<SQL
DELETE FROM paired_licenses
WHERE id_room_permission
IN
(
	SELECT room_permissions_id
	FROM room_permissions
	WHERE id_user = ?
)
SQL
					, array('s', $id_selected)))
				{
					admin_users_notice($notices, 'Release license failed', 'error');
					break;
				}
				admin_users_notice($notices, 'Release license succeeded, or already released', 'success');
			}
			break;

		case 'query_acquired_licenses':
			if(!is_null($id_selected))
			{
				$results = array('computer_name', 'room_title', 'expires', 'time_stamp');
				if(false === $DB->sql(
<<<SQL
SELECT
	hardware_2021.computer_name,
	rooms.room_title,
	paired_licenses.expires,
	paired_licenses.time_stamp
FROM paired_licenses
LEFT JOIN room_permissions ON paired_licenses.id_room_permission = room_permissions.room_permissions_id
LEFT JOIN rooms ON room_permissions.id_room = rooms.id_room
LEFT JOIN hardware_2021 ON paired_licenses.id_hardware = hardware_2021.id
WHERE hardware_2021.id_user = ?
AND paired_licenses.expires > NOW()
SQL
					, array('s', $id_selected)
					, $results))
				{
					admin_users_notice($notices, 'No licenses acquired.', 'info');
					break;
				}

				$licenses_html = '<div class="admin-users-table-wrap"><table class="admin-users-table"><thead><tr>'
					.'<th>Computer Name</th><th>Room Title</th><th>Expires</th><th>Acquired</th><th>Duration</th>'
					.'</tr></thead><tbody>';
				foreach($results as $item)
				{
					$duration = date_diff(date_create($item['expires']), date_create($item['time_stamp']));
					$licenses_html .= '<tr>'
						.'<td>'.admin_users_h($item['computer_name']).'</td>'
						.'<td>'.admin_users_h($item['room_title']).'</td>'
						.'<td>'.admin_users_h($item['expires']).'</td>'
						.'<td>'.admin_users_h($item['time_stamp']).'</td>'
						.'<td>'.admin_users_h($duration->format('%a')).' days</td>'
						.'</tr>';
				}
				$licenses_html .= '</tbody></table></div>';
				admin_users_notice($notices, 'Acquired licenses:', 'info');
			}
			break;

		default:
			break;
	}
}

if(!is_null($id_selected) && is_null($permissions))
{
	$results = array(
		'id_user','email','first_name','last_name','account_expires',
		'id_perm','id_room','room_title','can_read','can_upload','can_remove',
		'room_expires','hardware_key','password_last_changed'
	);
	$DB->sql(
		'SELECT '.
		'accounts.id_user,accounts.email,accounts.first_name,'.
		'accounts.last_name,accounts.expires,'.
		'room_permissions.room_permissions_id,room_permissions.id_room,rooms.room_title,'.
		'room_permissions.can_read,room_permissions.can_upload,room_permissions.can_remove,'.
		'room_permissions.expires,hardware.id_hardware,'.
		'pw.time_stamp AS password_last_changed '.
		'FROM accounts '.
		'LEFT JOIN room_permissions ON accounts.id_user = room_permissions.id_user '.
		'LEFT JOIN rooms ON rooms.id_room = room_permissions.id_room '.
		'LEFT JOIN hardware ON accounts.id_user = hardware.id_user '.
		'LEFT JOIN (SELECT MAX(time_stamp) AS time_stamp, id_user FROM passwords GROUP BY id_user) AS pw ON accounts.id_user = pw.id_user '.
		'WHERE accounts.id_user=?',
		array('s', $id_selected),
		$results
	);
	if(count($results) > 0 && !is_null($results[0]['id_user']))
	{
		$user_info = array_intersect_key(
			$results[0],
			array_flip(array('email','first_name','last_name','account_expires','password_last_changed'))
		);
		$permissions = $results;
	}
	$search_for = $id_selected;
}

$roomlist = array();
if(!is_null($id_selected))
{
	$roomdata = array('id_room', 'room_title');
	$DB->sql(
		'SELECT rooms.id_room,rooms.room_title FROM rooms',
		array(''),
		$roomdata
	);
	foreach($roomdata as $dat)
	{
		$roomlist[$dat['id_room']] = $dat['room_title'];
	}
}

ob_start();
?>
<link rel="stylesheet" href="/admin/users/css/users.css">
<div class="admin-users-page">
	<div class="admin-users-header">
		<h2>Edit User</h2>
		<p>Search for a user by email, name, or user ID. Manage account details, room access, and licenses.</p>
	</div>

	<?php foreach($notices as $notice): ?>
		<div class="admin-users-notice admin-users-notice--<?php echo admin_users_h($notice['type']); ?>">
			<?php echo admin_users_h($notice['text']); ?>
		</div>
	<?php endforeach; ?>

	<?php if($licenses_html !== ''): ?>
		<div class="admin-users-section">
			<h3 class="admin-users-section-title">Acquired Licenses</h3>
			<?php echo $licenses_html; ?>
		</div>
	<?php endif; ?>

	<section class="admin-users-section">
		<h3 class="admin-users-section-title">Search</h3>
		<form method="POST" action="/admin/users/" class="admin-users-form">
			<input type="hidden" name="action" value="search">
			<div class="admin-users-inline">
				<div class="admin-users-field">
					<label for="Search_For">Search For</label>
					<input type="text" class="input-field" name="Search_For" id="Search_For" value="<?php echo admin_users_h($search_for); ?>" placeholder="Email, name, or user ID" required>
				</div>
				<button type="submit" class="admin-users-btn">Search</button>
			</div>
		</form>
	</section>

<?php if(is_null($id_selected)): ?>
	<?php if(is_null($search_results)): ?>
		<section class="admin-users-section">
			<h3 class="admin-users-section-title">Create User</h3>
			<form method="POST" action="/admin/users/" class="admin-users-form">
				<input type="hidden" name="action" value="create_user">
				<div class="admin-users-inline">
					<div class="admin-users-field">
						<label for="id_selected_create">New User ID</label>
						<input type="text" class="input-field" name="id_selected" id="id_selected_create" placeholder="User ID" required>
					</div>
					<button type="submit" class="admin-users-btn admin-users-btn--secondary">Add</button>
				</div>
			</form>
		</section>
	<?php else: ?>
		<section class="admin-users-section">
			<h3 class="admin-users-section-title">Search Results</h3>
			<?php
			$duplicate = array();
			foreach($search_results as $res):
				if(array_key_exists($res['id_user'], $duplicate))
				{
					continue;
				}
				$duplicate[$res['id_user']] = 1;
				$label = $res['id_user'].': '.$res['first_name'].' '.$res['last_name'].', '.$res['email'];
			?>
				<form method="POST" action="/admin/users/">
					<input type="hidden" name="id_selected" value="<?php echo admin_users_h($res['id_user']); ?>">
					<button type="submit" class="admin-users-result"><?php echo admin_users_h($label); ?></button>
				</form>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
<?php else: ?>
	<section class="admin-users-section">
		<h3 class="admin-users-section-title">User Info — <?php echo admin_users_h($id_selected); ?></h3>
		<form method="POST" action="/admin/users/" class="admin-users-form">
			<input type="hidden" name="action" value="update_user">
			<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
			<div class="admin-users-field-row">
				<div class="admin-users-field">
					<label for="email">Email</label>
					<input type="email" class="input-field" name="email" id="email" value="<?php echo admin_users_h(isset($user_info['email']) ? $user_info['email'] : ''); ?>" required>
				</div>
				<div class="admin-users-field">
					<label for="first_name">First Name</label>
					<input type="text" class="input-field" name="first_name" id="first_name" value="<?php echo admin_users_h(isset($user_info['first_name']) ? $user_info['first_name'] : ''); ?>" required>
				</div>
				<div class="admin-users-field">
					<label for="last_name">Last Name</label>
					<input type="text" class="input-field" name="last_name" id="last_name" value="<?php echo admin_users_h(isset($user_info['last_name']) ? $user_info['last_name'] : ''); ?>" required>
				</div>
			</div>
			<div class="admin-users-field-row">
				<div class="admin-users-field">
					<label for="account_expires">Account Expires</label>
					<input type="text" class="input-field" name="account_expires" id="account_expires" value="<?php echo admin_users_h(isset($user_info['account_expires']) ? $user_info['account_expires'] : ''); ?>" required>
				</div>
				<div class="admin-users-field">
					<label for="password_last_changed">Password Last Changed</label>
					<input type="text" class="input-field" id="password_last_changed" value="<?php echo admin_users_h(isset($user_info['password_last_changed']) ? $user_info['password_last_changed'] : ''); ?>" readonly>
				</div>
			</div>
			<div class="admin-users-actions">
				<button type="submit" class="admin-users-btn">Update User Info</button>
			</div>
		</form>
	</section>

	<section class="admin-users-section">
		<h3 class="admin-users-section-title">Room Access</h3>
		<?php
		if(is_array($permissions)):
			foreach($permissions as $perm):
				if(is_null($perm['id_perm']))
				{
					continue;
				}
				$formId = 'room_perm_'.admin_users_h($perm['id_perm']);
		?>
			<form id="<?php echo $formId; ?>" method="POST" action="/admin/users/">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<input type="hidden" name="id_perm" value="<?php echo admin_users_h($perm['id_perm']); ?>">
				<input type="hidden" name="room_title" value="<?php echo admin_users_h($perm['room_title']); ?>">
			</form>
		<?php
			endforeach;
		endif;
		?>
		<form id="room_perm_new" method="POST" action="/admin/users/">
			<input type="hidden" name="action" value="add_room">
			<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
		</form>
		<div class="admin-users-table-wrap">
			<table class="admin-users-table admin-users-room-table">
				<thead>
					<tr>
						<th>Room</th>
						<th>Expiration</th>
						<th class="admin-users-col-check">Read</th>
						<th class="admin-users-col-check">Upload</th>
						<th class="admin-users-col-check">Remove</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php
				if(is_array($permissions)):
					foreach($permissions as $perm):
						if(is_null($perm['id_perm']))
						{
							continue;
						}
						$formId = 'room_perm_'.admin_users_h($perm['id_perm']);
				?>
					<tr>
						<td>
							<span class="admin-users-room-name"><?php echo admin_users_h($perm['room_title']); ?></span>
						</td>
						<td>
							<input type="datetime-local" class="input-field input-field--table input-field--datetime" form="<?php echo $formId; ?>" name="Expiration" value="<?php echo admin_users_h(admin_users_datetime_local($perm['room_expires'])); ?>" step="60">
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="<?php echo $formId; ?>" name="Read" value="1"<?php echo $perm['can_read'] ? ' checked' : ''; ?> aria-label="Read">
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="<?php echo $formId; ?>" name="Upload" value="1"<?php echo $perm['can_upload'] ? ' checked' : ''; ?> aria-label="Upload">
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="<?php echo $formId; ?>" name="Remove" value="1"<?php echo $perm['can_remove'] ? ' checked' : ''; ?> aria-label="Remove">
						</td>
						<td>
							<div class="admin-users-row-actions">
								<button type="submit" form="<?php echo $formId; ?>" name="action" value="Update" class="admin-users-btn admin-users-btn--secondary admin-users-btn--sm">Update</button>
								<button type="submit" form="<?php echo $formId; ?>" name="action" value="Delete" class="admin-users-btn admin-users-btn--danger admin-users-btn--sm">Delete</button>
							</div>
						</td>
					</tr>
				<?php
					endforeach;
				endif;
				?>
					<tr class="admin-users-room-add-row">
						<td>
							<select class="input-field input-field--table" form="room_perm_new" name="room_selected" id="room_selected_new" required>
								<option value="" disabled selected>-- Select --</option>
								<?php foreach($roomlist as $rid => $rtitle): ?>
									<option value="<?php echo admin_users_h($rid); ?>"><?php echo admin_users_h($rtitle); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="datetime-local" class="input-field input-field--table input-field--datetime" form="room_perm_new" name="Expiration" id="Expiration_new" value="" step="60" required>
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="room_perm_new" name="Read" value="1" aria-label="Read">
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="room_perm_new" name="Upload" value="1" aria-label="Upload">
						</td>
						<td class="admin-users-col-check">
							<input type="checkbox" form="room_perm_new" name="Remove" value="1" aria-label="Remove">
						</td>
						<td>
							<button type="submit" form="room_perm_new" class="admin-users-btn admin-users-btn--sm">Add New</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>

	<section class="admin-users-section">
		<h3 class="admin-users-section-title">Reset Password</h3>
		<form method="POST" action="/admin/reset_password.php" class="admin-users-form">
			<input type="hidden" name="Set_Password_For" value="<?php echo admin_users_h($id_selected); ?>">
			<input type="hidden" name="action" value="reset_password">
			<div class="admin-users-inline">
				<div class="admin-users-field">
					<label for="Temporary_Password">Temporary Password</label>
					<input type="password" class="input-field" name="Temporary_Password" id="Temporary_Password" required>
				</div>
				<button type="submit" class="admin-users-btn admin-users-btn--secondary">Submit</button>
			</div>
		</form>
	</section>

	<section class="admin-users-section">
		<h3 class="admin-users-section-title">Winner 2020 and prior</h3>
		<div class="admin-users-tools">
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="reset_hardware">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Reset Hardware Key</button>
			</form>
		</div>
	</section>

	<section class="admin-users-section">
		<h3 class="admin-users-section-title">Winner 2021 and later</h3>
		<div class="admin-users-tools">
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="set_key_destroyed">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<input type="hidden" name="destroyed" value="1">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Log Key as Destroyed</button>
			</form>
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="set_key_destroyed">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<input type="hidden" name="destroyed" value="0">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Log Key as Undestroyed</button>
			</form>
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="enable_key_undestroy">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Enable Key Undestroy</button>
			</form>
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="release_license">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Release License</button>
			</form>
			<form method="POST" action="/admin/users/">
				<input type="hidden" name="action" value="query_acquired_licenses">
				<input type="hidden" name="id_selected" value="<?php echo admin_users_h($id_selected); ?>">
				<button type="submit" class="admin-users-btn admin-users-btn--muted">Show Acquired Licenses</button>
			</form>
		</div>
	</section>
<?php endif; ?>
</div>
<?php
$page_html = ob_get_clean();

$set_title = 'Edit User - MyProCAT';
$sidebar_title = 'Edit User';
$page_banner = new content_block(NULL, 'div', array('class' => 'banner'));
$page_banner->push(new content_block('Edit User', 'h1'));

$set_body = new content_block(NULL, 'div', array('style' => 'width: 100%;'));
$set_body->push(new content_block($page_html, 'raw'));

$breadcrumb_items = array(
	array('text' => 'Home', 'url' => '/resources.php'),
	array('text' => 'Edit User', 'url' => '/admin/users/'),
);

require_once DOCUMENT_ROOT.'/templateV2/mainframe/mainframe.php';
?>
