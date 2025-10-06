<?php
/**
 *  Gaiterjones BuyXGetY Model - Buy X Get Y
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 */

namespace Gaiterjones\BuyXGetY\Model;

use Gaiterjones\BuyXGetY\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Model\Cart;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class BuyXGetY extends \Magento\Framework\Model\AbstractModel
{
    /** @var ProductRepository */
    protected $_productRepository;
    /** @var Cart */
    protected $_cart;
    /** @var CartRepositoryInterface */
    protected $_cartInterface;
    /** @var FormKey */
    protected $formKey;
    /** @var ManagerInterface */
    protected $_messageManager;
    /** @var Data */
    protected $_helperData;
    /** @var LoggerInterface */
    protected $_logger;
    /** @var array */
    private $_buyxgety = [];
    /** @var bool */
    private $_debug = false;

    public function __construct(
        ProductRepository       $productRepository,
        Cart                    $cart,
        FormKey                 $formKey,
        ManagerInterface        $messageManager,
        Data                    $helperData,
        LoggerInterface         $loggerInterface,
        CartRepositoryInterface $cartInterface
    ) {
        $this->_productRepository = $productRepository;
        $this->_cart              = $cart;
        $this->formKey            = $formKey;
        $this->_messageManager    = $messageManager;
        $this->_helperData        = $helperData;
        $this->_logger            = $loggerInterface;
        $this->_cartInterface     = $cartInterface;
        $this->loadConfig();
    }

    /**
     * Load BUYXGETY config data
     */
    protected function loadConfig(): void
    {
        // Optional debug flag (configure in system.xml if desired)
        $this->_debug = (bool)$this->_helperData->getConfig('buyxgety/general/debug');

        $productXSku            = $this->csvToArray($this->_helperData->getConfig('buyxgety/buyxgety/productxsku'));
        $productXMinRequiredQty = $this->csvToArray($this->_helperData->getConfig('buyxgety/buyxgety/productxminrequiredqty'), true);
        $productXMaxAllowedQty  = $this->csvToArray($this->_helperData->getConfig('buyxgety/buyxgety/productxmaxallowedqty'), true);
        $productYSku            = $this->csvToArray($this->_helperData->getConfig('buyxgety/buyxgety/productysku'));
        $productYDescription    = $this->csvToArray($this->_helperData->getConfig('buyxgety/buyxgety/productydescription'), true);

        $config = [
            'buyxgety' => [
                'productxsku'            => $productXSku ?: [],
                'productxminrequiredqty' => $productXMinRequiredQty ?: [],
                'productxmaxallowedqty'  => $productXMaxAllowedQty ?: [],
                'productysku'            => $productYSku ?: [],
                'productydescription'    => $productYDescription ?: [],
            ]
        ];

        $this->_buyxgety['config'] = $config;
        $this->log($config);
    }

    /**
     * Validate BUYXGETY config data
     */
    protected function isConfigValid(): bool
    {
        $cfg = $this->_buyxgety['config']['buyxgety'] ?? [];

        // required arrays present
        foreach (['productxsku', 'productxminrequiredqty', 'productxmaxallowedqty', 'productysku'] as $k) {
            if (empty($cfg[$k]) || !is_array($cfg[$k])) {
                return false;
            }
        }

        // lengths must match across X arrays and Y skus
        $count = count($cfg['productxsku']);
        foreach (['productxminrequiredqty', 'productxmaxallowedqty', 'productysku'] as $k) {
            if (count($cfg[$k]) !== $count) {
                return false;
            }
        }
        // descriptions optional but if provided must align
        if (!empty($cfg['productydescription']) && count($cfg['productydescription']) !== $count) {
            return false;
        }

        // SKUs uniqueness per list (note: isUnique() returns true when duplicates exist)
        if ($this->isUnique($cfg['productxsku']) === true) return false;
        if ($this->isUnique($cfg['productysku']) === true) return false;

        // numeric checks for min/max
        for ($i = 0; $i < $count; $i++) {
            $min = $cfg['productxminrequiredqty'][$i];
            $max = $cfg['productxmaxallowedqty'][$i]; // 0 means unlimited
            if (!ctype_digit((string)$min)) return false;
            if (!ctype_digit((string)$max)) return false;

            $min = (int)$min;
            $max = (int)$max;

            if ($min < 1) return false; // must buy at least 1
            if ($max !== 0 && $max < $min) return false; // if max present, must be >= min
        }

        return true;
    }

    /**
     * BUYXGETY Cart Update Logic
     */
    public function CartUpdate(): void
    {
        if (!$this->isEnabled()) {
            $this->log('BUYXGETY BUY X functionality is disabled in config.');
            return;
        }

        if (!$this->isConfigValid()) {
            $this->addMessage(__('Buy X Get Y configuration is invalid.'), 'error');
            $this->log('BUYXGETY configuration is invalid ' . print_r($this->_buyxgety['config']['buyxgety'] ?? [], true));
            return;
        }

        // get config
        $productXSkus         = $this->_buyxgety['config']['buyxgety']['productxsku'];
        $productXMinQtys      = $this->_buyxgety['config']['buyxgety']['productxminrequiredqty'];
        $productXMaxQtys      = $this->_buyxgety['config']['buyxgety']['productxmaxallowedqty'];
        $productYSkus         = $this->_buyxgety['config']['buyxgety']['productysku'];
        $productYDescriptions = $this->_buyxgety['config']['buyxgety']['productydescription'];

        $cartData = $this->getCartItems();
        if (!$cartData) {
            return;
        }

        foreach ($productXSkus as $key => $rawXSku) {

            // reset wildcard flag per rule
            $wildcardSku = false;
            $productXSku = $rawXSku;
            $productXName = $rawXSku;

            // detect wildcard format <like>TEXT</like>
            if (strpos($productXSku, '<like>') !== false) {
                if (preg_match('/<like>(.*?)<\/like>/s', $productXSku, $match) === 1) {
                    $productXSku = trim($match[1]);
                    $wildcardSku = true;
                    $this->log('BUYXGETY wildcard sku found : ' . $productXSku);
                } else {
                    $this->log('BUYXGETY Wildcard sku ' . $productXSku . ' is invalid.');
                    continue;
                }
            } else {
                // resolve product X to canonical SKU/name (guard exceptions)
                try {
                    $productX   = $this->_productRepository->get($productXSku);
                    $productXSku = $productX->getSku();
                    $productXName = $productX->getName();
                } catch (\Throwable $e) {
                    $this->log('BUYXGETY Product X SKU not found: ' . $productXSku . ' :: ' . $e->getMessage());
                    $this->addMessage(__('Buy X Get Y Product X SKU %1 is not valid.', $productXSku), 'error');
                    continue;
                }
            }

            // aggregate Product X quantity in cart
            $productXCartQuantity = 0;

            if ($wildcardSku) {
                foreach ($cartData['cartItemQuantitiesBySku'] as $cartItemSku => $qtys) {
                    if (strpos($cartItemSku, $productXSku) !== false) {
                        $sum = array_sum((array)$qtys);
                        $productXCartQuantity += (int)$sum;
                        $this->log('BUYXGETY wildcard match ' . $cartItemSku . ' qty ' . $sum . ' total=' . $productXCartQuantity);
                        $productXName = $productXSku;
                    }
                }
            } else {
                if (isset($cartData['cartItemQuantitiesBySku'][$productXSku])) {
                    $productXCartQuantity = (int)array_sum($cartData['cartItemQuantitiesBySku'][$productXSku]);
                }
            }

            $min = (int)$productXMinQtys[$key];
            $max = (int)$productXMaxQtys[$key]; // 0 => unlimited
            $ySku = $productYSkus[$key];
            $yDesc = $productYDescriptions[$key] ?? (string)__('Free Product');

            $this->log(sprintf('product X qty=%d (min=%d/max=%s) for rule %s', $productXCartQuantity, $min, ($max === 0 ? '∞' : (string)$max), $rawXSku));

            // If neither X nor Y in cart, nothing to do
            if ($productXCartQuantity === 0 && !isset($cartData[$ySku])) {
                $this->log('X not in cart and Y not in cart - do nothing.');
                continue;
            }

            // Nudge message: one more to qualify
            if ($productXCartQuantity > 0 && $productXCartQuantity + 1 === $min) {
                $this->addMessage(__('Buy one more %1 to qualify for a %2 !', $productXName, $yDesc));
                $this->log('One more X required to qualify.');
            }

            $meetsMin = ($productXCartQuantity >= $min);
            $withinMax = ($max === 0) ? true : ($productXCartQuantity <= $max);
            $qualifies = ($productXCartQuantity > 0 && $meetsMin && $withinMax);

            // CASE A: qualifies and Y already in cart -> enforce single Y (qty = 1)
            if ($qualifies && isset($cartData[$ySku])) {
                $yQty = (int)$cartData[$ySku]['qty'];
                if ($yQty > 1) {
                    $this->log('Qualifies; Y in cart with qty ' . $yQty . ' -> reducing to 1');
                    $this->checkProductCartQuantity($cartData[$ySku]['itemid'], 1);
                }
                continue;
            }

            // CASE B: qualifies and Y not in cart -> add Y (qty = 1)
            if ($qualifies && !isset($cartData[$ySku])) {
                $this->log('Qualifies; Y not in cart -> add Y: ' . $ySku);
                $this->addProductToCart($ySku, 1, $yDesc);
                continue;
            }

            // CASE C: not qualified but Y present -> remove Y
            if (!$qualifies && isset($cartData[$ySku])) {
                $this->log('Not qualified; Y is in cart -> remove Y');
                $this->removeProductFromCart($cartData[$ySku]['itemid'], $yDesc);
                continue;
            }

            // Else: not qualified and Y not present -> nothing
            $this->log('Not qualified and Y not present - nothing to do.');
        }
    }

    /**
     * Add product Y to cart (default qty = 1)
     */
    protected function addProductToCart($productSku, $qty = 1, $productYDescription = 'Free Product'): void
    {
        $this->log('Adding product SKU ' . $productSku . ' to the cart...');
        $product   = $this->_productRepository->get($productSku);
        $productID = (int)$product->getId();

        $params = [
            'product' => $productID,
            'qty'     => (int)$qty
        ];

        $this->_cart->addProduct($product, $params);
        $this->_cart->save();
        $this->recollectTotals();

        $this->addMessage(__('Your %1 has been added to your cart.', $productYDescription), 'notice');
        $this->log('Product SKU ' . $productSku . ' added to cart.');
    }

    /**
     * Remove product Y from cart by item id
     */
    protected function removeProductFromCart($itemId, $productYDescription = 'Free Product'): void
    {
        $this->_cart->removeItem($itemId);
        $this->_cart->save();
        $this->recollectTotals();

        // Optional: message disabled to avoid noise
        //$this->addMessage(__('Your %1 has been removed from your cart.', $productYDescription), 'notice');

        $this->log('Cart item ID ' . $itemId . ' removed from cart.');
    }

    /**
     * Enforce allowed quantity for an item in the cart
     */
    protected function checkProductCartQuantity($itemId, $qty = 1): void
    {
        $params = [];
        $params[$itemId]['qty'] = (int)$qty;

        $this->_cart->updateItems($params);
        $this->_cart->save();
        $this->recollectTotals();

        //$this->addMessage(__('Only %1 free product(s) allowed.', $qty), 'notice');
        $this->log('Cart item ID ' . $itemId . ' quantity set to ' . $qty . '.');
    }

    /**
     * Add Message with de-duplication
     */
    protected function addMessage($newMessage, $messageType = 'notice')
    {
        $newText = (string)$newMessage;

        foreach ($this->getMessages() as $message) {
            if ($newText === (string)$message) {
                return false; // prevent duplicates
            }
        }

        switch ($messageType) {
            case 'error':
                $this->_messageManager->addErrorMessage($newText);
                break;
            case 'success':
                $this->_messageManager->addSuccessMessage($newText);
                break;
            case 'warning':
                $this->_messageManager->addWarningMessage($newText);
                break;
            default:
                $this->_messageManager->addNoticeMessage($newText);
        }
    }

    /**
     * Get messages from Message Manager as strings
     */
    protected function getMessages(): array
    {
        $messages   = [];
        $collection = $this->_messageManager->getMessages();
        if ($collection && $collection->getItems()) {
            foreach ($collection->getItems() as $message) {
                $messages[] = $message->getText();
            }
        }
        return $messages;
    }

    /**
     * Gets cart items keyed by SKU with some metadata
     *
     * @return array|false
     */
    public function getCartItems()
    {
        $cartItems = $this->_helperData->getCartAllItems();
        $cartItemQuantities = [];
        $cartItemQuantitiesBySku = [];
        $cartData = [];
        $count = 0;

        foreach ($cartItems as $item) {
            $count++;

            $cartData[$item->getSku()] = [
                'name'      => $item->getName(),
                'qty'       => $item->getQty(),
                'itemid'    => $item->getId(),
                'type'      => $item->getProduct()->getTypeId(),
                'productid' => $item->getProduct()->getId(),
            ];

            $cartItemQuantities[$item->getProduct()->getId()][]       = $item->getQty();
            $cartItemQuantitiesBySku[$item->getProduct()->getSku()][] = $item->getQty();
        }

        $cartData['cartItemQuantities']      = $cartItemQuantities;
        $cartData['cartItemQuantitiesBySku'] = $cartItemQuantitiesBySku;

        $this->log(['BUYXGETY' => $cartData]);
        $this->log('BUYXGETY Total Cart Items : ' . $count);

        if (count($cartData) > 0) {
            return $cartData;
        }
        return false;
    }

    /**
     * Is module enabled
     */
    protected function isEnabled(): bool
    {
        return (bool)$this->_helperData->getConfig('buyxgety/buyxgety/buyxgetyenable');
    }

    /**
     * Debug log helper
     */
    public function log($data): void
    {
        if (!$this->_debug) {
            return;
        }

        if (is_array($data)) {
            $this->_logger->debug('debug BUYXGETY : ' . print_r($data, true));
        } else {
            $this->_logger->debug('debug BUYXGETY : ' . $data);
        }
    }

    /**
     * Convert CSV to array with trimming
     *  - When $keepZero = true, "0" is retained (useful for numeric configs like max=0 => unlimited)
     */
    private function csvToArray($csv, bool $keepZero = false): array
    {
        if ($csv === null) {
            return [];
        }
        $parts = array_map('trim', explode(',', (string)$csv));
        return array_values(array_filter($parts, function ($v) use ($keepZero) {
            return $keepZero ? ($v !== '') : ($v !== '' && $v !== '0');
        }));
    }

    /**
     * Check if array has duplicates (returns true if duplicates exist)
     */
    private function isUnique($array): bool
    {
        return (array_unique($array) != $array);
    }

    /**
     * Force totals recollection after cart mutations
     */
    private function recollectTotals(): void
    {
        $quote = $this->_cartInterface->get($this->_cart->getQuote()->getId());
        $quote->setTriggerRecollect(1);
        $quote->setIsActive(true);
        $this->_cartInterface->save($quote->collectTotals());
    }
}
