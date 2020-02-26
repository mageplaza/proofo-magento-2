<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Proofo
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Proofo\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\HTTP\Client\Curl;
use \Magento\Framework\Json\Helper\Data;
use Mageplaza\Proofo\Helper\Data as Helper;
use \Magento\Directory\Model\CountryFactory;

class SyncOrder implements ObserverInterface
{
    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var Data
     */
    protected $jsonHelper;

    /**
     * @var Helper
     */
    protected $_helperData;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    /**
     * SyncOrder constructor.
     *
     * @param Curl $curl
     * @param Data $jsonHelper
     * @param Helper $helper
     * @param CountryFactory $countryFactory
     */
    public function __construct(
        Curl $curl,
        Data $jsonHelper,
        Helper $helper,
        CountryFactory $countryFactory
    )
    {
        $this->_curl = $curl;
        $this->jsonHelper = $jsonHelper;
        $this->_helperData = $helper;
        $this->_countryFactory = $countryFactory;
    }

    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $sharedSecret = $this->_helperData->getSharedSecret();

        /**
         * @var $order  \Magento\Sales\Model\Order
         */
        $order = $observer->getEvent()->getOrder();

        /**
         * @var $billingAddress \Magento\Sales\Api\Data\OrderAddressInterface
         */
        $billingAddress = $order->getBillingAddress();
        $country = $this->_countryFactory->create()->load($billingAddress->getCountryId());
        $orderItems = $order->getAllVisibleItems();

        $lineItems = [];
        /**
         * @var $item \Magento\Sales\Model\Order\Item
         */
        foreach ($orderItems as $item) {
            $lineItems[] = [
                "title" => $item->getName(),
                "quantity" => $item->getQtyOrdered(),
                "price" => $item->getPrice(),
                "product_link" => $item->getProduct()->getProductUrl(),
                "product_image" => $this->_helperData->getProductImage($item->getProduct())
            ];
        }
        $body = $this->jsonHelper->jsonEncode([
            "billing_address" => [
                "city" => $billingAddress->getCity(),
                "country" => $country->getName(),
                "first_name" => $order->getCustomerFirstname(),
                "last_name" => $order->getCustomerLastname(),
            ],
            "created_at" => $order->getCreatedAt(),
            "line_items" => $lineItems
        ]);
        $generatedHash = base64_encode(hash_hmac('sha256', $body, $sharedSecret, true));
        $this->_curl->setHeaders([
            'Content-Type' => 'application/json',
            'X-Proofo-Hmac-Sha256' => $generatedHash
        ]);
        $this->_curl->post('https://ac1b44c6.ngrok.io/webhook/order', $body);
    }
}
