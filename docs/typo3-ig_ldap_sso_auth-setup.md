# TYPO3 SSO Integration with ig_ldap_sso_auth and OpenLDAP

---

## Quick Start: Copy & Paste Samples

### Example User Filter
```
(|(uid={USERNAME})(mail={USERNAME}))
```

### Example Attribute Mapping
| TYPO3 Field   | LDAP Attribute |
|--------------|---------------|
| Username     | uid           |
| Email        | mail          |
| First Name   | cn            |
| Last Name    | sn            |
| Password     | userPassword  |
| Passcode     | loginPasscode |

### Example LocalConfiguration.php Snippet
```php
'EXTENSIONS' => [
    'ig_ldap_sso_auth' => [
        'enabled' => 1,
        'ldap_server' => [
            [
                'host' => 'ldap.example.com',
                'port' => 636,
                'tls' => 1,
                'binddn' => 'cn=admin,dc=example,dc=com',
                'password' => 'yourpassword',
                'basedn' => 'ou=organizations,dc=example,dc=com',
                'usersdn' => 'ou=users,o=OrgName,ou=organizations,dc=example,dc=com',
                'filter' => '(|(uid={USERNAME})(mail={USERNAME}))',
                'mapping' => [
                    'username' => 'uid',
                    'email' => 'mail',
                    'first_name' => 'cn',
                    'last_name' => 'sn',
                ],
                // ... other options ...
            ],
        ],
        // ... other extension settings ...
    ],
],
```

### Example Group DN
```
cn=administrators,ou=roles,dc=example,dc=com
```

---

This guide explains how to connect TYPO3 to an OpenLDAP directory using the `ig_ldap_sso_auth` extension, enabling single sign-on (SSO) and LDAP-based authentication for TYPO3 users. The instructions are tailored for the LDAP structure and schema described in this project.

---

## Prerequisites

- TYPO3 is installed and running.
- The `ig_ldap_sso_auth` extension is installed (via Extension Manager or Composer).
- An OpenLDAP server is running with the following structure:
  - Users are stored under: `uid=...,ou=users,o=OrgName,ou=organizations,dc=example,dc=com`
  - Groups are under: `ou=roles,dc=example,dc=com` or per-organization.
  - User attributes include: `uid`, `mail`, `userPassword`, `loginPasscode`, `cn`, `sn`, etc.

---

## 1. Configure the LDAP Connection in TYPO3

1. Go to **Admin Tools > Settings > Extension Configuration > ig_ldap_sso_auth** in the TYPO3 backend.
2. Enter your LDAP server details:
   - **LDAP Host:** The URI of your LDAP server (e.g., `ldaps://ldap.example.com`)
   - **LDAP Port:** `636` for LDAPS, `389` for LDAP
   - **LDAP Bind DN:** A service account with read access, e.g., `cn=admin,dc=example,dc=com`
   - **LDAP Bind Password:** The password for the above DN
   - **LDAP Base DN:** `ou=organizations,dc=example,dc=com` (to search all organizations)
   - **Users DN:** `ou=users,o=OrgName,ou=organizations,dc=example,dc=com` (for a specific org)

---

## 2. Set Up User Search and Attribute Mapping

- **User Filter:**
  ```
  (|(uid={USERNAME})(mail={USERNAME}))
  ```
  This allows users to log in with either their username or email address.

- **Attribute Mapping:**
  - Username: `uid`
  - Email: `mail`
  - First Name: `cn` or `givenName`
  - Last Name: `sn`
  - Password: `userPassword`
  - Passcode: `loginPasscode` (see below for custom support)

---

## 3. Group Mapping (Optional)

If you want to map LDAP groups to TYPO3 user groups:
- Example LDAP group: `cn=administrators,ou=roles,dc=example,dc=com`
- Map these to TYPO3 frontend or backend groups as needed in the extension settings.

---

## 4. Authentication Flow

- Users log in to TYPO3 with their LDAP username (uid or mail) and password.
- The extension checks the `userPassword` attribute by default.
- If you have enabled passcode support in your LDAP, you can extend authentication to check the `loginPasscode` attribute as well (see below).

---

## 5. Passcode Support

By default, `ig_ldap_sso_auth` does not check a custom `loginPasscode` attribute. If you want to allow users to log in with a passcode:

- You will need to extend the extension or implement a custom TYPO3 authentication service.
- The custom logic should:
  1. Attempt to authenticate with the password.
  2. If that fails, fetch the `loginPasscode` attribute and verify it.
- For most SSO scenarios, password-based authentication is sufficient.

---

## 6. Example Configuration (LocalConfiguration.php)

Below is an example configuration for the extension in `typo3conf/LocalConfiguration.php`:

```php
'EXTENSIONS' => [
    'ig_ldap_sso_auth' => [
        'enabled' => 1,
        'ldap_server' => [
            [
                'host' => 'ldap.example.com',
                'port' => 636,
                'tls' => 1,
                'binddn' => 'cn=admin,dc=example,dc=com',
                'password' => 'yourpassword',
                'basedn' => 'ou=organizations,dc=example,dc=com',
                'usersdn' => 'ou=users,o=OrgName,ou=organizations,dc=example,dc=com',
                'filter' => '(|(uid={USERNAME})(mail={USERNAME}))',
                'mapping' => [
                    'username' => 'uid',
                    'email' => 'mail',
                    'first_name' => 'cn',
                    'last_name' => 'sn',
                ],
                // ... other options ...
            ],
        ],
        // ... other extension settings ...
    ],
],
```

---

## 7. SSO (Single Sign-On) Options

- For true SSO (e.g., Windows logon), configure your web server for Kerberos/NTLM and let ig_ldap_sso_auth use REMOTE_USER.
- Otherwise, users log in with their LDAP credentials as described above.

---

## 8. Testing

- Test login with a user from your LDAP directory.
- Check group mapping if you use LDAP groups for TYPO3 permissions.
- Verify that user attributes (name, email, etc.) are imported correctly.

---

## 9. References

- [ig_ldap_sso_auth Extension Manual](https://docs.typo3.org/p/ichhabrecht/ig-ldap-sso-auth/master/en-us/)
- [TYPO3 Authentication Services](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Authentication/Index.html)
- [OpenLDAP Documentation](https://www.openldap.org/doc/)

---

## Summary Table

| Setting                | Value/Example                                               |
|------------------------|------------------------------------------------------------|
| LDAP Host              | ldap(s)://ldap.example.com                                 |
| LDAP Port              | 636 (LDAPS) or 389 (LDAP)                                  |
| Bind DN                | cn=admin,dc=example,dc=com                                 |
| Base DN                | ou=organizations,dc=example,dc=com                         |
| User Filter            | (|(uid={USERNAME})(mail={USERNAME}))                       |
| Username Attribute     | uid                                                        |
| Email Attribute        | mail                                                       |
| Password Attribute     | userPassword                                               |
| Passcode Attribute     | loginPasscode (custom, needs extension for SSO)            |

--- 