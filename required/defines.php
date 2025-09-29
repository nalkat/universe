<?php // 8.3.6 - required/defines.php
define ("REQ_OS_NONE",0x0);
define ("REQ_OS_LINUX",0x1);
define ("REQ_OS_WINDOWS",0x2);

// define PHP extensions
define ("EXTENSION_NONE",0x0);
define ("EXTENSION_BZ2",0x1);
define ("EXTENSION_FILEINFO",0x2);
# 0x3 = REQ_MODULE_BZ2 & REQ_MODULE_FILEINFO
define ("EXTENSION_PCNTL",0x4);
# 0x5 = REQ_MODULE_BZ2 & REQ_MODULE_PCNTL
# 0x6 = REQ_MODULE_FILEINFO & REQ_MODULE_PCNTL
# 0x7 = REQ_MODULE_BZ2 & REQ_MODULE_FILEINFO & REQ_MODULE_PCNTL
define ("EXTENSION_PCRE",0x8);
define ("EXTENSION_PGSQL",0x16);
// add the module requirements 1 per line

define ("DEP_NULL",0x0);
define ("DEP_REQUIRED",0x1);
define ("DEP_OPTIONAL",0x2);
define ("DEP_ALL",0x3);
?>
