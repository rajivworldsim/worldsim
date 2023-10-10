<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManagerPlus\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magefan\GoogleTagManager\Model\Config;
use Magefan\GoogleTagManagerPlus\Api\DataLayer\AddToCartInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductAddToCartAfter implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var AddToCartInterface
     */
    private $addToCart;

    /**
     * ProductAddToCartAfter constructor.
     *
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param AddToCartInterface $addToCart
     */
    public function __construct(
        Config $config,
        CheckoutSession $checkoutSession,
        AddToCartInterface $addToCart
    ) {
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->addToCart = $addToCart;
    }

    /**
     * Set datalayer after add product to cart
     *
     * @param Observer $observer
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if ($this->config->isEnabled()) {
            $quoteItem = $observer->getData('quote_item');
            $dataLayers = $this->checkoutSession->getMfAddToCartDataLayers() ?: [];
            $dataLayers[] = $this->addToCart->get($quoteItem);
            $this->checkoutSession->setMfAddToCartDataLayers($dataLayers);
        }
    }
}
