<?php
// auth/logout.php
if (basename(__FILE__) == 'logout.php'):
session_start();
session_destroy();
header("Location: ../index.php");
exit();
endif;
?>