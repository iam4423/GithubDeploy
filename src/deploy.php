<?php
/**
 * Deploy endpoint to be hid by Github webhooks
 * 
 * @author Matt Holmes <iam4423@gmail.com>
 * @copyright (c) 2015, Matt Holmes
 * 
 * @package GithubDeploy
 */

(new GithubDeploy)->run();

final class GithubDeploy
{
  private $_conf        = null;
  private $_excludePath = "";
  
  /**
   * Run the deploy process
   * 
   * @param String $manifest
   */
  public function run($manifest = "manifest.json")
  {
    try {
      $this->_loadManifest($manifest);
        
      $this->_log("---------------------------------------" . PHP_EOL . 
        "Deplot initiated " . date("r") . PHP_EOL .
        "---------------------------------------"
      );
      
      $this->_checkHeaders();
      $this->_checkPayload();
      
      $this->_runPrePostScripts(@$this->_conf->preDeploy ?: []);
      
      $this->_createExcludeFile();
      $this->_runDeployScript();
      
      $this->_runPrePostScripts(@$this->_conf->postDeploy ?: []);
      
      $this->_cleanup();
    } catch (Exception $e) {/* nixy */}

    
  }
  
  /**
   * Load the manifest file
   * 
   * @param String $manifest
   * 
   * @throws \Exception
   */
  private function _loadManifest($manifest)
  {
    $raw = file_get_contents($manifest);
    $this->_conf = json_decode($raw);
    
    if (!$this->_conf instanceof stdClass
      || !isset($this->_conf->gitPath)
      || !isset($this->_conf->bashPath)
      || !isset($this->_conf->htdocsPath)
      || !isset($this->_conf->mergerPath)
      || !isset($this->_conf->deployBranch)
      || !isset($this->_conf->htdocsBranch)
      || !isset($this->_conf->eventTypes)
      || !isset($this->_conf->payloadSecret)
      || !isset($this->_conf->deployScript)
    ) {
      throw new Exception("Break Execution");
    }
  }
  
  /**
   * Check for valid headers to try and ensure the request is coming from github
   * 
   * @return Boolean
   * 
   * @throws \Exception
   */
  private function _checkHeaders()
  {
    // check request type
    if ("POST" !== @$_SERVER["REQUEST_METHOD"]) {
      $this->_log("GithubDeploy::_chekHeaders: Failed Request Method Check");
      throw new Exception("Break Execution");
    }
    
    // check user agent
    if (strpos(@$_SERVER["HTTP_USER_AGENT"] ?: "", "GitHub-Hookshot/") !== 0) {
      $this->_log("GithubDeploy::_chekHeaders: Failed User Agent Check");
      throw new Exception("Break Execution");
    }
    
    // check event type
    if (!in_array($this->_conf->evenTypes ?: [], @$_SERVER["HTTP_X_GITHUB_EVENT"])) {
      $this->_log("GithubDeploy::_chekHeaders: Failed Event Type Check");
      throw new Exception("Break Execution");
    }
    
    $this->_log("GithubDeploy::_chekHeaders: Passed");
  }
  
  /**
   * Check for a valif payload
   * 
   * @throws \Exception
   */
  private function _checkPayload()
  {
    $raw  = file_get_contents("php://input");
    $json = json_decode($raw);
    
    // check valid json
    if (empty($raw) || !$json instanceof \stdClass) {
      $this->_log("GithubDeploy::_checkPayload: Failed JSON Validity Check");
      throw new Exception("Break Execution");
    }
    
    // check hash
    $hash = "sha1=" . hash_hmac("sha1", $raw, $this->_conf->payloadSecret, false);
    if (@$_SERVER["HTTP_X_HUB_SIGNATURE"] !== $hash) {
      $this->_log("GithubDeploy::_checkPayload: Failed Payload Checksum");
      throw new Exception("Break Execution");
    }
    
    if ("refs/heads/{$this->_conf->deployBranch}" !== $json->ref) {
      $this->_log("GithubDeploy::_checkPayload: Wrong Branch");
      throw new Exception("Break Execution");
    }
    
    $this->_log("GithubDeploy::_checkPayload: Passed");
  }
  
  /**
   * Run an array of scripts/commands
   * 
   * @param Array $scripts
   */
  private function _runPrePostScripts(Array $scripts)
  {
    $log = @$this->_conf->logPath ?: "/dev/null";
    
    foreach ($scripts as $script) {
      $this->_log("GithubDeploy::_runPrePostScripts: Runnning script ($script)");
      `$script > $log 2>&1`;
    }
  }
  
  /**
   * Create tmp file for use by the shell script
   */
  private function _createExcludeFile()
  {
    if (!empty($this->_conf->excludeFiles) && is_array($this->_conf->excludeFiles)) {
      $this->_excludePath = __DIR__ . "excludeFiles.tmp";
      
      foreach ($this->_conf->excludeFiles as $file) {
        file_put_contents($this->_excludePath, $file . PHP_EOL, FILE_APPEND);
      }
    }
  }
  
  /**
   * Run deploy shell script
   */
  private function _runDeployScript()
  {
    $bash  = escapeshellarg($this->_conf->bashPath);
    $scr   = escapeshellarg($this->_conf->deployScript);
    $git   = escapeshellarg($this->_conf->gitPath);
    $docs  = escapeshellarg($this->_conf->htdocsPath);
    $mrgr  = escapeshellarg($this->_conf->mergerPath);
    $exc   = escapeshellarg($this->_excludePath);
    $dplBr = escapeshellarg($this->_conf->deployBranch);
    $htBr  = escapeshellarg($this->_conf->htdocsBranch);
    $log   = escapeshellarg(@$this->_conf->logPath ?: "/dev/null");
    
    `$bash -x $scr $git $docs $mrgr $exc $dplBr $htBr > $log 2>&1`;
  }
  
  /**
   * Cleanup temp files
   */
  private function _cleanup()
  {
    if ($this->_excludePath && file_exists($this->_excludePath)) {
      unlink($this->_excludePath);
    }
    
    $this->_log("---------------------------------------" . PHP_EOL . 
      "Deploy Complete " . date("r") . PHP_EOL .
      "---------------------------------------"
    );
  }
  
  /**
   * Add to the output log
   * 
   * @param String $text
   * @param Boolean $append
   */
  private function _log($text, $append = true)
  {
    if (!empty($this->_conf->logPath)) {
      file_put_contents($this->_conf->logPath, $text . PHP_EOL, $append ? FILE_APPEND : 0);
    }
  }
}