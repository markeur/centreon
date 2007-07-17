<?
/**
Oreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/gpl.txt
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
	if (!isset($oreon))
		exit();
		
	include("./include/common/autoNumLimit.php");
	
	if (isset($search))	{
		if ($oreon->user->admin || !HadUserLca($pearDB))
			$DBRESULT =& $pearDB->query("SELECT COUNT(*) FROM hostgroup WHERE (hg_name LIKE '".htmlentities($search, ENT_QUOTES)."' OR hg_alias LIKE '".htmlentities($search, ENT_QUOTES)."')");
		else
			$DBRESULT =& $pearDB->query("SELECT COUNT(*) FROM hostgroup WHERE (hg_name LIKE '".htmlentities($search, ENT_QUOTES)."' OR hg_alias LIKE '".htmlentities($search, ENT_QUOTES)."') AND hg_id IN (".$lcaHostGroupstr.")");
	}
	else	{
		if ($oreon->user->admin || !HadUserLca($pearDB))
			$DBRESULT =& $pearDB->query("SELECT COUNT(*) FROM hostgroup");
		else
			$DBRESULT =& $pearDB->query("SELECT COUNT(*) FROM hostgroup WHERE hg_id IN (".$lcaHostGroupstr.")");
	}
	if (PEAR::isError($DBRESULT))
		print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";	
	$tmp = & $DBRESULT->fetchRow();
	$rows = $tmp["COUNT(*)"];

	# start quickSearch form
	$advanced_search = 1;
	include_once("./include/common/quickSearch.php");
	# end quickSearch form

	include("./include/common/checkPagination.php");

	# Smarty template Init
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);
	# start header menu
	$tpl->assign("headerMenu_icone", "<img src='./img/icones/16x16/pin_red.gif'>");
	$tpl->assign("headerMenu_name", $lang['name']);
	$tpl->assign("headerMenu_desc", $lang['description']);
	$tpl->assign("headerMenu_status", $lang['status']);
	$tpl->assign("headerMenu_hostAct", $lang['hg_HostAct']);
	$tpl->assign("headerMenu_hostDeact", $lang['hg_HostDeact']);
	$tpl->assign("headerMenu_options", $lang['options']);
	# end header menu
	#Hostgroup list
	if ($search){
		if ($oreon->user->admin || !HadUserLca($pearDB))
			$rq = "SELECT hg_id, hg_name, hg_alias, hg_activate FROM hostgroup WHERE (hg_name LIKE '".htmlentities($search, ENT_QUOTES)."' OR hg_alias LIKE '".htmlentities($search, ENT_QUOTES)."') ORDER BY hg_name LIMIT ".$num * $limit.", ".$limit;
		else
			$rq = "SELECT hg_id, hg_name, hg_alias, hg_activate FROM hostgroup WHERE (hg_name LIKE '".htmlentities($search, ENT_QUOTES)."' OR hg_alias LIKE '".htmlentities($search, ENT_QUOTES)."') AND hg_id IN (".$lcaHostGroupstr.") ORDER BY hg_name LIMIT ".$num * $limit.", ".$limit;
	} else {
		if ($oreon->user->admin || !HadUserLca($pearDB))
			$rq = "SELECT hg_id, hg_name, hg_alias, hg_activate FROM hostgroup ORDER BY hg_name LIMIT ".$num * $limit.", ".$limit;
		else
			$rq = "SELECT hg_id, hg_name, hg_alias, hg_activate FROM hostgroup WHERE hg_id IN (".$lcaHostGroupstr.") ORDER BY hg_name LIMIT ".$num * $limit.", ".$limit;
	}
	$DBRESULT =& $pearDB->query($rq);
	if (PEAR::isError($DBRESULT))
		print "DB Error : ".$DBRESULT->getDebugInfo()."<br>";	
	
	$search = tidySearchKey($search, $advanced_search);
	
	$form = new HTML_QuickForm('select_form', 'POST', "?p=".$p);
	#Different style between each lines
	$style = "one";
	#Fill a tab with a mutlidimensionnal Array we put in $tpl
	$elemArr = array();
	for ($i = 0; $DBRESULT->fetchInto($hg); $i++) {
		$selectedElements =& $form->addElement('checkbox', "select[".$hg['hg_id']."]");	
		$moptions = "<a href='oreon.php?p=".$p."&hg_id=".$hg['hg_id']."&o=w&search=".$search."'><img src='img/icones/16x16/view.gif' border='0' alt='".$lang['view']."'></a>&nbsp;&nbsp;";
		$moptions .= "<a href='oreon.php?p=".$p."&hg_id=".$hg['hg_id']."&o=c&search=".$search."'><img src='img/icones/16x16/document_edit.gif' border='0' alt='".$lang['modify']."'></a>&nbsp;&nbsp;";
		$moptions .= "<a href='oreon.php?p=".$p."&hg_id=".$hg['hg_id']."&o=d&select[".$hg['hg_id']."]=1&num=".$num."&limit=".$limit."&search=".$search."' onclick=\"return confirm('".$lang['confirm_removing']."')\"><img src='img/icones/16x16/delete.gif' border='0' alt='".$lang['delete']."'></a>&nbsp;&nbsp;";
		if ($hg["hg_activate"])
			$moptions .= "<a href='oreon.php?p=".$p."&hg_id=".$hg['hg_id']."&o=u&limit=".$limit."&num=".$num."&search=".$search."'><img src='img/icones/16x16/element_previous.gif' border='0' alt='".$lang['disable']."'></a>&nbsp;&nbsp;";
		else
			$moptions .= "<a href='oreon.php?p=".$p."&hg_id=".$hg['hg_id']."&o=s&limit=".$limit."&num=".$num."&search=".$search."'><img src='img/icones/16x16/element_next.gif' border='0' alt='".$lang['enable']."'></a>&nbsp;&nbsp;";
		$moptions .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		$moptions .= "<input onKeypress=\"if(event.keyCode > 31 && (event.keyCode < 45 || event.keyCode > 57)) event.returnValue = false; if(event.which > 31 && (event.which < 45 || event.which > 57)) return false;\" maxlength=\"3\" size=\"3\" value='1' style=\"margin-bottom:0px;\" name='dupNbr[".$hg['hg_id']."]'></input>";
		/* Nbr Host */
		$nbrhostAct = array();
		$nbrhostDeact = array();
		$rq = "SELECT COUNT(*) as nbr FROM hostgroup_relation hgr, host WHERE hostgroup_hg_id = '".$hg['hg_id']."' AND host.host_id = hgr.host_host_id AND host.host_register = '1' AND host.host_activate = '1'";
		$DBRESULT2 =& $pearDB->query($rq);
		if (PEAR::isError($DBRESULT2))
			print "DB Error : ".$DBRESULT2->getDebugInfo()."<br>";	
		$nbrhostAct = $DBRESULT2->fetchRow();
		$rq = "SELECT COUNT(*) as nbr FROM hostgroup_relation hgr, host WHERE hostgroup_hg_id = '".$hg['hg_id']."' AND host.host_id = hgr.host_host_id AND host.host_register = '1' AND host.host_activate = '0'";
		$DBRESULT2 =& $pearDB->query($rq);
		if (PEAR::isError($DBRESULT2))
			print "DB Error : ".$DBRESULT2->getDebugInfo()."<br>";	
		$nbrhostDeact = $DBRESULT2->fetchRow();
		$elemArr[$i] = array("MenuClass"=>"list_".$style, 
						"RowMenu_select"=>$selectedElements->toHtml(),
						"RowMenu_name"=>$hg["hg_name"],
						"RowMenu_link"=>"?p=".$p."&o=c&hg_id=".$hg['hg_id'],
						"RowMenu_desc"=>$hg["hg_alias"],
						"RowMenu_status"=>$hg["hg_activate"] ? $lang['enable'] : $lang['disable'],
						"RowMenu_hostAct"=>$nbrhostAct["nbr"],
						"RowMenu_hostDeact"=>$nbrhostDeact["nbr"],
						"RowMenu_options"=>$moptions);
		$style != "two" ? $style = "two" : $style = "one";	}
	$tpl->assign("elemArr", $elemArr);
	#Different messages we put in the template
	$tpl->assign('msg', array ("addL"=>"?p=".$p."&o=a", "addT"=>$lang['add'], "delConfirm"=>$lang['confirm_removing']));
	
	#
	##Toolbar select $lang["lgd_more_actions"]
	#
	?>
	<SCRIPT LANGUAGE="JavaScript">
	function setO(_i) {
		document.forms['form'].elements['o'].value = _i;
	}
	</SCRIPT>
	<?
	$attrs1 = array(
		'onchange'=>"javascript: " .
				"if (this.form.elements['o1'].selectedIndex == 1 && confirm('".$lang['confirm_duplication']."')) {" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"else if (this.form.elements['o1'].selectedIndex == 2 && confirm('".$lang['confirm_removing']."')) {" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"else {" .
				" 	setO(this.form.elements['o1'].value); submit();} " .
				"this.form.elements['o1'].selectedIndex = 0");
	$form->addElement('select', 'o1', NULL, array(NULL=>$lang["lgd_more_actions"], "m"=>$lang['dup'], "d"=>$lang['delete'], "ms"=>$lang['m_mon_enable'], "mu"=>$lang['m_mon_disable']), $attrs1);
	$form->setDefaults(array('o1' => NULL));
		
	$attrs2 = array(
		'onchange'=>"javascript: " .
				"if (this.form.elements['o2'].selectedIndex == 1 && confirm('".$lang['confirm_duplication']."')) {" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"else if (this.form.elements['o2'].selectedIndex == 2 && confirm('".$lang['confirm_removing']."')) {" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"else {" .
				" 	setO(this.form.elements['o2'].value); submit();} " .
				"this.form.elements['o1'].selectedIndex = 0");
    $form->addElement('select', 'o2', NULL, array(NULL=>$lang["lgd_more_actions"], "m"=>$lang['dup'], "d"=>$lang['delete'], "ms"=>$lang['m_mon_enable'], "mu"=>$lang['m_mon_disable']), $attrs2);
	$form->setDefaults(array('o2' => NULL));

	$o1 =& $form->getElement('o1');
	$o1->setValue(NULL);
	$o1->setSelected(NULL);

	$o2 =& $form->getElement('o2');
	$o2->setValue(NULL);
	$o2->setSelected(NULL);
	
	$tpl->assign('limit', $limit);

	#
	##Apply a template definition
	#
	$renderer =& new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$form->accept($renderer);	
	$tpl->assign('form', $renderer->toArray());
	$tpl->display("listHostGroup.ihtml");
?>