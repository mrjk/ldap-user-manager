<?php



##################################

function render_submenu() {

  global $THIS_MODULE_PATH;

  $submodules = array( 'users' => 'index.php',
                       'groups' => 'groups.php'
                     );
  ?>
   <nav class="navbar navbar-default">
    <div class="container-fluid">
     <ul class="nav navbar-nav">
      <?php
      foreach (
        $submodules as $submodule => $path) {

       if (basename($_SERVER['SCRIPT_FILENAME']) == $path) {
        print "<li class='active'>";
       }
       else {
        print '<li>';
       }
       print "<a href='{$THIS_MODULE_PATH}/{$path}'>" . ucwords($submodule) . "</a></li>\n";

      }
      // Add Organizations link for admins/maintainers
      if (function_exists('currentUserIsGlobalAdmin') && function_exists('currentUserIsMaintainer') && (currentUserIsGlobalAdmin() || currentUserIsMaintainer())) {
        $active = (basename($_SERVER['SCRIPT_FILENAME']) == 'organizations.php') ? " class='active'" : "";
        print "<li$active><a href='organizations.php'>Organizations</a></li>\n";
      }
     ?>
     </ul>
    </div>
   </nav>
  <?php
 }

?>
