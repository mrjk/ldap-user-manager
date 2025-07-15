<?php
set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";
include_once "access_functions.inc.php";
include_once "organization_functions.inc.php";

// Access control: only admins, maintainers, or org managers for this org
if (!(currentUserIsGlobalAdmin() || currentUserIsMaintainer() || currentUserIsOrgManager($orgName))) {
    render_header('Organization User Management');
    echo "<div class='alert alert-danger'>You do not have permission to access this page.";
    render_footer();
    exit;
}

$orgName = isset($_GET['org']) ? $_GET['org'] : '';
$orgs = listOrganizations();
if (!is_array($orgs)) {
    $orgs = [];
}

// Validate orgName
$orgExists = false;
foreach ($orgs as $org) {
    if (strtolower($org['o'][0]) === strtolower($orgName)) {
        $orgExists = true;
        $orgDisplay = $org['o'][0];
        break;
    }
}

render_header('User Management for Organization');
render_submenu();

if (!$orgName || !$orgExists) {
    echo "<div class='alert alert-warning'>Please select a valid organization.</div>";
    echo '<ul>';
    foreach ($orgs as $org) {
        $orgNameVal = isset($org['o'][0]) ? $org['o'][0] : '';
        if ($orgNameVal === '') continue;
        echo '<li><a href="org_users.php?org=' . urlencode($orgNameVal) . '">' . htmlspecialchars($orgNameVal) . '</a></li>';
    }
    echo '</ul>';
    render_footer();
    exit;
}

// Fetch users in the organization
function getUsersInOrg($orgName) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $filter = '(objectClass=inetOrgPerson)';
    $attributes = ['uid', 'cn', 'sn', 'mail'];
    $result = @ldap_search($ldap, $usersDn, $filter, $attributes);
    if (!$result) return [];
    $entries = ldap_get_entries($ldap, $result);
    $users = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $users[] = $entries[$i];
    }
    return $users;
}

// Helper: get DN for a user in an org
function getUserDn($orgName, $uid) {
    global $LDAP;
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    return "uid=$uid,$usersDn";
}

// Helper: get org manager DNs
function getOrgManagerDns($orgName) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $groupDn = "cn=OrgAdmins,o={$orgRDN}," . $LDAP['org_dn'];
    $result = @ldap_read($ldap, $groupDn, '(objectClass=groupOfNames)', ['member']);
    if (!$result) return [];
    $entries = ldap_get_entries($ldap, $result);
    $dns = [];
    if ($entries['count'] > 0 && isset($entries[0]['member'])) {
        for ($i = 0; $i < $entries[0]['member']['count']; $i++) {
            $dns[] = $entries[0]['member'][$i];
        }
    }
    return $dns;
}

