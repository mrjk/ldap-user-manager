# Configuration

Configuration is via environmental variables.  Please bear the following in mind:

 * This tool needs to bind to LDAP as a user that has the permissions to modify everything under the base DN.
 * This interface is designed to work with a fresh LDAP server and should only be against existing, populated LDAP directories with caution and at your own risk.

#### Containers: using files/secrets to set configuration variables

When running the user manager as a container you can append `_FILE` to any of the configuration variables and set the value to a filepath.  Then when the container starts up it will set the appropriate configuration variable with the contents of the file.   
For example, if you're using Docker Swarm and you've set the LDAP bind password as a Docker secret (`echo "myLDAPadminPassword" | docker secret create ldap_admin_bind_pwd -`) then you can set `LDAP_ADMIN_BIND_PWD_FILE=/run/secrets/ldap_admin_bind_pwd`.  This will result in `LDAP_ADMIN_BIND_PWD` being set with the contents of `/run/secrets/ldap_admin_bind_pwd`.

### Mandatory:


* `LDAP_URI`:  The URI of the LDAP server, e.g. `ldap://ldap.example.com` or `ldaps://ldap.example.com`
   
* `LDAP_BASE_DN`:  The base DN for your organisation, e.g. `dc=example,dc=com`
   
* `LDAP_ADMIN_BIND_DN`: The DN for the user with permission to modify all records under `LDAP_BASE_DN`, e.g. `cn=admin,dc=example,dc=com`
   
* `LDAP_ADMIN_BIND_PWD`: The password for `LDAP_ADMIN_BIND_DN`
   
* `LDAP_ADMINS_GROUP`: The name of the group used to define accounts that can use this tool to manage LDAP accounts.  e.g. `admins`


#### Web server settings

* `SERVER_HOSTNAME` (default: *ldapusername.org*):  The hostname that this interface will be served from.
   
