<?php

set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";
include_once __DIR__ . "/../setup/module_functions.inc.php";

$attribute_map = $LDAP['default_attribute_map'];
if (isset($LDAP['account_additional_attributes'])) { $attribute_map = ldap_complete_attribute_array($attribute_map,$LDAP['account_additional_attributes']); }

if (! array_key_exists($LDAP['account_attribute'], $attribute_map)) {
  $attribute_r = array_merge($attribute_map, array($LDAP['account_attribute'] => array("label" => "Account UID")));
}

if ( isset($_POST['setup_admin_account']) ) {

  $admin_setup = TRUE;

  # Check if setup is disabled
  check_setup_disabled();

  validate_setup_cookie();
  set_page_access("setup");

  $completed_action="{$SERVER_PATH}log_in";
  $page_title="New administrator account";

  render_header("$ORGANISATION_NAME account manager - setup administrator account", FALSE);

}
else {
  set_page_access("admin");

  $completed_action="{$THIS_MODULE_PATH}/";
  $page_title="New account";
  $admin_setup = FALSE;

  render_header("$ORGANISATION_NAME account manager");
  render_submenu();
}

$invalid_password = FALSE;
$mismatched_passwords = FALSE;
$invalid_username = FALSE;
$weak_password = FALSE;
$invalid_email = FALSE;
$disabled_email_tickbox = TRUE;
$invalid_cn = FALSE;
$invalid_givenname = FALSE;
$invalid_sn = FALSE;
$invalid_account_identifier = FALSE;
$empty_password = FALSE;
$empty_confirm = FALSE;
$account_attribute = $LDAP['account_attribute'];

$new_account_r = array();

if ($SHOW_POSIX_ATTRIBUTES == TRUE) {

}

foreach ($attribute_map as $attribute => $attr_r) {

  if (isset($_FILES[$attribute]['size']) and $_FILES[$attribute]['size'] > 0) {

    $this_attribute = array();
    $this_attribute['count'] = 1;
    $this_attribute[0] = file_get_contents($_FILES[$attribute]['tmp_name']);
    $$attribute = $this_attribute;
    $new_account_r[$attribute] = $this_attribute;
    unset($new_account_r[$attribute]['count']);

  }

  if (isset($_POST[$attribute])) {

    $this_attribute = array();

    if (is_array($_POST[$attribute]) and count($_POST[$attribute]) > 0) {
      foreach($_POST[$attribute] as $key => $value) {
        if ($value != "") { $this_attribute[$key] = trim($value); }
      }
      if (count($this_attribute) > 0) {
        $this_attribute['count'] = count($this_attribute);
        $$attribute = $this_attribute;
      }
    }
    elseif ($_POST[$attribute] != "") {
      $this_attribute['count'] = 1;
      $this_attribute[0] = trim($_POST[$attribute]);
      $$attribute = $this_attribute;
    }

  }

  if (!isset($$attribute) and isset($attr_r['default'])) {
    $$attribute['count'] = 1;
    $$attribute[0] = $attr_r['default'];
  }

  if (isset($$attribute)) {
    $new_account_r[$attribute] = $$attribute;
    unset($new_account_r[$attribute]['count']);
  }

}

##

if (isset($_GET['account_request'])) {

  $givenname[0]=trim($_GET['first_name']);
  $new_account_r['givenname'] = $givenname[0];

  $sn[0]=trim($_GET['last_name']);
  $new_account_r['sn'] = $sn[0];

  $mail[0]=filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
  if ($mail[0] == "") {
    if (isset($EMAIL_DOMAIN)) {
      $mail[0] = $uid . "@" . $EMAIL_DOMAIN;
      $disabled_email_tickbox = FALSE;
    }
  }
  else {
    $disabled_email_tickbox = FALSE;
  }
  $new_account_r['mail'] = $mail;
  unset($new_account_r['mail']['count']);

}


if (isset($_GET['account_request']) or isset($_POST['create_account'])) {

  if (!isset($uid[0])) {
    $uid[0] = @generate_username($givenname[0],$sn[0]);
    $new_account_r['uid'] = $uid;
    unset($new_account_r['uid']['count']);
  }

  if (!isset($cn[0])) {
    $cn[0] = generate_cn($givenname[0], $sn[0]);
    $new_account_r['cn'] = $cn;
    unset($new_account_r['cn']['count']);
  }

}


