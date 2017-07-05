<?php

use WHMCS\Database\Capsule;

function freeradius_ConfigOptions(){
  $configarray = array(
    "radius_group" => array (
      "FriendlyName" => "Radius Group",
      "Type" => "text",
      "Size" => "25",
      "Description" => "FreeRADIUS group name"
    ),
    "usage_limit" => array (
      "FriendlyName" => "Usage Limit",
      "Type" => "text",
      "Size" => "25",
      "Description" => "In bytes. 0 or blank to disable"
    ),
    "rate_limit" => array (
      "FriendlyName" => "Rate Limit",
      "Type" => "text",
      "Size" => "25",
      "Description" => "Router specific. 0 or blank to disable"
    ),
    "session_limit" => array (
      "FriendlyName" => "Session Limit",
      "Type" => "text",
      "Size" => "5",
      "Description" => "Fixed number. 0 or balnk to disable"
    ),
    "account_prefix" => array (
      "FriendlyName" => "Prefix",
      "Type" => "text",
      "Size" => "10",
      "Description" => "Prefix"
    ),
    "realm" => array (
      "FriendlyName" => "Realm",
      "Type" => "text",
      "Size" => "25",
      "Description" => "Realm"
    )
  );
  return $configarray;
}

function freeradius_AdminServicesTabFields($params){
  $username = $params["username"];
  $serviceid = $params["serviceid"];

  $collected = collect_usage($params);

  $fieldsarray = array(
   '# of Logins' => $collected['logins'],
   'Accumalated Hours Online' => secs_to_h( $collected['logintime'] ),
   'Total Usage' => byte_size( $collected['total'] ),
   'Uploaded' => byte_size( $collected['uploads'] ),
   'Downloaded' => byte_size( $collected['downloads'] ),
   'Usage Limit' => byte_size( $collected['usage_limit'] ),
   'Status' => $collected['status']
  );
  return $fieldsarray;
}

function freeradius_ClientArea($params){
  $username = $params["username"];
  $serviceid = $params["serviceid"];

  $collected = collect_usage($params);

  return array(
    'templatefile' => 'clientarea',
    'vars' => array(
      'logins' => $collected['logins'],
      'logintime' => secs_to_h( $collected['logintime'] ),
      'logintime_seconds' => $collected['logintime'],
      'uploads' => byte_size( $collected['uploads'] ),
      'uploads_bytes' => $collected['uploads'],
      'downloads' => byte_size( $collected['downloads'] ),
      'downloads_bytes' => $collected['downloads'],
      'total' => byte_size( $collected['total'] ),
      'total_bytes' => $collected['total'],
      'limit' => byte_size( $collected['usage_limit']),
      'limit_bytes' => $collected['usage_limit'],
      'status' => $collected['status'],
    ),
  );
}

function freeradius_username($email, $groupname){
  global $CONFIG;

  $usernameExists = function($username) {
      return Capsule::table('tblhosting')
           ->where('username', '=', $username)
           ->exists();
  };

  $suffix = 0;

  $username = $email . "@" . $groupname;
  while ($usernameExists($username)) {
      $suffix++;
      $username = $email . $suffix . "@" . $groupname;
  }

  return $username;
}