* `SERVER_PATH` (default: */*): The path to the user manager on the webserver.  Useful if running this behind a reverse proxy.
   
* `SERVER_PORT` (default: *80 or 80 & 443*): The port the webserver inside the container will listen on.  If undefined then the internal webserver will listen on ports 80 and 443 (if `NO_HTTPS` is true it's just 80) and HTTP traffic is redirected to HTTPS.  When set this will disable the redirection and the internal webserver will listen for HTTPS traffic on this port (or for HTTP traffic if `NO_HTTPS` is true).  This is for use when the container's Docker network mode is set to `host`.
   
* `NO_HTTPS` (default: *FALSE*): If you set this to *TRUE* then the server will run in HTTP mode, without any encryption.  This is insecure and should only be used for testing.  See [HTTPS certificates](#https-certificates)
   
* `SERVER_KEY_FILENAME`: (default *server.key*): The filename of the HTTPS server key file. See [HTTPS certificates](#https-certificates)
   
* `SERVER_CERT_FILENAME`: (default *server.crt*): The filename of the HTTPS certficate file. See [HTTPS certificates](#https-certificates)
   
* `CA_CERT_FILENAME`: (default *ca.crt*): The filename of the HTTPS server key file. See [HTTPS certificates](#https-certificates)
   
* `SESSION_TIMEOUT` (default: *10 minutes*):  How long before an idle session will be timed out.

* `SETUP_DISABLED` (default: *FALSE*):  If set to *TRUE*, the setup functionality will be completely disabled. Users will not be able to access `/setup`, `/setup/run_checks.php`, `/setup/setup_ldap.php`, or any other setup-related pages. This is useful for production environments where you want to prevent accidental setup changes.

#### LDAP settings

* `LDAP_USER_OU` (default: *people*):  The name of the OU used to lookup user accounts (without the base DN appended). You can use children with `ou` instead, with the syntax: `people,ou=SUB_OU1,ou=SUB_OU2`.
   
* `LDAP_GROUP_OU` (default: *groups*):  The name of the OU used to lookup groups (without the base DN appended). You can use children with `ou` instead, with the syntax: `groups,ou=SUB_OU1,ou=SUB_OU2`.
   
* `LDAP_REQUIRE_STARTTLS` (default: *TRUE*):  If *TRUE* then a TLS connection is required for this interface to work.  If set to *FALSE* then the interface will work without STARTTLS, but a warning will be displayed on the page, (unless `LDAP_IGNORE_STARTTLS_WARNING` is *TRUE*).
   
* `LDAP_IGNORE_STARTTLS_WARNING` (default: *FALSE*):  If *TRUE* then the unsecure TLS connection warning is hidden.

* `LDAP_IGNORE_CERT_ERRORS` (default: *FALSE*): If *TRUE* then problems with the certificate presented by the LDAP server will be ignored (for example FQDN mismatches).  Use this if your LDAP server is using a self-signed certificate and you don't have a CA certificate for it or you're connecting to a pool of different servers via round-robin DNS.
   
* `LDAP_TLS_CACERT` (no default): If you need to use a specific CA certificate for TLS connections to the LDAP server (when `LDAP_REQUIRE_STARTTLS` is set) then assign the contents of the CA certificate to this variable.  e.g. `-e LDAP_TLS_CACERT="$(</path/to/ca.crt)"` (ensure you're using quotes or you'll get an "invalid reference format: repository name must be lowercase" error).  Alternatively you can bind-mount a certificate into the container and use `LDAP_TLS_CACERT_FILE` to specify the path to the file.

#### Advanced LDAP settings

These settings should only be changed if you're trying to make the user manager work with an LDAP directory that's already populated and the defaults don't work.
   
* `LDAP_ACCOUNT_ATTRIBUTE` (default: *uid*):  The attribute used as the account identifier.  See [Account names](#account-names) for more information.
   
* `LDAP_GROUP_ATTRIBUTE` (default: *cn*):  The attribute used as the group identifier.
   
* `LDAP_GROUP_MEMBERSHIP_ATTRIBUTE` (default: *memberUID* or *uniqueMember*):  The attribute used when adding a user's account to a group.  When the `groupOfMembers` objectClass is detected `FORCE_RFC2307BIS` is `TRUE` it defaults to `uniqueMember`, otherwise it'll default to `memberUID`. Explicitly setting this variable will override any default.
   
* `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` (no default): A comma-separated list of additional objectClasses to use when creating an account.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` (no default): A comma-separated list of extra attributes to display when creating an account.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `GROUP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` (no default): A comma-separated list of additional objectClasses to use when creating a group.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.

* `GROUP_ACCOUNT_ADDITIONAL_ATTRIBUTES` (no default): A comma-separated list of extra attributes to display when creating a group.  See [Extra objectClasses and attributes](#extra-objectclasses-and-attributes) for more information.
   
* `LDAP_GROUP_MEMBERSHIP_USES_UID` (default: *TRUE* or *FALSE*): If *TRUE* then the entry for a member of a group will be just the username, otherwise it's the member's full DN.  When the `groupOfMembers` objectClass is detected or `FORCE_RFC2307BIS` is `TRUE` it  defaults to `FALSE`, otherwise it'll default to `TRUE`. Explicitly setting this variable will override the default.
   
* `FORCE_RFC2307BIS` (default: *FALSE*): Set to *TRUE* if the auto-detection is failing to spot that the RFC2307BIS schema is available.  When *FALSE* the user manager will use auto-detection.  See [Using the RFC2307BIS schema](#using-the-rfc2307bis-schema) for more information.
   
* `LDAP_NEW_USER_OU` (default: *people*):  The name of the OU used to create user accounts (without the base DN appended). `LDAP_NEW_USER_OU` must be equal or below `LDAP_USER_OU`. Support children, if not set, `LDAP_USER_OU` is used as default.
   
* `LDAP_NEW_GROUP_OU` (default: *groups*):  The name of the OU used to create groups (without the base DN appended). `LDAP_NEW_GROUP_OU` must be equal or under `LDAP_GROUP_OU`. Support children, if not set, `LDAP_GROUP_OU` is used as default.

#### User account creation settings

* `DEFAULT_USER_GROUP` (default: *everybody*):  The group that new accounts are automatically added to when created.  *NOTE*: If this group doesn't exist then a group is created with the same name as the username and the user is added to that group.
   
* `DEFAULT_USER_SHELL` (default: */bin/bash*):  The shell that will be launched when the user logs into a server.
   
* `EMAIL_DOMAIN` (no default):  If set then the email address field will be automatically populated in the form of `username@email_domain`.
   
