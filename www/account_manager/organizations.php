<?php
set_include_path( ".:" . __DIR__ . "/../includes/");

include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "module_functions.inc.php";
include_once "access_functions.inc.php";
include_once "organization_functions.inc.php";

// Access control: only admins and maintainers
if (!(currentUserIsGlobalAdmin() || currentUserIsMaintainer())) {
    render_header('Organization Management');
    echo "<div class='alert alert-danger'>You do not have permission to access this page.</div>";
    render_footer();
    exit;
}

render_header('Organization Management');
render_submenu();

// Message handling
$message = '';
$message_type = '';

// List organizations (for duplicate check and display)
$orgs = listOrganizations();
if (!is_array($orgs)) {
    $orgs = [];
}
$orgNames = array_map(function($org) { return strtolower($org['o'][0] ?? ''); }, $orgs);

// Helper: count users in an organization
function countUsersInOrg($orgName) {
    global $LDAP;
    $ldap = open_ldap_connection();
    $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
    $usersDn = "ou=Users,o={$orgRDN}," . $LDAP['org_dn'];
    $filter = '(objectClass=inetOrgPerson)';
    $result = @ldap_search($ldap, $usersDn, $filter, ['uid']);
    if (!$result) return 0;
    $entries = ldap_get_entries($ldap, $result);
    return $entries['count'] ?? 0;
}