function freeradius_CreateAccount($params){
  $username = $params["username"];
  $password = $params["password"];
  $groupname = $params["configoption1"];
  $firstname = $params["clientsdetails"]["firstname"];
  $lastname = $params["clientsdetails"]["lastname"];
  $email = $params["clientsdetails"]["email"];
  $phonenumber = $params["clientsdetails"]["phonenumber"];
  $rate_limit = $params["configoption3"];
  $session_limit = $params["configoption4"];
  $account_prefix = $params["configoption5"];
  $realm = $params["configoption6"];

  if( !$username || strpos($username, $realm) !== true){
    $username = freeradius_username( $account_prefix, $realm);

    Capsule::table('tblhosting')
           ->where('id', '=', $params["serviceid"])
           ->update(array(
               "username" => $username,
           ));
  }

  $freeradiussql = freeradius_DatabaseConnect($params);
  if (is_string($freeradiussql)) {
       return $freeradiussql; // Error condition
   }

   try {
       $count = $freeradiussql
           ->from('radcheck')
           ->where('username', '=', $username)
           ->count();

       if ($count > 0) {
           return "Username Already Exists";
       }

       $freeradiussql
           ->from('radcheck')
           ->insert(array(
               'username' => $username,
               'attribute' => 'User-Password',
               'value' => $password,
               'op' => ':=',
           ));

       $freeradiussql
           ->from('radusergroup')
           ->insert(array(
               'username' => $username,
               'groupname' => $groupname,
           ));

	foreach( $params["configoptions"] as $key => $value ){
	  if( $key == 'Rate Limit' ){
	    $rate_limit = $value;
	  }
	  if( $key == 'Session Limit' ){
	    $session_limit = $value;
	  }
	}

	if( $rate_limit ){
          $freeradiussql
              ->from('radreply')
              ->insert(array(
                 'username' => $username,
                 'attribute' => 'Mikrotik-Rate-Limit',
		 'value' => $rate_limit,
		 'op' => ':=',
              ));
	}

	if( $session_limit ){
	  $freeradiussql
              ->from('radcheck')
              ->insert(array(
		 'username' => $username,
		 'attribute' => 'Simultaneous-Use',
		 'value' => $session_limit,
		 'op' => ':=',
	      ));
	}

   } catch (\Exception $e) {
       return "FreeRadius Database Query Error: " . $e->getMessage();
   }

  return "success";
}

function freeradius_SuspendAccount($params){
  $username = $params["username"];

  $freeradiussql = freeradius_DatabaseConnect($params);
  if (is_string($freeradiussql)) {
       return $freeradiussql; // Error condition
  }

  try {
	  $count = $freeradiussql
		  ->from('radcheck')
		  ->where('username', '=', $username)
		  ->count();
	  if (!$count) {
		  return "User Not Found";
	  }
	  $count = $freeradiussql
		  ->from('radcheck')
		  ->where('username', '=', $username)
		  ->where('attribute', '=', 'Expiration')
		  ->count();
	  if (!$count) {
		$freeradiussql
                    ->from('radcheck')
                    ->insert(array(
			'username' => $username,
			'attribute' => 'Expiration',
			'value' => date("d F Y"),
			'op' => ':=',
			));
	  } else {
		  $freeradiussql
			  ->from('radcheck')
			  ->where('username', '=', $username)
			  ->where('attribute', '=', 'Expiration')
			  ->update(array(
					  'value' => date("d F Y"),
					  'op' => ':=',
					));
	  }
  } catch (\Exception $e) {
	  return "FreeRadius Database Query Error: " . $e->getMessage();
  }

  return "success";
}

function freeradius_UnsuspendAccount($params){
    $username = $params["username"];

    $freeradiussql = freeradius_DatabaseConnect($params);
    if (is_string($freeradiussql)) {
        return $freeradiussql; // Error condition
    }

    try {
        $affectedRows = $freeradiussql
            ->from('radcheck')
            ->where('username', '=', $username)
            ->where('attribute', '=', 'Expiration')
            ->delete();

        if (!$affectedRows) {
            return "User Not Currently Suspended";
        }

    } catch (\Exception $e) {
          return "FreeRadius Database Query Error: " . $e->getMessage();
  }
  return "success";
}

function freeradius_TerminateAccount($params){
    $username = $params["username"];

    $freeradiussql = freeradius_DatabaseConnect($params);
    if (is_string($freeradiussql)) {
        return $freeradiussql; // Error condition
    }

    try {
        $freeradiussql
            ->from('radreply')
            ->where('username', '=', $username)
            ->delete();

        $freeradiussql
            ->from('radusergroup')
            ->where('username', '=', $username)
            ->delete();

        $freeradiussql
            ->from('radcheck')
            ->where('username', '=', $username)
            ->delete();

    } catch (\Exception $e) {
        return "FreeRadius Database Query Error: " . $e->getMessage();
    }

    return "success";
}

