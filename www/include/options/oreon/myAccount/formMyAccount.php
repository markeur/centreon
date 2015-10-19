<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 * 
 * This program is free software; you can redistribute it and/or modify it under 
 * the terms of the GNU General Public License as published by the Free Software 
 * Foundation ; either version 2 of the License.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with 
 * this program; if not, see <http://www.gnu.org/licenses>.
 * 
 * Linking this program statically or dynamically with other modules is making a 
 * combined work based on this program. Thus, the terms and conditions of the GNU 
 * General Public License cover the whole combination.
 * 
 * As a special exception, the copyright holders of this program give Centreon 
 * permission to link this program with independent modules to produce an executable, 
 * regardless of the license terms of these independent modules, and to copy and 
 * distribute the resulting executable under terms of Centreon choice, provided that 
 * Centreon also meet, for each linked independent module, the terms  and conditions 
 * of the license of that module. An independent module is a module which is not 
 * derived from this program. If you modify this program, you may extend this 
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 * 
 * For more information : contact@centreon.com
 * 
 * SVN : $URL$
 * SVN : $Id$
 * 
 */

	if (!isset ($oreon))
		exit ();
	
	// Pear library
	require_once "HTML/QuickForm.php";
	require_once 'HTML/QuickForm/advmultiselect.php';
	require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

	require_once "./include/common/common-Func.php";
	
	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
		
	// Path to the configuration dir
	$path = "./include/options/oreon/myAccount/";
	
	// PHP Functions
	require_once $path."DB-Func.php";
	
	/*
	 * Database retrieve information for the User
	 */
	$cct = array();
	if ($o == "c")	{	
		$DBRESULT = $pearDB->query("SELECT contact_id, contact_name, contact_alias, contact_lang, 
                                                contact_email, contact_pager, contact_js_effects, contact_autologin_key 
                                            FROM contact 
                                            WHERE contact_id = '".$oreon->user->get_id()."' LIMIT 1");
		// Set base value
		$cct = array_map("myDecode", $DBRESULT->fetchRow());
                $res = $pearDB->query("SELECT cp_key, cp_value 
                                       FROM contact_param 
                                       WHERE cp_contact_id = '".$pearDB->escape($oreon->user->get_id())."'");
                while ($row = $res->fetchRow()) {
                    $cct[$row['cp_key']] = $row['cp_value'];
                }
	}
        
	/*
	 * Database retrieve information for differents elements list we need on the page
	 *
	 * Langs -> $langs Array
         */
	$langs = array();
	$langs = getLangs();	
	$attrsText 		= array("size"=>"35");

	$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
	$form->addElement('header', 'title', _("Change my settings"));

	$form->addElement('header', 'information', _("General Information"));
	$form->addElement('text', 'contact_name', _("Name"), $attrsText);
	$form->addElement('text', 'contact_alias', _("Alias / Login"), $attrsText);
	$form->addElement('text', 'contact_email', _("Email"), $attrsText);
	$form->addElement('text', 'contact_pager', _("Pager"), $attrsText);
	$form->addElement('password', 'contact_passwd', _("Password"), array("size"=>"30", "autocomplete"=>"off", "id"=>"passwd1", "onFocus" => "resetPwdType(this);"));
	$form->addElement('password', 'contact_passwd2', _("Confirm Password"), array("size"=>"30", "autocomplete"=>"off", "id"=>"passwd2", "onFocus" => "resetPwdType(this);"));
        $form->addElement('button','contact_gen_passwd',_("Generate"), array('onclick'=>'generatePassword("passwd");'));
        $form->addElement('text', 'contact_autologin_key', _("Autologin Key"), array("size" => "30", "id" => "aKey"));
        $form->addElement('button','contact_gen_akey',_("Generate"), array( 'onclick' => 'generatePassword("aKey");'));
        $form->addElement('select', 'contact_lang', _("Language"), $langs);
        $form->addElement('checkbox', 'contact_js_effects', _("Animation effects"), null, $attrsText);


        $form->addElement('checkbox', 'monitoring_host_notification_0', _('Show Up status'));
        $form->addElement('checkbox', 'monitoring_host_notification_1', _('Show Down status'));
        $form->addElement('checkbox', 'monitoring_host_notification_2', _('Show Unreachable status'));
        $form->addElement('checkbox', 'monitoring_svc_notification_0', _('Show OK status'));
        $form->addElement('checkbox', 'monitoring_svc_notification_1', _('Show Warning status'));
        $form->addElement('checkbox', 'monitoring_svc_notification_2', _('Show Critical status'));
        $form->addElement('checkbox', 'monitoring_svc_notification_3', _('Show Unknown status'));

        $sound_files = scandir($centreon_path."www/sounds/");
        $sounds = array(null => null);
        foreach ($sound_files as $f) {
            if($f == "." || $f == "..") {
                continue;
            }
            $info = pathinfo($f);
            $fname = basename($f, ".".$info['extension']);
            $sounds[$fname] = $fname;
        }
        $form->addElement('select', 'monitoring_sound_host_notification_0', _("Sound for Up status"), $sounds);
        $form->addElement('select', 'monitoring_sound_host_notification_1', _("Sound for Down status"), $sounds);
        $form->addElement('select', 'monitoring_sound_host_notification_2', _("Sound for Unreachable status"), $sounds);
        $form->addElement('select', 'monitoring_sound_svc_notification_0', _("Sound for OK status"), $sounds);
        $form->addElement('select', 'monitoring_sound_svc_notification_1', _("Sound for Warning status"), $sounds);
        $form->addElement('select', 'monitoring_sound_svc_notification_2', _("Sound for Critical status"), $sounds);
        $form->addElement('select', 'monitoring_sound_svc_notification_3', _("Sound for Unknown status"), $sounds);

	$redirect = $form->addElement('hidden', 'o');
	$redirect->setValue($o);
	
	function myReplace()	{
		global $form;
		$ret = $form->getSubmitValues();
		return (str_replace(" ", "_", $ret["contact_name"]));
	}
	$form->applyFilter('__ALL__', 'myTrim');
	$form->applyFilter('contact_name', 'myReplace');
	$form->addRule('contact_name', _("Compulsory name"), 'required');
	$form->addRule('contact_alias', _("Compulsory alias"), 'required');
	$form->addRule('contact_email', _("Valid Email"), 'required');
	$form->addRule(array('contact_passwd', 'contact_passwd2'), _("Passwords do not match"), 'compare');
	$form->registerRule('exist', 'callback', 'testExistence');
	$form->addRule('contact_name', _("Name already in use"), 'exist');
	$form->registerRule('existAlias', 'callback', 'testAliasExistence');
	$form->addRule('contact_alias', _("Name already in use"), 'existAlias');
	$form->setRequiredNote("<font style='color: red;'>*</font>"._("Required fields"));
	
	// Smarty template Init
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);

	// Modify a contact information
	if ($o == "c")	{
		$subC = $form->addElement('submit', 'submitC', _("Save"), array("class" => "btc bt_success"));
		$res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
	    $form->setDefaults($cct);
	}
	
	if ($form->validate())	{
		updateContactInDB($oreon->user->get_id());
		if ($form->getSubmitValue("contact_passwd"))
			$oreon->user->passwd = md5($form->getSubmitValue("contact_passwd"));
		$o = NULL;
		$form->addElement("button", "change", _("Modify"), array("onClick"=>"javascript:window.location.href='?p=".$p."&o=c'"));
		$form->freeze();
	}
	//Apply a template definition	
	$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
	$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
	$form->accept($renderer);	
	$tpl->assign('form', $renderer->toArray());	
	$tpl->assign('o', $o);		
	$tpl->display("formMyAccount.ihtml");
?>
<script type='text/javascript' src='./include/common/javascript/keygen.js'></script>
<script type="text/javascript">
    jQuery(function() {
        jQuery("select[name*='_notification_']").change(function() {
            if (jQuery(this).val()) {
  			    var snd = new buzz.sound("sounds/"+jQuery(this).val(), {
                    formats: [ "ogg", "mp3" ]
 			    });
			}
            snd.play();
        });
    });
</script>