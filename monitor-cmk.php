<?php

// RackTables Check_MK API Plugin v0.1
// 20150811 - JR Morgan <jr@liveaverage.com>
// http://liveaverage.com

//INSTALLATION NOTES: Requires Check_MK 1.2.6p5 or above to use the webapi functionality

# RackTables Tab
# -----------------------------------------------------------------------------------------
$tab['object']['CheckMK'] = 'Check MK'; // Tab title
$trigger['object']['CheckMK'] = 'triggerCMK'; // Trigger (only when FQDN is present)
$tabhandler['object']['CheckMK'] = 'renderCMK'; // Register Tab function
# -----------------------------------------------------------------------------------------

# Settings
# -----------------------------------------------------------------------------------------
require_once "monitor-cmk-conf.php";
# -----------------------------------------------------------------------------------------

function triggerCMK()
{
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;

	assertUIntArg ('object_id', __FUNCTION__);
	$object = spotEntity ('object', $_REQUEST['object_id']);	
	$attributes = getAttrValues ($object['id'], TRUE);
	if(strlen($attributes[$attribute_id]['value'])) 
	{
	   return 1;
	}
	else
	{
	   return '';
	}

}

function renderCMK()
{

	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $cmk_gethost, $attribute_id;

	# Load object data
	assertUIntArg ('object_id', __FUNCTION__);
	$object = spotEntity ('object', $_REQUEST['object_id']);	
	$attributes = getAttrValues ($object['id'], TRUE);
	if(strlen($attributes[$attribute_id]['value'])) 
	{
	   $target = $attributes[$attribute_id]['value'];
	}
	else
	{
	   $target = $object['name'];
	}

	 $cmk_gethost = $cmk_url.'webapi.py?action=get_host&_username='.$cmk_user.'&_secret='.$cmk_pass.'&effective_attributes=1';
	 $cmk_url_data = '{"hostname":"'.$target.'"}';

	 # Curl request
	 $output = CMKcurl($cmk_gethost,'request='.$cmk_url_data);

	 $json_o = json_decode($output);

	 #If object exists, set flag
	 $exists = ((strpos($output,'not exist') !== false) ? 0 : 1);

	 //If host is already monitored, show the health status:
	if($exists)
	{
		echo '<div class=portlet>';
		echo renderCMKstatus($target);

	}

	echo htmlExtras();

	echo '<div class=portlet><h2>Configuration</h2><h3>'.$target.'</h3>';

	echo '<table class=status border="0" cellspacing="0" cellpadding="3" width="100%"><tbody>';


	foreach ($json_o->result->attributes as $key => $value)
	{
		if($key=="contactgroups")
		{
			$value = implode(", ",$value[1]);
		}

		echo '<tr><th width="50%" class="tdright">'.ucfirst(str_replace('_', ' ', $key)).'</th><td class="tdleft">'.(is_array($value) ? implode(", ",$value) : $value).'</td></tr>';
	}

	echo '</tbody></table></div>';

	//Check_MK Controls:
	echo '<div class=portlet>';

	if($exists)
	{
		echo '<input type="button" onclick="location.href=\''.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&reinventory\';" value="Reinventory services" />';
		echo '<input type="button" onclick="location.href=\''.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&renew\';" value="Renew host" />';
		echo '<input type="button" onclick="location.href=\''.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&remove\';" value="Remove host" />';
		echo '<input type="button" onclick="location.href=\''.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&activate\';" value="Activate all changes" />';
	}
	else
	{
		//echo '<input type="submit" value="Add Host" name="hostadd">&nbsp;';
		echo '<input type="button" onclick="location.href=\''.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'&add\';" value="Add host" />';
	}

	echo '</div>';

	//CMK Raw curl/json output:
	// if ($cmk_debug)
	// {
	// 	echo '<div class=portlet><h2>Debug</h2>'.$output;

	// 	echo '<p>Location: '.getLocation($object).'</p></div>';
	// }

	if ( isset($_GET['remove']) && $exists )
	{
		//Delete host
		resultMsg($target, "removed", CMKremove($target));
    	header('Refresh: 5; URL='.$_SERVER['PHP_SELF'].'?'.(str_replace('&remove', '', $_SERVER['QUERY_STRING'])));

	}

	if ( isset($_GET['renew']) && $exists )
	{
		//Renew entire host - results displayed from function call
		CMKrenew($object, $target, $dest);
    	header('Refresh: 5; URL='.$_SERVER['PHP_SELF'].'?'.(str_replace('&renew', '', $_SERVER['QUERY_STRING'])));

	}

	if ( isset($_GET['reinventory']) && $exists )
	{
		//Reinventory host services
		resultMsg($target, "reinventoried", CMKreinventory($target));
    	header('Refresh: 5; URL='.$_SERVER['PHP_SELF'].'?'.(str_replace('&reinventory', '', $_SERVER['QUERY_STRING'])));

	}

	if ( isset($_GET['activate']) && $exists )
	{
		//Reinventory host services
		resultMsg($target, "activated", CMKactivate());
    	header('Refresh: 5; URL='.$_SERVER['PHP_SELF'].'?'.(str_replace('&activate', '', $_SERVER['QUERY_STRING'])));

	}

	if ( isset($_GET['add']) && !$exists)
	{
		//Add a new host that doesn't exist using FQDN
		resultMsg($target, "added", CMKadd($object,$target,$dest));
    	header('Refresh: 5; URL='.$_SERVER['PHP_SELF'].'?'.(str_replace('&add', '', $_SERVER['QUERY_STRING'])));

	}
}

