# LDAP Directory Structure for ldap-user-manager

---

## Quick Start: Copy & Paste Samples

### Example Organization LDIF
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

### Example User LDIF (with passcode)
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

### Example Org Manager Group LDIF
```ldif
dn: cn=orgManagers,o=OrgA,ou=organizations,dc=example,dc=com
objectClass: groupOfNames
cn: orgManagers
member: uid=jane.doe,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
```

### Example Global Admin Group LDIF
```ldif
dn: cn=administrators,ou=roles,dc=example,dc=com
objectClass: groupOfNames
cn: administrators
member: uid=admin,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
```

### Example OpenLDAP ACLs
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

## 1. DIT Structure

```
dc=example,dc=com
|
|-- ou=organizations
|    |-- o=OrgA
|    |    |-- ou=users
|    |    |    |-- uid=user1
|    |    |    |-- uid=user2
|    |    |-- cn=orgManagers
|    |-- o=OrgB
|         |-- ou=users
|         |    |-- uid=user3
|         |-- cn=orgManagers
|
|-- ou=roles
|    |-- cn=administrators
|    |-- cn=maintainers
```

- **Organizations**: Each as an `organization` entry under `ou=organizations`.
- **Users**: Under `ou=users` within their org.
- **Org Managers**: Group entry per org.
- **Global Roles**: Under `ou=roles`.

---

## 2. Object Classes and Attributes

- **Organizations**: `organization`, `top`
- **Users**: `inetOrgPerson`, `top`
- **Groups**: `groupOfNames` (for role/manager groups)

---

## 3. Example LDIF Entries

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

### User
```ldif
dn: uid=jane.doe,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
objectClass: inetOrgPerson
objectClass: top
uid: jane.doe
mail: jane.doe@orga.com
cn: Jane Doe
sn: Doe
userPassword: {SSHA}...
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

## 4. Notes
- Use the same structure for each organization.
- Add users to the appropriate group for role-based access.
- Extend with additional attributes as needed (e.g., for organization address, website, etc.). 

---

## 5. Example OpenLDAP ACLs and Server Configuration

To enforce role-based access control at the LDAP server level, add the following example ACLs to your OpenLDAP configuration (e.g., `slapd.conf` or dynamic config via `ldapmodify`). Adjust DNs as needed for your deployment.

### Example ACLs

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

#### **Notes:**
- Adjust the regex and group DNs to match your DIT if you change the structure.
- The `$1` in the org manager ACL refers to the org name captured in the regex.
- Place more specific ACLs above more general ones.
- The `manage` privilege gives full control; `write` allows modification but not deletion of entries.

### Required LDAP Server Configuration

- **Groups:** Ensure your `administrators`, `maintainers`, and per-org `OrgAdmins` groups exist and are populated with the correct user DNs.
- **Schema:** The above ACLs assume you use `groupOfNames` for groups, with the `member` attribute.
- **DIT:** The DIT structure must match the patterns in the ACLs (see above in this document).
- **Restart slapd:** After updating ACLs, restart the LDAP server or reload the configuration.
- **Test:** Use `ldapsearch` and your application to verify access for each role.

#### **Example: Add a user to a group**
To add a user to the administrators group:
```ldif
dn: cn=administrators,ou=roles,dc=example,dc=com
changetype: modify
add: member
member: uid=admin,ou=users,o=OrgA,ou=organizations,dc=example,dc=com
```

--- 

---

## 6. Passcode Support for User Authentication

### Passcode Attribute
- **Attribute:** `loginPasscode`
- **ObjectClass:** (custom, but can be added to `inetOrgPerson` entries)
- **Purpose:** Allows users to authenticate with a passcode (in addition to password).
- **Storage:** Passcodes are stored as hashes (using the same method as passwords).
- **Usage:**
  - Can be set/changed by admins, org managers, or the user themselves.
  - Used for login as an alternative to password.

### Example LDIF (with passcode)
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

### Notes
- The `loginPasscode` attribute is not a standard LDAP attribute; you may need to extend your schema or use an existing extensible attribute (e.g., `description`) if schema extension is not possible.
- Passcodes should be managed and stored securely, just like passwords.
- The login form and backend now support authentication with either password or passcode.

--- 