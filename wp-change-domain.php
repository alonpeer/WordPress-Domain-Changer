<?php
/**
 * Author: Daniel Doezema
 * Author URI: http://dan.doezema.com
 * Version: 0.2 (Beta)
 * Description: This script was developed to help ease migration of WordPress sites from one domain to another.
 *
 * Copyright (c) 2010, Daniel Doezema
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *   * The names of the contributors and/or copyright holder may not be
 *     used to endorse or promote products derived from this software without
 *     specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL DANIEL DOEZEMA BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright Copyright (c) 2010 Daniel Doezema. (http://dan.doezema.com)
 * @license http://dan.doezema.com/licenses/new-bsd New BSD License
 */

/* == CONFIG ======================================================= */

// Authentication Password
define('DDWPDC_PASSWORD', 'Replace-This-Password');

// Cookie: Name: Authentication
define('DDWPDC_COOKIE_NAME_AUTH', 'DDWPDC_COOKIE_AUTH');

// Cookie: Name: Expiration
define('DDWPDC_COOKIE_NAME_EXPIRE', 'DDWPDC_COOKIE_EXPIRE');

// Cookie: Timeout (Default: 5 minutes)
define('DDWPDC_COOKIE_LIFETIME', 60 * 5);

/* == NAMESPACE CLASS ============================================== */

class DDWordPressDomainChanger {

    /**
     * Actions that occurred during request.
     *
     * @var array
     */
    public $actions = array();

    /**
     * Notices that occurred during request.
     *
     * @var array
     */
    public $notices = array();

    /**
     * Errors that occurred during request.
     *
     * @var array
     */
    public $errors = array();

    /**
     * File contents of the wp-config.php file.
     *
     * @var string
     */
    private $config = '';

    /**
     * Class Constructor
     *
     * @return void
     */
    public function __construct() {
        $this->loadConfigFile();
    }

    /**
     * Gets a constant's value from the wp-config.php file (if loaded).
     *
     * @return mixed; false if not found.
     */
    public function getConfigConstant($constant) {
        if($this->isConfigLoaded()) {
            preg_match("!define\('".$constant."',[^']*'(.+?)'\);!", $this->config, $matches);
            return (isset($matches[1])) ? $matches[1] : false;
        }
        return false;
    }
    
    /**
     * Gets $table_prefix value from the wp-config.php file (if loaded).
     *
     * @return string;
     */
    public function getConfigTablePrefix() {
        if($this->isConfigLoaded()) {
            preg_match("!table_prefix[^=]*=[^']*'(.+?)';!", $this->config, $matches);
            return (isset($matches[1])) ? $matches[1] : '';
        }
        return '';
    }

    /**
     * Gets the best guess of the "New Domain" based on this files location at runtime.
     *
     * @return string;
     */
    public function getNewDomain() {
        $new_domain = str_replace('http://','', $_SERVER['SERVER_NAME']);
        if(isset($_SERVER['SERVER_PORT']) && strlen($_SERVER['SERVER_PORT']) > 0 && $_SERVER['SERVER_PORT'] != 80) {
            $new_domain .= ':'.$_SERVER['SERVER_PORT'];
        }
        return $new_domain;
    }

    /**
     * Gets the "siteurl" WordPress option (if possible).
     *
     * @return mixed; false if not found.
     */
    public function getOldDomain() {
        if($this->isConfigLoaded()) {
            $mysqli = @new mysqli($this->getConfigConstant('DB_HOST'), $this->getConfigConstant('DB_USER'), $this->getConfigConstant('DB_PASSWORD'), $this->getConfigConstant('DB_NAME'));
            if(mysqli_connect_error()) {
                $this->notices[] = 'Unable to connect to this server\'s database using the settings from wp-config.php; check that it\'s properly configured.';
            } else {
                $result = $mysqli->query('SELECT * FROM '.$this->getConfigTablePrefix().'options WHERE option_name="siteurl";');
                if(is_object($result) && ($result->num_rows > 0)) {
                    $row = $result->fetch_assoc();
                    return str_replace('http://','', $row['option_value']);
                } else {
                    $this->error[] = 'The WordPress option_name "siteurl" does not exist in the "'.$this->getConfigTablePrefix().'options" table!';
                }
            }
        }
        return false;
    }

    /**
     * Returns true if the wp-config.php file was loaded successfully.
     *
     * @return bool;
     */
    public function isConfigLoaded() {
        return (strlen($this->config) > 0);
    }