function freeradius_ChangePassword($params){
    $username = $params["username"];
    $password = $params["password"];

    $freeradiussql = freeradius_DatabaseConnect($params);
    if (is_string($freeradiussql)) {
        return $freeradiussql; // Error condition
    }

    try {
        $count = $freeradiussql
            ->from('radcheck')
            ->where('username', '=', $username)
            ->count();

        if (!$count) {
            return "User Not Found";
        }

        $freeradiussql
            ->from('radcheck')
            ->where('username', '=', $username)
            ->where('attribute', '=', 'User-Password')
            ->update(array(
                'value' => $password,
            ));

    } catch (\Exception $e) {
        return "FreeRadius Database Query Error: " . $e->getMessage();
    }

    return "success";
}

function freeradius_ChangePackage($params){
    $username = $params["username"];
    $groupname = $params["configoption1"];
    $rate_limit = $params["configoption3"];
    $session_limit = $params["configoption4"];

    $freeradiussql = freeradius_DatabaseConnect($params);
    if (is_string($freeradiussql)) {
        return $freeradiussql; // Error condition
    }

    try {
        $count = $freeradiussql
            ->from('radusergroup')
            ->where('username', '=', $username)
            ->count();

        if (!$count) {
            return "User Not Found";
        }

        $freeradiussql
            ->from('radusergroup')
            ->where('username', '=', $username)
            ->update(array( 
                'groupname' => $groupname,
            ));

	foreach ($params["configoptions"] as $key => $value) {
		if ($key == 'Rate Limit') {
			$rate_limit = $value;
		}
		if ($key == 'Session Limit') {
			$session_limit = $value;
		}
	}
	if( $rate_limit ) {
		$freeradiussql
		    ->from('radreply')
		    ->where('username', '=', $username)
		    ->where('attribute', '=', 'Mikrotik-Rate-Limit')
		    ->update(array(
			'value' => $rate_limit
		    ));
	}
	if( $session_limit ) {
		$freeradiussql
		    ->from('radcheck')
		    ->where('username', '=', $username)
		    ->where('attribute', '=', 'Simultaneous-Use')
		    ->update(array(
			'value' => $session_limit
		    ));
	}

    } catch (\Exception $e) {
        return "FreeRadius Database Query Error: " . $e->getMessage();
    }

    return "success";
}

function freeradius_update_ip_address($params){

  $username = $params["username"];

  $dedicatedip = Capsule::table('tblhosting')
           ->where('id', '=', $params["serviceid"])
           ->where('username', $username)
           ->value('dedicatedip');

  $freeradiussql = freeradius_databaseConnect($params);
  try {
	$freeradiussql
		->from('radreply')
		->where('username', '=', $username)
		->where('attribute', '=', 'Framed-IP-Address')
		->delete();

  	if( $dedicatedip ){
		$freeradiussql
			->from('radreply')
			->insert(array(
				'username' => $username,
				'attribute' => 'Framed-IP-Address',
				'value'	=> $dedicatedip,
				'op' => ':='
			));
  	}
  } catch (\Exception $e) {
      return "FreeRadius Database Query Error: " . $e->getMessage();
  }
  return "success";
}

function freeradius_send_packet_of_disconnect($params){

  $username = $params["username"];

  $freeradiussql = freeradius_databaseConnect($params);
  try {
	  $data = $freeradiussql
			->from('radacct')
			->where('username', $username)
			->latest('acctstarttime')
			->first();

	$RadiusIP = '';
	$RadiusPassword = '';

	$Command = 'echo "NAS-IP-Address=\"'.$data->nasipaddress.'\", User-Name=\"'.$username.'\", Acct-Session-Id=\"'.$data->acctsessionid.'\", Framed-IP-Address=\"'.$data->framedipaddress.'\"" | radclient -r1 -x '.$data->nasipaddress.' disconnect '.$RadiusPassword.' 2>&1';
	
	  logActivity("Disconnect: " . $Command, $params['userid']);

	 $output = shell_exec($Command);

	logActivity("Output: " . $output); 
	#logActivity("Result: " . $result); 

  } catch (\Exception $e) {
      return "FreeRadius Database Query Error: " . $e->getMessage();
  }
  return "success";
}