if (isset($_POST['create_account'])) {

 $password  = $_POST['password'];
 $new_account_r['password'][0] = $password;
 $account_identifier = $new_account_r[$account_attribute][0];
 $this_cn=@$cn[0];
 $this_mail=@$mail[0];
 $this_givenname=@$givenname[0];
 $this_sn=@$sn[0];
 $this_password=@$password[0];

 if (!isset($this_cn) or $this_cn == "") { $invalid_cn = TRUE; }
 if ((!isset($account_identifier) or $account_identifier == "") and $invalid_cn != TRUE) { $invalid_account_identifier = TRUE; }
 if (!isset($this_givenname) or $this_givenname == "") { $invalid_givenname = TRUE; }
 if (!isset($this_sn) or $this_sn == "") { $invalid_sn = TRUE; }
 if ((!is_numeric($_POST['pass_score']) or $_POST['pass_score'] < 3) and $ACCEPT_WEAK_PASSWORDS != TRUE) { $weak_password = TRUE; }
 if (isset($this_mail) and !is_valid_email($this_mail)) { $invalid_email = TRUE; }
 if (preg_match("/\"|'/",$password)) { $invalid_password = TRUE; }
 if ($password != $_POST['password_match']) { $mismatched_passwords = TRUE; }
 if ($ENFORCE_SAFE_SYSTEM_NAMES == TRUE and !preg_match("/$USERNAME_REGEX/",$account_identifier)) { $invalid_account_identifier = TRUE; }
 
 // Additional validation for admin setup
 if ($admin_setup == TRUE) {
   if (!isset($password) or trim($password) == "") { $empty_password = TRUE; }
   if (!isset($_POST['password_match']) or trim($_POST['password_match']) == "") { $empty_confirm = TRUE; }
 }
 if (isset($_POST['send_email']) and isset($mail) and $EMAIL_SENDING_ENABLED == TRUE) { $send_user_email = TRUE; }

 if (     isset($this_givenname)
      and isset($this_sn)
      and isset($this_password)
      and !$mismatched_passwords
      and !$weak_password
      and !$invalid_password
      and !$invalid_account_identifier
      and !$invalid_cn
      and !$invalid_email
      and !$empty_password
      and !$empty_confirm) {

  $ldap_connection = open_ldap_connection();
  $new_account = ldap_new_account($ldap_connection, $new_account_r);

  if ($new_account) {

    $creation_message = "The account was created.";

    if (isset($send_user_email) and $send_user_email == TRUE) {

      include_once "mail_functions.inc.php";

      $mail_body = parse_mail_text($new_account_mail_body, $password, $account_identifier, $this_givenname, $this_sn);
      $mail_subject = parse_mail_text($new_account_mail_subject, $password, $account_identifier, $this_givenname, $this_sn);

      $sent_email = send_email($this_mail,"$this_givenname $this_sn",$mail_subject,$mail_body);
      $creation_message = "The account was created";
      if ($sent_email) {
        $creation_message .= " and an email sent to $this_mail.";
      }
      else {
        $creation_message .= " but unfortunately the email wasn't sent.<br>More information will be available in the logs.";
      }
    }

    if ($admin_setup == TRUE) {
      $member_add = ldap_add_member_to_group($ldap_connection, $LDAP['admins_group'], $account_identifier);
      if (!$member_add) { ?>
       <div class="alert alert-warning">
        <p class="text-center"><?php print $creation_message; ?> Unfortunately adding it to the admin group failed.</p>
       </div>
       <?php
      }
     #Tidy up empty uniquemember entries left over from the setup wizard
     $USER_ID="tmp_admin";
     ldap_delete_member_from_group($ldap_connection, $LDAP['admins_group'], "");
     if (isset($DEFAULT_USER_GROUP)) { ldap_delete_member_from_group($ldap_connection, $DEFAULT_USER_GROUP, ""); }
    }

   ?>
   <div class="col-sm-12 col-md-offset-0">
   <div class="alert alert-success">
   <p class="text-center"><?php print $creation_message; ?></p>
   </div>
   <div class="text-center">
    <?php if ($admin_setup != TRUE) { ?>
      <form action='<?php print $THIS_MODULE_PATH; ?>/new_user.php' method="post" style="display: inline-block;">
      <button type="submit" class="btn btn-primary">Create another user</button>
      </form>
    <?php } ?>
    <form action='<?php print $completed_action; ?>' style="display: inline-block; margin-right: 10px;">
     <input type='submit' class="btn btn-success" value='User list'>
    </form>
   </div>
   </div>
   <?php
   render_footer();
   exit(0);
  }
  else {
  ?>
    <div class="alert alert-warning">
     <p class="text-center">Failed to create the account:</p>
     <pre>
     <?php
       print ldap_error($ldap_connection) . "\n";
       ldap_get_option($ldap_connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $detailed_err);
       print $detailed_err;
     ?>
     </pre>
    </div>
    <?php

   render_footer();
   exit(0);

  }

 }

}

