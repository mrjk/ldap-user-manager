# LDAP Configuration for LDAP User Manager

This document describes all LDAP schema and configuration requirements for running LDAP User Manager, including required and optional schemas, custom extensions, and troubleshooting tips.

---

## 1. Required LDAP Schemas

LDAP User Manager requires the following standard OpenLDAP schemas:

- `core`
- `cosine`
- `inetorgperson`
- `organization`
- `locality`

These are typically included by default in most OpenLDAP installations.

---

## 2. Custom Schemas Provided

### 2.1 orgWithCountry (Auxiliary Object Class)
- **Purpose:** Allows the `c` (country) attribute on organization entries, which is not permitted by the standard `organization` object class.
- **LDIF:** [`ldif/orgWithCountry.ldif`](ldif/orgWithCountry.ldif)
- **How to load:**
  ```sh
  ldapadd -Y EXTERNAL -H ldapi:/// -f ldif/orgWithCountry.ldif
  ```
- **Usage:** Add the `orgWithCountry` object class to organization entries to allow the `c` attribute.

### 2.2 loginPasscode (Auxiliary Object Class)
- **Purpose:** Allows a hashed `loginPasscode` attribute on user entries for optional passcode authentication.
- **LDIF:** [`ldif/loginPasscode.ldif`](ldif/loginPasscode.ldif)
- **How to load:**
  ```sh
  ldapadd -Y EXTERNAL -H ldapi:/// -f ldif/loginPasscode.ldif
  ```
- **Usage:** Add the `loginPasscodeUser` object class to user entries to allow the `loginPasscode` attribute.

---

## 3. Example LDIF Loading

To load a schema LDIF into OpenLDAP (as root/admin):
```sh
ldapadd -Y EXTERNAL -H ldapi:/// -f ldif/orgWithCountry.ldif
ldapadd -Y EXTERNAL -H ldapi:/// -f ldif/loginPasscode.ldif
```

Restart or reload your LDAP server if required.

---

## 4. Attribute and ObjectClass Explanations

- **orgWithCountry**: Auxiliary object class to allow the `c` (country) attribute on organizations.
- **loginPasscodeUser**: Auxiliary object class to allow the `loginPasscode` attribute on user entries (for optional passcode authentication).
- **loginPasscode**: Single-valued, case-exact string attribute for storing a hashed passcode.

---

## 5. Troubleshooting Schema Errors

- **objectClass violation** or **attribute type undefined**: Ensure the relevant schema LDIF is loaded and the entry includes the required object class.
- **Cannot add country to organization**: Make sure `orgWithCountry` is loaded and the entry includes this object class.
- **Cannot add loginPasscode to user**: Make sure `loginPasscode.ldif` is loaded and the entry includes the `loginPasscodeUser` object class.

---

## 6. Advanced LDAP Compatibility Notes

- The application is designed for OpenLDAP but may work with other LDAP servers if the required schemas and object classes are present.
- If you use custom or additional attributes/objectClasses, ensure your LDAP server schema supports them.
- For advanced group and role management, see the main README and the "Extra objectClasses and attributes" section.

---

## 7. See Also

- [ldif/orgWithCountry.ldif](ldif/orgWithCountry.ldif)
- [ldif/loginPasscode.ldif](ldif/loginPasscode.ldif)
- [ldif/base.ldif](ldif/base.ldif) (example data, not schema)
- Main [README.md](README.md) for general setup and environment variables. 

---

## 8. Example: Loading Custom Schemas in Docker Compose

### Recommended: Use a Dedicated LDIF Directory Volume

For easier setup and maintenance, mount your entire `ldif/` directory into the container. Any LDIF file placed in this directory will be loaded automatically at startup (in filename order):

**Directory structure:**
```
project-root/
  ldif/
    10-orgWithCountry.ldif
    20-loginPasscode.ldif
    ...
```

**docker-compose.yml:**
```yaml
services:
  ldap:
    image: osixia/openldap:1.5.0
    container_name: openldap
    environment:
      LDAP_ORGANISATION: "ExampleOrg"
      LDAP_DOMAIN: "example.org"
      LDAP_ADMIN_PASSWORD: "admin"
      LDAP_CONFIG_PASSWORD: "config"
      LDAP_RFC2307BIS_SCHEMA: "true"
    ports:
      - "389:389"
      - "636:636"
    volumes:
      - ./ldif:/container/service/slapd/assets/config/bootstrap/ldif:ro
```

- **All LDIF files in `./ldif/`** will be loaded at container startup, in filename order.
- **To control load order**, prefix filenames with numbers (e.g., `10-...`, `20-...`).
- **To add new schemas or data**, simply drop new LDIF files into the directory—no need to edit your compose file.

---

### Alternative: Map Individual Files

You can still map individual LDIF files if you prefer, but using a directory is more scalable and maintainable.

--- 