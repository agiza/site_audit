<?php
/**
 * @file
 * Contains \SiteAudit\Check\Codebase\PhpLOC.
 */

use Symfony\Component\Process\Process;

/**
 * Class SiteAuditCheckCodebasePhpLOC.
 */
class SiteAuditCheckCodebasePhpLOC extends SiteAuditCheckAbstract {
  /**
   * Implements \SiteAudit\Check\Abstract\getLabel().
   */
  public function getLabel() {
    return dt('PHP Lines of Code');
  }

  /**
   * Implements \SiteAudit\Check\Abstract\getDescription().
   */
  public function getDescription() {
    return dt('Run phploc on custom code.');
  }

  /**
   * Implements \SiteAudit\Check\Abstract\getResultFail().
   */
  public function getResultFail() {
    return dt('Non-existent paths found in custom code');
  }

  /**
   * Implements \SiteAudit\Check\Abstract\getResultInfo().
   */
  public function getResultInfo() {
    if (isset($this->registry['phploc_path_error'])) {
      return dt('Missing phploc.');
    }
    if (isset($this->registry['custom_code'])) {
      return dt('No custom code path specified');
    }
    $ret_val = '';
    if (drush_get_option('html') == TRUE) {
      $ret_val .= '<table class="table table-condensed">';
      $ret_val .= '<thead><tr><th>' . dt('Metric') . '</th><th>' . dt('Value') . '</th></tr></thead>';
      foreach ($this->registry['phploc_out'] as $filename => $metrics) {
        $ret_val .= "<tr align='center'><td colspan='3'><b>File/Directory</b>: $filename</td></tr>";
        foreach ($metrics as $metric) {
          $name = '';
          if (isset($metric['name'])) {
            $name = $metric['name'];
          }
          else {
            $name = $metric->getName();
          }
          $ret_val .= "<tr><td>$name</td><td>$metric</td></tr>";
        }
      }
      $ret_val .= '</table>';
    }
    else {
      $rows = 0;
      foreach ($this->registry['phploc_out'] as $filename => $metrics) {
        if ($rows++ > 0) {
          $ret_val .= PHP_EOL;
          if (!drush_get_option('json')) {
            $ret_val .= str_repeat(' ', 4);
          }
        }
        $ret_val .= dt('File/Directory: @filename', array(
          '@filename' => $filename,
        ));
        foreach ($metrics as $metric) {
          $ret_val .= PHP_EOL;
          if (!drush_get_option('json')) {
            $ret_val .= str_repeat(' ', 6);
          }
          if (isset($metric['name'])) {
            $name = $metric['name'];
          }
          else {
            $name = $metric->getName();
          }
          $ret_val .= "$name : $metric";
        }
      }
    }
    return $ret_val;

  }


  /**
   * Implements \SiteAudit\Check\Abstract\getResultPass().
   */
  public function getResultPass() {}


  /**
   * Implements \SiteAudit\Check\Abstract\getResultWarn().
   */
  public function getResultWarn() {}

  /**
   * Implements \SiteAudit\Check\Abstract\getAction().
   */
  public function getAction() {
    if ($this->registry['phploc_path_error'] === TRUE) {
      return dt('Run "composer install" from site_audit root to install missing dependencies.');
    }
  }

  /**
   * Implements \SiteAudit\Check\Abstract\calculateScore().
   */
  public function calculateScore() {
    // Get the path of phploc.
    $phploc_path = $this->getExecPath('phploc');
    if ($phploc_path === '') {
      $this->registry['phploc_path_error'] = TRUE;
      return SiteAuditCheckAbstract::AUDIT_CHECK_SCORE_INFO;
    }
    // Get the custom code paths.
    // Get the custom code paths.
    $custom_code = $this->getCustomCodePaths();
    if (!$custom_code) {
      $this->abort = TRUE;
      return SiteAuditCheckAbstract::AUDIT_CHECK_SCORE_FAIL;
    }
    if (empty($custom_code)) {
      $this->registry['custom_code'] = $custom_code;
      return $custom_code;
    }
    // Get options.
    $valid_options = array(
      'names' => '*.php,*.module,*.install,*.test,*.inc,*.profile,*.theme',
      'names-exclude' => '*.features.*,*_default.inc,*.ds.inc,*.strongarm.inc,*.panelizer.inc,*_defaults.inc,*.box.inc,*.context.inc,*displays.inc',
    );
    $options = $this->getOptions($valid_options, 'phploc-');
    $temp_file = tempnam(sys_get_temp_dir(), 'site_audit');
    $option_string = " --log-xml=$temp_file";
    foreach ($options as $option => $value) {
      $option_string .= " --$option";
      if ($value !== TRUE) {
        $option_string .= "=$value";
      }
    }
    // Suppress XML errors which will be handled by try catch instead.
    libxml_use_internal_errors(TRUE);

    foreach ($custom_code as $path) {
      $command = $phploc_path . ' ' . $path . $option_string;
      $process = new Process($command);
      $process->run();
      try {
        $output = simplexml_load_file($temp_file);
        $this->registry['phploc_out'][$path] = $output;
      }
      catch (Exception $e) {
        $this->logXmlError($path, 'phploc');
        continue;
      }
    }
    return SiteAuditCheckAbstract::AUDIT_CHECK_SCORE_INFO;
  }

}