$errors="";
if ($invalid_cn) { $errors.="<li>The Common Name is required</li>\n"; }
if ($invalid_givenname) { $errors.="<li>First Name is required</li>\n"; }
if ($invalid_sn) { $errors.="<li>Last Name is required</li>\n"; }
if ($invalid_account_identifier) {  $errors.="<li>The account identifier (" . $attribute_map[$account_attribute]['label'] . ") is invalid.</li>\n"; }
if ($weak_password) { $errors.="<li>The password is too weak</li>\n"; }
if ($invalid_password) { $errors.="<li>The password contained invalid characters</li>\n"; }
if ($invalid_email) { $errors.="<li>The email address is invalid</li>\n"; }
if ($mismatched_passwords) { $errors.="<li>The passwords are mismatched</li>\n"; }
if ($invalid_username) { $errors.="<li>The username is invalid</li>\n"; }
if ($empty_password) { $errors.="<li>The password field is required</li>\n"; }
if ($empty_confirm) { $errors.="<li>The confirm password field is required</li>\n"; }

if ($errors != "") { ?>
<div class="alert alert-warning">
 <p class="text-align: center">
 There were issues creating the account:
 <ul>
 <?php print $errors; ?>
 </ul>
 </p>
</div>
<?php
}

render_js_username_check();
render_js_username_generator('givenname','sn',$LDAP['account_attribute'],$LDAP['account_attribute'] . '_div');
render_js_cn_generator('givenname','sn','cn','cn_div');
render_js_email_generator($LDAP['account_attribute'],'mail');
render_js_homedir_generator($LDAP['account_attribute'],'homedirectory');

$tabindex=1;

?>
<script src="<?php print $SERVER_PATH; ?>js/zxcvbn.min.js"></script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/zxcvbn-bootstrap-strength-meter.js"></script>
<script type="text/javascript">
 $(document).ready(function(){
   $("#StrengthProgressBar").zxcvbnProgressBar({ passwordInput: "#password" });
 });
</script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/generate_passphrase.js"></script>
<script type="text/javascript" src="<?php print $SERVER_PATH; ?>js/wordlist.js"></script>
<script>

 function check_passwords_match() {

   var passwordDiv = document.getElementById('password_div');
   var confirmDiv = document.getElementById('confirm_div');
   var passwordField = document.getElementById('password');
   var confirmField = document.getElementById('confirm');

   if (passwordField && confirmField && passwordField.value != confirmField.value ) {
       if (passwordDiv) { passwordDiv.classList.add("has-error"); }
       if (confirmDiv) { confirmDiv.classList.add("has-error"); }
   }
   else {
    if (passwordDiv) { passwordDiv.classList.remove("has-error"); }
    if (confirmDiv) { confirmDiv.classList.remove("has-error"); }
   }
  }

 function random_password() {

  generatePassword(4,'-','password','confirm');
  $("#StrengthProgressBar").zxcvbnProgressBar({ passwordInput: "#password" });
 }

 function back_to_hidden(passwordField,confirmField) {

  var passwordField = document.getElementById(passwordField);
  var confirmField = document.getElementById(confirmField);
  
  if (passwordField) { passwordField.type = 'password'; }
  if (confirmField) { confirmField.type = 'password'; }

 }