function renderCMKstatus($target)
{
	
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;
	$cmk_url_status = $cmk_url.'view.py?view_name=hoststatus&host='.$target.'&output_format=JSON&_username='.$cmk_user.'&_secret='.$cmk_pass;
	$status = json_decode(CMKcurl($cmk_url_status, ""));

	echo '<div class=portlet><h2>Status</h2><h3>'.$target.'</h3>';
	echo '<table class=status border="0" cellspacing="0" cellpadding="3" width="100%"><tbody>';
	$tr = "";
	$te = "";
	$i = -1;

	foreach ($status[0] as $key)
	{
		$i++;
		$value = $status[1][$i];
		//Only show some of the status fields, but uncomment for more:
		if ($i == 4)
		{
			if ($value == "UP")
			{
				$te .= '<tr><th colspan="2" width="50%" class="success"><h2>'.ucfirst(str_replace('_', ' ', $key)).' is UP</h2></th></tr>';
			}
			else
			{
				$te .= '<tr><th colspan="2" width="50%" class="warning"><h2>'.ucfirst(str_replace('_', ' ', $key)).' reflects HOST problems!</h2></th></tr>';
			}
			
		}
		elseif (preg_match("/crit/i", $key) && $value > 0)
		{

				$te .= '<tr><th colspan="2" width="50%" class="error"><h2>'.ucfirst(str_replace('_', ' ', $key)).' reflects '.$value.' SERVICE problems!</h2></th></tr>';
			
		}
		elseif (preg_match("/warn/i", $key) && $value > 0)
		{

				$te .= '<tr><th colspan="2" width="50%" class="error"><h2>'.ucfirst(str_replace('_', ' ', $key)).' reflects '.$value.' SERVICE problems!</h2></th></tr>';
			
		}
		if ( $i == 5 || $i == 6 || $i == 12 || preg_match("/services/i", $key))
		{
			$tr .= '<tr><th width="50%" class="tdright">'.ucfirst(str_replace('_', ' ', $key)).'</th><td class="tdleft">'.(is_array($value) ? implode(", ",$value) : $value).'</td></tr>';
		}

	}

	echo $te;
	echo $tr;
	echo '</tbody></table></div>';

}

