<?php

namespace Delight\Discount;

use Bitrix\Sale\Discount\CumulativeCalculator;

class CumulativeCalc extends \CSaleCondCtrlComplex
{
    /**
     * Получение имени класса
     *
     * @return string
     */
    public static function GetClassName(): string
    {
        return __CLASS__;
    }

    /**
     * Получение ID условия
     *
     * @return array
     */
    public static function GetControlID(): array
    {
        return array('DelightCumulativeDiscount');
    }

    /**
     * @param $arParams
     *
     * @return array
     */
    public static function GetControlShow($arParams)
    {
        $arControls = static::GetControls();
        $arResult   = array();
        foreach ($arControls as $arOneControl) {
            $arResult[] = array(
                'controlId' => $arOneControl['ID'],
                'group'     => false,
                'label'     => $arOneControl['LABEL'],
                'showIn'    => static::GetShowIn($arParams['SHOW_IN_GROUPS']),
                'control'   => array(
                    array(
                        'id'   => 'prefix',
                        'type' => 'prefix',
                        'text' => $arOneControl['PREFIX']
                    ),
                    static::GetLogicAtom($arOneControl['LOGIC']),
                    static::GetValueAtom($arOneControl['JS_VALUE'])
                )
            );
        }
        if (isset($arOneControl)) {
            unset($arOneControl);
        }

        return $arResult;
    }

    /**
     * @param bool|string $strControlID
     *
     * @return bool|array
     */
    public static function GetControls($strControlID = false)
    {
        $arControlList = array(
            'DelightCumulativeDiscount' => array(
                'ID'         => 'DelightCumulativeDiscount',
                'FIELD'      => 'ID',
                'FIELD_TYPE' => 'int',
                'LABEL'      => 'Сумма выполненных заказов + сумма текущего заказа',
                'PREFIX'     => 'Сумма выполненных заказов + сумма текущего заказа',
                'LOGIC'      => static::GetLogic(array(BT_COND_LOGIC_EGR)),
                'JS_VALUE'   => array(
                    'type' => 'input'
                ),
                'PHP_VALUE'  => ''
            )
        );

        foreach ($arControlList as &$control) {
            if ( ! isset($control['PARENT'])) {
                $control['PARENT'] = true;
            }
            $control['EXIST_HANDLER'] = 'Y';
            $control['MODULE_ID']     = 'sale';
            $control['MULTIPLE']      = 'N';
            $control['GROUP']         = 'N';
        }
        unset($control);

        if ($strControlID === false) {
            return $arControlList;
        } elseif (isset($arControlList[$strControlID])) {
            return $arControlList[$strControlID];
        } else {
            return false;
        }
    }

    /**
     * Считает сумму выполненных заказов
     *
     * @param array $arFilter
     *
     * @return int
     * @throws \Bitrix\Main\ArgumentException
     */
    public static function getOrdersSum(array $arFilter): int
    {
        $resultSum = 0;
        foreach (array(CumulativeCalculator::TYPE_ORDER_NON_ARCHIVED, CumulativeCalculator::TYPE_ORDER_ARCHIVED) as $orderType) {
            $provider = null;
            if ($orderType === CumulativeCalculator::TYPE_ORDER_ARCHIVED) {
                /** @var \Bitrix\Sale\Archive\Manager $provider */
                $provider = '\Bitrix\Sale\Archive\Manager';
            } elseif ($orderType === CumulativeCalculator::TYPE_ORDER_NON_ARCHIVED) {
                /** @var \Bitrix\Sale\Order $provider */
                $provider = '\Bitrix\Sale\Order';
            }

            $orders = $provider::getList(
                array(
                    'filter' => $arFilter,
                    'select' => array('DATE_INSERT', 'PRICE', 'CURRENCY')
                )
            );

            $currency = null;
            foreach ($orders as $orderData) {
                if ( ! $currency) {
                    $currency = $orderData['CURRENCY'];
                }

                if ($currency !== $orderData['CURRENCY']) {
                    $resultSum += \CCurrencyRates::ConvertCurrency($orderData['PRICE'], $orderData['CURRENCY'], $currency);
                } else {
                    $resultSum += $orderData['PRICE'];
                }
            }
        }

        return $resultSum;
    }

    /**
     * Функция должна вернуть callback функции, делающей расчеты
     *
     * @param      $arOneCondition
     * @param      $arParams
     * @param      $arControl
     * @param      $arSubs
     *
     * @return string
     */
    public static function Generate($arOneCondition, $arParams, $arControl, $arSubs = false): string
    {
        if (is_string($arControl)) {
            $arControl = static::GetControls($arControl);
        }
        $arValues = static::Check($arOneCondition, $arOneCondition, $arControl, false);

        return __CLASS__ . "::CalculateCumulativeDiscount(\$arOrder, {$arValues['value']})";
    }

    /**
     * Считает сумму выполненных заказов + сумму текущего заказа
     * Пример: 5000 >= 2000
     *
     * @param array $arOrder
     * @param int   $CondVal
     *
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     */
    public static function CalculateCumulativeDiscount(array $arOrder, int $CondVal): bool
    {
        if ($arOrder["USER_ID"] > 0) {
            $ordersSum  = self::getOrdersSum(array("STATUS_ID" => "F", "USER_ID" => $arOrder["USER_ID"], "=LID" => $arOrder["SITE_ID"], "=CANCELED" => "N"));
            
            $currentOrderSum = 0;
            // Не учитываем акционные товары при расчёте накопительной скидки на текущий заказ
            foreach($arOrder["BASKET_ITEMS"] as $basketItem){
                if($basketItem["PRICE"] == $basketItem["BASE_PRICE"]){
                    $currentOrderSum += $basketItem["PRICE"];
                }
            }
            
            $valueField = $ordersSum + $currentOrderSum;
            if ($valueField >= $CondVal) {
                return true;
            }
        }

        return false;
    }
}
