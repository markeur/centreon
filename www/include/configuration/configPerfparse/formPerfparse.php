<?php
/**
Centreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
Developped by : Julien Mathis - Romain Le Merlus

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/

	#
	## Database retrieve information for Nagios
	#
	$pp = array();
	$ppTemp = array();
	if (($o == "c" || $o == "w") && $perfparse_id)	{	
		$DBRESULT =& $pearDB->query("SELECT * FROM cfg_perfparse WHERE perfparse_id = '".$perfparse_id."' LIMIT 1");
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";
		# Set base value
		$ppTemp = array_map("myDecode", $DBRESULT->fetchRow());
		foreach ($ppTemp as $key=>$value)
			$pp[strtolower($key)] = $value;
		$DBRESULT->free();
	}
	##########################################################
	# Var information to format the element
	#
	$attrsText		= array("size"=>"30");
	$attrsText2 	= array("size"=>"50");
	$attrsText3 	= array("size"=>"10");
	$attrsTextarea 	= array("rows"=>"5", "cols"=>"40");
	$template 		= "<table><tr><td>{unselected}</td><td align='center'>{add}<br><br><br>{remove}</td><td>{selected}</td></tr></table>";

	#
	## Form begin
	#
	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
	if ($o == "a")
		$form->addElement('header', 'title', _("Add a Perfparse Configuration File"));
	else if ($o == "c")
		$form->addElement('header', 'title', _("Modify a Perfparse Configuration File"));
	else if ($o == "w")
		$form->addElement('header', 'title', _("View a Perfparse Configuration File"));

	#
	## Perfparse Configuration basic information
	#
	$form->addElement('header', 'information', _("General Information"));
	$form->addElement('text', 'perfparse_name',_("Perfparse File Name"), $attrsText);
	$form->addElement('textarea', 'perfparse_comment', _("Comments"), $attrsTextarea);
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'perfparse_activate', null, _("Enabled"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'perfparse_activate', null, _("Disabled"), '0');
	$form->addGroup($ppTab, 'perfparse_activate', _("Status"), '&nbsp;');	
	
	## Part 1
	$form->addElement('header', 'sManagement', _("Server Management"));
	$form->addElement('text', 'server_port', _("Server Port"), $attrsText3);

	## Part 2
	$form->addElement('header', 'pManagement', _("Parser Management"));
	$form->addElement('header', 'perfDLF', _("Performance Data Log Files ('-' for stdin)"));
	$form->addElement('text', 'service_log', _("Service Log"), $attrsText2);	
	$form->addElement('text', 'service_log_position_mark_path', _("Service Log Position Mark Path"), $attrsText2);
	
	$form->addElement('header', 'errHandling', _("Error handling"));
	$form->addElement('text', 'error_log', _("Error Log File"), $attrsText2);
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'error_log_rotate', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'error_log_rotate', null, _("No"), '0');
	$form->addGroup($ppTab, 'error_log_rotate', _("Error Log Rotate"), '&nbsp;');
	$form->addElement('text', 'error_log_keep_n_days', _("Error Log Keep N Days"), $attrsText3);	
	$form->addElement('text', 'drop_file', _("Drop File"), $attrsText2);
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'drop_file_rotate', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'drop_file_rotate', null, _("No"), '0');
	$form->addGroup($ppTab, 'drop_file_rotate', _("Drop File Rotate"), '&nbsp;');
	$form->addElement('text', 'drop_file_keep_n_days', _("Drop File Keep N Days"), $attrsText3);
	
	$form->addElement('header', 'lockFileTxt', _("Lock file for only one perfparse running at the same time"));	
	$form->addElement('text', 'lock_file', _("Lock File"), $attrsText2);
	
	## Part 3
	$form->addElement('header', 'reportOpt', _("Reporting Options"));
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'show_status_bar', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'show_status_bar', null, _("No"), '0');
	$form->addGroup($ppTab, 'show_status_bar', _("Show Status Bar"), '&nbsp;');
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'do_report', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'do_report', null, _("No"), '0');
	$form->addGroup($ppTab, 'do_report', _("Do_Report"), '&nbsp;');
	
	## Part 4
	$form->addElement('header', 'cgiMan', _("CGI Management"));	
    $form->addElement('select', 'default_user_permissions_policy', _("Default user permissions Policy"), array(1=>"ro", 2=>"rw", 3=>"hide"));
    $form->addElement('select', 'default_user_permissions_host_groups', _("Default user permissions Hostgroups"), array(1=>"ro", 2=>"rw", 3=>"hide"));
    $form->addElement('select', 'default_user_permissions_summary', _("Default user permissions Summary"), array(1=>"ro", 2=>"rw", 3=>"hide"));
	
	## Part 5
	$form->addElement('header', 'outLog', _("Output Logger"));
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'output_log_file', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'output_log_file', null, _("No"), '0');
	$form->addGroup($ppTab, 'output_log_file', _("Output Log File"), '&nbsp;');
	$form->addElement('text', 'output_log_filename', _("Output Log Filename"), $attrsText2);
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'output_log_rotate', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'output_log_rotate', null, _("No"), '0');
	$form->addGroup($ppTab, 'output_log_rotate', _("Output Log Rotate"), '&nbsp;');
	$form->addElement('text', 'output_log_keep_n_days', _("Output Log Keep N Days"), $attrsText3);
	
	## Part 6
	$form->addElement('header', 'SockOutMan', _("Socket_output managment"));
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'use_storage_socket_output', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'use_storage_socket_output', null, _("No"), '0');
	$form->addGroup($ppTab, 'use_storage_socket_output', _("Use Storage Socket Output"), '&nbsp;');
	$form->addElement('text', 'storage_socket_output_host_name', _("Storage Socket Output Host Name"), $attrsText3);
	$form->addElement('text', 'storage_socket_output_port', _("Storage Socket Output Port"), $attrsText3);
	
	## Part 7
	$form->addElement('header', 'dbMan', _("Database managment"));
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'use_storage_mysql', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'use_storage_mysql', null, _("No"), '0');
	$form->addGroup($ppTab, 'use_storage_mysql', _("Use Mysql Storage"), '&nbsp;');
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'no_raw_data', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'no_raw_data', null, _("No"), '0');
	$form->addGroup($ppTab, 'no_raw_data', _("No Raw Data"), '&nbsp;');
	$ppTab = array();
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'no_bin_data', null, _("Yes"), '1');
	$ppTab[] = &HTML_QuickForm::createElement('radio', 'no_bin_data', null, _("No"), '0');
	$form->addGroup($ppTab, 'no_bin_data', _("No Bin Data"), '&nbsp;');
	$form->addElement('text', 'db_user', _("DB User"), $attrsText3);
	$form->addElement('text', 'db_name', _("DB Name"), $attrsText3);
	$form->addElement('password', 'db_pass', _("DB Password"), $attrsText3);
	$form->addElement('text', 'db_host', _("DB Host"), $attrsText3);
	$form->addElement('text', 'dummy_hostname', _("Dummy Hostname"), $attrsText3);
	$form->addElement('text', 'storage_modules_load', _("Storage Modules Load"), $attrsText3);
		
	$tab = array();
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, _("List"), '1');
	$tab[] = &HTML_QuickForm::createElement('radio', 'action', null, _("Form"), '0');
	$form->addGroup($tab, 'action', _("Post Validation"), '&nbsp;');
	
	$form->setDefaults(array(
	"perfparse_activate"=>'1',
	"service_log"=>"-",
	"error_log_rotate"=>'1',
	"drop_file"=>"/tmp/perfparse.drop",
	"drop_file_rotate"=>'1',
	"lock_file"=>"/var/lock/perfparse.lock",
	"show_status_bar"=>'0',
	"do_report"=>'0',
	"default_user_permissions_policy"=>0,
	"default_user_permissions_host_groups"=>0,
	"default_user_permissions_summary"=>0,
	"output_log_file"=>'1',
	"output_log_rotate"=>'1',
	"use_storage_socket_output"=>'0',
	"use_storage_mysql"=>'1',
	"no_raw_data"=>'1',
	"no_bin_data"=>'0',
	"dummy_hostname"=>"dummy",
	"storage_modules_load"=>"mysql",
	"action"=>'1'
	));
		
	$form->addElement('hidden', 'perfparse_id');
	$redirect =& $form->addElement('hidden', 'o');
	$redirect->setValue($o);
	
	#
	## Form Rules
	#
	function slash($elem = NULL)	{
		if ($elem)
			return rtrim($elem, "/")."/";
	}
	$form->applyFilter('__ALL__', 'myTrim');
	$form->addRule('perfparse_name', _("Compulsory Name"), 'required');
	$form->addRule('perfparse_comment', _("Required Field"), 'required');
	$form->registerRule('exist', 'callback', 'testExistence');
	$form->addRule('perfparse_name', _("Name is already in use"), 'exist');
	$form->setRequiredNote("<font style='color: red;'>*</font>" . _(" Required fields"));
	
	# 
	##End of form definition
	#
	
	# Smarty template Init
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);
	
	# Just watch a nagios information
	if ($o == "w")	{
		$form->addElement("button", "change", _("Modify"), array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&perfparse_id=".$perfparse_id."'"));
	    $form->setDefaults($pp);
		$form->freeze();
	}
	# Modify a nagios information
	else if ($o == "c")	{
		$subC =& $form->addElement('submit', 'submitC', _("Save"));
		$res =& $form->addElement('reset', 'reset', _("Reset"));
	    $form->setDefaults($pp);
	}
	# Add a nagios information
	else if ($o == "a")	{
		$subA =& $form->addElement('submit', 'submitA', _("Save"));
		$res =& $form->addElement('reset', 'reset', _("Reset"));
	}
	$tpl->assign('msg', array ("nagios"=>$oreon->user->get_version()));
	
	$valid = false;
	if ($form->validate())	{
		$ppObj =& $form->getElement('perfparse_id');
		if ($form->getSubmitValue("submitA"))
			$ppObj->setValue(insertPerfparseInDB());
		else if ($form->getSubmitValue("submitC"))
			updatePerfparseInDB($ppObj->getValue());
		$o = NULL;
		$form->addElement("button", "change", _("Modify"), array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c&perfparse_id=".$ppObj->getValue()."'"));
		$form->freeze();
		$valid = true;
	}
	$action = $form->getSubmitValue("action");
	if ($valid && $action["action"]["action"])
		require_once($path."listPerfparse.php");
	else	{
		#Apply a template definition	
		$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
		$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
		$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
		$form->accept($renderer);	
		$tpl->assign('form', $renderer->toArray());	
		$tpl->assign('o', $o);		
		$tpl->display("formPerfparse.ihtml");
	}
?>