    /**
     * Attempts to load the wp-config.php file into $this->config
     *
     * @return void;
     */
    private function loadConfigFile() {
        $this->config = file_get_contents(dirname(__FILE__).'/wp-config.php');
        if(!$this->isConfigLoaded()) {
            $this->notices[] = 'Unable to find "wp-config.php" ... Make sure the '.basename(__FILE__).' file is in the root WordPress directory.';
        } else {
            $this->actions[] = 'wp-config.php file successfully loaded.';
        }
    }
}

/* == START PROCEDURAL CODE ============================================== */

// Config/Safety Check
if(DDWPDC_PASSWORD == 'Replace-This-Password') {
    die('This script will remain disabled until the default password is changed.');
}

// Password Check -> Set Cookie -> Redirect
if(isset($_POST['auth_password'])) {
    /**
     * Try and obstruct brute force attacks by making each login attempt 
     * take 5 seconds.This is total security-through-obscurity and can be 
     * worked around fairly easily, it's just one more step.
     *    
     * MAKE SURE you remove this script after the domain change is complete.
     */
    sleep(5);
    if(md5($_POST['auth_password']) == md5(DDWPDC_PASSWORD)) {
        $expire = time() + DDWPDC_COOKIE_LIFETIME;
        setcookie(DDWPDC_COOKIE_NAME_AUTH, md5(DDWPDC_PASSWORD), $expire);
        setcookie(DDWPDC_COOKIE_NAME_EXPIRE, $expire, $expire);
        die('<a href="'.basename(__FILE__).'">Click Here</a><script type="text/javascript">window.location = "'.basename(__FILE__).'";</script>');
    }
}

// Authenticate
$is_authenticated = (isset($_COOKIE[DDWPDC_COOKIE_NAME_AUTH]) && ($_COOKIE[DDWPDC_COOKIE_NAME_AUTH] == md5(DDWPDC_PASSWORD))) ? true : false;

