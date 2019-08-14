<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 *
 * @filesource  navBar.php
 *
 * Manages the navigation bar. 
 *
 *
**/
require_once('../../config.inc.php');
require_once("common.php");
$context = testlinkInitPage($db,('initProject' == 'initProject'));

$args = init_args($db,$context);
$gui = initializeGui($db,$args);

$smarty = new TLSmarty();
$smarty->assign('gui',$gui);
$smarty->display('navBar.tpl');


/**
 * 
 */
function getGrants(&$db,&$userObj) {
  $grants = new stdClass();
  $grants->view_testcase_spec = $userObj->hasRight($db,"mgt_view_tc");
  return $grants;  
}

/**
 * 
 */
function init_args(&$dbH,$context) {
	$iParams = array("testproject" => array(tlInputParameter::INT_N),
                   "tproject_id" => array(tlInputParameter::INT_N),
                   "caller" => array(tlInputParameter::STRING_N,1,6),
                   "viewer" => array(tlInputParameter::STRING_N, 0, 3),
                   "tplan_id" => array(tlInputParameter::INT_N)
                  );
	$args = new stdClass();
	$pParams = G_PARAMS($iParams,$args);

  if( is_null($args->viewer) || $args->viewer == '' ) {
    $args->viewer = isset($_SESSION['viewer']) ? $_SESSION['viewer'] : null;
  }  

  $args->ssodisable = getSSODisable();
  $args->user = $_SESSION['currentUser'];

  // Check if any project exists to display error
  $args->newInstallation = false;
  $args->tproject_id = intval($args->testproject);

  if($args->testproject <= 0) {
    $sch = tlObject::getDBTables(array('testprojects','nodes_hierarchy'));
    $sql = " SELECT NH.id, NH.name FROM {$sch['nodes_hierarchy']} NH " .
           " JOIN {$sch['testprojects']} TPRJ " .
           " ON TPRJ.id = NH.id ";
    $rs = (array)$dbH->get_recordset($sql);

    if(count($rs) == 0) {
      $args->newInstallation = true;
    }  

    if( null != $context ) {
      $args->tproject_id = $context->tproject_id;      
    }
  }  

  $args->testproject = $args->tproject_id;
	return $args;
}

/**
 *
 */
function initializeGui(&$db,&$args) {

  $gui = new stdClass();
  $opt = array('forceCreateProj' => $args->newInstallation);
  list($add2args,$gui) = initUserEnv($db,$opt); 

  $tproject_mgr = new testproject($db);
  $guiCfg = config_get("gui");

  $gui->tproject_id = $gui->tprojectID = $args->tproject_id;

  $opx = array('output' => 'map_name_with_inactive_mark',
               'field_set' => $guiCfg->tprojects_combo_format,
               'order_by' => $guiCfg->tprojects_combo_order_by);

  $gui->TestProjects = $tproject_mgr->get_accessible_for_user($args->user->dbID,$opx);

  $gui->TestProjectCount = sizeof($gui->TestProjects);
  if($gui->TestProjectCount == 0) {
    $gui->TestProjects = null;
   $gui->tproject_id = $gui->tprojectID = 0;
  } 

  if( $gui->tproject_id <= 0 ) {
    $ckObj = new stdClass();
    $ckCfg = config_get('cookie');

    // Try to get from Cookie
    $ckObj->name = $ckCfg->prefix . 
       "TL_user${_SESSION['userID']}_testProject";

    if( isset($_COOKIE[$ckObj->name]) ) {
      $gui->tproject_id = $gui->tprojectID = intval($_COOKIE[$ckObj->name]);
    }  
  }


  if($gui->tproject_id <= 0 && !$args->newInstallation) {
    // Well instead of this, try to get the firts test project 
    // user is enabled to.
    if( 0 == $gui->TestProjectCount ) {
      throw new Exception("Can't work without Test Project ID", 1);
    }
    $theOne = current(array_keys($gui->TestProjects));
    $gui->tproject_id = $gui->tprojectID = $theOne;
  }  

  $gui->tcasePrefix = '';
  $gui->searchSize = 8;
  $gui->tcasePrefix = $tproject_mgr->getTestCasePrefix($gui->tproject_id) . config_get('testcase_cfg')->glue_character;

  $gui->searchSize = tlStringLen($gui->tcasePrefix) + 
                     $guiCfg->dynamic_quick_tcase_search_input_size;

  $gui->TestPlanCount = 0; 

  $tprojectQty = $tproject_mgr->getItemCount();  
  if($gui->TestProjectCount == 0 && $tprojectQty > 0) {
    // User rights configurations does not allow 
    // access to ANY test project
    $_SESSION['testprojectTopMenu'] = '';
    $gui->tproject_id = 0;
  }

  if( $gui->tproject_id ) {
    $testPlanSet = (array)$args->user->getAccessibleTestPlans($db,$gui->tproject_id);
    $gui->TestPlanCount = sizeof($testPlanSet);

    if( $args->tplan_id > 0 ) {
      // Need to set this info on session with 
      // first Test Plan from $testPlanSet
      // if this test plan is present on $testPlanSet
      //    OK we will set it on $testPlanSet as selected one.
      // else 
      //    need to set test plan on session
      //
      $index=0;
      $testPlanFound=0;
      $loop2do=count($testPlanSet);
      for($idx=0; $idx < $loop2do; $idx++) {
        if( $testPlanSet[$idx]['id'] == $tplanID ) {
          $testPlanFound = 1;
          $index = $idx;
          break;
        }
      }

      if( $testPlanFound == 0 && is_array($testPlanSet) ) {
        $args->tplan_id = $testPlanSet[0]['id'];
        // setSessionTestPlan($testPlanSet[0]);      
      } 
      $testPlanSet[$index]['selected']=1;
    }
  } 
  $gui->tplan_id = $args->tplan_id;

  if ($gui->tproject_id && isset($args->user->tprojectRoles[$gui->tproject_id])) {
    // test project specific role applied
    $role = $args->user->tprojectRoles[$gui->tprojectID];
    $testprojectRole = $role->getDisplayName();
  } else {
    // general role applied
    $testprojectRole = $args->user->globalRole->getDisplayName();
  } 
  $gui->whoami = $args->user->getDisplayName() . ' ' . 
                 $guiCfg->role_separator_open . 
                 $testprojectRole . $guiCfg->role_separator_close;
                   

  // only when the user has changed project using 
  // the combo the _GET has this key.
  // Use this clue to launch a refresh of other 
  // frames present on the screen
  // using the onload HTML body attribute
  $gui->updateMainPage = 0;
  if( $args->tproject_id > 0) {
    // set test project ID for the next session
    $gui->updateMainPage = is_null($args->caller);

    $ckCfg = config_get('cookie');    
    $ckObj = new stdClass();
    $ckObj->name = $ckCfg->prefix . 'TL_lastTestProjectForUserID_'. 
                   $args->user->dbID;
    $ckObj->value = $args->testproject;
    tlSetCookie($ckObj);
  }

  $gui->viewer = $args->viewer;

  $gui->plugins = array();
  foreach(array('EVENT_TITLE_BAR') as $menu_item) {
    $menu_content = event_signal($menu_item);
    $gui->plugins[$menu_item] = !empty($menu_content) ? $menu_content : null;
  }

  $gui->ssodisable = $args->ssodisable;
  $sso = ($args->ssodisable ? '&ssodisable' : '');  
  $gui->logout = 'logout.php?viewer=' . $sso;

  // to do not break logic, it will be better to remove this
  $gui->testprojectID = $gui->tproject_id;  
  
  return $gui;
}
