# ldap-user-manager: Sample Configurations & Quick Start

This page collects the most important copy-paste samples for setting up your LDAP directory, access control, and TYPO3 SSO integration. Use these as a quick reference or starting point for your deployment.

---

## 1. LDAP Directory: Example LDIFs

### Organization
```ldif
dn: o=OrgA,ou=organizations,dc=example,dc=com
objectClass: organization
objectClass: top
o: OrgA
postalAddress: 123 Main St$12345$City$Country
telephoneNumber: +1 555 1234
labeledURI: http://www.orga.com
mail: info@orga.com
```

### User (with passcode)
```ldif
dn: uid=jane.doe,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
objectClass: inetOrgPerson
objectClass: top
uid: jane.doe
mail: jane.doe@orga.com
cn: Jane Doe
sn: Doe
userPassword: {SSHA}dummyhash
loginPasscode: {bcrypt}$2y$10$examplehash
```

### Org Manager Group
```ldif
dn: cn=orgManagers,o=OrgA,ou=organizations,dc=example,dc=com
objectClass: groupOfNames
cn: orgManagers
member: uid=jane.doe,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
```

### Global Admin Group
```ldif
dn: cn=administrators,ou=roles,dc=example,dc=com
objectClass: groupOfNames
cn: administrators
member: uid=admin,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
```

---

## 2. OpenLDAP: Example ACLs

```
# 1. Administrators: Full access everywhere
access to *
  by group.exact="cn=administrators,ou=roles,dc=example,dc=com" manage

# 2. Maintainers: Full access except admin users/groups
access to dn.regex="^uid=.+,ou=users,o=.*,ou=organizations,dc=example,dc=com$"
  by group.exact="cn=maintainers,ou=roles,dc=example,dc=com" write

# Prevent maintainers from modifying admin users
access to dn.regex="^uid=admin.*,ou=users,o=.*,ou=organizations,dc=example,dc=com$"
  by group.exact="cn=maintainers,ou=roles,dc=example,dc=com" none

# 3. Org Managers: Manage users in their own org
access to dn.regex="^uid=.+,ou=users,o=([^,]+),ou=organizations,dc=example,dc=com$"
  by group.exact="cn=OrgAdmins,o=$1,ou=organizations,dc=example,dc=com" write

# 4. Users: Self-management (e.g., change their own password)
access to dn.regex="^uid=([^,]+),ou=users,o=.*,ou=organizations,dc=example,dc=com$"
  by self write

# 5. Read access for all authenticated users
access to *
  by users read
  by anonymous auth
```

---

## 3. TYPO3 SSO Integration: Quick Samples

### User Filter
```
(|(uid={USERNAME})(mail={USERNAME}))
```

### Attribute Mapping
| TYPO3 Field   | LDAP Attribute |
|--------------|---------------|
| Username     | uid           |
| Email        | mail          |
| First Name   | cn            |
| Last Name    | sn            |
| Password     | userPassword  |
| Passcode     | loginPasscode |

### LocalConfiguration.php Snippet
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

## 4. Useful References
- See `docs/ldap-structure.md` for full DIT, object class, and ACL documentation.
- See `docs/typo3-ig_ldap_sso_auth-setup.md` for a step-by-step TYPO3 integration guide. 