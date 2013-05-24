<?php

class Service_groupsession extends EPLc_Service_IAbstractService {
	
	function perform($userinfo, $groupinfo, $serviceargs) {
		$m = 'Performing groupsession for ' . print_r($serviceargs, true) . ' for user ' . $userinfo->_userId;
		if ($groupinfo != null) { $m .= ' and group ' . $groupinfo->_groupId; }
		Logger_Log::debug($m, 'Service_groupsession');
		
		$oEPLclient = new EtherpadLiteClient(ETHERPADLITE_APIKEY, ETHERPADLITE_BASEURL);
		
		$ep_group = $oEPLclient->createGroupIfNotExistsFor($groupinfo->_groupId['groupId']);
        error_log("USERINFO: " . var_export($userinfo,true));
        error_log("USERINFO->USERID: " . $userinfo->_userId);
        error_log("USERINFO->COMMONNAME: " . $userinfo->_userCommonName);
        $ep_author = $oEPLclient->createAuthorIfNotExistsFor($userinfo->_userData->_userId, $userinfo->_userData->_userCommonName);
		
        error_log(var_export($ep_group,true));
        error_log(var_export($ep_author,true));
		$endtimestamp = time() + ETHERPADLITE_SESSION_DURATION;

		$ep_session = $oEPLclient->createSession(
			$ep_group->groupID, 
			$ep_author->authorID, 
			$endtimestamp);

		$sID = $ep_session->sessionID;
		setcookie("sessionID",$sID, $endtimestamp, '/'); // Set a cookie
		Logger_Log::debug("Created new session with id '{$sID}'", 'Service_groupsession');

        $userId = $userinfo->_userId;
		$result = EPLc_Service_Response::create(true, "Session created succesfully for userId:'{$userId}' and userCN:'{$userinfo->_userCommonName}'");
		$result->setData(array(
				'sessionID' => $sID,
			));
		
		return $result;
	}
}
