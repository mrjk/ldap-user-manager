<?php
set_include_path( ".:" . __DIR__ . "/../includes/");
include_once "web_functions.inc.php";
include_once "ldap_functions.inc.php";
include_once "access_functions.inc.php";
set_page_access("admin");

render_header("Role Management");
render_submenu();

$ldap_connection = open_ldap_connection();
$users = ldap_get_user_list($ldap_connection);

$admins_group = $LDAP['admins_group'];
$maintainers_group = $LDAP['maintainers_group'];

function is_group_member($ldap_connection, $group, $uid) {
    return ldap_is_group_member($ldap_connection, $group, $uid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_POST['uid'];
    $role = $_POST['role'];
    $action = $_POST['action'];
    $group = ($role === 'admin') ? $admins_group : $maintainers_group;

    if ($action === 'add') {
        ldap_add_member_to_group($ldap_connection, $group, $uid);
    } elseif ($action === 'remove') {
        ldap_delete_member_from_group($ldap_connection, $group, $uid);
    }
    render_alert_banner("Role updated for $uid.");
}

?>
<div class="container">
    <h2>Manage Admin and Maintainer Roles</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>Admin</th>
                <th>Maintainer</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $uid => $user): ?>
                <tr>
                    <td><?= htmlspecialchars($uid) ?></td>
                    <td>
                        <?php if (is_group_member($ldap_connection, $admins_group, $uid)): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                                <input type="hidden" name="role" value="admin">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="btn btn-danger btn-sm">Remove Admin</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                                <input type="hidden" name="role" value="admin">
                                <input type="hidden" name="action" value="add">
                                <button type="submit" class="btn btn-success btn-sm">Make Admin</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (is_group_member($ldap_connection, $maintainers_group, $uid)): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                                <input type="hidden" name="role" value="maintainer">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="btn btn-danger btn-sm">Remove Maintainer</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="uid" value="<?= htmlspecialchars($uid) ?>">
                                <input type="hidden" name="role" value="maintainer">
                                <input type="hidden" name="action" value="add">
                                <button type="submit" class="btn btn-success btn-sm">Make Maintainer</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
ldap_close($ldap_connection);
render_footer();
?> 