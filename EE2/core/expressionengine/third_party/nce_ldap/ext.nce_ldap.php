<?php  if ( ! defined('EXT') ) exit('Invalid file request');
/**
 * LDAP Authentication
 *
 * ### EE 2.1 version ###
 *
 * Based on: NCE LDAP
 *           http://code.google.com/p/ee-ldap-extension/
 * License: "if you've used this module and found that it needed something then please hand it back so that it can be shared with the world"
 * Site: http://code.google.com/p/ee-ldap-extension/wiki/Introduction
 *
 * An ExpressionEngine Extension that allows the authentication of users via LDAP
 * LDAP details are copied to the EE database before standard MySQL authentication is performed
 * If user is not found on LDAP, MySQL authentication will still be performed (useful for EE users not in LDAP)
 *
 * Dependancy: iconv PHP module
 *
 * @package DesignByFront
 * @author  Alistair Brown 
 * @author  Alex Glover
 * @link    http://github.com/designbyfront/LDAP-Authentication-for-ExpressionEngine
 * @since   Version 1.3
 *
 * LDAPS Instructions - http://github.com/designbyfront/LDAP-Authentication-for-ExpressionEngine/issues/closed#issue/1
 *
 * Enhancements to original:
 *  - Upgraded to EE2
 *  - Authentication against multiple LDAP servers
 *  - Non-LDAP user login (remove restriction)
 *  - Authentication even with LDAP server downtime (remove restriction)
 *  - Use EE global classes (rather then PHP global variables)
 *  - DB protection against injection (however unlikely)
 *  - Better code structure using functions
 *  - More settings control:
 *     -> Custom admin email message
 *  - Notifications:
 *     Adds to session data $_SESSION['ldap_message'] which can be used later for notification purposes
 *  - Use of character encoding for sent data (and ability to change in settings)
 *     PHP uses 'UTF-8' encoding; Windows server uses 'Windows-1252' encoding.
 *     Using the iconv PHP module, settings data saved in 'UTF-8' is dynamically encoded to 'Windows-1252' when being sent.
 *
 */

class Nce_ldap_ext {

	var $settings       = array();
	var $name           = 'LDAP authentication';
	var $version        = '1.3';
	var $description    = 'Handles LDAP login / account creation';
	var $settings_exist = 'y';
	var $docs_url       = 'http://github.com/designbyfront/LDAP-Authentication-for-ExpressionEngine/issues';
	var $debug          = FALSE;

// If you are looking here to edit the settings, you can always just change them in Extensions settings page :)
	var $admin_email               = 'admin@your_site.com'; // Change your_site.com to your sites domain
	var $from_email                = 'ldap@your_site.com'; // Change your_site.com to your sites domain
	var $mail_host                 = 'your_mail_host'; // Change your_mail_host to name / ip address of your mail host
	var $mail_message              = "This is an automated message from the ExpressionEngine LDAP authentication system.\n-------------------------\n\n{name} has just logged in for the first time. This has created an ExpressionEngine account for them using their LDAP details.\nTo complete their account details, please log in to http://{host} and update their member group, profile and 'people' weblog entry.\nTheir username is: {username}";
	var $use_ldap_account_creation = 'yes';
	var $ldap_host                 = 'ldap://your_ldap_host'; // Change your_ldap_host to name / ip address of your LDAP host
	var $ldap_port                 = '389'; // Change if your LDAP port is different
	var $ldap_search_base          = 'ldap_search_base'; // Change to your LDAP search base
	var $ldap_search_user          = 'ldap_search_user'; // Change to your LDAP search user
	var $ldap_search_password      = 'ldap_search_password'; // Change to your LDAP search password
	var $ldap_username_attribute   = 'ldap_username_attribute'; // Change to your LDAP username attribute
	var $ldap_character_encode     = 'Windows-1252';
	var $no_ldap_login_message     = 'LDAP authentication seems to be down at the moment. Please contact your administrator.';
	var $first_time_login_message  = 'This is your first time logging in! Your account has been automatically created for you, but your administrator may still need to alter your settings. Please contact them if you require more access.';
	var $created_user_group        = '5'; // User group id (members)