</script>
<script>

 function check_email_validity(mail) {

  var check_regex = <?php print $JS_EMAIL_REGEX; ?>;
  var mailDiv = document.getElementById("mail_div");
  var sendEmailCheckbox = document.getElementById("send_email_checkbox");

  if (! check_regex.test(mail) ) {
   if (mailDiv) { mailDiv.classList.add("has-error"); }
   <?php if ($EMAIL_SENDING_ENABLED == TRUE) { ?>if (sendEmailCheckbox) { sendEmailCheckbox.disabled = true; }<?php } ?>
  }
  else {
   if (mailDiv) { mailDiv.classList.remove("has-error"); }
   <?php if ($EMAIL_SENDING_ENABLED == TRUE) { ?>if (sendEmailCheckbox) { sendEmailCheckbox.disabled = false; }<?php } ?>
  }

 }

</script>

<?php render_dynamic_field_js(); ?>

<div class="container">
 <div class="col-sm-8 col-md-offset-2">

  <div class="panel panel-default">
   <div class="panel-heading text-center"><?php print $page_title; ?></div>
   <div class="panel-body text-center">

    <form class="form-horizontal" action="" enctype="multipart/form-data" method="post">

     <?php if ($admin_setup == TRUE) { ?><input type="hidden" name="setup_admin_account" value="true"><?php } ?>
     <input type="hidden" name="create_account">
     <input type="hidden" id="pass_score" value="0" name="pass_score">

     <?php
       foreach ($attribute_map as $attribute => $attr_r) {
         $label = $attr_r['label'];
         if (isset($attr_r['onkeyup'])) { $onkeyup = $attr_r['onkeyup']; } else { $onkeyup = ""; }
         if ($attribute == $LDAP['account_attribute']) { $label = "<strong>$label</strong><sup>&ast;</sup>"; }
         if (isset($attr_r['required']) and $attr_r['required'] == TRUE) { $label = "<strong>$label</strong><sup>&ast;</sup>"; }
         if (isset($$attribute)) { $these_values=$$attribute; } else { $these_values = array(); }
         if (isset($attr_r['inputtype'])) { $inputtype = $attr_r['inputtype']; } else { $inputtype = ""; }
         render_attribute_fields($attribute,$label,$these_values,"",$onkeyup,$inputtype,$tabindex);
         $tabindex++;
       }
     ?>

     <div class="form-group" id="password_div">
      <label for="password" class="col-sm-3 control-label">Password</label>
      <div class="col-sm-6">
       <input tabindex="<?php print $tabindex+2; ?>" type="text" class="form-control" id="password" name="password" onkeyup="back_to_hidden('password','confirm');">
      </div>
      <div class="col-sm-1">
       <input tabindex="<?php print $tabindex+1; ?>" type="button" class="btn btn-sm" id="password_generator" onclick="random_password();" value="Generate password">
      </div>
     </div>

     <div class="form-group" id="confirm_div">
      <label for="confirm" class="col-sm-3 control-label">Confirm</label>
      <div class="col-sm-6">
       <input tabindex="<?php print $tabindex+3; ?>" type="password" class="form-control" id="confirm" name="password_match" onkeyup="check_passwords_match()">
      </div>
     </div>

     <div class="form-group" id="strength_div">
      <label for="password" class="col-sm-3 control-label"></label>
      <div class="col-sm-6">
          <div class="progress" style="margin-bottom: 0px;">
            <div id="StrengthProgressBar" class="progress-bar"></div>
          </div>
      </div>
     </div>

<?php  if ($EMAIL_SENDING_ENABLED == TRUE and $admin_setup != TRUE) { ?>
      <div class="form-group" id="send_email_div">
       <label for="send_email" class="col-sm-3 control-label"> </label>
       <div class="col-sm-6">
        <input tabindex="<?php print $tabindex+4; ?>" type="checkbox" class="form-check-input" id="send_email_checkbox" name="send_email" <?php if ($disabled_email_tickbox == TRUE) { print "disabled"; } ?>>  Email these credentials to the user?
       </div>
      </div>
<?php } ?>

     <div class="form-group">
       <button tabindex="<?php print $tabindex+5; ?>" type="submit" class="btn btn-warning">Create account</button>
     </div>

    </form>


    <div><sup>&ast;</sup>The account identifier</div>

   </div>
  </div>

 </div>
</div>
<?php



render_footer();

?>
