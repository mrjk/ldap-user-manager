<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";
set_page_access("admin");

render_header("$ORGANISATION_NAME account manager");
render_submenu();

$ldap_connection = open_ldap_connection();

if (isset($_POST['delete_user'])) {

  $this_user = $_POST['delete_user'];
  $this_user = urldecode($this_user);

  $del_user = ldap_delete_account($ldap_connection,$this_user);

  if ($del_user) {
    render_alert_banner("User <strong>$this_user</strong> was deleted.");
  }
  else {
    render_alert_banner("User <strong>$this_user</strong> wasn't deleted.  See the logs for more information.","danger",15000);
  }


}

$people_data = ldap_get_user_datalist($ldap_connection);
$sub_groups = $people_data["groups"];
$people = $people_data["records"];
?>

<div class="container">

  <div class="row">
    <div class="col-md-4">
      <form action="<?php print $THIS_MODULE_PATH; ?>/new_user.php" method="post">
        <button id="add_group" class="btn btn-primary" type="submit">New user</button>
      </form> 
    </div>

    <div class="col-md-4">
      <input class="form-control" id="search_input" type="text" placeholder="Filter...">
    </div>

    <div class="col-sm-4 text-right">
      <span class="label label-primary"><?php print count($people);?> account<?php if (count($people) != 1) { print "s"; }?></span>  
    </div>
  </div>

 <?php
foreach ($sub_groups as $sub_group){

  $first_user = null;
  foreach ($people as $user) {
    if (isset($user['group_name']) && $user['group_name'] == $sub_group) {
      $first_user = $user;
      break;
    }
  }
  $group_suffix = "";
  if ($first_user['managed'] == TRUE) {
    $group_suffix = '<span class="label label-success">managed</span>';
  } else {
    $group_suffix = '<span class="label label-warning">unmanaged</span>';
  }

?>

 <table class="table table-striped table-fixed">
  <thead>
   <tr>
     <th class="col-md-3"><?php print $sub_group; ?>
     </th>
     <th class="col-md-3"></th>
     <th class="col-lg-6 text-right" style="padding-right: 0px;" >
     <?php print $group_suffix; ?>
     </th>
   </tr>
  </thead>
 <tbody id="userlist">
   <script>
    $(document).ready(function(){
      $("#search_input").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#userlist tr").filter(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
      });
    });
  </script>
<?php
foreach ($people as $record ){ //=> $attribs){
  $group_name = $record['group_name'];
  if ($sub_group == $record["group_name"] ){

    $record_dn = $record["dn"];
    $account_identifier="";
    if (isset($record["account_identifier"])) {
      $account_identifier = $record["account_identifier"];
    }

    $group_membership = ldap_user_group_membership($ldap_connection,$account_identifier);
    if (isset($record['mail'])) { $this_mail = $record['mail']; } else { $this_mail = ""; }

    print " <tr>\n   <td><a href='{$THIS_MODULE_PATH}/show_user.php?account_identifier=" .
    urlencode($account_identifier) . "'>" . htmlspecialchars($account_identifier, ENT_QUOTES, 'UTF-8') . "</a></td>\n";
    print "   <td>" . htmlspecialchars($this_mail, ENT_QUOTES, 'UTF-8') . "</td>\n"; 
    print "   <td>" . htmlspecialchars(implode(", ", $group_membership), ENT_QUOTES, 'UTF-8') . "</td>\n";
    print " </tr>\n";
  }
}
?>
  </tbody>
 </table>

 <?php
}
?>

</div>
<?php

ldap_close($ldap_connection);
render_footer();
?>
