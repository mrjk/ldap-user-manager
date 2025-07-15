<?php

include_once "ldap_functions.inc.php";
include_once "config.inc.php";

function createOrganization($orgData) {
    global $LDAP;
    $ldap = open_ldap_connection();

    $orgRDN = ldap_escape($orgData['o'], '', LDAP_ESCAPE_DN);
    $orgDN = "o={$orgRDN}," . $LDAP['org_dn'];

    $orgEntry = [
        'objectClass' => ['top', 'organization', 'locality'],
        'o' => $orgData['o'],
        'l' => $orgData['l'],
        'postalCode' => $orgData['postalCode'],
        'c' => $orgData['c'],
        'telephoneNumber' => $orgData['telephoneNumber'],
        'labeledURI' => $orgData['labeledURI'],
        'mail' => $orgData['mail'],
        'description' => 'enabled'
    ];

    $usersOU = [
        'objectClass' => ['top', 'organizationalUnit'],
        'ou' => 'Users'
    ];

    $orgAdminsGroup = [
        'objectClass' => ['top', 'groupOfNames'],
        'cn' => 'OrgAdmins',
        'member' => [$orgData['creatorDN']]
    ];

    ldap_add($ldap, $orgDN, $orgEntry);
    ldap_add($ldap, "ou=Users,{$orgDN}", $usersOU);
    ldap_add($ldap, "cn=OrgAdmins,{$orgDN}", $orgAdminsGroup);
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

