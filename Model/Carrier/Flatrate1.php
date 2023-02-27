<?php

/**
 * Copyright © Ariya InfoTech(Yuvraj Raulji) All rights reserved.
 * See COPYING.txt for license details.
 */

namespace AriyaInfoTech\MultiMultiFlatshipping\Model\Carrier;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use AriyaInfoTech\MultiMultiFlatshipping\Model\Carrier\Flatrate\PriceCalcItem;
use Psr\Log\LoggerInterface;

class Flatrate1 extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'flatrate1';
    protected $_isFixed = true;
    protected $rateResultFactory;
    protected $rateMethodFactory;
    protected $session;
    protected $state;
    private $itemPriceCalculator;

    public function __construct(ScopeConfigInterface $scopeConfig, ErrorFactory $rateErrorFactory, LoggerInterface $logger, ResultFactory $rateResultFactory, MethodFactory $rateMethodFactory, PriceCalcItem $itemPriceCalculator, Session $session, State $state, array $data = [])
    {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->session = $session;
        $this->state = $state;
        $this->itemPriceCalculator = $itemPriceCalculator;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')){
            return false;
        }

        if ($this->getConfigFlag('for_admin')) {
            if ($this->state->getAreaCode() != 'adminhtml') {
                return false;
            }
        }

        $freeBoxes = $this->getBoxesCountFree($request);
        $this->setFreeBoxes($freeBoxes);

        $result = $this->rateResultFactory->create();

        $shippingPrice = $this->getPriceShipping($request, $freeBoxes);

        if ($shippingPrice !== false) {
            $method = $this->createMethodResult($shippingPrice);
            $result->append($method);
        }
        return $result;
    }

    private function getBoxesCountFree(RateRequest $request)
    {
        $freeBoxes = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    $freeBoxes += $this->getFreeBoxesCountFromChildren($item);
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        return $freeBoxes;
    }

    private function getPriceShipping(RateRequest $request, $freeBoxes)
    {
        $shippingPrice = false;

        $configPrice = $this->getConfigData('price');
        if ($this->getConfigData('type') === 'O') {
            // per order
            $shippingPrice = $this->itemPriceCalculator->getPriceShippingPerOrder($request, $configPrice, $freeBoxes);
        } elseif ($this->getConfigData('type') === 'I') {
            // per item
            $shippingPrice = $this->itemPriceCalculator->getPriceShippingPerItem($request, $configPrice, $freeBoxes);
        }

        $onlyShippingPrice = $shippingPrice;

        $shippingPrice = $this->getFinalPriceWithHandlingFee($shippingPrice);

        if ($shippingPrice !== false && $request->getPackageQty() == $freeBoxes) {
            $shippingPrice = '0.00';
        }

        if ($this->getConfigData('handling_type') == 'FI') { // Fixed - Per Item
            if ($request->getPackageQty() > 1) {
                $onlyhandelfee = $shippingPrice - $onlyShippingPrice;
                $feeadded = $onlyhandelfee * ($request->getPackageQty() - 1);
                $shippingPrice = $shippingPrice + $feeadded;
            }
        }

        if ((($this->getConfigData('min_amount') != '')
                && ($request->getBaseSubtotalInclTax() <= $this->getConfigData('min_amount')))
            && (($this->getConfigData('max_amount') != '')
                && ($request->getBaseSubtotalInclTax() <= $this->getConfigData('max_amount')))) {
            $shippingPrice = false;
        } elseif ((($this->getConfigData('min_amount') != '')
                && ($request->getBaseSubtotalInclTax() >= $this->getConfigData('min_amount')))
            && (($this->getConfigData('max_amount') != '')
                && ($request->getBaseSubtotalInclTax() >= $this->getConfigData('max_amount')))) {
            $shippingPrice = false;
        }
        elseif ((($this->getConfigData('min_amount') != '')
                && ($request->getBaseSubtotalInclTax() <= $this->getConfigData('min_amount')))
            && (($this->getConfigData('max_amount') == ''))) {
            $shippingPrice = false;
        } elseif ((($this->getConfigData('max_amount') != '')
                && ($request->getBaseSubtotalInclTax() >= $this->getConfigData('max_amount')))
            && (($this->getConfigData('min_amount') == ''))) {
            $shippingPrice = false;
        }

        return $shippingPrice;
    }

    protected function createMethodResult($shippingPrice)
    {
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData('title'));
        /**
         * Displayed as shipping method under Carrier
         */
        $method->setMethod($this->getCarrierCode());
        $method->setMethodTitle($this->getConfigData('name'));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        return $method;
    }

    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => $this->getConfigData('name')];
    }
}
