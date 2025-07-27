<?php

/**
 * imh-php-extension, a Web Interface for cPanel/WHM and CWP
 *
 * Displays installed PHP versions and extensions.
 *
 * Compatible with:
 *   - cPanel/WHM: /usr/local/cpanel/whostmgr/docroot/cgi/imh-php-extension/index.php
 *   - CWP:       /usr/local/cwpsrv/htdocs/resources/admin/modules/imh-php-extension.php
 *
 * Author: 
 * Maintainer: InMotion Hosting
 * Version: 0.0.2
 */


// ==========================
// 1. Environment Detection
// 2. Session & Security
// 3. HTML Header & CSS
// 4. Main Interface
// 5. First Tab
// 7. HTML Footer
// ==========================





// ==========================
// 1. Environment Detection
// ==========================

declare(strict_types=1);
$script_name = "imh-php-extension";

$isCPanelServer = (
    (is_dir('/usr/local/cpanel') || is_dir('/var/cpanel') || is_dir('/etc/cpanel')) && (is_file('/usr/local/cpanel/cpanel') || is_file('/usr/local/cpanel/version'))
);

$isCWPServer = (
    is_dir('/usr/local/cwp')
);

if ($isCPanelServer) {
    if (getenv('REMOTE_USER') !== 'root') exit('Access Denied');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} else { // CWP
    if (!isset($_SESSION['logged']) || $_SESSION['logged'] != 1 || !isset($_SESSION['username']) || $_SESSION['username'] !== 'root') {
        exit('Access Denied');
    }
};










// ==========================
// 2. Session & Security
// ==========================

$CSRF_TOKEN = NULL;

if (!isset($_SESSION['csrf_token'])) {
    $CSRF_TOKEN = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $CSRF_TOKEN;
} else {
    $CSRF_TOKEN = $_SESSION['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        exit("Invalid CSRF token");
    }
}

define('IMH_SAR_CACHE_DIR', '/root/tmp/' . $script_name . '');

if (!is_dir(IMH_SAR_CACHE_DIR)) {
    mkdir(IMH_SAR_CACHE_DIR, 0700, true);
}

// Clear old cache files

$cache_dir = IMH_SAR_CACHE_DIR;
$expire_seconds = 3600; // e.g. 1 hour

foreach (glob("$cache_dir/*.cache") as $file) {
    if (is_file($file) && (time() - filemtime($file) > $expire_seconds)) {
        unlink($file);
    }
}

function imh_safe_cache_filename($tag)
{
    return IMH_SAR_CACHE_DIR . '/sar_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $tag) . '.cache';
}


function imh_cached_shell_exec($tag, $command, $sar_interval)
{
    $cache_file = imh_safe_cache_filename($tag);



    if (file_exists($cache_file)) {
        if (fileowner($cache_file) !== 0) { // 0 = root
            unlink($cache_file);
            // treat as cache miss
        } else {
            $mtime = filemtime($cache_file);
            if (time() - $mtime < $sar_interval) {
                return file_get_contents($cache_file);
            }
        }
    }
    $out = shell_exec($command);
    if (strlen(trim($out))) {
        file_put_contents($cache_file, $out);
    }
    return $out;
}












// ==========================
// 3. HTML Header & CSS
// ==========================

if ($isCPanelServer) {
    require_once('/usr/local/cpanel/php/WHM.php');
    WHM::header($script_name . ' WHM Interface', 0, 0);
} else {
    echo '<div class="panel-body">';
};








// Styles for the tabs and buttons

?>

