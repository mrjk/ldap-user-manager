<?php
function currentUserIsGlobalAdmin() {
    global $LDAP, $currentUserGroups;
    return isset($LDAP['admins_group']) && in_array($LDAP['admins_group'], is_array($currentUserGroups) ? $currentUserGroups : []);
}

function currentUserIsMaintainer() {
    global $LDAP, $currentUserGroups;
    return isset($LDAP['maintainers_group']) && in_array($LDAP['maintainers_group'], is_array($currentUserGroups) ? $currentUserGroups : []);
}

function currentUserIsOrgManager($orgName) {
    global $LDAP, $USER_DN;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $groupDn = "cn=OrgAdmins,o={$orgRDN}," . $LDAP['org_dn'];
    $result = @ldap_read($ldap, $groupDn, '(objectClass=groupOfNames)', ['member']);
    if (!$result) return false;
    $entries = ldap_get_entries($ldap, $result);
    if ($entries['count'] > 0 && isset($entries[0]['member'])) {
        for ($i = 0; $i < $entries[0]['member']['count']; $i++) {
            if ($entries[0]['member'][$i] === $USER_DN) {
                return true;
            }
        }
    }
    return false;
}