function freeradius_AdminCustomButtonArray(){
    $buttonarray = array(
   "Update IP Address" => "update_ip_address",
   "Disconnect" => "send_packet_of_disconnect"
  );
  return $buttonarray;
}

function date_range($nextduedate, $billingcycle) {
  $year = substr( $nextduedate, 0, 4 );
  $month = substr( $nextduedate, 5, 2 );
  $day = substr( $nextduedate, 8, 2 );

  if( $billingcycle == "Monthly" ){
    $new_time = mktime( 0, 0, 0, $month - 1, $day, $year );
  } elseif( $billingcycle == "Quarterly" ){
    $new_time = mktime( 0, 0, 0, $month - 3, $day, $year );
  } elseif( $billingcycle == "Semi-Annually" ){
    $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
  } elseif( $billingcycle == "Annually" ){
    $new_time = mktime( 0, 0, 0, $month, $day, $year - 1 );
  } elseif( $billingcycle == "Biennially" ){
    $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
  }
  $startdate = date( "Y-m-d", $new_time );
  $enddate = "";

  if( date( "Ymd", $new_time ) >= date( "Ymd" ) ){
    if( $billingcycle == "Monthly" ){
      $new_time = mktime( 0, 0, 0, $month - 2, $day, $year );
    } elseif( $billingcycle == "Quarterly" ){
      $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
    } elseif( $billingcycle == "Semi-Annually" ){
      $new_time = mktime( 0, 0, 0, $month - 12, $day, $year );
    } elseif( $billingcycle == "Annually" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
    } elseif( $billingcycle == "Biennially" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 4 );
    }
    $startdate = date( "Y-m-d", $new_time );
    if( $billingcycle == "Monthly" ){
      $new_time = mktime( 0, 0, 0, $month - 1, $day, $year );
    } elseif( $billingcycle == "Quarterly" ){
      $new_time = mktime( 0, 0, 0, $month - 3, $day, $year );
    } elseif( $billingcycle == "Semi-Annually" ){
      $new_time = mktime( 0, 0, 0, $month - 6, $day, $year );
    } elseif( $billingcycle == "Annually" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 1 );
    } elseif( $billingcycle == "Biennially" ){
      $new_time = mktime( 0, 0, 0, $month, $day, $year - 2 );
    }
    $enddate = date( "Y-m-d", $new_time );
  }
  return array(
    "startdate" => $startdate,
    "enddate" => $enddate
  );
}

