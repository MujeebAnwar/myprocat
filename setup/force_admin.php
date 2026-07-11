<?php
if(!($Session->valid && $UserAccount->logged_in && $UserAccount->user_details['is_admin']))
{
	GoToPage('/logout.php');
}
?>