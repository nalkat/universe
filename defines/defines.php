<?php // 7.3.0
// file locations rooted inside the repository so the simulator operates without
// external /env mounts or shared environment variables.
$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$logRoot = $projectRoot . '/runtime/logs';
$runRoot = $projectRoot . '/runtime/run';
$tmpRoot = $projectRoot . '/runtime/tmp';
$hostId = $_SERVER['SERVER_NAME'] ?? php_uname('n') ?? gethostname() ?? 'local';

define("LOGROOT", rtrim($logRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hostId . DIRECTORY_SEPARATOR . 'hostDaemon');
define("RUNROOT", rtrim($runRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hostId . DIRECTORY_SEPARATOR . 'hostDaemon');
define("TMPROOT", rtrim($tmpRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hostId . DIRECTORY_SEPARATOR . 'hostDaemon');
if (!defined("PHPROOT")) {
        define("PHPROOT", rtrim($projectRoot, DIRECTORY_SEPARATOR));
}

define ("SIGNAL_DAEMON",0x0);
define ("SIGNAL_PARENT",0x1);
define ("SIGNAL_CHILD",0x2);

// polling modes
define ("POLL_SELECT", 0x1);
define ("SELECT_POLL_REALTIME", 0x0);
define ("SELECT_POLL_ACTIVE", 0x1);
define ("SELECT_POLL_INACTIVE", 0x2);
define ("SELECT_POLL_OFFLINE", 0x4);	// unused

define ("CLIENT_POLL_REALTIME", 0x0);
define ("CLIENT_POLL_ACTIVE", 0x1);
define ("CLIENT_POLL_INACTIVE", 0x2);

define ("POLL_REAPER", 0x2);
define ("REAPER_POLL_REALTIME",0x0);
define ("REAPER_POLL_ACTIVE",0x1);
define ("REAPER_POLL_INACTIVE",0x2);
define ("REAPER_POLL_OFFLINE", 0x4);	// unused

// server command result codes
define ("SERVER_COMMAND_OK",0);		// the command completed successfully
define ("SERVER_COMMAND_INVALID", 1);	// the command was not recognized 
define ("SERVER_COMMAND_ERROR",2);	// an error occurred while executing the command
define ("SERVER_COMMAND_RESTART",252);		// user requested that the service restart
define ("SERVER_COMMAND_DISCONNECT",253);	// user issued "q|uit", exit, logout, logoff, etc...
define ("SERVER_COMMAND_SHUTDOWN",254);		// user requested that the service shutdown

// daemonize result codes
define ("DAEMON_FAILSETPGRP",-3);
define ("DAEMON_SESSFAIL",-2);
define ("DAEMON_FORKFAIL",-1);
define ("DAEMON_PARENT",0);
define ("DAEMON_SESSLEAD",1);
define ("DAEMON_RUNNING",2);

// log output flags
if (!defined("L_NULL")) define ("L_NULL", 0x0);
if (!defined("L_ERROR")) define ("L_ERROR", 0x1);
if (!defined("L_ACCESS")) define ("L_ACCESS", 0x2);
if (!defined("L_CONSOLE")) define ("L_CONSOLE", 0x4);
if (!defined("L_DEBUG")) define ("L_DEBUG", 0x8);
if (!defined("L_ALL")) define ("L_ALL", 0xF);

// error codes
define ("E_BADSOCKET", 127);
define ("E_NOCLIENT", 128);

define ("CLIENT_MAX_IBUFFER", 1024);	// read buffer
define ("CLIENT_MAX_OBUFFER", 4096);	// write buffer
?>
