<?php
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');
include(dirname(__FILE__) . '/pharmagestPacom1.php');
if (substr(Tools::encrypt('pharmagestPacom1/cron'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('pharmagestPacom1')) :
    die('Bad token');
else :
    $pharmagest_module = new pharmagestPacom1();
    $pharmagest = $pharmagest_module->pharmagestStock();
endif;