function CMKadd($object, $target, $dest)
{
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;

	//Add new host
	$loc = explode(':',preg_replace('/\s+/', '', getLocation($object)));
	$dest = strtolower($cmk_fld.'/row_'.($loc[0]).'/'.$cmk_pre.'-'.($loc[1]));

	//Query for appropriate agent tag (Check_MK, SNMP, or Ping-only)
        $tag_agent = CMKagentcheck($target);

	$cmk_url_addhost = $cmk_url.'webapi.py?action=add_host&_username='.$cmk_user.'&_secret='.$cmk_pass.'&effective_attributes=1';
	$cmk_url_adddata = array( 
		'attributes' => array(
			'tag_agent'=> $tag_agent,
			'tag_criticality' => 'prod'),
		'hostname' => urlencode($target),
		'folder' => urlencode($dest)
		);

	return CMKcurl($cmk_url_addhost, 'request='.(json_encode($cmk_url_adddata)));
}

function CMKremove($target)
{
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;

	$cmk_url_rmhost = $cmk_url.'webapi.py?action=delete_host&_username='.$cmk_user.'&_secret='.$cmk_pass.'&effective_attributes=1';
	$cmk_url_rmdata = array( 
		'hostname' => urlencode($target),
		);

	return CMKcurl($cmk_url_rmhost, 'request='.(json_encode($cmk_url_rmdata)));
}

function CMKrenew($object, $target, $dest)
{
	//Simply a call of CMKremove + CMKadd -- webapi.py currently has no support for moving hosts between folders

	resultMsg($target, "removed", CMKremove($target));
	resultMsg($target, "added", CMKadd($object,$target,$dest));

}


function CMKreinventory($target)
{
	//Simply a call of CMKremove + CMKadd -- webapi.py currently has no support for moving hosts between folders
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;

	$cmk_url_refhost = $cmk_url.'webapi.py?action=discover_services&_username='.$cmk_user.'&_secret='.$cmk_pass.'&mode=fixall'; //Others might not want the FIXALL mode
	$cmk_url_refdata = array( 
		'hostname' => urlencode($target),
		);

	return CMKcurl($cmk_url_refhost, 'request='.(json_encode($cmk_url_refdata)));
}

function CMKactivate()
{
	global $cmk_user, $cmk_pass, $cmk_url, $cmk_fld, $cmk_pre, $cmk_debug, $attribute_id;

	//Activates all pending Check_MK changes -- be careful!
	$cmk_url_refhost = $cmk_url.'webapi.py?action=activate_changes&_username='.$cmk_user.'&_secret='.$cmk_pass.'&mode=all'; //Others might not want to activate ALL site changes

	return CMKcurl($cmk_url_refhost, "");
}

function CMKagentcheck($target)
{
	global $cmk_snmp;
	$tag_agent = 'ping';

	$fp_cmka = fsockopen("tcp://".$target, 6556);
	$fp_snmp = snmpget($target, $cmk_snmp, "sysName.0");

	if ($fp_cmka)
	{
		//Detected CMK Agent (always use first)
		$tag_agent = 'cmk-agent';
	}
	elseif ($fp_snmp)
	{
		//Detected SNMP is listening
		$tag_agent = 'snmp-only';
	}

	fclose($fp_cmka);

	return $tag_agent;

}

function resultMsg($target, $action, $result)
{
		if (strcasecmp($result,'{"result": null, "result_code": 0}') == 0 || strpos($result,'successful') !== false)
		{
			echo '<div class="success"><h2>Success:</h2> Host <strong>'.$target.'</strong> was '.$action.' <br>JSON Result: '.$result.'</div>';
		}
		else
		{
			echo '<div class="error"><h2>Failure</h2> Host <strong>'.$target.'</strong> was not '.$action.' <br>JSON Result: '.$result.'</div>'; 
		}
}

function CMKcurl($url, $data)
{
	 	
	# Curl request
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);

	if($data != "")
	{ 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	 return $output;
}