	// PHP4 Constructor
	function Nce_ldap_ext($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
	}


// ----------------------


	/**
	* EE method called when the extension is activated
	*/
	function activate_extension ()
	{
		$settings = array();
		$settings['admin_email']               = $this->admin_email;
		$settings['from_email']                = $this->from_email;
		$settings['mail_host']                 = $this->mail_host;
		$settings['mail_message']              = $this->mail_message;
		$settings['use_ldap_account_creation'] = $this->use_ldap_account_creation;
		$settings['ldap_host']                 = $this->ldap_host;
		$settings['ldap_port']                 = $this->ldap_port;
		$settings['ldap_search_base']          = $this->ldap_search_base;
		$settings['ldap_search_user']          = $this->ldap_search_user;
		$settings['ldap_search_password']      = $this->ldap_search_password;
		$settings['ldap_username_attribute']   = $this->ldap_username_attribute;
		$settings['ldap_character_encode']     = $this->ldap_character_encode;
		$settings['no_ldap_login_message']     = $this->no_ldap_login_message;
		$settings['first_time_login_message']  = $this->first_time_login_message;
		$settings['created_user_group']        = $this->created_user_group;

		$hooks = array(
			'login_authenticate_start'  => 'login_authenticate_start',
			'member_member_login_start' => 'member_member_login_start'
		);

		foreach ($hooks as $hook => $method)
		{
			$this->EE->db->query($this->EE->db->insert_string('exp_extensions',
				array(
					'extension_id' => '',
					'class'        => __CLASS__,
					'method'       => $method,
					'hook'         => $hook,
					'settings'     => serialize($settings),
					'priority'     => 10,
					'version'      => $this->version,
					'enabled'      => "y"
				)
			));
		}
	}


// ----------------------


	/**
	* EE method called when the extension is updated
	*/
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
			return FALSE;

		$this->EE->db->query('UPDATE exp_extensions SET version = \''.$this->EE->db->escape_str($this->version).'\' WHERE class = \''.$this->EE->db->escape_str(__CLASS__).'\'');
	}


// ----------------------


	/**
	* EE method called when the extension is disabled
	*/
	function disable_extension()
	{
		$this->EE->db->query('DELETE FROM exp_extensions WHERE class = \''.$this->EE->db->escape_str(__CLASS__).'\'');
	}


// ----------------------


	/**
	* Configuration for the extension settings page
	*/
	function settings()
	{
		$settings = array();
		$settings['ldap_host']                 = $this->ldap_host;
		$settings['ldap_port']                 = $this->ldap_port;
		$settings['ldap_search_base']          = $this->ldap_search_base;
		$settings['ldap_search_user']          = $this->ldap_search_user;
		$settings['ldap_search_password']      = $this->ldap_search_password;
		$settings['ldap_username_attribute']   = $this->ldap_username_attribute;
		$settings['ldap_character_encode']     = $this->ldap_character_encode;
		$settings['use_ldap_account_creation'] = array('r', array('yes' => 'yes_ldap_account_creation',
		                                                           'no'  => 'no_ldap_account_creation'),
		                                                'yes');
		$settings['admin_email']               = $this->admin_email;
		$settings['from_email']                = $this->from_email;
		$settings['mail_host']                 = $this->mail_host;
		$settings['mail_message']              = array('t', $this->mail_message);
		$settings['no_ldap_login_message']     = array('t', $this->no_ldap_login_message);
		$settings['first_time_login_message']  = array('t', $this->first_time_login_message);
		$settings['created_user_group']        = $this->created_user_group;

		return $settings;
	}


// ----------------------


	/**
	 * Called by the member_member_login_start hook
	 */
	function member_member_login_start()
	{
		return $this->login_authenticate_start();
	}


