<?php

/**
 * Copyright © Ariya InfoTech(Yuvraj Raulji) All rights reserved.
 * See COPYING.txt for license details.
 */

namespace AriyaInfoTech\MultiFlatshipping\Model\Carrier\Flatrate;

use Magento\Quote\Model\Quote\Address\RateRequest;

class PriceItemCalc
{
    /**
     * @param RateRequest $request
     * @param int $basePrice
     * @param int $freeBoxes
     * @return float
     */
    public function getShippingPricePerItem(
        \Magento\Quote\Model\Quote\Address\RateRequest $request,
        $basePrice,
        $freeBoxes
    )
    {
        return $request->getPackageQty() * $basePrice - $freeBoxes * $basePrice;
    }

    /**
     * @param RateRequest $request
     * @param int $basePrice
     * @param int $freeBoxes
     * @return float
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getShippingPricePerOrder(
        \Magento\Quote\Model\Quote\Address\RateRequest $request,
        $basePrice,
        $freeBoxes
    )
    {
        return $basePrice;
    }
}
