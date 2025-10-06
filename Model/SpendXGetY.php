<?php
/**
 *  Gaiterjones BuyXGetY Model - Spend X Get Y (min/max subtotal)
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 */

namespace Gaiterjones\BuyXGetY\Model;

use Gaiterjones\BuyXGetY\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Model\Cart;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class SpendXGetY extends \Magento\Framework\Model\AbstractModel
{
    protected $_productRepository;
    protected $_cart;
    protected $_cartInterface;
    protected $formKey;
    protected $_messageManager;
    protected $_helperData;
    protected $_logger;

    private $_buyxgety = [];
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

    protected function loadConfig(): void
    {
        $this->_debug = (bool)$this->_helperData->getConfig('buyxgety/general/debug');

        $productYSku            = $this->csvToArray($this->_helperData->getConfig('buyxgety/spendxgety/spendproductysku'));
        $spendCartMaxRequired   = $this->csvToArray($this->_helperData->getConfig('buyxgety/spendxgety/spendcartylimit'), true); // 0 => no upper bound
        $spendCartMinRequired   = $this->csvToArray($this->_helperData->getConfig('buyxgety/spendxgety/spendcarttotalrequired'), true);
        $productYDescription    = $this->csvToArray($this->_helperData->getConfig('buyxgety/spendxgety/productydescription'), true);

        $config = [
            'spendxgety' => [
                'productysku'            => $productYSku ?: [],
                'spendcartmaxrequired'   => $spendCartMaxRequired ?: [],
                'spendcartminrequired'   => $spendCartMinRequired ?: [],
                'productydescription'    => $productYDescription ?: [],
            ]
        ];

        $this->_buyxgety['config'] = $config;
        $this->log($config);
    }

    protected function isConfigValid(): bool
    {
        $cfg = $this->_buyxgety['config']['spendxgety'] ?? [];

        foreach (['productysku','spendcartminrequired'] as $k) {
            if (empty($cfg[$k]) || !is_array($cfg[$k])) return false;
        }

        $count = count($cfg['productysku']);

        // must align
        foreach (['spendcartminrequired','spendcartmaxrequired'] as $k) {
            if (!empty($cfg[$k]) && is_array($cfg[$k]) && count($cfg[$k]) !== $count) return false;
        }
        if (!empty($cfg['productydescription']) && count($cfg['productydescription']) !== $count) return false;

        // numeric validation; min > 0; max >= min (unless max == 0)
        for ($i = 0; $i < $count; $i++) {
            $min = $cfg['spendcartminrequired'][$i] ?? null;
            $max = $cfg['spendcartmaxrequired'][$i] ?? 0;

            if (!is_numeric($min)) return false;
            if (!is_numeric($max) && $max !== null) return false;

            $min = (float)$min;
            $max = (float)$max;

            if ($min <= 0) return false;
            if ($max !== 0.0 && $max < $min) return false;
        }

        // ensure Y SKUs unique (isUnique returns true when duplicates exist)
        if ($this->isUnique($cfg['productysku']) === true) return false;

        return true;
    }

    public function CartUpdate(): void
    {
        if (!$this->isEnabled()) {
            $this->log('BUYXGETY SPEND X is disabled in config.');
            return;
        }

        if (!$this->isConfigValid()) {
            $this->addMessage(__('Spend X Get Y configuration is invalid.'), 'error');
            $this->log('SPENDX invalid config ' . print_r($this->_buyxgety['config']['spendxgety'] ?? [], true));
            return;
        }

        $cfg = $this->_buyxgety['config']['spendxgety'];
        $productYSkus         = $cfg['productysku'];
        $minThresholds        = $cfg['spendcartminrequired'];
        $maxThresholds        = $cfg['spendcartmaxrequired'];
        $productYDescriptions = $cfg['productydescription'];

        // Toggle base vs store subtotal here if needed:
        // $subTotal = (float)$this->_cart->getQuote()->getBaseSubtotalWithDiscount(); // base currency
        $subTotal = (float)$this->_cart->getQuote()->getSubtotalWithDiscount();       // store currency (default)

        $cartData = $this->getCartItems();

        foreach ($productYSkus as $key => $ySku) {
            $min = (float)$minThresholds[$key];
            $max = isset($maxThresholds[$key]) && $maxThresholds[$key] !== '' ? (float)$maxThresholds[$key] : 0.0;
            $desc = $productYDescriptions[$key] ?? (string)__('Free Product');

            $meetsMin = $subTotal >= $min;
            $withinMax = ($max == 0.0) ? true : ($subTotal <= $max);
            $qualifies = $meetsMin && $withinMax;

            $this->log(sprintf('subtotal=%.2f, min=%.2f, max=%s -> qualifies=%s',
                $subTotal, $min, ($max == 0.0 ? '∞' : number_format($max,2)), $qualifies ? 'yes' : 'no'
            ));

            if ($qualifies) {
                if ($cartData && isset($cartData[$ySku])) {
                    // Ensure qty is 1
                    $yQty = (int)$cartData[$ySku]['qty'];
                    if ($yQty > 1) {
                        $this->checkProductCartQuantity($cartData[$ySku]['itemid'], 1);
                        $this->log('Reduced Y qty to 1 for ' . $ySku);
                    }
                } else {
                    $this->addProductToCart($ySku, 1, $desc);
                    $this->log('Added Y ' . $ySku);
                }
            } else {
                if ($cartData && isset($cartData[$ySku])) {
                    $this->removeProductFromCart($cartData[$ySku]['itemid'], $desc);
                    $this->log('Removed Y ' . $ySku . ' (not qualified)');
                } else {
                    $this->log('Y ' . $ySku . ' not in cart (not qualified)');
                }
            }
        }
    }

    public function getCartItems()
    {
        $items = $this->_helperData->getCartAllItems();
        $cartData = [];
        $byId = [];
        $bySku = [];
        $count = 0;

        foreach ($items as $item) {
            $count++;
            $cartData[$item->getSku()] = [
                'name'      => $item->getName(),
                'qty'       => $item->getQty(),
                'itemid'    => $item->getId(),
                'type'      => $item->getProduct()->getTypeId(),
                'productid' => $item->getProduct()->getId(),
            ];
            $byId[$item->getProduct()->getId()][]        = $item->getQty();
            $bySku[$item->getProduct()->getSku()][]      = $item->getQty();
        }

        $cartData['cartItemQuantities']      = $byId;
        $cartData['cartItemQuantitiesBySku'] = $bySku;

        $this->log(['SPENDXGETY' => $cartData]);
        $this->log('SPENDXGETY Total Cart Items: ' . $count);

        return count($cartData) > 0 ? $cartData : false;
    }

    protected function addProductToCart($productSku, $qty = 1, $productYDescription = 'Free Product'): void
    {
        $product   = $this->_productRepository->get($productSku);
        $productID = (int)$product->getId();

        $params = ['product' => $productID, 'qty' => (int)$qty];

        $this->_cart->addProduct($product, $params);
        $this->_cart->save();
        $this->recollectTotals();

        $this->addMessage(__('Your %1 has been added to your cart.', $productYDescription), 'notice');
    }

    protected function removeProductFromCart($itemId, $productYDescription = 'Free Product'): void
    {
        $this->_cart->removeItem($itemId);
        $this->_cart->save();
        $this->recollectTotals();
        // Intentionally no message to avoid spam
    }

    protected function checkProductCartQuantity($itemId, $qty = 1): void
    {
        $params = [];
        $params[$itemId]['qty'] = (int)$qty;

        $this->_cart->updateItems($params);
        $this->_cart->save();
        $this->recollectTotals();
    }

    protected function addMessage($newMessage, $messageType = 'notice')
    {
        $newText = (string)$newMessage;
        foreach ($this->getMessages() as $message) {
            if ($newText === (string)$message) return false;
        }
        switch ($messageType) {
            case 'error':   $this->_messageManager->addErrorMessage($newText); break;
            case 'success': $this->_messageManager->addSuccessMessage($newText); break;
            case 'warning': $this->_messageManager->addWarningMessage($newText); break;
            default:        $this->_messageManager->addNoticeMessage($newText);
        }
    }

    protected function getMessages(): array
    {
        $messages = [];
        $collection = $this->_messageManager->getMessages();
        if ($collection && $collection->getItems()) {
            foreach ($collection->getItems() as $message) {
                $messages[] = $message->getText();
            }
        }
        return $messages;
    }

    protected function isEnabled(): bool
    {
        return (bool)$this->_helperData->getConfig('buyxgety/spendxgety/spendxgetyenable');
    }

    public function log($data): void
    {
        if (!$this->_debug) return;
        $this->_logger->debug('debug SPENDXGETY : ' . (is_array($data) ? print_r($data, true) : $data));
    }

    private function csvToArray($csv, bool $keepZero = false): array
    {
        if ($csv === null) return [];
        $parts = array_map('trim', explode(',', (string)$csv));
        return array_values(array_filter($parts, function ($v) use ($keepZero) {
            return $keepZero ? ($v !== '') : ($v !== '' && $v !== '0');
        }));
    }

    // returns true if duplicates exist (kept to match earlier code)
    private function isUnique($array): bool
    {
        return (array_unique($array) != $array);
    }

    private function recollectTotals(): void
    {
        $quote = $this->_cartInterface->get($this->_cart->getQuote()->getId());
        $quote->setTriggerRecollect(1);
        $quote->setIsActive(true);
        $this->_cartInterface->save($quote->collectTotals());
    }
}