function collect_usage($params){

	$username = $params["username"];

	$freeradiussql = freeradius_DatabaseConnect($params);
	if (is_string($freeradiussql)) {
		return $freeradiussql; // Error condition
	}

	$data = Capsule::table('tblhosting')
           ->where('id', $params["serviceid"])
           ->first();

  	$date_range = date_range( $data->nextduedate, $data->billingcycle );

	$startdate = $date_range["startdate"];
	$enddate = $date_range["enddate"];

	$data = $freeradiussql
		->from('radacct')
		->where('Username', $username)
		->where('AcctStartTime', '>=', $startdate)
		->when($enddate, function($query) use ($enddate){
			return $query->where('AcctStartTime', '<=', $enddate);
		       })
		->selectRaw('COUNT(*) as logins,
			  SUM(`AcctSessionTime`) AS logintime,
			  SUM(`AcctInputOctets`) AS uploads,
			  SUM(`AcctOutputOctets`) AS downloads,
			  SUM(`AcctOutputOctets`) + SUM(`AcctInputOctets`) AS total')
		->first();

	$logins = $data->logins;
	$logintime = $data->logintime;
	$uploads = $data->uploads;
	$downloads = $data->downloads;
	$total = $data->total;

	$data = $freeradiussql
			->from('radacct')
			->where('username', $username)
			->select('AcctStartTime as start', 'AcctStopTime as stop')
			->orderBy('AcctStartTime', 'desc')
			->limit(1);

	$sessions = $data->count();
	$start = $data->value('start');
	$end = $data->value('stop');

	$status = "Offline";
	if( $end ) {
	  $status = "Logged in at ".$start;
	}
	if( $sessions < 1 ){
	  $status = "No logins";
	}

	  $usage_limit = 0;
	  if( !empty( $params["configoption2"] ) ){
	    if( is_numeric($params["configoption2"]) ) { $usage_limit = $params["configoption2"]; }
	  }
	  foreach( $params["configoptions"] as $key => $value ){
	    $Megabytes = 0;
	    $Gigabytes = 0;
	    if( $key == 'Megabytes' ){
	      if( is_numeric($value) ){
		$Gigabytes = $value * 1024 * 1024;
	      }
	    }
	    if($key == 'Gigabytes'){
	      if( is_numeric($value) ){
		$Gigabytes = $value * 1024 * 1024 * 1024;
	      }
	    }
	    if( ( $Megabytes > 0 ) || ( $Gigabytes > 0 ) ){
	      $usage_limit = $Megabytes + $Gigabytes;
	    }
	  }

	  return array(
	   'logins' => $logins,
	   'logintime' => $logintime,
	   'total' => $total,
	   'uploads' => $uploads,
	   'downloads' => $downloads,
	   'usage_limit' => $usage_limit,
	   'status' => $status,
	  );
}

function secs_to_h($secs){
  $units = array(
    "week"   => 7*24*3600,
    "day"    => 24*3600,
    "hour"   => 3600,
    "minute" => 60
  );
  if ( $secs == 0 ) return "0 seconds";
  if ( $secs < 60 ) return "{$secs} seconds";
  $s = "";

  foreach ( $units as $name => $divisor ) {
    if ( $quot = intval($secs / $divisor) ) {
      $s .= $quot." ".$name;
      $s .= (abs($quot) > 1 ? "s" : "") . ", ";
      $secs -= $quot * $divisor;
    }
  }
  return substr($s, 0, -2);
}

function byte_size($bytes){
  $size = $bytes / 1024;

  #logActivity("Bytes passed: " . $size, $params['userid']);
  if( $size < 1024 ) {
    $size = number_format( $size, 2 );
    $size .= ' KB';
  } 
  else {
    if( $size / 1024 < 1024 ) {
      $size = number_format($size / 1024, 2);
      $size .= ' MB';
    } 
    else 
      if ( $size / 1024 / 1024 < 1024 ) {
        $size = number_format($size / 1024 / 1024, 2);
        $size .= ' GB';
      }
      else 
        if ( $size / 1024 / 1024 /1024 < 1024 ) {
          $size = number_format($size / 1024 / 1024 / 1024, 2);
          $size .= ' TB';
        }
  }
  return $size;
}

/**
 * @param $params
 * @return \Illuminate\Database\Query\Builder|null|string
 */
function freeradius_DatabaseConnect($params)
{
    $pdo = null;
    try {
        $pdo = Capsule::getInstance()->getConnection('freeradius');
    } catch (\Exception $e) {
        // freeradius connect has not yet be created

        $sqlhost = $params["serverip"];

        if (empty($sqlhost)) {
            $sqlhost = $params["serverhostname"];
        }

        $config = array(
            'driver' => 'mysql',
            'host' => $sqlhost,
            'database' => $params["serveraccesshash"],
            'username' => $params["serverusername"],
            'password' => $params["serverpassword"],
            'charset'  => 'utf8',
        );

        try {
            Capsule::getInstance()->addConnection(
                $config,
                'freeradius'
            );

            $pdo = Capsule::getInstance()->getConnection('freeradius');

        } catch (\Exception $e) {
            return "Unable to connect to FreeRadius Database.  "
            . "Please check FreeRadius server configuration.  "
            . $e->getMessage();
        }
    }   

    if (is_object($pdo)) {
        if (method_exists($pdo, 'query')) {
            $ret = $pdo->query();
        } else {
            $processor = $pdo->getPostProcessor();
            $ret = new \Illuminate\Database\Query\Builder($pdo, $pdo->getQueryGrammar(), $processor);
        }
    } else {
        $ret = $pdo;
    }   

#    logModuleCall("Radius", "freeradius_DatabaseConnect", $params, "", "", ['password']);
    return $ret;
}

?>
