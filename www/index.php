<?php

set_include_path( __DIR__ . "/includes/");
include_once "web_functions.inc.php";

// Check if user is authenticated, if not redirect to login page
if (!$VALIDATED) {
    $redirect_url = base64_encode($_SERVER['REQUEST_URI']);
    header("Location: //{$_SERVER['HTTP_HOST']}{$SERVER_PATH}log_in/);
    exit();
}

render_header();

 if (isset($_GET['logged_in'])) {
 ?>
 <div class="alert alert-success">
 <p class="text-center">You're logged in. Select from the menu above.</p>
 </div>
 <?php
 }

render_footer();
?>
