<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @filesource  mainPage.php
 * 
 * Page has two functions: navigation and select Test Plan
 *
 * This file is the first page that the user sees when they log in.
 * Most of the code in it is html but there is some logic that displays
 * based upon the login. 
 * There is also some javascript that handles the form information.
 *
 **/

require_once('../../config.inc.php');
require_once('common.php');

testlinkInitPage($db,TRUE);

$smarty = new TLSmarty();
$tprjMgr = new testproject($db);

$args = initArgs($db);
$opt = array('forceCreateProj' => $args->newInstallation);
list($add2args,$gui) = initUserEnv($db,$opt);
$k2l = get_object_vars($add2args);
foreach($k2l as $prop => $pval) {
  $args->$prop = $pval;
}

$gui->plugins = array();
foreach(array('EVENT_LEFTMENU_TOP',
              'EVENT_LEFTMENU_BOTTOM',
              'EVENT_RIGHTMENU_TOP',
              'EVENT_RIGHTMENU_BOTTOM') as $menu_item) {
  # to be compatible with PHP 5.4
  $menu_content = event_signal($menu_item);
  if( !empty($menu_content) ) {
    $gui->plugins[$menu_item] = $menu_content;
  }
}

$tplKey = 'mainPage';
$tpl = $tplKey . '.tpl';
$tplCfg = config_get('tpl');
if( null !== $tplCfg && isset($tplCfg[$tplKey]) ) {
  $tpl = $tplCfg->$tplKey;
} 

//var_dump($gui->grants);

$smarty->assign('gui',$gui);
$smarty->display($tpl);

/**
 * Get User Documentation 
 * based on contribution by Eugenia Drosdezki
 */
function getUserDocumentation() {
  $target_dir = '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'docs';
  $documents = null;
    
  if ($handle = opendir($target_dir))  {
    while (false !== ($file = readdir($handle))) {
      clearstatcache();
      if (($file != ".") && ($file != "..")) {
        if (is_file($target_dir . DIRECTORY_SEPARATOR . $file)) {
          $documents[] = $file;
        }    
      }
    }
    closedir($handle);
  }
  return $documents;
}

/**
 *
 */
function initArgs(&$dbH) {
  $iParams = array("testproject" => array(tlInputParameter::INT_N),
                   "tproject_id" => array(tlInputParameter::INT_N),
                   "current_tproject_id" => array(tlInputParameter::INT_N),
                   "tplan_id" => array(tlInputParameter::INT_N),
                   "caller" => array(tlInputParameter::STRING_N,1,6),
                   "viewer" => array(tlInputParameter::STRING_N, 0, 3)
                  );
  $args = new stdClass();
  $pParams = G_PARAMS($iParams,$args);

  // Need to understand @20190302
  if( is_null($args->viewer) || $args->viewer == '' ) {
    $args->viewer = isset($_SESSION['viewer']) ? $_SESSION['viewer'] : null;
  }  

  $args->ssodisable = getSSODisable();
  $args->user = $_SESSION['currentUser'];

  // Check if any project exists to display error
  $args->newInstallation = false;

  if( $args->tproject_id == 0 ) {
    $args->tproject_id = $args->testproject;
  }

  if($args->tproject_id <= 0) {
    $sch = tlObject::getDBTables(array('testprojects','nodes_hierarchy'));
    $sql = " SELECT NH.id FROM {$sch['nodes_hierarchy']} NH " .
           " JOIN {$sch['testprojects']} TPRJ " .
           " ON TPRJ.id = NH.id ";
    $rs = (array)$dbH->get_recordset($sql);

    if(count($rs) == 0) {
      $args->newInstallation = true;
    }  
  }  

  return $args;
}