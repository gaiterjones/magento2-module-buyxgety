<?php

/**
 * BuyXGetY data helper
 */
namespace Gaiterjones\BuyXGetY\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Checkout\Model\Session as CheckoutSession;
use \Psr\Log\LoggerInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var CheckoutSession */
    protected $checkoutSession;
    /** @var Logger */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        CheckoutSession $checkoutSession,
        LoggerInterface $loggerInterface
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->_logger = $loggerInterface;
        parent::__construct($context);
    }

    public function isProductInCart($productSku)
    {
        $cartItems = $this->getCartAllItems();

        foreach ($cartItems as $item)
        {
            if ($item->getSku()==$productSku) {return true;}
        }

        return false;
    }

    public function getCartQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    public function log($data)
    {
        $this->_logger->debug('Logging BUYXGETY METHOD : '. $data);
    }

    public function getCartAllItems()
    {
        $quote = $this->getCartQuote();
        //return $quote->getAllItems();
        return $quote->getAllVisibleItems();
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

}
