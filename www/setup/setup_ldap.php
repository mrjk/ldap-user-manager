<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";

validate_setup_cookie();
set_page_access("setup");

render_header("$ORGANISATION_NAME account manager setup");

$ldap_connection = open_ldap_connection();

$no_errors = TRUE;
$show_create_admin_button = FALSE;

# Recursive helper
function loop_over_children($dn) {

  $parts = array_reverse(explode(",", $dn));
  $ret = array();
  $current = array();
  foreach ($parts as $part) {
    error_log("SETOUUUUTTTPPP  " . $part , 0);
    array_push($current, $part);
    array_push($ret, implode(",", array_reverse($current)));
  }
  return $ret;
}

function build_tree($ldap_connection, $part) {
  global $li_warn, $li_fail, $li_good;

  if (substr( $part, 0, 3 ) === "ou="){
    $ou_add = @ ldap_add($ldap_connection, $part, array( 'objectClass' => 'organizationalUnit', 'ou' => $part ));
    if ($ou_add == TRUE) {
      print "$li_good Created OU <strong>{$part}</strong></li>\n";
    }
    else {
      $error = ldap_error($ldap_connection);
      print "$li_fail Couldn't create {$part}: <pre>$error</pre></li>\n";
      $no_errors = FALSE;
    }
  } elseif (substr( $part, 0, 3 ) === "dc="){
    ;
  } else {
    print "$li_warn Ignore part: " . $part . "  " . "</li>\n";
  }
 }

# Set up missing stuff

if (isset($_POST['fix_problems'])) {
?>
<script>
    $(document).ready(function(){
     $('[data-toggle="popover"]').popover(); 
    });
</script>
<div class='container'>

 <div class="panel panel-default">
  <div class="panel-heading">Updating LDAP...</div>
   <div class="panel-body">
    <ul class="list-group">

<?php

 if (isset($_POST['setup_group_ou'])) {
  foreach (loop_over_children($LDAP['group_dn']) as $part) {
    build_tree($ldap_connection, $part);
  }
 }


 if (isset($_POST['setup_user_ou'])) {
  foreach (loop_over_children($LDAP['user_dn']) as $part) {
    build_tree($ldap_connection, $part);
  }
 }


 if (isset($_POST['setup_last_gid'])) {

  $highest_gid = ldap_get_highest_id($ldap_connection,'gid');
  $description = "Records the last GID used to create a Posix group. This prevents the re-use of a GID from a deleted group.";

  $add_lastgid_r = array( 'objectClass' => array('device','top'),
                          'serialnumber' => $highest_gid,
                          'description' => $description );

  $gid_add = @ ldap_add($ldap_connection, "cn=lastGID,{$LDAP['base_dn']}", $add_lastgid_r);

  if ($gid_add == TRUE) {
   print "$li_good Created <strong>cn=lastGID,{$LDAP['base_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create cn=lastGID,{$LDAP['base_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_last_uid'])) {

  $highest_uid = ldap_get_highest_id($ldap_connection,'uid');
  $description = "Records the last UID used to create a Posix account. This prevents the re-use of a UID from a deleted account.";

  $add_lastuid_r = array( 'objectClass' => array('device','top'),
                          'serialnumber' => $highest_uid,
                          'description' => $description );

  $uid_add = @ ldap_add($ldap_connection, "cn=lastUID,{$LDAP['base_dn']}", $add_lastuid_r);

  if ($uid_add == TRUE) {
   print "$li_good Created <strong>cn=lastUID,{$LDAP['base_dn']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create cn=lastUID,{$LDAP['base_dn']}: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }


 if (isset($_POST['setup_default_group'])) {

  $group_add = ldap_new_group($ldap_connection,$DEFAULT_USER_GROUP);
  
  if ($group_add == TRUE) {
   print "$li_good Created default group: <strong>$DEFAULT_USER_GROUP</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create default group: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }

 if (isset($_POST['setup_admins_group'])) {

  $group_add = ldap_new_group($ldap_connection,$LDAP['admins_group']);
  
  if ($group_add == TRUE) {
   print "$li_good Created LDAP administrators group: <strong>{$LDAP['admins_group']}</strong></li>\n";
  }
  else {
   $error = ldap_error($ldap_connection);
   print "$li_fail Couldn't create LDAP administrators group: <pre>$error</pre></li>\n";
   $no_errors = FALSE;
  }
 }

 $admins = ldap_get_group_members($ldap_connection,$LDAP['admins_group']);

 if (count($admins) < 1) {

  ?>
  <div class="form-group">
  <form action="<?php print "{$SERVER_PATH}account_manager/new_user.php"; ?>" method="post">
  <input type="hidden" name="setup_admin_account">
  <?php
  print "$li_fail The LDAP administration group is empty. ";
  print "<a href='#' data-toggle='popover' title='LDAP account administrators' data-content='";
  print "Only members of this group ({$LDAP['admins_group']}) will be able to access the account managment section, so we need to add people to it.";
  print "'>What's this?</a>";
  print "<label class='pull-right'><input type='checkbox' name='setup_admin_account' class='pull-right' checked>Create a new account and add it to the admin group?&nbsp;</label>";
  print "</li>\n";
  $show_create_admin_button = TRUE;
 }
 else {
  print "$li_good The LDAP account administrators group (<strong>{$LDAP['admins_group']}</strong>) isn't empty.</li>";
 }


?>
  </ul>
 </div>
</div>
<?php

##############

 if ($no_errors == TRUE) {
  if ($show_create_admin_button == FALSE) {
 ?>
 </form>
 <div class='well'>
  <form action="<?php print $THIS_MODULE_PATH; ?>">
   <input type='submit' class="btn btn-success center-block" value='Finished' class='center-block'>
  </form>
 </div>
 <?php
  }
  else {
  ?>
    <div class='well'>
    <input type='submit' class="btn btn-warning center-block" value='Create new account >' class='center-block'>
   </form>
  </div>
  <?php 
  }
 }
 else {
 ?>
 </form>
 <div class='well'>
  <form action="<?php print $THIS_MODULE_PATH; ?>/run_checks.php">
   <input type='submit' class="btn btn-danger center-block" value='< Re-run setup' class='center-block'>
  </form>
 </div>
<?php

 }

}

render_footer();

?>
