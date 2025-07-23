# Usage


## Account names

Your login ID is whatever the *account identifier* value is for your account.  By default the user manager uses the **System username** as your login; this is actually the LDAP `uid` attribute.  So if your system username is `test-person`, that's what you'll use to log in with.    
   
The `uid` is the attribute that's normally used as the login username for systems like Linux, FreeBSD, NetBSD etc., and so is a great choice if you're using LDAP to create server accounts.   
Other services or software might use the *Common Name* (`cn`) attribute, which is normally a person's full name. So you might therefore log in as `Test Person`.   
   
The account identifier is what uniquely identifies the account, so you can't create multiple accounts where the account identifier is the same.   
You should ensure your LDAP clients use the same account identifier attribute when authenticating users.   
   
If you're using LDAP for server accounts then you'll find there are  normally constraints on how many characters and the type of characters you're allowed to use.  The user manager will validate user and group names against `USERNAME_REGEX`.  If you don't need to be so strict then you can disable these checks by setting `ENFORCE_SAFE_SYSTEM_NAMES` to `FALSE`.

***

## HTTPS certificates
The user manager runs in HTTPS mode by default and so uses HTTPS certificates.  You can pass in your own certificates by bind-mounting a local path to `/opt/ssl` in the container and then  specifying the names of the files via `SERVER_KEY_FILENAME`, `SERVER_CERT_FILENAME` and optionally `CA_CERT_FILENAME` (this will set Apache's `SSLCertificateChainFile` directive).   
If the certificate and key files don't exist then a self-signed certificate will be created when the container starts.
   
When using your own certificates, the certificate's common name (or one of the alternative names) need to match the value you set for `SERVER_HOSTNAME`.
   
For example, if your key and certificate files are in `/home/myaccount/ssl` you can bind-mount that folder by adding these lines to the `docker run` example above (place them above the final line):
```
-e "SERVER_KEY_FILENAME=lum.example.com.key" \
-e "SERVER_CERT_FILENAME=lum.example.com.crt" \
-e "CA_CERT_FILENAME=ca_bundle.pem" \
-v /home/myaccount/ssl:/opt/ssl \
```
   
If you don't want to use HTTPS certificates then set `NO_HTTPS` to **TRUE** to run in HTTP mode.  It's advised that you only do this when testing.

***

## Sending emails

When you create an account you'll have an option to send an email to the person you created the account for.  The email will give them their new username, password and a link to the self-service password change utility.   

Emails are sent via SMTP, so you'll need to be able to connect to an SMTP server and pass in the settings for that server via environmental variables - see **Email sending** above.   
If you haven't passed in those settings or if the account you've created has no (valid) email address then the option to send an email will be disabled.

When the account is created you'll be told if the email was sent or not but be aware that just because your SMTP server accepted the email it doesn't mean that it was able to deliver it.  If you get a message saying the email wasn't sent then check the logs for the error.  You can increase the log level (`SMTP_LOG_LEVEL`) to above 0 in order to see SMTP debug logs.

You can set the email subject and text for new account and password reset emails via the `NEW_ACCOUNT_EMAIL_SUBJECT`, `NEW_ACCOUNT_EMAIL_BODY`, `RESET_PASSWORD_EMAIL_SUBJECT` and `RESET_PASSWORD_EMAIL_BODY` variables.  These variables are parsed before sending and the following macros will be replaced with the relevant information:

 * `{password}` : the new password for the account
 * `{login}` : the user's login (the value of the attribute defined by `LDAP_ACCOUNT_ATTRIBUTE`.  See [Account names](#account-names) for more information.
 * `{first_name}` : the user's first name
 * `{last_name}` : the user's surname
 * `{organisation}` : the value set by `ORGANISATION_NAME`
 * `{site_url}` : a link to the user manager site using the values set by `SERVER_HOSTNAME/SERVER_PATH`
 * `{change_password_url}` : a link to the self-service password change page `SERVER_HOSTNAME/SERVER_PATH/change_password`

The email body should be in HTML.  As an example, the default email subject on creating a new account is `Your {organisation} account has been created.` and the email body is
```
You've been set up with an account for {organisation}.  Your credentials are:
<p>
Login: {login}<br>
Password: {password}
<p>
You should log into {change_password_url} and change the password as soon as possible.
```

***

## Username format

When entering a person's name the system username is automatically filled-in based on a template.  The template is defined in `USERNAME_FORMAT` and is a string containing predefined macros that are replaced with the relevant value.   
The default is `{first_name}-{last_name}` with which *Jonathan Testperson*'s username would be *jonathan-testperson*.   
Currently the available macros are:

 * `{first_name}` : the first name in lowercase
 * `{first_name_initial}` : the first letter of the first name in lowercase
 * `{last_name}`: the last name in lowercase
 * `{last_name_initial}`: the first initial of the last name in lowercase

Anything else in the `USERNAME_FORMAT` string is left unmodified.  If `ENFORCE_SAFE_SYSTEM_NAMES` is set then the username is also checked for validity against `USERNAME_REGEX`.  This is to ensure that there aren't any characters forbidden when using LDAP to create server or email accounts.   
   
If `EMAIL_DOMAIN` is set then the email address field will be automatically updated in the form of `username@email_domain`.  Entering anything manually in that field will stop the automatic update of the email field.

***

## CN format

When entering a person's name the common name (CN) is automatically filled-in based on a template.  The template is defined in `CN_FORMAT` and is a string containing predefined macros that are replaced with the relevant value.   
The default is `{first_name} {last_name}` with which *Jonathan Testperson*'s CN would be *Jonathan Testperson*.   
Currently the available macros are:

 * `{first_name}` : the first name (preserves case)
 * `{first_name_initial}` : the first letter of the first name (preserves case)
 * `{last_name}`: the last name (preserves case)
 * `{last_name_initial}`: the first initial of the last name (preserves case)
 * `{username}`: the generated username based on USERNAME_FORMAT template

Anything else in the `CN_FORMAT` string is left unmodified.  If `ENFORCE_SAFE_SYSTEM_NAMES` is set then the CN will have accents removed to ensure compatibility.

***

## Extra objectClasses and attributes

By default accounts are created with `person`, `inetOrgPerson` and `posixAccount` object classes.  Groups are created with `posixGroup` - if [the RFC2307BIS schema](#using-the-rfc2307bis-schema) is available then `groupOfUniqueNames` is automatically added too.   

If you need to add additional objectClasses and attributes to accounts or groups then you can add them via `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES`, `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES`, `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` and `LDAP_GROUP_ADDITIONAL_ATTRIBUTES`.

`LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES` and `LDAP_GROUP_ADDITIONAL_OBJECTCLASSES take a comma-separated list of objectClasses to add.  For example, `LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=ldappublickey,couriermailaccount`.   

`LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` and `LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES` take a comma-separated list of attributes to be displayed as extra fields for the account or group.    
By default these fields will be empty with the field named for the attribute, but you can set the field labels (and optionally the default values) by appending the attribute names with colon-separated values like so: `attribute_name:label:default_value`.   
Multiple attributes are separated by commas, so you can define the label and default values for several attributes as follows:  `attribute1:label1:default_value1,attribute2:label2:default_value2,attribute3:label3`.   

As an example, to set a mailbox name and quota for the `couriermailaccount` schema you can pass these variables to the container:   
```
LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=couriermailaccount
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES="mailbox:Mailbox:domain.com,quota:Mail quota:20"
```

_Note_: ObjectClasses often have attributes that _must_ have a value, so you should set a default value for these attributes, otherwise if you forget to add a value when filling in the form an error will be thrown on submission.

### Multi-value attributes

If you have an attribute that could have several values, you can add a `+` to end of the attribute name.  This will modify the form so you can add or remove extra values for that attribute.  For example, if you want to have multiple email aliases when using the _PostfixBookMailAccount_ schema then you can pass these variables to the container:   
```
LDAP_ACCOUNT_ADDITIONAL_OBJECTCLASSES=PostfixBookMailAccount" \
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES=mailAlias+:Email aliases"
```

### Binary attributes

If you have an attribute that stores the contents of a binary file (for example, a JPEG) then you can add a `^` to the end of the attribute name.  This will modify the form so that this attribute has an upload button.  If a JPEG has already been uploaded then it will display the image.  Otherwise the mime-type is displayed and there's a link for downloading the file.  For example, to allow you to set a user's photo:

```
LDAP_ACCOUNT_ADDITIONAL_ATTRIBUTES=jpegPhoto^:Photograph"
```
The maximum filesize you can upload is 2MB.


### Caveat

These settings are advanced usage and the user manager doesn't attempt to validate any objectClasses, attributes, labels or default values you pass in.  It's up to you to ensure that your LDAP server has the appropriate schemas and that the labels and values are sane.

***

## Using the RFC2307BIS schema

Using the **RFC2307BIS** will allow you to use `memberOf` in LDAP searches which gives you an easy way to check if a user is a member of a group. For example: `(&(objectClass=posixAccount)(memberof=cn=somegroup,ou=groups,dc=ldapusermanager,dc=org))`.   
   
OpenLDAP will use the RFC2307 (NIS) schema by default; you'll need to configure your server to use the **RFC2307BIS** schema when setting up your directory.    See [this guide](https://unofficialaciguide.com/2019/07/31/ldap-schemas-for-aci-administrators-rfc2307-vs-rfc2307bis/) for more information regarding RFC2307 vs RFC2307BIS.   
Setting up RFC2307BIS is way beyond the scope of this README, but if you plan on using [osixia/openldap](https://github.com/osixia/docker-openldap) as your LDAP server then you can easily enable the RFC2307BIS schema by setting `LDAP_RFC2307BIS_SCHEMA` to `true` during the initial setup.   
   
The user manager will attempt detect if your LDAP server has the RFC2307BIS schema available and, if it does, use it when creating groups.  This will allow you to use `memberOf` in LDAP searches which gives you an easy way to check if a user is a member of a group. For example: `(&(objectClass=posixAccount)(memberof=cn=somegroup,ou=groups,dc=ldapusermanager,dc=org))`.   

If for some reason you do have the schema available but it isn't being detected then you can force the user manager to use it by setting `FORCE_RFC2307BIS` to `TRUE`.   
**Note**: if you force-enable using RFC2307BIS but your LDAP server doesn't have that schema available then creating and adding users to groups won't work and the user manager will throw errors.
