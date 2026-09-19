<?php
(@include_once("./auth.php")) or die("Cannot read auth.php file<BR>");

// Lock every group again in this browser
require_post();
auth_logout();

echo json_encode(array('is_valid' => 1, 'message' => "Logged out."));
exit;
