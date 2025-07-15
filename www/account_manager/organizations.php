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
        $message = 'Please fill in all required fields (Name, City, Postal Code, Country).';
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
            list($success, $msg) = createOrganization($orgData);
            if ($success) {
                $message = $msg;
                $message_type = 'success';
                // Refresh org list
                $orgs = listOrganizations();
                $orgNames = array_map(function($org) { return strtolower($org['o'][0] ?? ''); }, $orgs);
            } else {
                $message = 'Error creating organization: ' . htmlspecialchars($msg);
                $message_type = 'danger';
            }
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
            <select class="form-control" name="c" id="c" required style="width:100%">
                <option value="">Select a country...</option>
                <option value="AF">Afghanistan</option>
                <option value="AL">Albania</option>
                <option value="DZ">Algeria</option>
                <option value="AS">American Samoa</option>
                <option value="AD">Andorra</option>
                <option value="AO">Angola</option>
                <option value="AI">Anguilla</option>
                <option value="AQ">Antarctica</option>
                <option value="AG">Antigua and Barbuda</option>
                <option value="AR">Argentina</option>
                <option value="AM">Armenia</option>
                <option value="AW">Aruba</option>
                <option value="AU">Australia</option>
                <option value="AT">Austria</option>
                <option value="AZ">Azerbaijan</option>
                <option value="BS">Bahamas</option>
                <option value="BH">Bahrain</option>
                <option value="BD">Bangladesh</option>
                <option value="BB">Barbados</option>
                <option value="BY">Belarus</option>
                <option value="BE">Belgium</option>
                <option value="BZ">Belize</option>
                <option value="BJ">Benin</option>
                <option value="BM">Bermuda</option>
                <option value="BT">Bhutan</option>
                <option value="BO">Bolivia</option>
                <option value="BQ">Bonaire, Sint Eustatius and Saba</option>
                <option value="BA">Bosnia and Herzegovina</option>
                <option value="BW">Botswana</option>
                <option value="BV">Bouvet Island</option>
                <option value="BR">Brazil</option>
                <option value="IO">British Indian Ocean Territory</option>
                <option value="BN">Brunei Darussalam</option>
                <option value="BG">Bulgaria</option>
                <option value="BF">Burkina Faso</option>
                <option value="BI">Burundi</option>
                <option value="CV">Cabo Verde</option>
                <option value="KH">Cambodia</option>
                <option value="CM">Cameroon</option>
                <option value="CA">Canada</option>
                <option value="KY">Cayman Islands</option>
                <option value="CF">Central African Republic</option>
                <option value="TD">Chad</option>
                <option value="CL">Chile</option>
                <option value="CN">China</option>
                <option value="CX">Christmas Island</option>
                <option value="CC">Cocos (Keeling) Islands</option>
                <option value="CO">Colombia</option>
                <option value="KM">Comoros</option>
                <option value="CG">Congo</option>
                <option value="CD">Congo, Democratic Republic of the</option>
                <option value="CK">Cook Islands</option>
                <option value="CR">Costa Rica</option>
                <option value="CI">Côte d'Ivoire</option>
                <option value="HR">Croatia</option>
                <option value="CU">Cuba</option>
                <option value="CW">Curaçao</option>
                <option value="CY">Cyprus</option>
                <option value="CZ">Czechia</option>
                <option value="DK">Denmark</option>
                <option value="DJ">Djibouti</option>
                <option value="DM">Dominica</option>
                <option value="DO">Dominican Republic</option>
                <option value="EC">Ecuador</option>
                <option value="EG">Egypt</option>
                <option value="SV">El Salvador</option>
                <option value="GQ">Equatorial Guinea</option>
                <option value="ER">Eritrea</option>
                <option value="EE">Estonia</option>
                <option value="SZ">Eswatini</option>
                <option value="ET">Ethiopia</option>
                <option value="FK">Falkland Islands (Malvinas)</option>
                <option value="FO">Faroe Islands</option>
                <option value="FJ">Fiji</option>
                <option value="FI">Finland</option>
                <option value="FR">France</option>
                <option value="GF">French Guiana</option>
                <option value="PF">French Polynesia</option>
                <option value="TF">French Southern Territories</option>
                <option value="GA">Gabon</option>
                <option value="GM">Gambia</option>
                <option value="GE">Georgia</option>
                <option value="DE">Germany</option>
                <option value="GH">Ghana</option>
                <option value="GI">Gibraltar</option>
                <option value="GR">Greece</option>
                <option value="GL">Greenland</option>
                <option value="GD">Grenada</option>
                <option value="GP">Guadeloupe</option>
                <option value="GU">Guam</option>
                <option value="GT">Guatemala</option>
                <option value="GG">Guernsey</option>
                <option value="GN">Guinea</option>
                <option value="GW">Guinea-Bissau</option>
                <option value="GY">Guyana</option>
                <option value="HT">Haiti</option>
                <option value="HM">Heard Island and McDonald Islands</option>
                <option value="VA">Holy See</option>
                <option value="HN">Honduras</option>
                <option value="HK">Hong Kong</option>
                <option value="HU">Hungary</option>
                <option value="IS">Iceland</option>
                <option value="IN">India</option>
                <option value="ID">Indonesia</option>
                <option value="IR">Iran</option>
                <option value="IQ">Iraq</option>
                <option value="IE">Ireland</option>
                <option value="IM">Isle of Man</option>
                <option value="IL">Israel</option>
                <option value="IT">Italy</option>
                <option value="JM">Jamaica</option>
                <option value="JP">Japan</option>
                <option value="JE">Jersey</option>
                <option value="JO">Jordan</option>
                <option value="KZ">Kazakhstan</option>
                <option value="KE">Kenya</option>
                <option value="KI">Kiribati</option>
                <option value="KP">Korea (Democratic People's Republic of)</option>
                <option value="KR">Korea (Republic of)</option>
                <option value="KW">Kuwait</option>
                <option value="KG">Kyrgyzstan</option>
                <option value="LA">Lao People's Democratic Republic</option>
                <option value="LV">Latvia</option>
                <option value="LB">Lebanon</option>
                <option value="LS">Lesotho</option>
                <option value="LR">Liberia</option>
                <option value="LY">Libya</option>
                <option value="LI">Liechtenstein</option>
                <option value="LT">Lithuania</option>
                <option value="LU">Luxembourg</option>
                <option value="MO">Macao</option>
                <option value="MG">Madagascar</option>
                <option value="MW">Malawi</option>
                <option value="MY">Malaysia</option>
                <option value="MV">Maldives</option>
                <option value="ML">Mali</option>
                <option value="MT">Malta</option>
                <option value="MH">Marshall Islands</option>
                <option value="MQ">Martinique</option>
                <option value="MR">Mauritania</option>
                <option value="MU">Mauritius</option>
                <option value="YT">Mayotte</option>
                <option value="MX">Mexico</option>
                <option value="FM">Micronesia (Federated States of)</option>
                <option value="MD">Moldova</option>
                <option value="MC">Monaco</option>
                <option value="MN">Mongolia</option>
                <option value="ME">Montenegro</option>
                <option value="MS">Montserrat</option>
                <option value="MA">Morocco</option>
                <option value="MZ">Mozambique</option>
                <option value="MM">Myanmar</option>
                <option value="NA">Namibia</option>
                <option value="NR">Nauru</option>
                <option value="NP">Nepal</option>
                <option value="NL">Netherlands</option>
                <option value="NC">New Caledonia</option>
                <option value="NZ">New Zealand</option>
                <option value="NI">Nicaragua</option>
                <option value="NE">Niger</option>
                <option value="NG">Nigeria</option>
                <option value="NU">Niue</option>
                <option value="NF">Norfolk Island</option>
                <option value="MK">North Macedonia</option>
                <option value="MP">Northern Mariana Islands</option>
                <option value="NO">Norway</option>
                <option value="OM">Oman</option>
                <option value="PK">Pakistan</option>
                <option value="PW">Palau</option>
                <option value="PS">Palestine, State of</option>
                <option value="PA">Panama</option>
                <option value="PG">Papua New Guinea</option>
                <option value="PY">Paraguay</option>
                <option value="PE">Peru</option>
                <option value="PH">Philippines</option>
                <option value="PN">Pitcairn</option>
                <option value="PL">Poland</option>
                <option value="PT">Portugal</option>
                <option value="PR">Puerto Rico</option>
                <option value="QA">Qatar</option>
                <option value="RE">Réunion</option>
                <option value="RO">Romania</option>
                <option value="RU">Russian Federation</option>
                <option value="RW">Rwanda</option>
                <option value="BL">Saint Barthélemy</option>
                <option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
                <option value="KN">Saint Kitts and Nevis</option>
                <option value="LC">Saint Lucia</option>
                <option value="MF">Saint Martin (French part)</option>
                <option value="PM">Saint Pierre and Miquelon</option>
                <option value="VC">Saint Vincent and the Grenadines</option>
                <option value="WS">Samoa</option>
                <option value="SM">San Marino</option>
                <option value="ST">Sao Tome and Principe</option>
                <option value="SA">Saudi Arabia</option>
                <option value="SN">Senegal</option>
                <option value="RS">Serbia</option>
                <option value="SC">Seychelles</option>
                <option value="SL">Sierra Leone</option>
                <option value="SG">Singapore</option>
                <option value="SX">Sint Maarten (Dutch part)</option>
                <option value="SK">Slovakia</option>
                <option value="SI">Slovenia</option>
                <option value="SB">Solomon Islands</option>
                <option value="SO">Somalia</option>
                <option value="ZA">South Africa</option>
                <option value="GS">South Georgia and the South Sandwich Islands</option>
                <option value="SS">South Sudan</option>
                <option value="ES">Spain</option>
                <option value="LK">Sri Lanka</option>
                <option value="SD">Sudan</option>
                <option value="SR">Suriname</option>
                <option value="SJ">Svalbard and Jan Mayen</option>
                <option value="SE">Sweden</option>
                <option value="CH">Switzerland</option>
                <option value="SY">Syrian Arab Republic</option>
                <option value="TW">Taiwan</option>
                <option value="TJ">Tajikistan</option>
                <option value="TZ">Tanzania</option>
                <option value="TH">Thailand</option>
                <option value="TL">Timor-Leste</option>
                <option value="TG">Togo</option>
                <option value="TK">Tokelau</option>
                <option value="TO">Tonga</option>
                <option value="TT">Trinidad and Tobago</option>
                <option value="TN">Tunisia</option>
                <option value="TR">Turkey</option>
                <option value="TM">Turkmenistan</option>
                <option value="TC">Turks and Caicos Islands</option>
                <option value="TV">Tuvalu</option>
                <option value="UG">Uganda</option>
                <option value="UA">Ukraine</option>
                <option value="AE">United Arab Emirates</option>
                <option value="GB">United Kingdom</option>
                <option value="UM">United States Minor Outlying Islands</option>
                <option value="US">United States of America</option>
                <option value="UY">Uruguay</option>
                <option value="UZ">Uzbekistan</option>
                <option value="VU">Vanuatu</option>
                <option value="VE">Venezuela</option>
                <option value="VN">Viet Nam</option>
                <option value="VG">Virgin Islands (British)</option>
                <option value="VI">Virgin Islands (U.S.)</option>
                <option value="WF">Wallis and Futuna</option>
                <option value="EH">Western Sahara</option>
                <option value="YE">Yemen</option>
                <option value="ZM">Zambia</option>
                <option value="ZW">Zimbabwe</option>
            </select>
        </div>
        <div class="form-group">
            <label for="telephoneNumber">Phone</label>
            <input type="text" class="form-control" name="telephoneNumber" id="telephoneNumber">
        </div>
        <div class="form-group">
            <label for="labeledURI">Website</label>
            <input type="text" class="form-control" name="labeledURI" id="labeledURI" placeholder="Website (optional)">
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