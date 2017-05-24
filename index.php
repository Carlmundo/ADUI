<?php
include("settings.php");
include("functions.php");

?>
<form method="GET" target="_self" class="form-center text-center form-inline" enctype="multipart/form-data">
	<label for="name">Search</label>
	<input type="text" class="form-control text-center" name="name">
	<input type="submit" class="btn btn-primary" value="Search">
</form>
<?php
//Sanitise user inputted data
if (!empty($_GET)){
    $_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
}

if (!empty($_GET['name'])){
	$search = strip_tags($_GET['name']);	
}

if (!empty($search)){
    if (ldap_login($GLOBALS["ldap_user"],$GLOBALS["ldap_pass"])){
        $filter_userinfo = ldap_filter("(samaccountname=$search)","cn,directreports,distinguishedname,mail,manager,memberof,title,samaccounttype,msexchdelegatelistbl");
        if ($filter_userinfo['count'] == 0){
        	echo "No results found.";
        }
        else{
        	$AD_name = $filter_userinfo[0]['cn'][0];
	        $AD_name_distinguished = $filter_userinfo[0]['distinguishedname'][0];
	        switch ($filter_userinfo[0]['samaccounttype'][0]){
		        case "805306368":
	        		$AD_user = true;
					break;
				case "268435456":
					$AD_user = false;
					$AD_group = "Security";
					break;
				case "268435457":
					$AD_user = false;
					$AD_group = "Distribution";
					break;
				default:
					$AD_user = false;
	        }	
			echo '<h2 class="text-center h2">'.$AD_name."</h2>";
	        echo '<div class="text-center well">';

	        if ($AD_user){
	        	if (!empty($filter_userinfo[0]['mail'])){
		        	$AD_email = $filter_userinfo[0]['mail'][0];
		        }
		        else{
		        	$AD_email = "Not found";
		        }
		        if (!empty($filter_userinfo[0]['title'])){
		        	$AD_jobtitle = $filter_userinfo[0]['title'][0];
		        }
		        else{
		        	$AD_jobtitle = "Not found";
		        }
	        	echo '<table class="table text-left" style="display:inline">
	        	<tr><th>Job Title</th><td>'.$AD_jobtitle.'</td></tr>
	        	<tr><th>Email</th><td>'.$AD_email.'</td></tr>';
				
	        	if (!empty($filter_userinfo[0]['manager'])){
	        		CleanUser($filter_userinfo[0]['manager']);
	        		$AD_manager = $filter_userinfo[0]['manager'][0];
	        		echo "<tr><th>Manager</th><td>".$AD_manager."</td></tr>";
	        	}
	        	if (!empty($filter_userinfo[0]['directreports'])){
	        		echo '<tr><th valign="top">Staff</th><td>';
				    CleanUser($filter_userinfo[0]['directreports']);
	        		sort($filter_userinfo[0]['directreports']);
	        		foreach ($filter_userinfo[0]['directreports'] as $staff) {
	        			echo $staff.'<br/>';
	        		}
				    echo '</td></tr>';	
	        	}
	        	echo '</table>';
	        }
	        elseif (!empty($AD_group)){
	        	echo $AD_group." Group";
	        }
	        echo '</div>';

	        function ListManagedGroups($distinguishedname, $heading){
	        	$filter = ldap_filter("(&(objectCategory=group)(ManagedBy=".$distinguishedname."))","cn,description,distinguishedname,member,samaccounttype");
	        	//print_r($filter);
	        	$array_inherited = array();
	        	if ($filter['count'] > 0){
	        		HeadingGroup();
		        	echo '<h4>'.$heading.'</h4><div class="col-md-12 well"><table class="table"><tr><th>Group</th><th>Description</th><th>Type</th><th>Members</th></tr>';
		        	$buttonid = strtolower(preg_replace('/[^\da-z]/i', '', $heading));
					for ($i=0;$i<$filter['count'];$i++){
						array_push($array_inherited,$filter[$i]['distinguishedname'][0]);
						echo "<tr><td>".$filter[$i]['cn'][0]."</td>";
						if (!isset($filter[$i]['description'][0])){
							$filter[$i]['description'][0] = "No Description";
						}
						echo "<td>".$filter[$i]['description'][0]."</td>";
						switch($filter[$i]['samaccounttype'][0]){
							case "268435456":
								echo "<td>Security</td>";
								break;
							case "268435457":
								echo "<td>Distribution</td>";
								break;
							default:
								echo $filter[$i]['samaccounttype'][0];
						}
						echo '<td class="col-md-3">';
						if (!empty($filter[$i]['member'])){
							$arr_member = array();
							
							echo '
							<div class="panel-group">
							<div class="panel panel-default">
							<div class="panel-heading">
							<div class="panel-title text-center">
							<a data-toggle="collapse" href="#'.$buttonid.$i.'">View Members</a>
							</div>
							</div>
							<div id="'.$buttonid.$i.'" class="panel-collapse collapse">
							<div class="panel-body">';
						    CleanUser($filter[$i]['member']);
						    foreach($filter[$i]['member'] as $member) {
								array_push($arr_member, $member);
							}
							sort($arr_member);
							foreach($arr_member as $member) {
							    echo $member."<br/>";
							}
						    echo '</div>
						    </div>
						  	</div>
							</div>';	
						}
						else{
							echo "None";
						}
						echo "</td></tr>";
					}
					echo "</table></div>";
				}								
				foreach ($array_inherited as $group) {
					$group_clean = preg_replace('/,.*/', '', $group);
					$group_clean = str_replace("CN=", "", $group_clean);
					ListManagedGroups($group, 'Inherited Manager of '.$group_clean);
				}
	    	}	

	    	function HeadingGroup(){
	    		global $HeadingGroup;
    			if ($HeadingGroup == FALSE){
    				$HeadingGroup = TRUE;
    				echo "<h3>Group Information</h3>";
    			}
	    	}
	    					    
		    ListManagedGroups($AD_name_distinguished, 'Manager of');				
			
			if ($AD_user){
				// No information found, bad user
		        if ($filter_userinfo['count'] == 0){
		            echo "No groups found.";
		        }
		        else{
		        	// Get groups and primary group token
			        $arr_memberof = $filter_userinfo[0]['memberof'];
			        // Remove extraneous "count" first entry
			        array_shift($arr_memberof);
			        sort($arr_memberof);
			        HeadingGroup();
			        echo '<h4>Member of</h4><div class="col-md-12 well"><ul>';
			        foreach($arr_memberof as $groups){
			            $groups = explode(",",$groups);
			            $group = $groups[0];
			            $group = str_replace("CN=","",$group);
			            echo '<li><a href="?name='.$group.'">'.$group.'</a></li>';
			        }
			        echo "</ul></div>";
		        }
		    }
		    elseif ($AD_group){
		    	if ($AD_group == "Distribution"){
		    		$filter = ldap_filter("(&(objectClass=user)(objectCategory=person)(memberof=CN=".$search.",OU=Distribution Groups,OU=Groups,OU=allusers,DC=microlinkpc,DC=com))", "cn");
		    	}
		    	elseif ($AD_group == "Security"){
		    		$filter = ldap_filter("(&(objectClass=user)(objectCategory=person)(memberof=CN=".$search.",OU=Departments,OU=Microlink Groups,OU=Microlink Company,OU=allusers,DC=microlinkpc,DC=com))", "cn");
		    	}
		    	
		    	array_shift($filter);
		    	sort($filter);
		    	if (count($filter)>0){
		    		HeadingGroup();
		    		echo '<h4>Members</h4><ul>';
			    	for ($i=0;$i<count($filter);$i++){
			    		echo '<li>'.$filter[$i]['cn'][0].'</li>';
			    	}
			    	echo '</ul>';
		    	}
		    }
			if (!empty($filter_userinfo[0]['msexchdelegatelistbl'])){
				echo '<h4>Shared Mailboxes</h4><div class="col-md-12 well"><ul>';
		        array_shift($filter_userinfo[0]['msexchdelegatelistbl']);
		        foreach($filter_userinfo[0]['msexchdelegatelistbl'] as $sharedmailbox){
		            $sharedmailbox = explode(",",$sharedmailbox);
		            if ($sharedmailbox[1] == "OU=Shared Mailboxes"){
		            	$sharedmailbox = $sharedmailbox[0];
			            $sharedmailbox = str_replace("CN=","",$sharedmailbox);
			            echo '<li>'.$sharedmailbox.'</li>';
		            }
		        }
		        echo "</ul></div>";
			}
        }
    }
}
?>