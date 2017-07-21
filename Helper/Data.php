<?php

/**
 * BuyXGetY data helper
 */
namespace Gaiterjones\BuyXGetY\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Checkout\Model\Session as CheckoutSession;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Framework\HTTP\Adapter\FileTransferFactory
     */
    protected $httpFactory;

    /**
     * File Uploader factory
     *
     * @var \Magento\Core\Model\File\UploaderFactory
     */
    protected $_fileUploaderFactory;

    /**
     * File Uploader factory
     *
     * @var \Magento\Framework\Io\File
     */
    protected $_ioFile;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /** @var CheckoutSession */
    protected $checkoutSession;

    protected $_logger;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\File\Size $fileSize,
        \Magento\Framework\HTTP\Adapter\FileTransferFactory $httpFactory,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory,
        \Magento\Framework\Filesystem\Io\File $ioFile,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Image\Factory $imageFactory,
        CheckoutSession $checkoutSession,
        \Psr\Log\LoggerInterface $loggerInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->httpFactory = $httpFactory;
        $this->_fileUploaderFactory = $fileUploaderFactory;
        $this->_ioFile = $ioFile;
        $this->_storeManager = $storeManager;
        $this->_imageFactory = $imageFactory;
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

    public function testMessage()
    {
        return 'This is a test';
    }

}
