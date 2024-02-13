<?php

  // Namespace
  namespace BMI\Plugin\Heart;

  // Usage
  use BMI\Plugin\BMI_Logger AS Logger;
  use BMI\Plugin\Progress\BMI_ZipProgress AS Output;
  use BMI\Plugin\Checker\System_Info as SI;
  use BMI\Plugin\Dashboard as Dashboard;
  use BMI\Plugin\Database\BMI_Database as Database;
  use BMI\Plugin\Database\BMI_Database_Exporter as BetterDatabaseExport;
  use BMI\Plugin\Backup_Migration_Plugin as BMP;
  use BMI\Plugin\BMI_Pro_Core as Pro_Core;
  use BMI\Plugin AS BMI;

  // Exit on direct access
  if (!(defined('BMI_CURL_REQUEST') || defined('ABSPATH'))) exit;

  // Fixes for some cases
  require_once BMI_INCLUDES . '/compatibility.php';

  /**
   * Main class to handle heartbeat of the backup
   */
  class BMI_Backup_Heart {
    
    public $it;
    public $dbit;
    public $abs;
    public $dir;
    public $url;
    public $curl;
    public $config;
    public $content;
    public $backups;
    public $dblast;
    public $output;
    
    public $identy;
    public $manifest;
    public $backupname;
    public $safelimit;
    public $total_files;
    public $rev;
    public $backupstart;
    public $filessofar;
    public $identyfile;
    public $browserSide;
    
    public $identyFolder;
    public $fileList;
    public $dbfile;
    public $db_dir_v2;
    public $db_v2_engine;
    
    public $headersSet;
    public $final_made;
    public $final_batch;
    public $dbitJustFinished;
    public $lock_cli;
    
    public $_zip;
    public $_lib;
    public $batches_left;

    // Prepare the request details
    function __construct($curl = false, $config = false, $content = false, $backups = false, $abs = false, $dir = false, $url = false, $remote_settings = [], $it = 0, $dbit = 0, $dblast = 0) {
      
      if (isset($remote_settings['bmitmp'])) {
        if (!defined('BMI_TMP')) define('BMI_TMP', $remote_settings['bmitmp']);
      }
      
      $this->it = intval($it);
      $this->dbit = intval($dbit);
      $this->abs = $abs;
      $this->dir = $dir;
      $this->url = $url;
      $this->curl = $curl;
      $this->config = $config;
      $this->content = $content;
      $this->backups = $backups;
      $this->dblast = $dblast;

      $this->identy = $remote_settings['identy'];
      $this->manifest = $remote_settings['manifest'];
      $this->backupname = $remote_settings['backupname'];
      $this->safelimit = intval($remote_settings['safelimit']);
      $this->total_files = $remote_settings['total_files'];
      $this->rev = intval($remote_settings['rev']);
      $this->backupstart = $remote_settings['start'];
      $this->filessofar = intval($remote_settings['filessofar']);
      $this->identyfile = BMI_TMP . DIRECTORY_SEPARATOR . '.' . $this->identy;
      $this->browserSide = ($remote_settings['browser'] === true || $remote_settings['browser'] === 'true') ? true : false;

      $this->identyFolder = BMI_TMP . DIRECTORY_SEPARATOR . 'bg-' . $this->identy;
      $this->fileList = BMI_TMP . DIRECTORY_SEPARATOR . 'files_latest.list';
      $this->dbfile = BMI_TMP . DIRECTORY_SEPARATOR . 'bmi_database_backup.sql';
      $this->db_dir_v2 = BMI_TMP . DIRECTORY_SEPARATOR . 'db_tables';
      $this->db_v2_engine = false;

      $this->headersSet = false;
      $this->final_made = false;
      $this->final_batch = false;
      $this->dbitJustFinished = false;

      $this->lock_cli = BMI_BACKUPS . '/.backup_cli_lock';
      if ($this->it > 1) @touch($this->lock_cli);
      
      if ($this->isFunctionEnabled('ini_set')) {
        ini_set('log_errors', 1);
        ini_set('error_log', BMI_CONFIG_DIR . '/background-errors.log');
      }

    }
    
    // Make sure it's impossible to unlink some files
    public function unlinksafe($path) {
      
      if (substr($path, 0, 7) == 'file://') {
        $path = substr($path, 7);
      }
      
      $path = realpath($path);
      if ($path === false) return;
      if (strpos($path, 'wp-config.php') !== false) return;
      
      @unlink('file://' . $path);
      
    }

    // Human size from bytes
    public static function humanSize($bytes) {
      if (is_int($bytes)) {
        $label = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        for ($i = 0; $bytes >= 1024 && $i < (count($label) - 1); $bytes /= 1024, $i++);

        return (round($bytes, 2) . " " . $label[$i]);
      } else return $bytes;
    }

    // Create new process
    public function send_beat($manual = false, &$logger = null) {

      try {
        
        $fofs = 0;
        if (substr($this->config, 0, 7) == 'file://') $fofs = 7;

        $header = array(
          // 'Content-Type:Application/x-www-form-urlencoded',
          'Content-Accept:*/*',
          'Access-Control-Allow-Origin:*',
          'Content-ConfigDir:' . substr($this->config, $fofs),
          'Content-Content:' . substr($this->content, $fofs),
          'Content-Backups:' . substr($this->backups, $fofs),
          'Content-Identy:' . $this->identy,
          'Content-Url:' . $this->url,
          'Content-Abs:' . substr($this->abs, $fofs),
          'Content-Dir:' . substr($this->dir, $fofs),
          'Content-Manifest:' . substr($this->manifest, $fofs),
          'Content-Name:' . $this->backupname,
          'Content-Safelimit:' . $this->safelimit,
          'Content-Start:' . $this->backupstart,
          'Content-Filessofar:' . $this->filessofar,
          'Content-Total:' . $this->total_files,
          'Content-Rev:' . $this->rev,
          'Content-It:' . $this->it,
          'Content-Dbit:' . $this->dbit,
          'Content-Dblast:' . $this->dblast,
          'Content-Bmitmp:' . substr(BMI_TMP, $fofs),
          'Content-Browser:' . $this->browserSide ? 'true' : 'false'
        );

        // if (!defined('CURL_HTTP_VERSION_2_0')) {
        //   define('CURL_HTTP_VERSION_2_0', CURL_HTTP_VERSION_1_0);
        // }

        // $ckfile = tempnam(BMI_TMP, "CURLCOOKIE");
        $c = curl_init();
             curl_setopt($c, CURLOPT_POST, 1);
             curl_setopt($c, CURLOPT_TIMEOUT, 10);
             // curl_setopt($c, CURLOPT_NOBODY, true);
             curl_setopt($c, CURLOPT_VERBOSE, false);
             curl_setopt($c, CURLOPT_HEADER, false);
             // curl_setopt($c, CURLOPT_COOKIEJAR, $ckfile);
             curl_setopt($c, CURLOPT_URL, $this->url);
             curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
             curl_setopt($c, CURLOPT_MAXREDIRS, 10);
             curl_setopt($c, CURLOPT_COOKIESESSION, true);
             // curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 1);
             curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
             curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
             curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
             curl_setopt($c, CURLOPT_HTTPHEADER, $header);
             curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'POST');
             curl_setopt($c, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
             // curl_setopt($c, CURLOPT_USERAGENT, 'BMI_HEART_TIMEOUT_BYPASS_' . $this->it);
             curl_setopt($c, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        $r = curl_exec($c);

        if ($manual === true && $logger !== null) {
          if ($r === false) {
            if (intval(curl_errno($c)) !== 28) {
              Logger::error(print_r(curl_getinfo($c), true));
              Logger::error(curl_errno($c) . ': ' . curl_error($c));
              $logger->log('There was something wrong with the request:', 'WARN');
              $logger->log(curl_errno($c) . ': ' . curl_error($c), 'WARN');
            }
          } else {
            $logger->log('Request sent successfully, without error returned.', 'SUCCESS');
          }
        }

        curl_close($c);
        // if (file_exists($ckfile)) $this->unlinksafe($ckfile);
        if (isset($this->output)) $this->output->end();

      } catch (Exception $e) {

        error_log($e->getMessage());
        if (isset($this->output)) $this->output->end();

      } catch (Throwable $e) {

        error_log($e->getMessage());
        if (isset($this->output)) $this->output->end();

      }

    }

    // Load backup logger
    public function load_logger() {

      require_once BMI_INCLUDES . '/logger.php';
      require_once BMI_INCLUDES . '/progress/logger-only.php';

      $this->output = new Output();
      $this->output->start();

    }

    // Remove common files
    public function remove_commons() {

      // Remove list if exists
      $identyfile = $this->identyfile;
      $logfile = BMI_TMP . DIRECTORY_SEPARATOR . 'bmi_logs_this_backup.log';
      $clidata = BMI_TMP . DIRECTORY_SEPARATOR . 'bmi_cli_data.json';
      if (file_exists($this->fileList)) $this->unlinksafe($this->fileList);
      if (file_exists($this->dbfile)) $this->unlinksafe($this->dbfile);
      if (file_exists($this->manifest)) $this->unlinksafe($this->manifest);
      if (file_exists($logfile)) $this->unlinksafe($logfile);
      if (file_exists($clidata)) $this->unlinksafe($clidata);
      if (file_exists($identyfile)) $this->unlinksafe($identyfile);
      if (file_exists($identyfile . '-running')) $this->unlinksafe($identyfile . '-running');
      if (file_exists($this->lock_cli)) $this->unlinksafe($this->lock_cli);

      // Remove backup
      if (file_exists(BMI_BACKUPS . '/.running')) $this->unlinksafe(BMI_BACKUPS . '/.running');
      if (file_exists(BMI_BACKUPS . '/.abort')) $this->unlinksafe(BMI_BACKUPS . '/.abort');

      // Remove group folder
      if (file_exists($this->identyFolder)) {
        $files = glob($this->identyFolder . '/*');
        foreach ($files as $file) if (is_file($file)) $this->unlinksafe($file);
        @rmdir($this->identyFolder);
      }

      // Remove tmp database files
      if (file_exists($this->db_dir_v2) && is_dir($this->db_dir_v2)) {
        $files = glob($this->db_dir_v2 . '/*');
        foreach ($files as $file) if (is_file($file)) $this->unlinksafe($file);
        if (is_dir($this->db_dir_v2)) @rmdir($this->db_dir_v2);
      }

    }

    // Make success
    public function send_success() {

      // Set header for browser
      if ($this->browserSide && $this->headersSet === false) {

        // Content finished
        header('Content-Finished: true');
        header('Content-It: ' . ($this->it + 1));
        header('Content-Dbit: ' . $this->dbit);
        header('Content-Dblast: ' . $this->dblast);
        header('Content-Filessofar: ' . $this->filessofar);
        http_response_code(200);
        $this->headersSet = true;

      }

      // Display the success
      $this->output->log('Backup completed successfully!', 'SUCCESS');
      $this->output->log('#001', 'END-CODE');

      // Remove common files
      $this->remove_commons();

      // End logger
      if (isset($this->output)) @$this->output->end();

      $this->actionsAfterProcess(true);

      // End the process
      exit;

    }

    // Make error
    public function send_error($reason = false, $abort = false) {

      // Set header for browser
      if ($this->browserSide && $this->headersSet === false) {

        // Content finished
        header('Content-Finished: false');
        header('Content-It: ' . ($this->it + 1));
        header('Content-Dbit: ' . $this->dbit);
        header('Content-Dblast: ' . $this->dblast);
        header('Content-Filessofar: ' . $this->filessofar);
        http_response_code(200);
        $this->headersSet = true;

      }

      // Log error
      $this->output->log('Something went wrong with background process... ' . '(part: ' . $this->it . ')', 'ERROR');
      if ($reason !== false) $this->output->log('Reason: ' . $reason, 'ERROR');
      $this->output->log('Removing backup files... ', 'ERROR');

      // Remove common files
      $this->remove_commons();

      // Remove backup
      if (file_exists(BMI_BACKUPS . DIRECTORY_SEPARATOR . $this->backupname)) $this->unlinksafe(BMI_BACKUPS . DIRECTORY_SEPARATOR . $this->backupname);

      // Abort step
      $this->output->log('Aborting backup... ', 'STEP');
      if ($abort === false) $this->output->log('#002', 'END-CODE');
      else $this->output->log('#003', 'END-CODE');
      if (isset($this->output)) @$this->output->end();

      $this->actionsAfterProcess();
      exit;

    }

    // Group files for batches
    public function make_file_groups() {

      if (!(file_exists($this->fileList) && is_readable($this->fileList))) {
        return $this->send_error('File list is not accessible or does not exist, try to run your backup process once again.', true);
      }

      $this->output->log('Making batches for each process...', 'STEP');
      $list_path = $this->fileList;

      $file = fopen($list_path, 'r');
      $this->output->log('Reading list file...', 'INFO');
      $first_line = explode('_', fgets($file));
      $files = intval($first_line[0]);
      $firstmax = intval($first_line[1]);

      if ($files > 0) {
        $batches = 100;
        if ($files <= 200) $batches = 100;
        if ($files > 200) $batches = 200;
        if ($files > 1600) $batches = 400;
        if ($files > 3200) $batches = 800;
        if ($files > 6400) $batches = 1600;
        if ($files > 12800) $batches = 3200;
        if ($files > 25600) $batches = 5000;
        if ($files > 30500) $batches = 10000;
        if ($files > 60500) $batches = 20000;
        if ($files > 90500) $batches = 40000;

        $this->output->log('Each batch will contain up to ' . $batches . ' files.', 'INFO');
        $this->output->log('Large files takes more time, you will be notified about those.', 'INFO');

        $folder = $this->identyFolder;
        mkdir($folder, 0755, true);

        $limitcrl = 96;
        if (BMI_CLI_REQUEST === true) {
          $limitcrl = 512;
          if ($files > 30000) $limitcrl = 1024;
        }

        $i = 0; $bigs = 0; $prev = 0; $currsize = 0;
        while (($line = fgets($file)) !== false) {

          $line = explode(',', $line);
          $last = sizeof($line) - 1;
          $size = intval($line[$last]);
          unset($line[$last]);
          $line = implode(',', $line);

          $i++;
          if ($firstmax != -1 && $i > $firstmax) $bigs++;
          $suffix = intval(ceil(abs($i / $batches))) + $bigs;

          if ($prev == $suffix) {
            $currsize += $size;
          } else {
            $currsize = $size;
            $prev = $suffix;
          }

          $skip = false;
          if ($currsize > ($limitcrl * (1024 * 1024))) $skip = true;

          $groupFile = $folder . DIRECTORY_SEPARATOR . $this->identy . '-' . $suffix . '.files';
          $group = fopen($groupFile, 'a');
                   fwrite($group, $line . ',' . $size . "\r\n");
                   fclose($group);

          if ($skip === true) $bigs++;
          unset($line);

        }

        fclose($file);
        usleep(100);
        if (file_exists($this->fileList)) $this->unlinksafe($this->fileList);

      } else {

        $this->output->log('No file found to be backed up, omitting files.', 'INFO');

      }

      if (file_exists($this->fileList)) $this->unlinksafe($this->fileList);
      $this->output->log('Batches completed...', 'SUCCESS');

    }

    // Final batch
    public function get_final_batch() {

      $db_root_dir = BMI_TMP . DIRECTORY_SEPARATOR;
      $logs = $db_root_dir . 'bmi_logs_this_backup.log';

      $log_file = fopen($logs, 'w');
                  fwrite($log_file, file_get_contents(BMI_BACKUPS . DIRECTORY_SEPARATOR . 'latest.log'));
                  fclose($log_file);
      $files = [substr($logs, 7), substr($this->manifest, 7)];

      return $files;

    }

    // Final logs
    public function log_final_batch() {

      $this->output->log('Finalizing backup', 'STEP');
      $this->output->log('Closing files and archives', 'STEP');
      $this->output->log('Archiving of ' . $this->total_files . ' files took: ' . number_format(microtime(true) - floatval($this->backupstart), 2) . 's', 'INFO');

      if (!BMI_CLI_REQUEST) {
        if (!$this->browserSide) sleep(1);
      }

      if (file_exists(BMI_BACKUPS . '/.abort')) {
        $this->send_error('Backup aborted manually by user.', true);
        return;
      }

      $this->send_success();

    }

    // Load batch
    public function load_batch() {

      if (!(file_exists($this->identyFolder) && is_dir($this->identyFolder))) {
        return $this->send_error('Temporary directory does not exist, please start the backup once again.', true);
      }

      $allFiles = scandir($this->identyFolder);
      $files = array_slice((array) $allFiles, 2);
      if (sizeof($files) > 0) {

        $largest = $files[0]; $prev_size = 0;
        for ($i = 0; $i < sizeof($files); ++$i) {
          $curr_size = filesize($this->identyFolder . DIRECTORY_SEPARATOR . $files[$i]);
          if ($curr_size > $prev_size) {
            $largest = $files[$i];
            $prev_size = $curr_size;
          }
        }
        $this->batches_left = sizeof($files);

        if (sizeof($files) == 1) {
          $this->final_batch = true;
        }

        return $this->identyFolder . DIRECTORY_SEPARATOR . $largest;

      } else {

        $this->log_final_batch();
        return false;

      }

    }

    // Cut Path for ZIP structure
    public function cutDir($file) {

      if (substr($file, -4) === '.sql') {

        if ($this->db_v2_engine == true) {

          return 'db_tables' . DIRECTORY_SEPARATOR . basename($file);

        } else {

          return basename($file);

        }

      } else {

        return basename($file);

      }

    }

    // Add files to ZIP – The Backup
    public function add_files($files = [], $file_list = false, $final = false, $dbLog = false) {

      try {

        // TODO: Remove false && or replace with option in settings to switch
        if (false && (class_exists('\ZipArchive') || class_exists('ZipArchive'))) {

          // Initialize Zip
          if (!isset($this->_zip)) {
            $this->_zip = new \ZipArchive();
          }

          if ($this->_zip) {

            // Show what's in use
            if ($this->it === 1) {
              $this->output->log('Using ZipArchive module to create the Archive.', 'INFO');
              if ($dbLog == true) {
                $this->output->log('Adding database SQL file(s) to the backup file.', 'STEP');
              }
            }

            // Open / create ZIP file
            $back = BMI_BACKUPS . DIRECTORY_SEPARATOR . $this->backupname;
            if (BMI_CLI_REQUEST) {
              if (!isset($this->zip_initialized)) {
                if (file_exists($back)) $this->_zip->open($back);
                else $this->_zip->open($back, \ZipArchive::CREATE);
              }
            } else {
              if (file_exists($back)) $this->_zip->open($back);
              else $this->_zip->open($back, \ZipArchive::CREATE);
            }

            // Final operation
            if ($final || $dbLog) {

              // Add files
              for ($i = 0; $i < sizeof($files); ++$i) {

                if (file_exists($files[$i]) && is_readable($files[$i]) && !is_link($files[$i])) {

                  // Add the file
                  $this->_zip->addFile($files[$i], $this->cutDir($files[$i]));

                } else {

                  $this->output->log('This file is not readable, it will not be included in the backup: ' . $files[$i], 'WARN');

                }

              }

              if ($dbLog === false) {
                $this->final_made = true;
              }

            } else {

              // Add files
              for ($i = 0; $i < sizeof($files); ++$i) {

                if (file_exists($files[$i]) && is_readable($files[$i]) && !is_link($files[$i])) {

                  // Calculate Path in ZIP
                  $path = 'wordpress' . DIRECTORY_SEPARATOR . substr($files[$i], strlen(ABSPATH) - 7);

                  // Add the file
                  $this->_zip->addFile($files[$i], $path);

                } else {

                  $this->output->log('This file is not readable, it will not be included in the backup: ' . $files[$i], 'WARN');

                }

              }

            }

            // Close archive and prepare next batch
            touch(BMI_BACKUPS . '/.running');
            if (!BMI_CLI_REQUEST || $final) {
              $result = $this->_zip->close();

              if ($result === true) {

                // Remove batch
                if ($file_list && file_exists($file_list)) {
                  $this->unlinksafe($file_list);
                }

              } else {

                $this->send_error('Error, there is most likely not enough space for the backup.');
                return false;

              }
            } else {

              // Remove batch
              if ($file_list && file_exists($file_list)) {
                $this->unlinksafe($file_list);
              }

            }

          } else {
            $this->send_error('ZipArchive error, please contact support - your site may be special case.');
          }

        } else {

          // Check if PclZip exists
          if (!class_exists('PclZip')) {
            if (!defined('PCLZIP_TEMPORARY_DIR')) {
              $bmi_tmp_dir = BMI_TMP;
              if (!file_exists($bmi_tmp_dir)) {
                @mkdir($bmi_tmp_dir, 0775, true);
              }

              define('PCLZIP_TEMPORARY_DIR', $bmi_tmp_dir . DIRECTORY_SEPARATOR . 'bmi-');
            }
          }

          // Require the LIB and check if it's compatible
          $alternative = dirname($this->dir) . '/backup-backup-pro/includes/pcl.php';
          if ($this->rev === 1 || !file_exists($alternative)) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
          } else {
            require_once $alternative;
            if ($this->it === 1) {
              $this->output->log('Using dedicated PclZIP for Pro', 'INFO');
              if ($dbLog == true) {
                $this->output->log('Adding database SQL file(s) to the backup file.', 'STEP');
              }
            }
          }

          // Get/Create the Archive
          if (!isset($this->_lib)) {
            $this->_lib = new \PclZip(BMI_BACKUPS . DIRECTORY_SEPARATOR . $this->backupname);
          }

          if (!$this->_lib) {
            $this->send_error('PHP-ZIP: Permission Denied or zlib cannot be found');
            return;
          }

          if (sizeof($files) <= 0) {
            return false;
          }
          
          $back = 0;
          $files = array_filter($files, function ($path) {
            if (is_readable($path) && file_exists($path) && !is_link($path)) return true;
            else {
              $this->output->log("Excluding file that cannot be read: " . $path, 'warn');
              return false;
            }
          });

          // Add files
          if ($final || $dbLog) {

            // Final configuration
            if (sizeof($files) > 0) {
              $back = $this->_lib->add($files, PCLZIP_OPT_REMOVE_PATH, substr(BMI_TMP, 7) . DIRECTORY_SEPARATOR, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_OPT_TEMP_FILE_THRESHOLD, $this->safelimit);
            }
            
            if ($dbLog === false) {
              $this->final_made = true;
            }

          } else {

            // Additional path
            $add_path = 'wordpress' . DIRECTORY_SEPARATOR;

            // Casual configuration
            if (sizeof($files) > 0) {
              $back = $this->_lib->add($files, PCLZIP_OPT_REMOVE_PATH, substr(ABSPATH, 7), PCLZIP_OPT_ADD_PATH, $add_path, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_OPT_TEMP_FILE_THRESHOLD, $this->safelimit);
            }

          }

          // Check if there was any error
          touch(BMI_BACKUPS . '/.running');
          if ($back == 0) {

            $this->send_error($this->_lib->errorInfo(true));
            return false;

          } else {

            if ($file_list && file_exists($file_list)) {
              $this->unlinksafe($file_list);
            }

          }

        }

      } catch (\Exception $e) {

        $this->send_error($e->getMessage());
        return false;

      } catch (\Throwable $e) {

        $this->send_error($e->getMessage());
        return false;

      }

    }

    // ZIP one of the grouped files
    public function zip_batch() {

      if ($this->it === 1) {
        
        $files = [];
        if (file_exists($this->dbfile)) {
          $files[] = substr($this->dbfile, 7);
        } elseif (file_exists($this->db_dir_v2) && is_dir($this->db_dir_v2)) {
          $this->db_v2_engine = true;
          $db_files = scandir($this->db_dir_v2);
          foreach ($db_files as $i => $name) {
            if (!($name == '.' || $name == '..')) {
              $files[] = substr($this->db_dir_v2, 7) . DIRECTORY_SEPARATOR . $name;
            }
          }
        }

        if (sizeof($files) > 0) {
          $this->add_files($files, false, false, true);
          $this->output->log('Database added to the backup file.', 'SUCCESS');
          $this->output->log('Performing site files backup...', 'STEP');
          return true;
        }

        $this->output->log('Performing site files backup...', 'STEP');

      }

      $list_file = $this->load_batch();
      if ($list_file === false) return true;
      $files = explode("\r\n", file_get_contents($list_file));

      $total_size = 0;
      $parsed_files = [];
      
      $absWo = substr(ABSPATH, 7);
      $wpcDirWo = substr(WP_CONTENT_DIR, 7);

      for ($i = 0; $i < sizeof($files); ++$i) {
        if (strlen(trim($files[$i])) <= 1) {
          $this->total_files--;
          continue;
        }

        $files[$i] = explode(',', $files[$i]);
        $last = sizeof($files[$i]) - 1;
        $size = intval($files[$i][$last]);
        unset($files[$i][$last]);
        $files[$i] = implode(',', $files[$i]);

        $file = null;
        if ($files[$i][0] . $files[$i][1] . $files[$i][2] === '@1@') {
          $file = $wpcDirWo . DIRECTORY_SEPARATOR . substr($files[$i], 3);
        } else if ($files[$i][0] . $files[$i][1] . $files[$i][2] === '@2@') {
          $file = $absWo . DIRECTORY_SEPARATOR . substr($files[$i], 3);
        } else {
          $file = $files[$i];
        }

        if (!file_exists($file)) {
          $this->output->log('Removing this file from backup (it does not exist anymore): ' . $file, 'WARN');
          $this->total_files--;
          continue;
        }

        if (filesize($file) === 0) {
          $this->output->log('Removing this file from backup (file size is equal to 0 bytes): ' . $file, 'WARN');
          $this->total_files--;
          continue;
        }

        $parsed_files[] = $file;
        $total_size += $size;
        unset($file);
      }

      unset($files);
      if (sizeof($parsed_files) === 1) {
        $this->output->log('Adding: ' . sizeof($parsed_files) . ' file...' . ' [Size: ' . $this->humanSize($total_size) . ']', 'INFO');
        $this->output->log('Alone-file mode for: ' . $parsed_files[0] . ' file...', 'INFO');
      } else $this->output->log('Adding: ' . sizeof($parsed_files) . ' files...' . ' [Size: ' . $this->humanSize($total_size) . ']', 'INFO');

      if ((60 * (1024 * 1024)) < $total_size) $this->output->log('Current batch is quite large, it may take some time...', 'WARN');

      $this->add_files($parsed_files, $list_file);
      $this->filessofar += sizeof($parsed_files);

      $this->output->progress($this->filessofar . '/' . $this->total_files);
      $this->output->log('Milestone: ' . $this->filessofar . '/' . $this->total_files . ' [' . $this->batches_left . ' batches left]', 'SUCCESS');

      if ($this->final_batch === true) {
        $this->output->log('Adding final files to this batch...', 'STEP');
        $this->output->log('Adding manifest as addition...', 'INFO');

        $additionalFiles = $this->get_final_batch();
        $this->add_files($additionalFiles, false, true);
        $this->log_final_batch();
        return true;
      }

    }

    // Shutdown callback
    public function shutdown() {

      // Check if there was any error
      $err = error_get_last();
      if ($err != null) {
        Logger::error('Shuted down');
        Logger::error(print_r($err, true));
        $this->output->log('Background process had some issues, more details printed to global logs.', 'WARN');
      }

      // Remove lock
      if (file_exists($this->lock_cli)) {
        $this->unlinksafe($this->lock_cli);
      }

      // Send next beat to handle next batch
      if (BMI_CLI_REQUEST) return;
      if (file_exists($this->identyfile)) {

        if ($this->dbit === -1 && $this->dbitJustFinished == false) {
          $this->it += 1;
        }

        // Set header for browser
        if ($this->browserSide && $this->headersSet === false) {

          // Content finished
          header('Content-Finished: false');
          header('Content-It: ' . $this->it);
          header('Content-Dbit: ' . $this->dbit);
          header('Content-Dblast: ' . $this->dblast);
          header('Content-Filessofar: ' . $this->filessofar);
          http_response_code(200);
          $this->headersSet = true;

        } else {

          usleep(100);
          $this->send_beat();

        }

      }

    }

    // Handle received batch
    public function handle_batch() {

      // Check if aborted
      if (file_exists(BMI_BACKUPS . '/.abort')) {
        if (!isset($this->output)) $this->load_logger();
        $this->send_error('Backup aborted manually by user.', true);
        return;
      }

      // Handle cURL
      if ($this->curl == true) {

        // Check if it was triggered by verified user
        if (!file_exists($this->identyfile)) {
          return;
        }

        // Register shutdown
        register_shutdown_function([$this, 'shutdown']);

        // Load logger
        $this->load_logger();

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
          Logger::error('Bypasser error:');
          Logger::error($errno . ' - ' . $errstr);
          Logger::error($errfile . ' - ' . $errline);
        });

        // Notice parent script
        touch($this->identyfile . '-running');
        touch(BMI_BACKUPS . '/.running');

        // CLI case
        if (BMI_CLI_REQUEST) {

          $this->output->log('Starting database backup exporter', 'STEP');
          $this->output->log('Database exporter started via CLI', 'VERBOSE');
          while ($this->dbit !== -1) {
            $this->databaseBackupMaker();
          }

          // Log
          $this->output->log("PHP CLI initialized - process ran successfully", 'SUCCESS');
          $this->make_file_groups();

          // Make ZIP
          $this->output->log('Making archive...', 'STEP');
          while (!$this->final_made) {
            touch($this->identyfile . '-running');
            touch(BMI_BACKUPS . '/.running');
            $this->it++;
            $this->zip_batch();
          }

        } else {

          // Background
          if ($this->dbit !== -1) {

            if ($this->dbit === 0) {
              $this->output->log('Background process initialized', 'SUCCESS');
              $this->output->log('Starting database backup exporter', 'STEP');
              $this->output->log('Database exporter started via WEB REQUESTS', 'VERBOSE');
            }

            $this->databaseBackupMaker();

          } else {

            if ($this->it === 0) {

              $this->make_file_groups();
              $this->output->log('Making archive...', 'STEP');

            } else $this->zip_batch();

          }

        }

      }

    }

    public function fixSlashes($str, $slash = false) {
      // Old version
      // $str = str_replace('\\\\', DIRECTORY_SEPARATOR, $str);
      // $str = str_replace('\\', DIRECTORY_SEPARATOR, $str);
      // $str = str_replace('\/', DIRECTORY_SEPARATOR, $str);
      // $str = str_replace('/', DIRECTORY_SEPARATOR, $str);

      // if ($str[strlen($str) - 1] == DIRECTORY_SEPARATOR) {
      //   $str = substr($str, 0, -1);
      // }
      
      // Since 1.3.2
      $protocol = '';
      if ($slash == false) $slash = DIRECTORY_SEPARATOR;
      if (substr($str, 0, 7) == 'http://') $protocol = 'http://';
      else if (substr($str, 0, 8) == 'https://') $protocol = 'https://';
      
      $str = substr($str, strlen($protocol));
      $str = preg_replace('/[\\\\\/]+/', $slash, $str);
      $str = rtrim($str, '/\\' );

      return $protocol . $str;
    }
    
    public function isFunctionEnabled($func) {
      $disabled = explode(',', ini_get('disable_functions'));
      $isDisabled = in_array($func, $disabled);
      if (!$isDisabled && function_exists($func)) return true;
      else return false;
    }

    // Database batch maker and dumper
    // We need WP instance for that to get access to wpdb
    public function databaseBackupMaker() {

      if ($this->dbit === -1) return;

      $this->loadWordPressAndBackupPlugin();

      // DB File Name for that type of backup
      $dbbackupname = 'bmi_database_backup.sql';
      $database_file = $this->fixSlashes(BMI_TMP . DIRECTORY_SEPARATOR . $dbbackupname);

      if (Dashboard\bmi_get_config('BACKUP:DATABASE') == 'true') {

        if (Dashboard\bmi_get_config('OTHER:BACKUP:DB:SINGLE:FILE') == 'true') {

          // Require Database Manager
          require_once BMI_INCLUDES . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'manager.php';

          // Log what's going on
          $this->output->log('Making single-file database backup (using deprecated engine, due to used settings)', 'STEP');

          // Get database dump
          $databaser = new Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
          $databaser->exportDatabase($dbbackupname);
          $this->output->log("Database size: " . $this->humanSize(filesize($database_file)), 'INFO');
          $this->output->log('Database (single-file) backup finished.', 'SUCCESS');

          $this->dbitJustFinished = true;
          $this->dbit = -1;
          return true;

        } else {

          // Log what's going on
          if ($this->dbit === 0) {
            $this->output->log("Making database backup (using v3 engine, requires at least v1.2.2 to restore)", 'STEP');
            $this->output->log("Iterating database...", 'INFO');
          }

          // Require Database Manager
          require_once BMI_INCLUDES . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'better-backup-v3.php';

          $database_file_dir = $this->fixSlashes((dirname($database_file))) . DIRECTORY_SEPARATOR;
          $better_database_files_dir = $database_file_dir . 'db_tables';
          $better_database_files_dir = str_replace('file:', 'file://', $better_database_files_dir);

          if (!is_dir($better_database_files_dir)) @mkdir($better_database_files_dir, 0755, true);
          $db_exporter = new BetterDatabaseExport($better_database_files_dir, $this->output, $this->dbit, intval($this->backupstart));

          $dbBatchingEnabled = false;
          if (Dashboard\bmi_get_config('OTHER:BACKUP:DB:BATCHING') == 'true') {
            $dbBatchingEnabled = true;
          } else {
            if ($this->dbit === 0) {
              $this->output->log("Database batching is disabled in options, consider to use this option if your database backup fails.", 'WARN');
            }
          }

          if (BMI_CLI_REQUEST === true || $dbBatchingEnabled === false) {

            $this->output->log('Exporting database via bypasser.php @ CLI || batching disabled', 'VERBOSE');
            $results = $db_exporter->export();

            $this->output->log("Database backup finished", 'SUCCESS');
            $this->dbitJustFinished = true;
            $this->dbit = -1;
            $this->dblast = 0;

          } else {
            
            $this->output->log('Exporting database via bypasser.php @ WEB REQUEST', 'VERBOSE');
            $results = $db_exporter->export($this->dbit, $this->dblast);

            $this->dbit = intval($results['batchingStep']);
            $this->dblast = intval($results['finishedQuery']);
            $dbFinished = $results['dumpCompleted'];

            if ($dbFinished == true) {
              $this->output->log("Database backup finished", 'SUCCESS');
              $this->dbitJustFinished = true;
              $this->dbit = -1;
            }

          }

          return true;

        }

      } else {

        $this->output->log('Database will not be dumped due to user settings.', 'INFO');
        $this->dbitJustFinished = true;
        $this->dbit = -1;
        return true;

      }

    }
    
    public function loadWordPressAndBackupPlugin() {
      
      // Define how WP should load
      define('WP_USE_THEMES', false);
      define('SHORTINIT', true);
      
      // Set path to our plugin's main file
      $bmiPluginPathToLoad = $this->fixSlashes(dirname(__DIR__) . '/backup-backup.php');
      $bmiPluginPathToLoadPro = $this->fixSlashes(dirname(dirname(__DIR__)) . '/backup-backup-pro/backup-backup-pro.php');

      // Use WP Globals and load WordPress
      global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
      require_once $this->bmi_find_wordpress_base_path() . DIRECTORY_SEPARATOR . 'wp-load.php';
      global $wp_version;
      
      // Load directory WordPress constants 
      require_once ABSPATH . WPINC . '/formatting.php';
      require_once ABSPATH . WPINC . '/meta.php';
      wp_plugin_directory_constants();
      
      // Allow to register activation hook and realpath
      $GLOBALS['wp_plugin_paths'] = array();
      $GLOBALS['shortcode_tags'] = array();
      
      // Load all dependencies of WordPress for Backup plugin
      $dependencies = [
        ABSPATH . WPINC . '/l10n.php',
        ABSPATH . WPINC . '/plugin.php',
        ABSPATH . WPINC . '/link-template.php',
        ABSPATH . WPINC . '/class-wp-textdomain-registry.php',
        ABSPATH . WPINC . '/class-wp-locale.php',
        ABSPATH . WPINC . '/class-wp-locale-switcher.php',
        ABSPATH . WPINC . '/session.php',
        ABSPATH . WPINC . '/pluggable.php',
        ABSPATH . WPINC . '/class-wp-ajax-response.php',
        ABSPATH . WPINC . '/capabilities.php',
        ABSPATH . WPINC . '/class-wp-roles.php',
        ABSPATH . WPINC . '/class-wp-role.php',
        ABSPATH . WPINC . '/class-wp-user.php',
        ABSPATH . WPINC . '/class-wp-query.php',
        ABSPATH . WPINC . '/query.php',
        ABSPATH . WPINC . '/general-template.php',
        ABSPATH . WPINC . '/http.php',
        ABSPATH . WPINC . '/class-http.php',
        ABSPATH . WPINC . '/class-wp-http.php',
        ABSPATH . WPINC . '/class-wp-http-streams.php',
        ABSPATH . WPINC . '/class-wp-http-curl.php',
        ABSPATH . WPINC . '/class-wp-http-proxy.php',
        ABSPATH . WPINC . '/class-wp-http-cookie.php',
        ABSPATH . WPINC . '/class-wp-http-encoding.php',
        ABSPATH . WPINC . '/class-wp-http-response.php',
        ABSPATH . WPINC . '/class-wp-http-requests-response.php',
        ABSPATH . WPINC . '/class-wp-http-requests-hooks.php',
        ABSPATH . WPINC . '/widgets.php',
        ABSPATH . WPINC . '/class-wp-widget.php',
        ABSPATH . WPINC . '/class-wp-widget-factory.php',
        ABSPATH . WPINC . '/class-wp-user-request.php',
        ABSPATH . WPINC . '/user.php',
        ABSPATH . WPINC . '/class-wp-user-query.php',
        ABSPATH . WPINC . '/class-wp-session-tokens.php',
        ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php',
        ABSPATH . WPINC . '/rest-api.php',
        ABSPATH . WPINC . '/kses.php',
        ABSPATH . WPINC . '/theme.php',
        ABSPATH . WPINC . '/rewrite.php',
        ABSPATH . WPINC . '/class-wp-block-editor-context.php',
        ABSPATH . WPINC . '/class-wp-block-type.php',
        ABSPATH . WPINC . '/class-wp-block-pattern-categories-registry.php',
        ABSPATH . WPINC . '/class-wp-block-patterns-registry.php',
        ABSPATH . WPINC . '/class-wp-block-styles-registry.php',
        ABSPATH . WPINC . '/class-wp-block-type-registry.php',
        ABSPATH . WPINC . '/class-wp-block.php',
        ABSPATH . WPINC . '/class-wp-block-list.php',
        ABSPATH . WPINC . '/class-wp-block-parser-block.php',
        ABSPATH . WPINC . '/class-wp-block-parser-frame.php',
        ABSPATH . WPINC . '/class-wp-block-parser.php',
        ABSPATH . WPINC . '/blocks.php',
        ABSPATH . WPINC . '/blocks/index.php',
      ];
      
      for ($i = 0; $i < sizeof($dependencies); ++$i) { 
        $dependency = $dependencies[$i];
        if (strpos($dependency, 'class-http.php') && version_compare($wp_version, '5.9.0', '>=')) {
          continue;
        }
        if (strpos($dependency, 'session.php') && version_compare($wp_version, '4.7.0', '>=')) {
          continue;
        }
        if (file_exists($dependency)) require_once $dependency;
      }
      
      // Load Cookie Constants
      wp_cookie_constants();
      
      // Load SSL Constants for DB export
      wp_ssl_constants();
      
      // Register Translation
      if (class_exists('WP_Textdomain_Registry')) {
        $GLOBALS['wp_textdomain_registry'] = new \WP_Textdomain_Registry();
      }
      
      if (is_readable($bmiPluginPathToLoadPro)) {
        wp_register_plugin_realpath($bmiPluginPathToLoadPro);
        include_once $bmiPluginPathToLoadPro;
        
        require_once BMI_PRO_ROOT_DIR . '/classes/core' . ((BMI_PRO_DEBUG) ? '.to-enc' : '') . '.php';
        $bmi_pro_instance = new Pro_Core();
        $bmi_pro_instance->initialize();
      }
      
      // Register our backup plugin and load its contents
      wp_register_plugin_realpath($bmiPluginPathToLoad);
      include_once $bmiPluginPathToLoad;
      
      // Enable our plugin WITHOUT calling plugins_loaded hook – it's important
      require_once BMI_ROOT_DIR . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'constants.php';

      // Initialize backup-migration
      if (!class_exists('Backup_Migration_Plugin')) {

        // Require initializator
        require_once BMI_ROOT_DIR . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'initializer.php';

        // Initialize entire plugin
        $bmi_instance = new BMI\Backup_Migration_Plugin();
        $bmi_instance->initialize();

      }
      
    }

    public function actionsAfterProcess($success = false) {
      
      $this->loadWordPressAndBackupPlugin();
      BMP::handle_after_cron();
      
      return null;

    }

    public function bmi_find_wordpress_base_path() {

      $dir = dirname(__FILE__);
      $previous = null;

      do {

        if (file_exists($dir . '/wp-load.php') && file_exists($dir . '/wp-config.php')) return $dir;
        if ($previous == $dir) break;
        $previous = $dir;

      } while ($dir = dirname($dir));

      return null;

    }

  }