* `ENFORCE_SAFE_SYSTEM_NAMES` (default: *TRUE*):  If set to `TRUE` (the default) this will check system login and group names against `USERNAME_REGEX` to ensure they're safe to use on servers.  See [Account names](#account-names) for more information.
   
* `USERNAME_FORMAT` (default: *{first_name}-{last_name}*):  The template used to dynamically generate the usernames stored in the `uid` attribute.  See [Username format](#username-format).
   
* `USERNAME_REGEX` (default: *[a-z][a-zA-Z0-9\._-]{3,32}*): The regular expression used to ensure account names and group names are safe to use on servers. The regex is automatically anchored with `^$`. See [Username format](#username-format).
   
* `PASSWORD_HASH` (no default):  Select which hashing method which will be used to store passwords in LDAP.  Options are (in order of precedence) `SHA512CRYPT`, `SHA256CRYPT`, `MD5CRYPT`, `SSHA`, `SHA`, `SMD5`, `MD5`, `ARGON2`, `CRYPT` & `CLEAR`.  If your chosen method isn't available on your system then the strongest available method will be automatically selected - `SSHA` is the strongest method guaranteed to be available. (Note that for `ARGON2` to work your LDAP server will need to have the ARGON2 module enabled. If you don't the passwords will be saved but the user won't be able to authenticate.) Cleartext passwords should NEVER be used in any situation outside of a test.
   
* `ACCEPT_WEAK_PASSWORDS` (default: *FALSE*):  Set this to *TRUE* to prevent a password being rejected for being too weak.  The password strength indicators will still gauge the strength of the password.  Don't enable this in a production environment.


#### Website appearance and behaviour settings

* `ORGANISATION_NAME`: (default: *LDAP*): Your organisation's name.
   
* `SITE_NAME` (default: *`ORGANISATION_NAME` user manager*):  Change this to replace the title in the menu, e.g. "My Company Account Management".
   
* `SITE_LOGIN_LDAP_ATTRIBUTE` (default: *`LDAP_ACCOUNT_ATTRIBUTE`*):  The LDAP account attribute to use when logging into the user-manager.  For example, set this to `mail` to use email addresses to log in. Use this with extreme caution. The value for this attribute needs to be unique for each account; if more than one result is found when searching for an account then you won't be able to log in.
   
* `SITE_LOGIN_FIELD_LABEL` (default: *Username*):  This is the label that appears next to the username field on the login page.  If you change `SITE_LOGIN_LDAP_ATTRIBUTE` then you might want to change this.  For example, `SITE_LOGIN_FIELD_LABEL="Email address"`.
   
* `SHOW_POSIX_ATTRIBUTES` (default: *FALSE*):  If set to `TRUE` this show extra attributes for **posixAccount** and **posixGroup** in the account and group forms.  Leave this set to `FALSE` if you don't use LDAP accounts to log into servers etc., as it makes the interface much simpler.   The Posix values are still set in the background using the default values.  This setting doesn't hide any Posix attributes set via `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` or `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`.

* `REMOTE_HTTP_HEADERS_LOGIN`(default: *FALSE*) Enables session managment from an external service like Authelia. _This setting will compromise your security if you're not using an Auth-Proxy in front of this application_.

* `SITE_THEME_NAME`: The theme to use for the web interface. Available options: `classic`, `modern` (default). e.g. `SITE_THEME_NAME=modern`

* `THEME_VARIANT`: The color variant for the modern theme. Available options: `anthracite` (default), `orange`, `blue`, `anthracite`, `green`, `yellow`. Only applies when `SITE_THEME_NAME=modern`. e.g. `THEME_VARIANT=orange`

* `SITE_LINKS_USERS` (no default): Configurable links for all authenticated users. Format: `NAME=URL|NAME=URL|...`. Creates an "Other" dropdown menu in the navigation. e.g. `SITE_LINKS_USERS="Help=https://help.example.com|Wiki=https://wiki.example.com"`

* `SITE_LINKS_ADMIN` (no default): Configurable links for admin users only. Format: `NAME=URL|NAME=URL|...`. Creates a "Tools" dropdown menu in the navigation. e.g. `SITE_LINKS_ADMIN="LDAP Admin=https://ldapadmin.example.com|Monitoring=https://monitoring.example.com"`



#### Email sending settings

To send emails you'll need to use an existing SMTP server.  Email sending will be disabled if `SMTP_HOSTNAME` isn't set.
   
* `SMTP_HOSTNAME` (no default): The hostname of an SMTP server - used to send emails when creating new accounts.
   
* `SMTP_HOST_PORT` (default: *25*): The SMTP port on the SMTP server.
   
* `SMTP_HELO_HOST` (no default): The hostname to send with the HELO/EHLO command.

* `SMTP_USERNAME` (no default): The username to use when the SMTP server requires authentication.
   
* `SMTP_PASSWORD` (no default): The password to use when the SMTP server requires authentication.
   
* `SMTP_USE_TLS` (default: *FALSE*): Set to TRUE if the SMTP server requires TLS to be enabled.  Overrides `SMTP_USE_SSL`.
   
* `SMTP_USE_SSL` (default: *FALSE*): Set to TRUE if the SMTP server requires SSL to be enabled.  This will be unset if `SMTP_USE_TLS` is `TRUE`.
   
* `EMAIL_FROM_ADDRESS` (default: *admin@`EMAIL_DOMAIN`*): The FROM email address used when sending out emails.  The default domain is taken from `EMAIL_DOMAIN` under **User account settings**.
   
* `EMAIL_FROM_NAME` (default: *`SITE_NAME`*): The FROM name used when sending out emails.  The default name is taken from `SITE_NAME` under **Organisation settings**.
   
* `MAIL_SUBJECT` (default: *Your `ORGANISATION_NAME` account has been created.*): The mail subject for new account emails.
   
* `NEW_ACCOUNT_EMAIL_SUBJECT`, `NEW_ACCOUNT_EMAIL_BODY`, `RESET_PASSWORD_EMAIL_SUBJECT` & `RESET_PASSWORD_EMAIL_BODY`: Change the email contents for emails sent to users when you create an account or reset a password.  See [Sending emails](#sending_emails) for full details.


**Account requests**

#### Account request settings

* `ACCOUNT_REQUESTS_ENABLED` (default: *FALSE*): Set to TRUE in order to enable a form that people can fill in to request an account.  This will send an email to `ACCOUNT_REQUESTS_EMAIL` with their details and a link to the account creation page where the details will be filled in automatically.  You'll need to set up email sending (see **Email sending**, above) for this to work.  If this is enabled but email sending isn't then requests will be disabled and an error message sent to the logs.
   
* `ACCOUNT_REQUESTS_EMAIL` (default: *{EMAIL_FROM_ADDRESS}*): This is the email address that any requests for a new account are sent to.


#### Website customization

* `$CUSTOM_LOGO` (default: *FALSE*)*: If this is defined with path to image file, then this image will be displayed in header. You need also mount volume with this file. 

* `$CUSTOM_STYLES` (default: *FALSE*)*:  If this is defined with path to css file, then this style will be used in header. Also helps vith logo positioninig. You need also mount volume with this file.

docker-compose.yml example:

```yaml
ldap-user-manager:
  environment:
    CUSTOM_LOGO: "../gfx/logo.svg"
    CUSTOM_STYLES: "../css/custom.css"
  volumes:
    - '/opt/openldap/www/gfx:/opt/ldap_user_manager/gfx'
    - '/opt/openldap/www/css:/opt/ldap_user_manager/css'
```

#### Debugging settings

* `LDAP_DEBUG` (default: *FALSE*): Set to TRUE to increase the logging level for LDAP requests.  This will output passwords to the error log - don't enable this in a production environment.  This is for information on problems updating LDAP records and such.  To debug problems connecting to the LDAP server in the first place use `LDAP_VERBOSE_CONNECTION_LOGS`.
   
* `LDAP_VERBOSE_CONNECTION_LOGS` (default: *FALSE*): Set to TRUE to enable detailed LDAP connection logs (PHP's LDAP_OPT_DEBUG_LEVEL 7).  This will flood the logs with detailled LDAP connection information so disable this for production environments.
   
* `SESSION_DEBUG` (default: *FALSE*): Set to TRUE to increase the logging level for sessions and user authorisation.  This will output cookie passkeys to the error log - don't enable this in a production environment.
   
* `SMTP_LOG_LEVEL` (default: *0*): Set to between 1-4 to get SMTP logging information (0 disables SMTP debugging logs though it will still display errors). See https://github.com/PHPMailer/PHPMailer/wiki/SMTP-Debugging for details of the levels.

***
