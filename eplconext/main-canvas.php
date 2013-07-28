<?php
require_once("lib/all.php");
require_once("include/config.php");

require_once( "include/epl_OAuthStorage.php" );

$userdata = array();

$storage = new EPLc_Storage('padaccesstoken');
// Get userattributes from store
$userattributes = null;
$padaccesstoken = Web_CGIUtil::get_prf_argument($_GET, null, null, 'pat', null);
if ($padaccesstoken != null) {
	$record = $storage->get('userdata', $padaccesstoken, null);
	$userattributes = $record['value'];
}

if ($userattributes != null) {
	// Logger_Log::debug("Retrieved from store: ". print_r($userattributes, true), 'main-canvas.php');
	// only allow one-time-use, so clear from session now:
	$storage->remove('userdata', $padaccesstoken, null);
	$storage->removeExpired();	// maintenance/cleanup
} else {
	print("Unknown or invalid PadAccessToken."); die();
}


/* ================================================================ */
/* is user logged on? */
if (isset($userattributes['conext'])) {
	$userId = $userattributes['conext'];
} else {
	$userId = $userattributes['userinfo']->_userId;
}
if (is_array($userId)) { $userId = join(',', $userId); }
$userCommonName = $userattributes['userinfo']->_userCommonName;
if (is_array($userCommonName)) { $userCommonName = join(',', $userCommonName); }


// ================================================================
// establish current group:
$osGroup = new osapiGroup();
$osGroup->id = array('groupId' => $userattributes['groupinfo']->_groupId);
$osGroup->title = 'undefined';
$osGroup->description = 'undefined';

$groupInstance = Group::fromOsapi($osGroup);
error_log("GROUP INSTANCE: " . var_export($groupInstance,true));
$manager = new EPLc_Manager();
// always explicitly set groupsession
$manager->performParsed("groupsession", $userattributes, $groupInstance, array());


// ================================================================
// establish active pad from the token-data (provided padname must be FQ)
$active_pad = $userattributes['padname'];

$oEPLclient = new MyEtherpadLiteClient();

$userdata = $userattributes;
$padurl = ETHERPADLITE_PADBASEURL . '/' . $active_pad;
error_log("PAD URL: " . $padurl);
$t = MyEtherpadLiteClient::splitGrouppadName($active_pad);
$padname = $t[1];
include("templates/show_pad.php");
exit();
?>
