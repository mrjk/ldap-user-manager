<?php

$li_good="<li class='list-group-item list-group-item-success'>$GOOD_ICON";
$li_warn="<li class='list-group-item list-group-item-warning'>$WARN_ICON";
$li_fail="<li class='list-group-item list-group-item-danger'>$FAIL_ICON";

/**
 * Check if setup access is disabled and handle accordingly
 * This function should be called at the beginning of setup pages
 */
function check_setup_disabled() {
    global $SETUP_DISABLED;
    
    if ($SETUP_DISABLED == TRUE) {
        render_header("Setup Disabled");
        ?>
        <div class="container">
         <div class="panel panel-danger">
          <div class="panel-heading">Setup Access Disabled</div>
          <div class="panel-body">
           <p class="text-center">The setup functionality has been disabled by the administrator.</p>
           <p class="text-center">Please contact your system administrator if you need access to setup features.</p>
          </div>
         </div>
        </div>
        <?php
        render_footer();
        exit(0);
    }
}

?>
