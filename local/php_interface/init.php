<?php
use Bitrix\Main\EventManager;

if (file_exists(Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/include/delight.php')) {
    require_once(Bitrix\Main\Application::getDocumentRoot() . '/local/php_interface/include/delight.php');
}

if (\Bitrix\Main\Loader::includeModule('sale')) {
    $obDelightCartRules = new \Delight\Discount\CumulativeCalc();
    EventManager::getInstance()->addEventHandler("sale", "OnCondSaleControlBuildList", array($obDelightCartRules, "GetControlDescr"));
}