function htmlExtras () 
{
    echo '
      <script type="text/javascript">
          function popup (url) {
              popup = window.open(url, "Nagios", "width=1024,height=800,resizable=yes");
              popup.focus();
              return false;
          }
      </script>
      <style type="text/css">
      	  input[type=button]  { margin: 5px; }
      	  .info, .success, .warning, .error, .validation {
			border: 1px solid;
			margin: 10px 0px;
			padding:15px 10px 15px 50px;
			background-repeat: no-repeat;
			background-position: 10px center;
			}
			.info {
			color: #00529B;
			background-color: #BDE5F8;
			}
			.success {
			color: #4F8A10;
			background-color: #DFF2BF;
			}
			.warning {
			color: #9F6000;
			background-color: #FEEFB3;
			}
			.error {
			color: #D8000C;
			background-color: #FFBABA;
			}
          .status { font-family: arial,serif;  background-color: white;  color: black; }
          .errorMessage { font-family: arial,serif;  text-align: center;  color: red;  font-weight: bold;  font-size: 12pt; }
          .errorDescription { font-family: arial,serif;  text-align: center;  font-weight: bold;  font-size: 12pt; }
          .warningMessage { font-family: arial,serif;  text-align: center;  color: red;  font-weight: bold;  font-size: 10pt; }
          .infoMessage { font-family: arial,serif;  text-align: center;  color: red;  font-weight: bold; }
          .infoBox { font-family: arial,serif;  font-size: 8pt;  background-color: #C4C2C2;  padding: 2; }
          .infoBoxTitle { font-family: arial,serif;  font-size: 10pt;  font-weight: bold; }
          .infoBoxBadProcStatus { font-family: arial,serif;  color: red; }
          A.homepageURL:Hover { font-family: arial,serif;  color: red; }
          .linkBox { font-family: arial,serif;  font-size: 8pt;  background-color: #DBDBDB;  padding: 1; }
          .filter { font-family: arial,serif;  font-size: 8pt;  background-color: #DBDBDB; }
          .filterTitle { font-family: arial,serif;  font-size: 10pt;  font-weight: bold;  background-color: #DBDBDB; }
          .filterName { font-family: arial,serif;  font-size: 8pt;  background-color: #DBDBDB; }
          .filterValue { font-family: arial,serif;  font-size: 8pt;  background-color: #DBDBDB; }
          .itemTotalsTitle { font-family: arial,serif;  font-size: 8pt;  text-align: center; }
          .statusTitle { font-family: arial,serif;  text-align: center;  font-weight: bold;  font-size: 12pt; }
          .statusSort { font-family: arial,serif;  font-size: 8pt; }
          TABLE.status { font-family: arial,serif;  font-size: 8pt;  background-color: white;  padding: 2; }
          TH.status { font-family: arial,serif;  font-size: 10pt;  text-align: left;  background-color: #999797;  color: #DCE5C1; }
          DIV.status { font-family: arial,serif;  font-size: 10pt;  text-align: center; }
          .statusOdd { font-family: arial,serif;  font-size: 8pt;  background-color: #DBDBDB; }
          .statusEven { font-family: arial,serif;  font-size: 8pt;  background-color: #C4C2C2; }
          .statusPENDING { font-family: arial,serif;  font-size: 8pt;  background-color: #ACACAC; }
          .statusOK { font-family: arial,serif;  font-size: 8pt;  background-color: #33FF00; }
          .statusRECOVERY { font-family: arial,serif;  font-size: 8pt;  background-color: #33FF00; }
          .statusUNKNOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #FF9900; }
          .statusWARNING { font-family: arial,serif;  font-size: 8pt;  background-color: #FFFF00; }
          .statusCRITICAL { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTPENDING { font-family: arial,serif;  font-size: 8pt;  background-color: #ACACAC; }
          .statusHOSTUP { font-family: arial,serif;  font-size: 8pt;  background-color: #33FF00; }
          .statusHOSTDOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTDOWNACK { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTDOWNSCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTUNREACHABLE { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTUNREACHABLEACK { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusHOSTUNREACHABLESCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838; }
          .statusBGUNKNOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #FFDA9F; }
          .statusBGUNKNOWNACK { font-family: arial,serif;  font-size: 8pt;  background-color: #FFDA9F; }
          .statusBGUNKNOWNSCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #FFDA9F; }
          .statusBGWARNING { font-family: arial,serif;  font-size: 8pt;  background-color: #FEFFC1; }
          .statusBGWARNINGACK { font-family: arial,serif;  font-size: 8pt;  background-color: #FEFFC1; }
          .statusBGWARNINGSCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #FEFFC1; }
          .statusBGCRITICAL { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGCRITICALACK { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGCRITICALSCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGDOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGDOWNACK { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGDOWNSCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGUNREACHABLE { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGUNREACHABLEACK { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          .statusBGUNREACHABLESCHED { font-family: arial,serif;  font-size: 8pt;  background-color: #FFBBBB; }
          DIV.serviceTotals { font-family: arial,serif;  text-align: center;  font-weight: bold;  font-size: 10pt; }
          TABLE.serviceTotals { font-family: arial,serif;  font-size: 10pt;  background-color: white;  padding: 2; }
          TH.serviceTotals,A.serviceTotals { font-family: arial,serif;  font-size: 10pt;  background-color: white;  text-align: center;  background-color: #999797;  color: #DCE5C1; }
          TD.serviceTotals { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #e9e9e9; }
          .serviceTotalsOK { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #33FF00; }
          .serviceTotalsWARNING { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #FFFF00;  font-weight: bold; }
          .serviceTotalsUNKNOWN { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #FF9900;  font-weight: bold; }
          .serviceTotalsCRITICAL { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #F83838;  font-weight: bold; }
          .serviceTotalsPENDING { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #ACACAC; }
          .serviceTotalsPROBLEMS { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: orange;  font-weight: bold; }
          DIV.hostTotals { font-family: arial,serif;  text-align: center;  font-weight: bold;  font-size: 10pt; }
          TABLE.hostTotals { font-family: arial,serif;  font-size: 10pt;  background-color: white;  padding: 2; }
          TH.hostTotals,A.hostTotals { font-family: arial,serif;  font-size: 10pt;  background-color: white;  text-align: center;  background-color: #999797;  color: #DCE5C1; }
          TD.hostTotals { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #e9e9e9; }
          .hostTotalsUP { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #33FF00; }
          .hostTotalsDOWN { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #F83838;  font-weight: bold; }
          .hostTotalsUNREACHABLE { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #F83838;  font-weight: bold; }
          .hostTotalsPENDING { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: #ACACAC; }
          .hostTotalsPROBLEMS { font-family: arial,serif;  font-size: 8pt;  text-align: center;  background-color: orange;  font-weight: bold; }
          .miniStatusPENDING { font-family: arial,serif;  font-size: 8pt;  background-color: #ACACAC;  text-align: center; }
          .miniStatusOK { font-family: arial,serif;  font-size: 8pt;  background-color: #33FF00;  text-align: center; }
          .miniStatusUNKNOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #FF9900;  text-align: center; }
          .miniStatusWARNING { font-family: arial,serif;  font-size: 8pt;  background-color: #FFFF00;  text-align: center; }
          .miniStatusCRITICAL { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838;  text-align: center; }
          .miniStatusUP { font-family: arial,serif;  font-size: 8pt;  background-color: #33FF00;  text-align: center; }
          .miniStatusDOWN { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838;  text-align: center; }
          .miniStatusUNREACHABLE { font-family: arial,serif;  font-size: 8pt;  background-color: #F83838;  text-align: center; }
          .hostImportantProblem { text-align: left;  font-family: arial;  font-size: 8pt;  background-color: #ff0000;  color: black; text-decoration: blink; }
          .hostUnimportantProblem { text-align: left;  font-family: arial;  font-size: 8pt;  background-color: #ffcccc;  color: black; }
          .serviceImportantProblem { text-align: left;  font-family: arial;  font-size: 8pt;  background-color: #ff0000;  color: black; text-decoration: blink; }
          .serviceUnimportantProblem { text-align: left;  font-family: arial;  font-size: 8pt;  background-color: #ffcccc;  color: black; }
      </style>
    ';
}


?>