// ----------------------


	/**
	 * Called by the login_authenticate_start hook
	 */
	function login_authenticate_start()
	{
		$provided_username = $this->EE->input->post('username');
		$provided_password = $this->EE->input->post('password');

		// Multiple LDAP servers
		$ldap_hosts = explode(',', $this->settings['ldap_host']);
		foreach ($ldap_hosts as $ldap_host)
		{
			$connection = $this->create_connection($ldap_host, $this->settings['ldap_port'], $this->settings['ldap_search_user'], $this->settings['ldap_search_password']);
			$result = $this->authenticate_user($connection, $provided_username, $provided_password, $this->settings['ldap_username_attribute'], $this->settings['ldap_search_base']);
			if ($result['authenticated'])
				break;
		}

		if ($this->debug)
		{
			echo'<pre>';
			var_dump($result);
			echo'</pre>';
		}

		if ($result['authenticated'])
		{
			$this->sync_user_details($result);
		}
		else
		{
			$this->debug_print('Could not authenticate username \''.$provided_username.'\' with LDAP');
		}
		$this->close_connection($connection);

		if ($this->debug)
			exit();
	}


// ----------------------


	function sync_user_details($user_info)
	{
			// Sync EE password to match LDAP (if account exists)
			$encrypted_password = $this->EE->functions->hash(stripslashes($user_info['password']));
			$sql = 'UPDATE exp_members SET password = \''.$this->EE->db->escape_str($encrypted_password).'\' WHERE username = \''.$this->EE->db->escape_str($user_info['username']).'\'';
			$this->debug_print('Updating user with SQL: '.$sql);
			$this->EE->db->query($sql);

			// now we might want to do some EE account creation
			if ($this->settings['use_ldap_account_creation'] === 'yes')
			{
				$this->create_ee_user($user_info, $encrypted_password);
			}

	}


// ----------------------


	function create_ee_user($user_info, $encrypted_password)
	{
		$sql = 'SELECT \'username\' FROM exp_members WHERE username = \''.$this->EE->db->escape_str($user_info['username']).'\'';
		$this->debug_print('Checking for existing user with SQL: '.$sql);
		$query = $this->EE->db->query($sql);

		// user doesn't exist in exp_members table, so we will create an EE account
		if ($query->num_rows === 0)
		{
			$this->debug_print('Using LDAP for account creation...');

			$data['screen_name']      = $user_info['cn'][0];
			$data['username']         = $user_info['username'];
			$data['password']         = $encrypted_password;
			$data['email']            = $user_info['mail'][0];
			$data['ip_address']       = '0.0.0.0';
			$data['unique_id']        = $this->EE->functions->random('encrypt');
			$data['join_date']        = $this->EE->localize->now;
			$data['language']         = 'english';
			$data['timezone']         = 'UTC';
			$data['daylight_savings'] = 'n';
			$data['time_format']      = 'eu';
			$data['group_id']         = $this->settings['created_user_group'];

			$this->debug_print('Inserting user with data: '.print_r($data, TRUE));

			$this->EE->load->model('member_model');
			$member_id = $this->EE->member_model->create_member($data);
			if ($member_id > 0) // update other relevant fields
			{
				$sql = 'UPDATE exp_members SET photo_filename = \'photo_'.$member_id.'.jpg\', photo_width = \'90\', photo_height = \'120\'';
				$query = $this->EE->db->query($sql);

				//$this->EE->db->query('INSERT INTO exp_member_data SET member_id = '.$this->EE->db->escape_str($member_id));
				//$this->EE->db->query('INSERT INTO exp_member_homepage SET member_id = '.$this->EE->db->escape_str($member_id));

				$this->EE->stats->update_member_stats();

				$this->settings['mail_message'] = str_replace('{name}',     $user_info['cn'][0],    $this->settings['mail_message']);
				$this->settings['mail_message'] = str_replace('{username}', $user_info['username'], $this->settings['mail_message']);
				$this->settings['mail_message'] = str_replace('{host}',     $_SERVER['HTTP_HOST'],  $this->settings['mail_message']);

				// Email the admin with the details of the new user
				ini_set('SMTP', $this->settings['mail_host']);
				$headers = 'From: '.$this->settings['from_email']."\r\n" .
									 'X-Mailer: PHP/' . phpversion();
				$success = mail(
													$this->settings['admin_email'], 
													'New member \''.$user_info['username'].'\' on http://'.$_SERVER['HTTP_HOST'],
													$this->settings['mail_message'],
													$headers
												);
				$this->EE->session->userdata['ldap_message'] = $this->settings['first_time_login_message'];
			}
			else
			{
				exit('Could not create user account for '.$user_info['username'].'<br/>'."\n");
			}
		}
	}