<style>
    .imh-title {
        margin: 0.25em 0 1em 0;
    }

    .imh-title-img {
        margin-right: 0.5em;
    }

    .tabs-nav {
        display: flex;
        border-bottom: 1px solid #e3e3e3;
        margin-bottom: 2em;
    }

    .tabs-nav button {
        border: none;
        background: #f8f8f8;
        color: #333;
        padding: 12px 28px;
        cursor: pointer;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        font-size: 1em;
        margin-bottom: -1px;
        border-bottom: 2px solid transparent;
        transition: background 0.15s, border-color 0.15s;
    }

    .tabs-nav button.active {
        background: #fff;
        border-bottom: 2px solid rgb(175, 82, 32);
        color: rgb(175, 82, 32);
        font-weight: 600;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .imh-box {
        margin: 2em 0;
        padding: 1em;
        border: 1px solid #ccc;
        border-radius: 8px;
        display: block;
    }

    .imh-box.margin-bottom {
        margin-bottom: 1em;
    }

    .imh-larger-text {
        font-size: 1.5em;
    }

    .imh-spacer {
        margin-top: 2em;
    }

    .imh-footer-box {
        margin: 2em 0 2em 0;
        padding: 1em;
        border: 1px solid #ccc;
        border-radius: 8px;
        display: block;
    }

    .imh-footer-img {
        margin-bottom: 1em;
    }

    .imh-footer-box a {
        color: rgb(175, 82, 32);
    }

    .imh-footer-box a:hover,
    .imh-footer-box a:focus {
        color: rgb(97, 51, 27);
    }

    .imh-cell {
        padding: 0.5em;
        border: 1px solid #ddd;
        text-align: center;
    }

    .imh-left {
        text-align: left;
    }

    .imh-big {
        font-size: 1.2em;
    }

    .imh-bold {
        font-weight: bold;
    }
</style>

<?php





// ==========================
// 4. Main Interface
// ==========================

$img_src = $isCWPServer ? 'design/img/' . $script_name . '.png' : $script_name . '.png';
echo '<h1 class="imh-title"><img src="' . htmlspecialchars($img_src) . '" alt="php-extensions" class="imh-title-img" />PHP Extensions</h1>';







// ==========================
// 5. PHP Extensions Table
// ==========================

echo '<div id="tab-one" class="tab-content active">';

// -- Begin PHP extension table for cPanel/WHM only

if ($isCPanelServer) {
    // 1. Discover all ea-phpXX versions installed via rpm
    $php_versions = [];
    $rpm_qa = shell_exec("rpm -qa");
    if (preg_match_all('/\bea-php([0-9]{2})\b/', $rpm_qa, $matches)) {
        $php_versions = array_unique($matches[1]);
        sort($php_versions, SORT_NUMERIC);
    }

    if (empty($php_versions)) {
        echo "<div class='imh-box' style='color: red'>No EasyApache PHP versions found via RPM.</div>";
    } else {
        // 2. List of extensions to check (same as your CWP list for consistency)
        $extensions = array('php-fpm', 'curl', 'exif', 'imagick', 'fileinfo', 'imap', 'xml', 'xmlrpc', 'bcmath', 'gmp', 'intl', 'mbstring', 'soap', 'sodium', 'iconv', 'zip', 'ioncube');

        // 3. Build table header
        echo "<div class='imh-box imh-box.margin-bottom'><b>Installed PHP versions and extensions:</b><br/><br/>";
        echo '<table border="1" cellpadding="6" style="border-collapse:collapse; background:#fff;">';
        echo '<tr style="background:#f8f8f8;"><th class="imh-cell">Extension</th>';
        foreach ($php_versions as $ver) {
            echo '<th class="imh-cell">php' . htmlspecialchars($ver) . '</th>';
        }
        echo "</tr>";

        // 4. For each extension, each PHP version, check installed via RPM
        foreach ($extensions as $ext) {
            // Special: ioncube column at end (handled separately below)
            if ($ext === 'ioncube') continue;
            echo "<tr><td class='imh-cell imh-left imh-bold'>$ext</td>";
            foreach ($php_versions as $ver) {
                // For php-fpm, the rpm is ea-phpXX-php-fpm
                if ($ext === "php-fpm") {
                    $package = "ea-php{$ver}-php-fpm";
                } elseif ($ext === "imagick") {
                    // Sometimes it's ea-phpXX-php-imagick
                    $package = "ea-php{$ver}-php-imagick";
                } else {
                    // Try ea-phpXX-php-EXT and, if not found, ea-phpXX-EXT
                    $package1 = "ea-php{$ver}-php-$ext";
                    $package2 = "ea-php{$ver}-$ext";
                }
                $found = false;
                if (isset($package)) {
                    // Only one package name
                    $rc = 1;
                    @exec("rpm -q $package", $dummy, $rc);
                    $found = ($rc === 0);
                } else {
                    // Try both
                    $rc1 = $rc2 = 1;
                    @exec("rpm -q $package1", $dummy1, $rc1);
                    @exec("rpm -q $package2", $dummy2, $rc2);
                    $found = ($rc1 === 0 || $rc2 === 0);
                }
                if ($found) {
                    echo "<td class='imh-cell imh-big' style='color:green' title='Installed'>&#10004;</td>";
                } else {
                    echo "<td class='imh-cell imh-big' style='color:#b00' title='Not installed'>&#10008;</td>";
                }
                unset($dummy, $dummy1, $dummy2, $package, $package1, $package2);
            }
            echo "</tr>\n";
        }

        // 5. Ioncube: check via RPM for ea-phpXX-php-ioncube
        echo "<tr><td class='imh-cell imh-left imh-bold'>ioncube</td>";
        foreach ($php_versions as $ver) {
            $rc = 1;
            @exec("rpm -q ea-php{$ver}-php-ioncube", $dummy, $rc);
            if ($rc === 0) {
                echo "<td class='imh-cell imh-big' style='color:green' title='Installed'>&#10004;</td>";
            } else {
                echo "<td class='imh-cell imh-big' style='color:#b00' title='Not installed'>&#10008;</td>";
            }
            unset($dummy);
        }
        echo "</tr>";

        echo "</table></div>";
    }
}















// -- Begin PHP extension table for CWP only

if ($isCWPServer) {

    // 1. Find all PHP installs in /opt/alt/
    $phpInstalls = [];
    foreach (glob('/opt/alt/php*[0-9][0-9]', GLOB_ONLYDIR) as $d) {
        if (preg_match('~/opt/alt/(php(-fpm)?[0-9]{2})$~', $d, $m)) {
            $phpInstalls[] = $m[1];
        }
    }
    sort($phpInstalls);

    if (empty($phpInstalls)) {
        echo "<div class='imh-box' style='color: red'>No PHP installs found in <code>/opt/alt/</code>.</div>";
    } else {
        // 2. List of common extensions to check
        $extensions = array('bcmath', 'curl', 'exif', 'fileinfo', 'gd', 'gmp', 'iconv', 'imagick', 'imap', 'intl', 'mbstring', 'memcache', 'memcached', 'mysqli', 'opcache', 'pdo', 'pdo_mysql', 'redis', 'soap', 'sodium', 'xml', 'xmlrpc', 'zip');

        // 3. Build the table header
        echo "<div class='imh-box imh-box.margin-bottom'><b>Installed PHP versions and extensions:</b><br/><br/>";
        echo '<table border="1" cellpadding="6" style="border-collapse:collapse; background:#fff;">';
        echo '<tr style="background:#f8f8f8;"><th class="imh-cell">Extension</th>';
        foreach ($phpInstalls as $install_id) {
            echo '<th class="imh-cell">' . htmlspecialchars($install_id) . '</th>';
        }
        echo "</tr>";

        // 4. For each extension, each PHP install, check enabled
        foreach ($extensions as $ext) {
            echo "<tr><td class='imh-cell imh-left imh-bold'>$ext</td>";
            foreach ($phpInstalls as $install_id) {
                $php_bin = "/opt/alt/{$install_id}/usr/bin/php";
                if (is_executable($php_bin)) {
                    $output = null;
                    $rc = 0;
                    exec("$php_bin -m 2>/dev/null", $output, $rc);
                    // extension names in $output; case-insensitive
                    if (preg_grep("/^$ext$/i", $output)) {
                        echo "<td class='imh-cell imh-big' style='color:green' title='Enabled'>&#10004;</td>";
                    } else {
                        echo "<td class='imh-cell imh-big' style='color:#b00' title='Not enabled'>&#10008;</td>";
                    }
                } else {
                    echo "<td class='imh-cell imh-big' style='color:#777'>?</td>";
                }
            }
            echo "</tr>\n";
        }

        // 5. Ioncube loader: check via php -v output
        echo "<tr><td class='imh-cell imh-left imh-bold'>ioncube</td>";
        foreach ($phpInstalls as $install_id) {
            $php_bin = "/opt/alt/{$install_id}/usr/bin/php";
            if (is_executable($php_bin)) {
                $output = null;
                $rc = 0;
                exec("$php_bin -v 2>&1", $output, $rc);
                $has_ioncube = false;
                foreach ($output as $line) {
                    if (stripos($line, "ionCube PHP Loader") !== false) {
                        $has_ioncube = true;
                        break;
                    }
                }
                if ($has_ioncube) {
                    echo "<td class='imh-cell imh-big' style='color:green' title='Enabled'>&#10004;</td>";
                } else {
                    echo "<td class='imh-cell imh-big' style='color:#b00' title='Not enabled'>&#10008;</td>";
                }
            } else {
                echo "<td class='imh-cell imh-big' style='color:#777'>?</td>";
            }
        }
        echo "</tr>";

        echo "</table></div>";
    }
}


echo "</div>";













// ==========================
// 6. HTML Footer
// ==========================

echo '<div class="imh-footer-box"><p>Plugin by <a href="https://inmotionhosting.com" target="_blank">InMotion Hosting</a>.</p></div>';




if ($isCPanelServer) {
    WHM::footer();
} else {
    echo '</div>';
};
