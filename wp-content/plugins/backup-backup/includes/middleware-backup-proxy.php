<?php

  // Namespace
  namespace BMI\Plugin\Heart;

  // Allow only POST requests
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
  }

  function isFunctionEnabled($func) {
    $disabled = explode(',', ini_get('disable_functions'));
    $isDisabled = in_array($func, $disabled);
    if (!$isDisabled && function_exists($func)) return true;
    else return false;
  }

  // Make sure getallheaders will work
  if (!function_exists('__getallheaders')) {
    function __getallheaders() {
      $headers = [];
      foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
      }
      return $headers;
    }
  }
  
  function bmiQuickEnd($checkFailed = -1) {
    error_log('Backup failed with end code due to parameter checks: ' . $checkFailed);
    die("Incorrect parameters (" . $checkFailed . ")." );
  }
  
  // Filter and prevent PHP filter injection
  function filterChainFix($content) {
    
    // Make sure it exist and is string
    if (!is_string($content)) bmiQuickEnd(1);
    
    // Check if it's not larger than max allowed path length (default systems)
    if (strlen($content) > 256) bmiQuickEnd(2);
    
    // Check if the path does not contain "php:"
    if (strpos($content, "php:")) bmiQuickEnd(3);
    
    // Check if the path contain "|", it's not possible to use this character with our backups paths
    if (strpos($content, "|")) bmiQuickEnd(4);
    
    // Check if the directory/file exist otherwise fail
    if (!(is_dir($content) || file_exists($content))) bmiQuickEnd(5);
    
    // Return correct content
    return $content;
    
  }
  
  if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
      $needle_len = strlen($needle);
      return ($needle_len === 0 || 0 === substr_compare($haystack, $needle, -$needle_len));
    }
  }

  // Get fields from header
  if (isFunctionEnabled('getallheaders')) {
    $fields = getallheaders();
  }

  // Check headers
  if (!isset($fields['Content-Content']) && !isset($fields['content-content'])) {
    $fields = __getallheaders();
  }

  // Lowercase
  foreach ($fields as $key => $value) {
    $buffer = $value;
    unset($fields[$key]);
    $fields[strtolower($key)] = $value;
  }

  // Perform path and variable sanitization
  $fields['content-identy'] = rawurlencode($fields['content-identy']);
  $fields['content-manifest'] = 'file://' . realpath(dirname($fields['content-manifest'])) . '/bmi_backup_manifest.json';
  $fields['content-bmitmp'] = 'file://' . realpath(dirname($fields['content-bmitmp'])) . '/tmp';
  $fields['content-backups'] = 'file://' . realpath(dirname($fields['content-backups'])) . '/backups';
  $fields['content-name'] = $fields['content-name'];
  
  $fields['content-configdir'] = 'file://' . realpath($fields['content-configdir']);
  $fields['content-dir'] = 'file://' . realpath(dirname($fields['content-dir'])) . '/backup-backup' . '/';
  $fields['content-abs'] = 'file://' . realpath($fields['content-abs']) . '/';

  $fields['content-content'] = 'file://' . realpath($fields['content-content']) . '/';
  $fields['content-safelimit'] = intval($fields['content-safelimit']);
  $fields['content-start'] = floatval($fields['content-start']);
  $fields['content-dblast'] = intval($fields['content-dblast']);
  $fields['content-dbit'] = intval($fields['content-dbit']);
  

  
  // content-identy, content-manifest, content-bmitmp, content-backups, content-configdir
  if (preg_match('/BMI\-\d{8}/', $fields['content-identy']) === false || strlen($fields['content-identy']) != 12) bmiQuickEnd(6);
  if (dirname($fields['content-manifest']) != $fields['content-bmitmp']) bmiQuickEnd(7);
  if (dirname($fields['content-backups']) != $fields['content-configdir']) bmiQuickEnd(8);
  
  // content-bmitmp, content-manifest, content-backups
  $tmpF = dirname($fields['content-manifest']); $tmpS = dirname($fields['content-bmitmp']); $tmpB = dirname($fields['content-backups']);
  if (!file_exists($tmpF . '/' . 'index.php') || !file_exists($tmpF . '/' . 'index.html')) bmiQuickEnd(9);
  if (!file_exists($tmpS . '/' . 'index.php') || !file_exists($tmpS . '/' . 'index.html')) bmiQuickEnd(10);
  if (!file_exists($tmpB . '/' . 'index.php') || !file_exists($tmpB . '/' . 'index.html')) bmiQuickEnd(11);
  
  // content-name
  $bcknm = $fields['content-name'];
  if (!(str_ends_with($bcknm, '.tar') || str_ends_with($bcknm, '.zip') || str_ends_with($bcknm, '.gz'))) bmiQuickEnd(12);
  
  // content-configdir
  $wpcDir = dirname($fields['content-configdir']) . '/';
  if (!file_exists($wpcDir . 'plugins') || !file_exists($wpcDir . 'themes') || !file_exists($wpcDir . 'uploads')) bmiQuickEnd(13);
  
  // content-dir
  if ($fields['content-dir'] != $wpcDir . 'plugins/backup-backup/') bmiQuickEnd(14);

  // content-abs
  if ($fields['content-abs'] != dirname($wpcDir) . '/') bmiQuickEnd(15);
  
  // content-content
  if (dirname($fields['content-content']) != dirname($wpcDir)) bmiQuickEnd(16);

  // Let other files know that it's CURL request
  define('BMI_CURL_REQUEST', true);
  define('BMI_CLI_REQUEST', false);

  // Load some constants
  define('ABSPATH', filterChainFix($fields['content-abs']));
  if (substr($fields['content-content'], -1) != '/') {
    $fields['content-content'] = $fields['content-content'] . '/';
  }
  if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', $fields['content-content']);
  }
  define('BMI_CONFIG_DIR', $fields['content-configdir']);
  define('BMI_BACKUPS', $fields['content-backups']);
  define('BMI_ROOT_DIR', filterChainFix($fields['content-dir']));
  // define('BMI_SHARE_LOGS_ALLOWED', $fields['content-shareallowed']);
  define('BMI_INCLUDES', BMI_ROOT_DIR . 'includes');
  define('BMI_SAFELIMIT', intval($fields['content-safelimit']));

  // Replace error-log file
  if (isFunctionEnabled('ini_set')) {
    @ini_set('log_errors', 1);
    @ini_set('error_log', BMI_CONFIG_DIR . '/background-errors.log');
  }

  // Increase max execution time
  if (isFunctionEnabled('set_time_limit')) @set_time_limit(259200);
  if (isFunctionEnabled('ini_set')) {
    @ini_set('memory_limit', (BMI_SAFELIMIT * 4 + 16) . 'M');
    @ini_set('max_input_time', '259200');
    @ini_set('max_execution_time', '259200');
    @ini_set('session.gc_maxlifetime', '1200');
  }

  // Let the server know it's server-side script
  if (isFunctionEnabled('ignore_user_abort')) {
    @ignore_user_abort(true);
  }

  if (!isset($fields['content-browser'])) $fields['content-browser'] = false;
  if (!($fields['content-browser'] === 'true' || $fields['content-browser'] === true)) {

    // Also return something, so it can close the connection
    ob_start();

    // The message
    echo 'This is server side script, you will not get any response here.';

    // Don't block server handler
    if (isFunctionEnabled('session_write_close')) {
      @session_write_close();
    }

    // Set proper headers
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');

    // End the output for user
    ob_end_clean();
    flush();

  }

  // Start output for server
  ob_start();

  // Catch anything if possible
  try {

    // Load bypasser
    require_once filterChainFix(BMI_INCLUDES) . '/bypasser.php';
    $request = new BMI_Backup_Heart(true,
      $fields['content-configdir'],
      $fields['content-content'],
      $fields['content-backups'],
      filterChainFix($fields['content-abs']),
      filterChainFix($fields['content-dir']),
      $fields['content-url'],
      [
        'identy' => $fields['content-identy'],
        'manifest' => $fields['content-manifest'],
        'safelimit' => $fields['content-safelimit'],
        'rev' => $fields['content-rev'],
        'backupname' => $fields['content-name'],
        'start' => $fields['content-start'],
        'filessofar' => $fields['content-filessofar'],
        'total_files' => $fields['content-total'],
        'browser' => $fields['content-browser'],
        'bmitmp' => $fields['content-bmitmp']
      ],
      $fields['content-it'],
      $fields['content-dbit'],
      $fields['content-dblast']
    );

    // Handle request
    $request->handle_batch();

  } catch (\Exception $e) {

    error_log('There was an error with Backup Migration plugin: ' . $e->getMessage());
    error_log(strval($e));

  } catch (\Throwable $e) {

    error_log('There was an error with Backup Migration plugin: ' . $e->getMessage());
    error_log(strval($e));

  }

  // End the server task
  ob_end_clean(); exit;
