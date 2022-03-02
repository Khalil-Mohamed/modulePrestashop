<?php
include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');
include(dirname(__FILE__) . '/elvetisPacom1.php');
if (substr(Tools::encrypt('elvetisPacom1/cron'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('elvetisPacom1')) :
    die('Bad token');
else :
    $elevtis_module = new elvetispacom1();
    $elvetis = $elevtis_module->elvetisStock();
endif;
