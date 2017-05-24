<?php
$GLOBALS["ldap_connect"] = ldap_connect($GLOBALS["ldap_host"]);
$GLOBALS["error"] = "";

function ldap_login($user, $pass){
    //Check if domain suffix is included in username and append it if not
    if (strpos($user, $GLOBALS["ldap_suffix"]) !== false) {
        $ldap_user = $user;
    }
    else{
        $ldap_user = $user.$GLOBALS["ldap_suffix"];
    }  
    
    $ldap_login = @ldap_bind($GLOBALS["ldap_connect"],$ldap_user,$pass);
    if (!$ldap_login){
        $ErrorNumber = ldap_errno($GLOBALS["ldap_connect"]);
        $ErrorSubcode = null;
        if ($ErrorNumber == 49){
            //Get LDAP Subcode - https://ldapwiki.com/wiki/Common%20Active%20Directory%20Bind%20Errors
            define(LDAP_OPT_DIAGNOSTIC_MESSAGE, 0x0032);
            if (ldap_get_option($GLOBALS["ldap_connect"], LDAP_OPT_DIAGNOSTIC_MESSAGE, $GLOBALS["error"])) {
                if (preg_match("/(?<=data\s).*?(?=\,)/", $GLOBALS["error"], $ErrorSubcode)) {
                    $ErrorSubcode = $ErrorSubcode[0];
                }
            }
            if ($ErrorSubcode == 773){
                $GLOBALS["error"] = "<p>Error: Your password has expired.</p>";
            }
            elseif ($ErrorSubcode == 775){
                $GLOBALS["error"] = "<p>Error: Your account is locked out.</p>";
            }
            else{
                $GLOBALS["error"] = "<p>Error: Wrong username or password.</p>
                <p>Error Sub-code: ".$ErrorSubcode."</p>";  
            }            
        }
        elseif ($ErrorNumber == -1){
            $GLOBALS["error"] = "<p>Error: Can't connect to Active Directory</p>";
        }
        if (!empty($GLOBALS["error"])){
            echo $GLOBALS["error"];
        }
    }
    else{
        return true;   
    }
}
function ldap_filter($filter, $array=""){
    if (!empty($array)){
        if (strpos($array, ",") !== false) {
            $filterArray = explode(',', $array);
        }
        else{
            $filterArray = array($array);
        }
        return ldap_get_entries($GLOBALS["ldap_connect"], ldap_search($GLOBALS["ldap_connect"],$GLOBALS["ldap_dn"],$filter,$filterArray));
    }
    else{
        return ldap_get_entries($GLOBALS["ldap_connect"], ldap_search($GLOBALS["ldap_connect"],$GLOBALS["ldap_dn"],$filter)); 
    } 
}
function CleanUser(&$array){
    $array = preg_replace('/,.*/', '', $array);
    $array = str_replace("CN=", "", $array);
    array_shift($array);
}
?>