// Handle form submission for creating a new organization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_org'])) {
    validate_csrf_token();
    $orgName = trim($_POST['o']);
    $city = trim($_POST['l']);
    $postalCode = trim($_POST['postalCode']);
    $country = trim($_POST['c']);
    $phone = trim($_POST['telephoneNumber']);
    $website = trim($_POST['labeledURI']);
    $email = trim($_POST['mail']);
    $creatorDN = $_POST['creatorDN'] ?? 'cn=admin,dc=example,dc=com';

    // Server-side validation
    if ($orgName === '' || $city === '' || $postalCode === '' || $country === '') {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } elseif (in_array(strtolower($orgName), $orgNames)) {
        $message = 'An organization with this name already exists.';
        $message_type = 'warning';
    } else {
        $orgData = [
            'o' => $orgName,
            'l' => $city,
            'postalCode' => $postalCode,
            'c' => $country,
            'telephoneNumber' => $phone,
            'labeledURI' => $website,
            'mail' => $email,
            'creatorDN' => $creatorDN
        ];
        try {
            createOrganization($orgData);
            $message = 'Organization created successfully.';
            $message_type = 'success';
            // Refresh org list
            $orgs = listOrganizations();
            $orgNames = array_map(function($org) { return strtolower($org['o'][0] ?? ''); }, $orgs);
        } catch (Exception $e) {
            $message = 'Error creating organization: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $orgName = $_GET['delete'];
    try {
        deleteOrganization($orgName);
        $message = 'Organization deleted: ' . htmlspecialchars($orgName);
        $message_type = 'warning';
        // Refresh org list
        $orgs = listOrganizations();
        $orgNames = array_map(function($org) { return strtolower($org['o'][0] ?? ''); }, $orgs);
    } catch (Exception $e) {
        $message = 'Error deleting organization: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Handle edit (update organization)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_org'])) {
    validate_csrf_token();
    $orgName = trim($_POST['edit_o']);
    $city = trim($_POST['edit_l']);
    $postalCode = trim($_POST['edit_postalCode']);
    $country = trim($_POST['edit_c']);
    $phone = trim($_POST['edit_telephoneNumber']);
    $website = trim($_POST['edit_labeledURI']);
    $email = trim($_POST['edit_mail']);
    try {
        setOrganizationStatus($orgName, $_POST['edit_description'] ?? 'enabled');
        // Update other attributes
        global $LDAP;
        $ldap = open_ldap_connection();
        $orgRDN = ldap_escape($orgName, '', LDAP_ESCAPE_DN);
        $orgDN = "o={$orgRDN}," . $LDAP['org_dn'];
        $entry = [
            'l' => $city,
            'postalCode' => $postalCode,
            'c' => $country,
            'telephoneNumber' => $phone,
            'labeledURI' => $website,
            'mail' => $email
        ];
        ldap_modify($ldap, $orgDN, $entry);
        $message = 'Organization updated successfully.';
        $message_type = 'success';
        // Refresh org list
        $orgs = listOrganizations();
        $orgNames = array_map(function($org) { return strtolower($org['o'][0] ?? ''); }, $orgs);
    } catch (Exception $e) {
        $message = 'Error updating organization: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// For edit modal
$editOrg = null;
if (isset($_GET['edit'])) {
    foreach ($orgs as $org) {
        if (strtolower($org['o'][0]) === strtolower($_GET['edit'])) {
            $editOrg = $org;
            break;
        }
    }
}
?>
<div class="container">
    <h2>Organizations</h2>
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"> <?= $message ?> </div>
    <?php endif; ?>
    <input class="form-control mb-2" id="org_search_input" type="text" placeholder="Search organizations..">
    <table class="table table-bordered" id="org_table">
        <thead>
            <tr>
                <th>Name</th>
                <th>City</th>
                <th>Postal Code</th>
                <th>Country</th>
                <th>Phone</th>
                <th>Website</th>
                <th>Email</th>
                <th>Status</th>
                <th>Users</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orgs as $org): ?>
                <tr>
                    <td><?= htmlspecialchars($org['o'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['l'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['postalCode'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['c'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['telephoneNumber'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['labeledURI'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['mail'][0] ?? '') ?></td>
                    <td><?= htmlspecialchars($org['description'][0] ?? '') ?></td>
                    <td><?= countUsersInOrg($org['o'][0] ?? '') ?></td>
                    <td>
                        <a href="?edit=<?= urlencode($org['o'][0]) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <a href="?delete=<?= urlencode($org['o'][0]) ?>" onclick="return confirm('Are you sure you want to delete this organization?');" class="btn btn-danger btn-sm">Delete</a>
                        <a href="org_users.php?org=<?= urlencode($org['o'][0]) ?>" class="btn btn-primary btn-sm">Manage Users</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Create New Organization</h3>
    <form method="post" id="create_org_form" onsubmit="return validateOrgForm();">
        <?= csrf_token_field() ?>
        <div class="form-group">
            <label for="o">Name</label>
            <input type="text" class="form-control" name="o" id="o" required>
        </div>
        <div class="form-group">
            <label for="l">City</label>
            <input type="text" class="form-control" name="l" id="l" required>
        </div>
        <div class="form-group">
            <label for="postalCode">Postal Code</label>
            <input type="text" class="form-control" name="postalCode" id="postalCode" required>
        </div>
        <div class="form-group">
            <label for="c">Country</label>
            <input type="text" class="form-control" name="c" id="c" required>
        </div>
        <div class="form-group">
            <label for="telephoneNumber">Phone</label>
            <input type="text" class="form-control" name="telephoneNumber" id="telephoneNumber">
        </div>
        <div class="form-group">
            <label for="labeledURI">Website</label>
            <input type="url" class="form-control" name="labeledURI" id="labeledURI">
        </div>
        <div class="form-group">
            <label for="mail">Email</label>
            <input type="email" class="form-control" name="mail" id="mail">
        </div>
        <!-- In a real app, set creatorDN from the logged-in user -->
        <input type="hidden" name="creatorDN" value="cn=admin,dc=example,dc=com">
        <button type="submit" name="create_org" class="btn btn-primary">Create Organization</button>
    </form>

    <!-- Edit Organization Modal -->
    <?php if ($editOrg): ?>
    <div class="modal show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3);">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            <?= csrf_token_field() ?>
            <div class="modal-header">
              <h5 class="modal-title">Edit Organization: <?= htmlspecialchars($editOrg['o'][0]) ?></h5>
              <a href="organizations.php" class="close">&times;</a>
            </div>
            <div class="modal-body">
              <input type="hidden" name="edit_o" value="<?= htmlspecialchars($editOrg['o'][0]) ?>">
              <div class="form-group">
                <label for="edit_l">City</label>
                <input type="text" class="form-control" name="edit_l" id="edit_l" value="<?= htmlspecialchars($editOrg['l'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_postalCode">Postal Code</label>
                <input type="text" class="form-control" name="edit_postalCode" id="edit_postalCode" value="<?= htmlspecialchars($editOrg['postalCode'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_c">Country</label>
                <input type="text" class="form-control" name="edit_c" id="edit_c" value="<?= htmlspecialchars($editOrg['c'][0] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label for="edit_telephoneNumber">Phone</label>
                <input type="text" class="form-control" name="edit_telephoneNumber" id="edit_telephoneNumber" value="<?= htmlspecialchars($editOrg['telephoneNumber'][0] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="edit_labeledURI">Website</label>
                <input type="url" class="form-control" name="edit_labeledURI" id="edit_labeledURI" value="<?= htmlspecialchars($editOrg['labeledURI'][0] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="edit_mail">Email</label>
                <input type="email" class="form-control" name="edit_mail" id="edit_mail" value="<?= htmlspecialchars($editOrg['mail'][0] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="edit_description">Status</label>
                <input type="text" class="form-control" name="edit_description" id="edit_description" value="<?= htmlspecialchars($editOrg['description'][0] ?? '') ?>">
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" name="edit_org" class="btn btn-primary">Save Changes</button>
              <a href="organizations.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
</div>
<script>
// Client-side validation for create org form
function validateOrgForm() {
    var required = ['o','l','postalCode','c'];
    for (var i=0; i<required.length; i++) {
        var el = document.getElementById(required[i]);
        if (!el.value.trim()) {
            alert('Please fill in all required fields.');
            el.focus();
            return false;
        }
    }
    return true;
}
// Search/filter for organizations
const orgSearchInput = document.getElementById('org_search_input');
if (orgSearchInput) {
    orgSearchInput.addEventListener('keyup', function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll('#org_table tbody tr');
        rows.forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().indexOf(value) > -1 ? '' : 'none';
        });
    });
}
</script>
<?php
render_footer(); 