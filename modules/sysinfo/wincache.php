<?php
/**
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2008-2017
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

$extdir =  eZExtension::baseDirectory();
ob_start();
include( $extdir . '/ggsysinfo/modules/sysinfo/lib/wincache.php' );
$output = ob_get_contents();
ob_end_clean();
$output = preg_replace( array( '#^.*<body>#s','#</body>.*$#s' ), '', $output );

$tpl->setVariable( 'css', 'wincache.css' );
$tpl->setVariable( 'info', $output );