// Handle org manager role toggle
if (isset($_GET['toggle_manager']) && isset($_GET['uid'])) {
    $uid = $_GET['uid'];
    $userDn = getUserDn($orgName, $uid);
    $orgManagerDns = getOrgManagerDns($orgName);
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $orgAdminsDn = "cn=OrgAdmins,o={$orgRDN}," . $LDAP['org_dn'];
    // Ensure OrgAdmins group exists
    $orgAdmins_search = @ldap_read($ldap, $orgAdminsDn, '(objectClass=groupOfNames)', ['cn']);
    $orgAdmins_exists = false;
    if ($orgAdmins_search) {
        $orgAdmins_entries = ldap_get_entries($ldap, $orgAdmins_search);
        if ($orgAdmins_entries && $orgAdmins_entries['count'] > 0) {
            $orgAdmins_exists = true;
        }
    }
    if (!$orgAdmins_exists) {
        global $USER_DN;
        $orgAdminsGroup = [
            'objectClass' => ['top', 'groupOfNames'],
            'cn' => 'OrgAdmins',
            'member' => [$USER_DN]
        ];
        $orgAdmins_create = @ldap_add($ldap, $orgAdminsDn, $orgAdminsGroup);
        if (!$orgAdmins_create) {
            $message = 'Failed to create OrgAdmins group for this organization: ' . ldap_error($ldap);
            $message_type = 'danger';
            goto after_toggle_manager;
        }
        // Refresh orgManagerDns after creation
        $orgManagerDns = getOrgManagerDns($orgName);
    }
    try {
        if (in_array($userDn, $orgManagerDns)) {
            removeUserFromOrgManagers($orgName, $userDn);
            $message = 'User removed from Org Manager role.';
            $message_type = 'warning';
        } else {
            addUserToOrgManagers($orgName, $userDn);
            $message = 'User assigned as Org Manager.';
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error updating Org Manager role: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
    after_toggle_manager:
}

// Message handling
$message = '';
$message_type = '';

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
    }
    $uid = trim($_POST['uid']);
    $cn = trim($_POST['cn']);
    $sn = trim($_POST['sn']);
    $mail = trim($_POST['mail']);
    $password = $_POST['password'];
    $passcode = $_POST['passcode'];
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $userDn = "uid=" . ldap_escape($uid, '', LDAP_ESCAPE_DN) . "," . $usersDn;

    // Ensure ou=Users exists
    $ldap = open_ldap_connection();
    $ou_search = @ldap_read($ldap, $usersDn, '(objectClass=organizationalUnit)', ['ou']);
    $ou_exists = false;
    if ($ou_search) {
        $ou_entries = ldap_get_entries($ldap, $ou_search);
        if ($ou_entries && $ou_entries['count'] > 0) {
            $ou_exists = true;
        }
    }
    if (!$ou_exists) {
        $usersOU = [
            'objectClass' => ['top', 'organizationalUnit'],
            'ou' => 'Users'
        ];
        $ou_create = @ldap_add($ldap, $usersDn, $usersOU);
        if (!$ou_create) {
            $message = 'Failed to create Users OU for this organization: ' . ldap_error($ldap);
            $message_type = 'danger';
            // Don't proceed if we can't create the OU
            goto after_add_user;
        }
    }

    // Server-side validation
    if ($uid === '' || $cn === '' || $sn === '' || $mail === '' || $password === '') {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // Check for duplicate uid
        $existingUsers = getUsersInOrg($orgName);
        if (!is_array($existingUsers)) {
            $existingUsers = [];
        }
        $uids = array_map(function($u) { return strtolower($u['uid'][0] ?? ''); }, $existingUsers);
        if (in_array(strtolower($uid), $uids)) {
            $message = 'A user with this username already exists in this organization.';
            $message_type = 'warning';
        } else {
            $entry = [
                'objectClass' => ['inetOrgPerson', 'top'],
                'uid' => $uid,
                'cn' => $cn,
                'sn' => $sn,
                'mail' => $mail,
                'userPassword' => password_hash($password, PASSWORD_DEFAULT), // For demo; use LDAP hash in production
            ];
            if ($passcode !== '') {
                $entry['loginPasscode'] = password_hash($passcode, PASSWORD_DEFAULT); // Store passcode as hash
            }
            try {
                ldap_add($ldap, $userDn, $entry);
                $message = 'User created successfully.';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error creating user: ' . htmlspecialchars($e->getMessage());
                $message_type = 'danger';
            }
        }
    }
    after_add_user:
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $uidToDelete = $_GET['delete_user'];
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $userDn = "uid=" . ldap_escape($uidToDelete, '', LDAP_ESCAPE_DN) . ",$usersDn";
    $ldap = open_ldap_connection();
    try {
        ldap_delete($ldap, $userDn);
        $message = 'User deleted successfully.';
        $message_type = 'warning';
    } catch (Exception $e) {
        $message = 'Error deleting user: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Handle edit user
$editUser = null;
if (isset($_GET['edit_user'])) {
    $uidToEdit = $_GET['edit_user'];
    $existingUsers = getUsersInOrg($orgName);
    if (!is_array($existingUsers)) {
        $existingUsers = [];
    }
    foreach ($existingUsers as $user) {
        if (strtolower($user['uid'][0]) === strtolower($uidToEdit)) {
            $editUser = $user;
            break;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
    }
    $uid = trim($_POST['edit_uid']);
    $cn = trim($_POST['edit_cn']);
    $sn = trim($_POST['edit_sn']);
    $mail = trim($_POST['edit_mail']);
    $password = $_POST['edit_password'];
    $passcode = $_POST['edit_passcode'];
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $userDn = "uid=" . ldap_escape($uid, '', LDAP_ESCAPE_DN) . ",$usersDn";
    $ldap = open_ldap_connection();
    $entry = [
        'cn' => $cn,
        'sn' => $sn,
        'mail' => $mail
    ];
    if ($password !== '') {
        $entry['userPassword'] = password_hash($password, PASSWORD_DEFAULT); // For demo; use LDAP hash in production
    }
    if ($passcode !== '') {
        $entry['loginPasscode'] = password_hash($passcode, PASSWORD_DEFAULT);
    }
    try {
        ldap_modify($ldap, $userDn, $entry);
        $message = 'User updated successfully.';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error updating user: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Handle reset password/passcode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_creds'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
    }
    $uid = trim($_POST['reset_uid']);
    $new_password = $_POST['reset_password'];
    $new_passcode = $_POST['reset_passcode'];
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $userDn = "uid=" . ldap_escape($uid, '', LDAP_ESCAPE_DN) . ",$usersDn";
    $ldap = open_ldap_connection();
    $entry = [];
    if ($new_password !== '') {
        $entry['userPassword'] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    if ($new_passcode !== '') {
        $entry['loginPasscode'] = password_hash($new_passcode, PASSWORD_DEFAULT);
    }
    if (!empty($entry)) {
        try {
            ldap_modify($ldap, $userDn, $entry);
            $message = 'Credentials reset successfully.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error resetting credentials: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    } else {
        $message = 'Please enter a new password and/or passcode.';
        $message_type = 'warning';
    }
}

$users = getUsersInOrg($orgName);
if (!is_array($users)) {
    $users = [];
}
$orgManagerDns = getOrgManagerDns($orgName);
?>
<div class="container">
    <h2>Users in Organization: <?= htmlspecialchars($orgDisplay) ?></h2>
    <a href="organizations.php" class="btn btn-secondary mb-3">&larr; Back to Organizations</a>
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" id="msgbox"> <?= $message ?> </div>
    <?php endif; ?>
    <input class="form-control mb-2" id="user_search_input" type="text" placeholder="Search users..">
    <table class="table table-bordered" id="user_table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Org Manager</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): 
                $isManager = in_array(getUserDn($orgName, $user['uid'][0] ?? ''), $orgManagerDns);
            ?>
                <tr<?= $isManager ? ' class="table-success"' : '' ?>>
                    <td><?= htmlspecialchars($user['uid'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars(($user['cn'][0] ?? '') . ' ' . ($user['sn'][0] ?? '')) ?></td>
                    <td><?= htmlspecialchars($user['mail'][0] ?? '') ?></td>
                    <td>
                        <form method="get" style="display:inline">
                            <input type="hidden" name="org" value="<?= htmlspecialchars($orgName) ?>">
                            <input type="hidden" name="uid" value="<?= htmlspecialchars($user['uid'][0] ?? '') ?>">
                            <input type="hidden" name="toggle_manager" value="1">
                            <input type="checkbox" onchange="this.form.submit()" <?= $isManager ? 'checked' : '' ?> title="Toggle Org Manager role">
                        </form>
                    </td>
                    <td>
                        <a href="?org=<?= urlencode($orgName) ?>&edit_user=<?= urlencode($user['uid'][0]) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <a href="?org=<?= urlencode($orgName) ?>&delete_user=<?= urlencode($user['uid'][0]) ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="btn btn-danger btn-sm">Delete</a>
                        <a href="?org=<?= urlencode($orgName) ?>&reset_user=<?= urlencode($user['uid'][0]) ?>" class="btn btn-warning btn-sm">Reset</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Add User to Organization</h3>
    <form method="post" class="mb-4" id="add_user_form" onsubmit="return validateAddUserForm();">
        <?= csrf_token_field() ?>
        <div class="form-group">
            <label for="uid">Username</label>
            <input type="text" class="form-control" name="uid" id="uid" required>
        </div>
        <div class="form-group">
            <label for="cn">First Name</label>
            <input type="text" class="form-control" name="cn" id="cn" required>
        </div>
        <div class="form-group">
            <label for="sn">Last Name</label>
            <input type="text" class="form-control" name="sn" id="sn" required>
        </div>
        <div class="form-group">
            <label for="mail">Email</label>
            <input type="email" class="form-control" name="mail" id="mail" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" name="password" id="password" required>
        </div>
        <div class="form-group">
            <label for="passcode">Passcode (optional)</label>
            <input type="text" class="form-control" name="passcode" id="passcode">
        </div>
        <button type="submit" name="add_user" class="btn btn-primary" id="add_user_btn">Add User</button>
        <span id="add_user_spinner" style="display:none;"><span class="spinner-border spinner-border-sm"></span> Adding...</span>
    </form>

    <!-- Edit User Modal -->
    <?php if ($editUser): ?>
    <div class="modal show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3); z-index:1050;">
      <div class="modal-dialog">
        <div class="modal-content border-primary">
          <form method="post">
            <?= csrf_token_field() ?>
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">Edit User: <?= htmlspecialchars($editUser['uid'][0]) ?></h5>
              <a href="org_users.php?org=<?= urlencode($orgName) ?>" class="close text-white">&times;</a>
            </div>
            <div class="modal-body">
              <input type="hidden" name="edit_uid" value="<?= htmlspecialchars($editUser['uid'][0]) ?>">
              <div class="form-group">
                <label for="edit_cn">First Name</label>
                <input type="text" class="form-control" name="edit_cn" id="edit_cn" value="<?= htmlspecialchars($editUser['cn'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_sn">Last Name</label>
                <input type="text" class="form-control" name="edit_sn" id="edit_sn" value="<?= htmlspecialchars($editUser['sn'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_mail">Email</label>
                <input type="email" class="form-control" name="edit_mail" id="edit_mail" value="<?= htmlspecialchars($editUser['mail'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_password">Password (leave blank to keep unchanged)</label>
                <input type="password" class="form-control" name="edit_password" id="edit_password">
              </div>
              <div class="form-group">
                <label for="edit_passcode">Passcode (leave blank to keep unchanged)</label>
                <input type="text" class="form-control" name="edit_passcode" id="edit_passcode">
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="save_user" class="btn btn-primary">Save Changes</button>
              <a href="org_users.php?org=<?= urlencode($orgName) ?>" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['reset_user'])): $resetUid = $_GET['reset_user']; ?>
    <div class="modal show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3); z-index:1050;">
      <div class="modal-dialog">
        <div class="modal-content border-warning">
          <form method="post">
            <?= csrf_token_field() ?>
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title">Reset Credentials for <?= htmlspecialchars($resetUid) ?></h5>
              <a href="org_users.php?org=<?= urlencode($orgName) ?>" class="close text-dark">&times;</a>
            </div>
            <div class="modal-body">
              <input type="hidden" name="reset_uid" value="<?= htmlspecialchars($resetUid) ?>">
              <div class="form-group">
                <label for="reset_password">New Password (leave blank to keep unchanged)</label>
                <input type="password" class="form-control" name="reset_password" id="reset_password">
              </div>
              <div class="form-group">
                <label for="reset_passcode">New Passcode (leave blank to keep unchanged)</label>
                <input type="text" class="form-control" name="reset_passcode" id="reset_passcode">
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="reset_creds" class="btn btn-warning">Reset</button>
              <a href="org_users.php?org=<?= urlencode($orgName) ?>" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <p class="text-muted mt-2">(Role management and UI refinements complete.)</p>
</div>
<script>
// Client-side validation for add user form
function validateAddUserForm() {
    var required = ['uid','cn','sn','mail','password'];
    for (var i=0; i<required.length; i++) {
        var el = document.getElementById(required[i]);
        if (!el.value.trim()) {
            alert('Please fill in all required fields.');
            el.focus();
            return false;
        }
    }
    document.getElementById('add_user_btn').disabled = true;
    document.getElementById('add_user_spinner').style.display = '';
    return true;
}
// Search/filter for users
const userSearchInput = document.getElementById('user_search_input');
if (userSearchInput) {
    userSearchInput.addEventListener('keyup', function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll('#user_table tbody tr');
        rows.forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().indexOf(value) > -1 ? '' : 'none';
        });
    });
}
// Auto-dismiss feedback messages after 4 seconds
setTimeout(function() {
    var msg = document.getElementById('msgbox');
    if (msg) { msg.style.display = 'none'; }
}, 4000);
</script>
<?php
render_footer(); 