<?php

namespace Automattic\VIP\Admin_Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-admin-notice.php';
require_once __DIR__ . '/class-admin-notice-controller.php';
require_once __DIR__ . '/conditions/interface-condition.php';
require_once __DIR__ . '/conditions/class-date-condition.php';

$admin_notice_controller = new Admin_Notice_Controller();
$admin_notice_controller->init();

$admin_notice_controller->add(
	new Admin_Notice(
		'WordPress 5.5.2 will be released on Friday, October 30th',
		[ new Date_Condition( '01-07-2020', '30-10-2020 15:00' ) ]
) );