// ----------------------


	function authenticate_user($conn, $username, $password, $ldap_username_attribute, $ldap_search_base)
	{
		$this->debug_print('Searching for attribute '.$ldap_username_attribute.'='.$username.' ...');
		// Search username entry
		$result = ldap_search($conn, $ldap_search_base, $ldap_username_attribute.'='.$username);
		$this->debug_print('Search result is: '.$result);

		// Search not successful (server down?), so do nothing - standard MySQL authentication can take over
		if ($result === FALSE)
		{
			$this->EE->session->userdata['ldap_message'] = $this->settings['no_ldap_login_message'];
			return array('authenticated' => false);
		}

		$this->debug_print('Number of entires returned is '.ldap_count_entries($conn, $result));
		// username not found, so do nothing - standard MySQL authentication can take over
		if (ldap_count_entries($conn, $result) < 1)
		{
			return array('authenticated' => false);
		}

		$this->debug_print('Getting entries for \''.$username.'\' ...');
		$info = ldap_get_entries($conn, $result); // entry for username found in directory, retrieve entries
		$user_info = $info[0];
		$this->debug_print('Data for '.$info["count"].' items returned<br/>');

		$user_info['username'] = $username;
		$user_info['password'] = $password;
		// Authenticate LDAP user against password submitted on login
		$dn = $user_info['dn'];
		$success = @ldap_bind($conn, $dn, $this->ldap_encode($password)); // bind with user credentials

		if (!$success) 
		{
			$this->debug_print('Error binding with supplied password (dn: '.$dn.') ERROR: '.ldap_error($conn));
		}

		$user_info['authenticated'] = $success;
		return $user_info;
	}


// ----------------------


	function create_connection($ldap_host, $ldap_port, $ldap_search_user, $ldap_search_password)
	{
		$this->debug_print('Connecting to LDAP...');
		$conn = ldap_connect($ldap_host, $ldap_port) or
			die('Could not connect to host: '.$ldap_host.':'.$ldap_port.'<br/>'."\n");
		$this->debug_print('connect result is '.$conn);

		// Perform bind with search user
		if (empty($ldap_search_user))
		{
			$this->debug_print('Binding anonymously...');
			$success = ldap_bind($conn); // this is an "anonymous" bind, typically read-only access
		}
		else
		{
			$this->debug_print('Binding with user: ['.$ldap_search_user.']-['.$ldap_search_password.'] ...');
			$success = ldap_bind($conn, $this->ldap_encode($ldap_search_user), $this->ldap_encode($ldap_search_password)); // bind with credentials
		}
		$this->debug_print('Bind result is '.$success);
		return $conn;
	}


// ----------------------


	function close_connection($conn)
	{
		$this->debug_print('Closing connection...');
		ldap_close($conn) or
			die('Could not close the LDAP connection<br/>'."\n");
	}


// ----------------------


	function debug_print($message, $br="<br/>\n")
	{
		if ($this->debug)
		{
			if (is_array($message))
			{
				print('<pre>');
				print_r($message);
				print('</pre>'.$br);
			}
			else
			{
				print($message.' '.$br);
			}
		}
	}


	function ldap_encode($text)
	{
		return iconv("UTF-8", $this->settings['ldap_character_encode'], $text);
	}


}
// END CLASS Nce_ldap

/* End of file ext.nce_ldap.php */
/* Location: ./system/expressionengine/third_party/nce_ldap/ext.nce_ldap.php */