// Check if user is authenticated
if($is_authenticated) {
    $DDWPDC = new DDWordPressDomainChanger();
    try {
        // Start change process
        if(isset($_POST) && is_array($_POST) && (count($_POST) > 0)) {
            // Clean up data & check for empty fields
            $POST = array();
            foreach($_POST as $key => $value) {
                $value = trim($value);
                if(strlen($value) <= 0) throw new Exception('One or more of the fields was blank; all are required.');
                if(get_magic_quotes_gpc()) $value = stripslashes($value);
                $POST[$key] = $value;
            }

            // Check for "http://" in the new domain
            if(stripos($POST['new_domain'], 'http://') !== false) {
                // Let them correct this instead of assuming it's correct and removing the "http://".
                throw new Exception('The "New Domain" field must not contain "http://"');
            }

            // DB Connection
            $mysqli = @new mysqli($POST['host'], $POST['username'], $POST['password'], $POST['database']);
            if(mysqli_connect_error()) {
                throw new Exception('Unable to create database connection; most likely due to incorrect connection settings.');
            }

            // Escape for Database
            $data = array();
            foreach($_POST as $key => $value) {
                $data[$key] = $mysqli->escape_string($value);
            }
            
            // Update Options
            $result = $mysqli->query('UPDATE '.$data['prefix'].'options SET option_value = REPLACE(option_value,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
            if(!$result) {
                throw new Exception($mysqli->error);
            } else {
                $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'options.option_value';
            }

            // Update Post Content
            $result = $mysqli->query('UPDATE '.$data['prefix'].'posts SET post_content = REPLACE(post_content,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
            if(!$result) {
                throw new Exception($mysqli->error);
            } else {
                $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'posts.post_content';
            }

            // Update Post GUID
            $result = $mysqli->query('UPDATE '.$data['prefix'].'posts SET guid = REPLACE(guid,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
            if(!$result) {
                throw new Exception($mysqli->error);
            } else {
                $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'posts.guid';
            }

            // Update "upload_path"
            $upload_dir = dirname(__FILE__).'/wp-content/uploads';
            $result = $mysqli->query('UPDATE '.$data['prefix'].'options SET option_value = "'.$upload_dir.'" WHERE option_name="upload_path";');
            if(!$result) {
                throw new Exception($mysqli->error);
            } else {
                $DDWPDC->actions[] = 'Option "upload_path" has been changed to "'.$upload_dir.'"';
            }

            // Delete "recently_edited" option. (Will get regerated by WordPress)
            $result = $mysqli->query('DELETE FROM '.$data['prefix'].'options WHERE option_name="recently_edited";');
            if(!$result) {
                throw new Exception($mysqli->error);
            } else {
                $DDWPDC->actions[] = 'Option "recently_edited" has been deleted -> Will be regenerated by WordPress.';
            }
            
            // Updates for MU websites
            if (isset($data['multisite']) && $data['multisite'] == '1') {
                $i = 2;
                do {
                    // Check if there exists another website
                    $result = mysqli_fetch_array($mysqli->query('SHOW TABLES LIKE "'.$data['prefix'].$i.'_options"'));
                    if (null !== $result && count($result) > 0) {
                        $prefix = $data['prefix'].$i.'_';
                        // Update Options
                        $result = $mysqli->query('UPDATE '.$prefix.'options SET option_value = REPLACE(option_value,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                        if(!$result) {
                            throw new Exception($mysqli->error);
                        } else {
                            $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$prefix.'options.option_value';
                        }
                        
                        // Update Post Meta
                        $result = $mysqli->query('UPDATE '.$prefix.'postmeta SET meta_value = REPLACE(meta_value,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                        if(!$result) {
                            throw new Exception($mysqli->error);
                        } else {
                            $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$prefix.'postmeta.meta_value';
                        }
                        
                        // Update Posts GUID
                        $result = $mysqli->query('UPDATE '.$prefix.'posts SET guid = REPLACE(guid,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                        if(!$result) {
                            throw new Exception($mysqli->error);
                        } else {
                            $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$prefix.'posts.guid';
                        }
                    }
                    ++$i;
                } while (null !== $result && count($result) > 0);
                
                // Update Blogs Domain
                $result = $mysqli->query('UPDATE '.$data['prefix'].'blogs SET domain = REPLACE(domain,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                if(!$result) {
                    throw new Exception($mysqli->error);
                } else {
                    $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'blogs.domain';
                }
                
                // Update Site Domain
                $result = $mysqli->query('UPDATE '.$data['prefix'].'site SET domain = REPLACE(domain,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                if(!$result) {
                    throw new Exception($mysqli->error);
                } else {
                    $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'site.domain';
                }
                
                // Update Site Meta
                $result = $mysqli->query('UPDATE '.$data['prefix'].'sitemeta SET meta_value = REPLACE(meta_value,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                if(!$result) {
                    throw new Exception($mysqli->error);
                } else {
                    $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'sitemeta.meta_value';
                }
                
                // Update User Meta
                $result = $mysqli->query('UPDATE '.$data['prefix'].'usermeta SET meta_value = REPLACE(meta_value,"'.$data['old_domain'].'","'.$data['new_domain'].'");');
                if(!$result) {
                    throw new Exception($mysqli->error);
                } else {
                    $DDWPDC->actions[] = 'Old domain ('.$data['old_domain'].') replaced with new domain ('.$data['new_domain'].') in '.$data['prefix'].'usermeta.meta_value';
                }
            }

        }
    } catch (Exception $exception) {
        $DDWPDC->errors[] = $exception->getMessage();
    }
}
?>
<html>
    <head>
        <title>WordPress Domain Changer by Daniel Doezema</title>
        <script type="text/javascript" language="Javascript">
            window.onload = function() {
                if(document.getElementById('seconds')) {
                    window.setInterval(function() {
                        var seconds_elem = document.getElementById('seconds');
                        var bar_elem     = document.getElementById('bar');
                        var seconds      = parseInt(seconds_elem.innerHTML);
                        var percentage   = Math.round(seconds / <?= DDWPDC_COOKIE_LIFETIME + 5; ?> * 100);
                        var bar_color    = '#00FF19';
                        if(percentage < 25) {
                            bar_color = 'red';
                        } else if (percentage < 75) {
                            bar_color = 'yellow';
                        }
                        if(seconds <= 0) window.location.reload();
                        bar_elem.style.width = percentage + '%';
                        bar_elem.style.backgroundColor = bar_color;
                        seconds_elem.innerHTML = --seconds;
                    }, 1000);
                }
            }
        </script>
        <style type="text/css">
            body {font:14px Tahoma, Arial;}
            div.clear {clear:both;}
            h1 {padding:0; margin:0;}
            h2, h3 {padding:0; margin:0 0 15px 0;}
            form { display:block; padding:10px; margin-top:15px; background-color:#FCFCFC; border:1px solid gray;}
            form label {font-weight:bold;}
            form div {margin:0 15px 15px 0;}
            form div input[type="text"] {width:80%;}
            #left {width:35%;float:left;}
            #right {margin-top:5px;float:right; width:63%; text-align:left;}
            div.log {padding:5px 10px; margin:10px 0;}
            div.error { background-color:#FFF8F8; border:1px solid red;}
            div.notice { background-color:#FFFEF2; border:1px solid #FDC200;}
            div.action { background-color:#F5FFF6; border:1px solid #01BE14;}
            #timeout {padding:5px 10px 10px 10px; background-color:black; color:white; font-weight:bold;position:absolute;top:0;right:10px;}
            #bar {height:10px;margin:5px 0 0 0;}
        </style>
    </head>
    <body>
        <h1>WordPress Domain Changer</h1>
        <span>By <a href="http://dan.doezema.com" target="_blank">Daniel Doezema</a></span>
        <div class="body">
            <?php if($is_authenticated): ?>
                <div id="timeout">
                    <div>You have <span id="seconds"><?= ((int) $_COOKIE[DDWPDC_COOKIE_NAME_EXPIRE] + 5) - time();?></span> Seconds left in this session.</div>
                    <div id="bar"></div>
                </div>
                <div class="clear"></div>
                <div id="left">
                    <form method="post" action="<?= basename(__FILE__);?>">
                        <h3>Database Connection Settings</h3>
                        <blockquote>
                            <?php
                            // Try to Auto-Detect Settings from wp-config.php file and pre-populate fields.
                            if($DDWPDC->isConfigLoaded()) $DDWPDC->actions[] = 'Attempting to auto-detect form field values.';
                            ?>
                            <label for="host">Host</label>
                            <div><input type="text" id="host" name="host" value="<?= $DDWPDC->getConfigConstant('DB_HOST'); ?>" /></div>

                            <label for="username">User</label>
                            <div><input type="text" id="username" name="username" value="<?= $DDWPDC->getConfigConstant('DB_USER'); ?>" /></div>

                            <label for="password">Password</label>
                            <div><input type="text" id="password" name="password" value="<?= $DDWPDC->getConfigConstant('DB_PASSWORD'); ?>" /></div>

                            <label for="database">Database Name</label>
                            <div><input type="text" id="database" name="database" value="<?= $DDWPDC->getConfigConstant('DB_NAME'); ?>" /></div>

                            <label for="prefix">Table Prefix</label>
                            <div><input type="text" id="prefix" name="prefix" value="<?= $DDWPDC->getConfigTablePrefix(); ?>" /></div>
                        </blockquote>

                        <label for="old_domain">Old Domain</label>
                        <div>http://<input type="text" id="old_domain" name="old_domain" value="<?= $DDWPDC->getOldDomain(); ?>" /></div>

                        <label for="new_domain">New Domain</label>
                        <div>http://<input type="text" id="new_domain" name="new_domain" value="<?= $DDWPDC->getNewDomain(); ?>" /></div>
                        
                        <div><input type="checkbox" id="multisite" name="multisite" value="1" /><label for="multisite">Is this a Multi-Site?</label></div>

                        <input type="submit" id="submit_button" name="submit_button" value="Change Domain!" />
                    </form>
                </div>
                <div id="right">
                    <?php if(count($DDWPDC->errors) > 0): foreach($DDWPDC->errors as $error):?>
                        <div class="log error"><strong>Error:</strong> <?=$error;?></div>
                    <?php endforeach; endif; ?>

                    <?php if(count($DDWPDC->notices) > 0): foreach($DDWPDC->notices as $notice):?>
                        <div class="log notice"><strong>Notice:</strong> <?=$notice;?></div>
                    <?php endforeach; endif; ?>

                    <?php if(count($DDWPDC->actions) > 0): foreach($DDWPDC->actions as $action):?>
                        <div class="log action"><strong>Action: </strong><?=$action;?></div>
                    <?php endforeach; endif; ?>
                </div>
            <?php else: ?>
                <?if(isset($_POST['auth_password'])):?>
                    <div class="log error"><strong>Error:</strong> Incorrect password, please try again.</div>
                <?endif;?>
                <form id="login" name="login" method="post" action="<?= basename(__FILE__);?>">
                    <h3>Authenticate</h3>
                    <label for="auth_password">Password</label>
                    <input type="password" id="auth_password" name="auth_password" value="" />
                    <input type="submit" id="submit_button" name="submit_button" value="Submit!" />
                </form>
            <?php endif; ?>
        </div>
    </body>
</html>