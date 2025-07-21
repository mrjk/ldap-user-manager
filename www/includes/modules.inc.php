<?php

 #Modules and how they can be accessed.

######################################################

function parse_site_links($links_string) {
  # Parse site links string in format: NAME=URL|NAME=URL|...
  # Returns array of arrays with 'name' and 'url' keys
  
  if (empty($links_string)) {
    return array();
  }
  
  $links = array();
  $pairs = explode('|', $links_string);
  
  foreach ($pairs as $pair) {
    $parts = explode('=', $pair, 2);
    if (count($parts) == 2) {
      $links[] = array(
        'name' => trim($parts[0]),
        'url' => trim($parts[1])
      );
    }
  }
  
  return $links;
}

######################################################

 #access:
 #auth = need to be logged-in to see it
 #hidden_on_login = only visible when not logged in
 #admin = need to be logged in as an admin to see it

 $MODULES = array(
                    'log_in'          => 'hidden_on_login',
                    'change_password' => 'auth',
                    'account_manager' => 'admin',
                  );


# Add site links modules if configured
if (!empty($SITE_LINKS_USERS)) {
  $MODULES['site_links_users'] = array(
    'access' => 'auth',
    'name' => 'Other',
    'links' => parse_site_links($SITE_LINKS_USERS)
  );
}

if (!empty($SITE_LINKS_ADMIN)) {
  $MODULES['site_links_admin'] = array(
    'access' => 'admin',
    'name' => 'Admin',
    'links' => parse_site_links($SITE_LINKS_ADMIN)
  );
}


if ($ACCOUNT_REQUESTS_ENABLED == TRUE) {
  $MODULES['request_account'] = 'hidden_on_login';
}
if (!$REMOTE_HTTP_HEADERS_LOGIN) {
  $MODULES['log_out'] = 'auth';
}


?>
