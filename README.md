# LDAP User Manager

This is a PHP LDAP account manager; a web-based GUI interface which allows you to quickly populate a new LDAP directory and easily manage user accounts and groups.  It also has a self-service password change module.   
It's designed to work with OpenLDAP and to be run as a container.  It complements OpenLDAP containers such as [*osixia/openldap*](https://hub.docker.com/r/osixia/openldap/).

***

## Features

 * Setup wizard: this will create the necessary structure to allow you to add users and groups and will set up an initial admin user that can log into the user manager.
 * Organization management: create, edit, and delete organizations (companies, universities, etc.) in your LDAP directory.
 * Role-based access control: assign users as administrators, maintainers, or organization managers, each with appropriate permissions.
 * Group creation and management.
 * User account creation and management.
 * Optionally send an email to the user with their new or updated account credentials.
 * Secure password auto-generator: click the button to generate a secure password.
 * Password strength indicator.
 * Self-service account management: users can edit their own details, change passwords/passcodes, or delete their account.
 * Credential reset: authorized roles can reset passwords and passcodes for users.
 * Passcode support: optional passcode attribute for user accounts, with UI and backend support.
 * Self-service password change: non-admin users can log in to change their password.
 * An optional form for people to request accounts (request emails are sent to an administrator).

***

## Role-based Access Control

LDAP User Manager supports multiple user roles for scalable, secure delegation:

- **Administrators**: Full access to all organizations, users, and settings.
- **Maintainers**: Can manage all organizations and users, but with limited access to global settings.
- **Organization Managers**: Can manage users and groups within their assigned organization(s).
- **Regular Users**: Can view and edit their own account, change their password/passcode, or delete their account.

Role assignment is managed via LDAP group membership and/or a dedicated role attribute. Access control is enforced throughout the UI and backend.

***

## Screenshots

**Edit accounts**:   

![account_overview](https://user-images.githubusercontent.com/17613683/59344255-9c692480-8d05-11e9-9207-051291bafd91.png)


**Manage group membership**:   

![group_membership](https://user-images.githubusercontent.com/17613683/59344247-97a47080-8d05-11e9-8606-0bcc40471458.png)


**Self-service password change**:   

![self_service_password_change](https://user-images.githubusercontent.com/17613683/59344258-9ffcab80-8d05-11e9-9dc2-27dfd373fcc8.png)

***

## Quick start

```
docker run \
           --detach \
           --name=lum \
           -p 80:80 \
           -p 443:443 \
           -e "SERVER_HOSTNAME=lum.example.com" \
           -e "LDAP_URI=ldap://ldap.example.com" \
           -e "LDAP_BASE_DN=dc=example,dc=com" \
           -e "LDAP_REQUIRE_STARTTLS=TRUE" \
           -e "LDAP_ADMINS_GROUP=admins" \
           -e "LDAP_ADMIN_BIND_DN=cn=admin,dc=example,dc=com" \
           -e "LDAP_ADMIN_BIND_PWD=secret"\
           -e "LDAP_IGNORE_CERT_ERRORS=true" \
           -e "EMAIL_DOMAIN=ldapusermanager.org" \
           wheelybird/ldap-user-manager:v1.11
```
Change the variable values to suit your environment.  Now go to https://lum.example.com/setup.

***

## LDAP Schema and Configuration Requirements

LDAP User Manager requires certain standard and custom LDAP schemas to function fully (e.g., for organization country, user passcodes, etc.).

- **Standard schemas:** core, cosine, inetorgperson, organization, locality
- **Custom schemas:** orgWithCountry (for organization country), loginPasscode (for user passcodes)

**Full details, LDIFs, and troubleshooting:**
See [LDAP-CONFIGURATION.md](LDAP-CONFIGURATION.md) for all required and optional schemas, custom extensions, loading instructions, and advanced notes.

***

## Testing with an OpenLDAP container

This will set up an OpenLDAP container you can use to test the user manager against.  It uses the RFC2307BIS schema.
```
docker run \
             --detach \
             --restart unless-stopped \
             --name openldap \
             -e "LDAP_ORGANISATION=ldapusermanager" \
             -e "LDAP_DOMAIN=ldapusermanager.org" \
             -e "LDAP_ADMIN_PASSWORD=change_me" \
             -e "LDAP_RFC2307BIS_SCHEMA=true" \
             -e "LDAP_REMOVE_CONFIG_AFTER_SETUP=true" \
             -e "LDAP_TLS_VERIFY_CLIENT=never" \
             -p 389:389 \
             --volume /opt/docker/openldap/var_lib_ldap:/var/lib/ldap \
             --volume /opt/docker/openldap/etc_ldap_slapd.d:/etc/ldap/slapd.d \
             osixia/openldap:latest
   
docker run \
             --detach \
             --name=lum \
             -p 80:80 \
             -p 443:443 \
             -e "SERVER_HOSTNAME=localhost" \
             -e "LDAP_URI=ldap://172.17.0.1" \
             -e "LDAP_BASE_DN=dc=ldapusermanager,dc=org" \
             -e "LDAP_ADMINS_GROUP=admins" \
             -e "LDAP_ADMIN_BIND_DN=cn=admin,dc=ldapusermanager,dc=org" \
             -e "LDAP_ADMIN_BIND_PWD=change_me" \
             -e "LDAP_IGNORE_CERT_ERRORS=true" \
             wheelybird/ldap-user-manager:latest
```
Now go to https://localhost/setup - the password is `change_me` (unless you changed it).  As this will use self-signed certificates you might need to tell your browser to ignore certificate warnings.

---

### 1. **Configuration via Environment Variables**

- **In `config.inc.php`:**
  - `FILE_UPLOAD_MAX_SIZE` (default: 2MB) and `FILE_UPLOAD_ALLOWED_MIME_TYPES` (default: images, PDF, text) are now set from environment variables.
  - All upload validation in the app uses these variables.

- **How to use:**
  - To change the max upload size (e.g., to 5MB):  
    `-e FILE_UPLOAD_MAX_SIZE=5242880`
  - To allow more file types (e.g., add ZIP):  
    `-e FILE_UPLOAD_ALLOWED_MIME_TYPES="image/jpeg,image/png,application/pdf,application/zip"`

---

### 2. **README.md Documentation**

- The new variables are now documented in the "Optional" section:
  ```
  * `FILE_UPLOAD_MAX_SIZE` (default: *2097152*): The maximum allowed file upload size in bytes. Default is 2MB (2 * 1024 * 1024). Example: `-e FILE_UPLOAD_MAX_SIZE=5242880` for 5MB.
  * `FILE_UPLOAD_ALLOWED_MIME_TYPES` (default: *image/jpeg,image/png,image/gif,application/pdf,text/plain*): Comma-separated list of allowed MIME types for file uploads. Example: `-e FILE_UPLOAD_ALLOWED_MIME_TYPES="image/jpeg,image/png,application/pdf,application/zip"`.
  ```

---

**You can now control file upload size and allowed types via Docker environment variables, and this is clearly documented for users.**

Would you like to test this, or is there anything else you'd like to customize?
