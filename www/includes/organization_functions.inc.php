<?php

include_once "ldap_functions.inc.php";
include_once "config.inc.php";

function createOrganization($orgData) {
    global $LDAP;
    $ldap = open_ldap_connection();

    // Validate required fields
    $required = ['o', 'l', 'postalCode', 'c', 'telephoneNumber', 'labeledURI', 'mail', 'creatorDN'];
    foreach ($required as $field) {
        if (empty($orgData[$field])) {
            error_log("createOrganization: Missing required field '$field'.");
            return [false, "Missing required field: $field"];
        }
    }

    $orgRDN = ldap_escape($orgData['o'], '', LDAP_ESCAPE_DN);
    $orgDN = "o={$orgRDN}," . $LDAP['org_dn'];

    // Check that parent DN exists
    $parentSearch = ldap_read($ldap, $LDAP['org_dn'], '(objectClass=*)', ['dn']);
    if (!$parentSearch) {
        error_log("createOrganization: Parent DN {$LDAP['org_dn']} does not exist.");
        return [false, "Parent DN does not exist: {$LDAP['org_dn']}"];
    }

    // Organization entry (only allowed attributes)
    $orgEntry = [
        'objectClass' => ['top', 'organization'],
        'o' => $orgData['o'],
        'description' => 'enabled'
    ];
    if (!empty($orgData['telephoneNumber'])) {
        $orgEntry['telephoneNumber'] = $orgData['telephoneNumber'];
    }
    if (!empty($orgData['facsimileTelephoneNumber'])) {
        $orgEntry['facsimileTelephoneNumber'] = $orgData['facsimileTelephoneNumber'];
    }

    $result = ldap_add($ldap, $orgDN, $orgEntry);
    if (!$result) {
        $err = ldap_error($ldap);
        error_log("createOrganization: Failed to add org entry: $err");
        return [false, "Failed to add organization: $err"];
    }

    // Subordinate locality entry for extra attributes
    $hasLocality = !empty($orgData['l']) || !empty($orgData['c']) || !empty($orgData['postalCode']) || !empty($orgData['mail']) || !empty($orgData['labeledURI']);
    if ($hasLocality) {
        $localityEntry = [
            'objectClass' => ['top', 'locality'],
        ];
        if (!empty($orgData['l'])) {
            $localityEntry['l'] = $orgData['l'];
        }
        if (!empty($orgData['c'])) {
            $localityEntry['c'] = $orgData['c'];
        }
        if (!empty($orgData['postalCode'])) {
            $localityEntry['postalCode'] = $orgData['postalCode'];
        }
        if (!empty($orgData['mail'])) {
            $localityEntry['mail'] = $orgData['mail'];
        }
        if (!empty($orgData['labeledURI'])) {
            $localityEntry['labeledURI'] = $orgData['labeledURI'];
        }
        if (!empty($orgData['facsimileTelephoneNumber'])) {
            $localityEntry['facsimileTelephoneNumber'] = $orgData['facsimileTelephoneNumber'];
        }
        // Use city as RDN if available, otherwise use 'locality'
        $localityRDN = !empty($orgData['l']) ? ldap_escape($orgData['l'], '', LDAP_ESCAPE_DN) : 'locality';
        $localityDN = "l={$localityRDN},{$orgDN}";
        $resultLocality = ldap_add($ldap, $localityDN, $localityEntry);
        if (!$resultLocality) {
            $err = ldap_error($ldap);
            error_log("createOrganization: Failed to add locality entry: $err");
            return [false, "Failed to add locality entry: $err"];
        }
    }

    $usersOU = [
        'objectClass' => ['top', 'organizationalUnit'],
        'ou' => 'Users'
    ];

    $orgAdminsGroup = [
        'objectClass' => ['top', 'groupOfNames'],
        'cn' => 'OrgAdmins',
        'member' => [$orgData['creatorDN']]
    ];

    $resultUsers = ldap_add($ldap, "ou=Users,{$orgDN}", $usersOU);
    if (!$resultUsers) {
        $err = ldap_error($ldap);
        error_log("createOrganization: Failed to add Users OU: $err");
        return [false, "Failed to add Users OU: $err"];
    }

    $resultAdmins = ldap_add($ldap, "cn=OrgAdmins,{$orgDN}", $orgAdminsGroup);
    if (!$resultAdmins) {
        $err = ldap_error($ldap);
        error_log("createOrganization: Failed to add OrgAdmins group: $err");
        return [false, "Failed to add OrgAdmins group: $err"];
    }

    return [true, "Organization created successfully."];
}

function deleteOrganization($orgName) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $orgDN = "o={$orgRDN}," . $LDAP['org_dn'];

    // Recursively delete organization subtree
    ldap_delete_recursive($ldap, $orgDN);
}

function setOrganizationStatus($orgName, $status) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $orgDN = "o={$orgRDN}," . $LDAP['org_dn'];

    $entry = ['description' => $status];
    ldap_modify($ldap, $orgDN, $entry);
}

function listOrganizations() {
    global $LDAP;
    $ldap = open_ldap_connection();
    $baseDn = $LDAP['org_dn'];
    $filter = '(objectClass=organization)';
    $attributes = ['o', 'l', 'postalCode', 'c', 'telephoneNumber', 'labeledURI', 'mail', 'description'];
    $result = ldap_search($ldap, $baseDn, $filter, $attributes);
    if (!$result) {
        return [];
    }
    $entries = ldap_get_entries($ldap, $result);
    $orgs = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $orgs[] = $entries[$i];
    }
    return $orgs;
}

function ldap_delete_recursive($ldap, $dn) {
    $search = ldap_list($ldap, $dn, "(objectClass=*)", ['dn']);
    if ($search) {
        $entries = ldap_get_entries($ldap, $search);
        for ($i = 0; $i < $entries['count']; $i++) {
            ldap_delete_recursive($ldap, $entries[$i]['dn']);
        }
    }
    ldap_delete($ldap, $dn);
}

function addUserToOrgManagers($orgName, $userDn) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $groupDn = "cn=OrgAdmins,o={$orgRDN}," . $LDAP['org_dn'];
    $entry = ['member' => $userDn];
    ldap_mod_add($ldap, $groupDn, $entry);
}

function removeUserFromOrgManagers($orgName, $userDn) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $groupDn = "cn=OrgAdmins,o={$orgRDN}," . $LDAP['org_dn'];
    $entry = ['member' => $userDn];
    ldap_mod_del($ldap, $groupDn, $entry);
}

