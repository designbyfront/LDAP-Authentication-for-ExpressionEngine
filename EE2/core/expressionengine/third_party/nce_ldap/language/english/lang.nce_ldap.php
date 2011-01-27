<?php

$lang = array(

	'ldap_host'                 => 'Server Address [e.g. ldap://myhost] (comma separate for multiple servers)',
	'ldap_port'                 => 'Port number [default = 389]',
	'ldap_search_base'          => 'Search base',
	'ldap_search_user'          => 'LDAP user (leave blank for anonymous binding)',
	'ldap_search_password'      => 'LDAP password (leave blank for anonymous binding)',
	'ldap_username_attribute'   => 'Attribute in the directory to identify your users',
	'ldap_character_encode'     => 'LDAP encoding type',
	'use_ldap_account_creation' => 'Use LDAP for account creation',
	'created_user_group'        => 'Group ID to assign newly created users',

	'admin_email'  => 'Address to send system notifications (e.g. when new user regsitered via LDAP)',
	'from_email'   => 'System notifications will appear to be from',
	'mail_host'    => 'SMTP mail host',
	'mail_message' => 'System notifications sent to EE administrator when new account created',

	'no_ldap_login_message'    => 'Message attached to user session object when LDAP server is down [Use Extension_msg extension to display message]',
	'first_time_login_message' => 'Message attached to user session object on first log in (ie. when preferences have not been set)  [Use Extension_msg extension to display message]',

	'yes_ldap_account_creation' => 'Yes',
	'no_ldap_account_creation'  => 'No',

	'' => ''
);
// end array

/* End of file lang.nce_ldap.php */
/* Location: ./system/expressionengine/third_party/nce_ldap/language/english/lang.nce_ldap.php */