<?php
/**
 *  Gaiterjones BuyXGetY Model
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */

namespace Gaiterjones\BuyXGetY\Model;
use Gaiterjones\BuyXGetY\Helper\Data;
use \Magento\Catalog\Model\ProductRepository;
use \Magento\Checkout\Model\Cart;
use \Magento\Framework\Data\Form\FormKey;
use \Magento\Framework\Message\ManagerInterface;
use \Psr\Log\LoggerInterface;

/**
 * BuyXGetY Model
 */
class BuyXGetY extends \Magento\Framework\Model\AbstractModel
{

    /**
     * @var ProductRepository
     */
    protected $_productRepository;
    /**
     * @var Cart
     */
    protected $_cart;
    /**
     * @var FormKey
     */
    protected $formKey;
    /**
     * @var ManagerInterface
     */
    protected $_messageManager;
    /**
     * @var Helper
     */
    protected $_helperData;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var array
     */
    private $_buyxgety;
    /**
     * @var boolean
     */
    private $_debug;

    /**
     * @param ProductRepository $productRepository
     * @param Cart $cart
     * @param FormKey $formKey
     * @param ManagerInterface $messageManager
     * @param Data $helperData
     * @param LoggerInterface $loggerInterface
     */
    public function __construct(
        ProductRepository $productRepository,
        Cart $cart,
        FormKey $formKey,
        ManagerInterface $messageManager,
        Data $helperData,
        LoggerInterface $loggerInterface
    ) {
        $this->_productRepository = $productRepository;
        $this->_cart = $cart;
        $this->formKey = $formKey;
        $this->_messageManager = $messageManager;
        $this->_helperData = $helperData;
        $this->_logger = $loggerInterface;
        $this->loadConfig();

    }

    /**
     * loadConfig load BUYXGETY config data
     *
     * @return boolean
     */
    protected function loadConfig(){

        $this->debug=true;

        $productXSku=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/general/productxsku')));
        if (empty($productXSku)){$productXSku=false;}

        $productXMinRequiredQty=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/general/productxminrequiredqty')));
        if (empty($productXMinRequiredQty)){$productXMinRequiredQty=false;}

        $productYSku=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/general/productysku')));
        if (empty($productYSku)){$productYSku=false;}

        $config=array(
            'buyxgety' => array(
                'productxsku'              => $productXSku,
                'productxminrequiredqty'   => $productXMinRequiredQty,
                'productysku'              => $productYSku
            )
        );

        $this->log($config);
        $this->_buyxgety['config']=$config;
    }

    /**
     * isConfigValid validate BUYXGETY config data
     *
     * @return boolean
     */
    protected function isConfigValid()
    {
        foreach($this->_buyxgety['config']['buyxgety'] as $key => $configData)
        {
            if ($configData===false){return false;}
            if ($key !== 'productxminrequiredqty')
            {
                if ($this->isUnique($configData)== true){return false;}
            }
        }

        return true;
    }

    /**
     * CartUpdate BUYXGETY Cart Update Logic
     *
     * @return boolean
     */
    public function CartUpdate()
    {
        // is module enabled
        //
        if (!$this->isEnabled()){return;}
        // is module config valid
        //
        if (!$this->isConfigValid())
        {
            $this->addMessage('Buy X Get Y configuration is invalid.','error');
            return;
        }
        // get config
        //
        $productXSkus=$this->_buyxgety['config']['buyxgety']['productxsku'];
        $productXMinQtys=$this->_buyxgety['config']['buyxgety']['productxminrequiredqty'];
        $productYSkus=$this->_buyxgety['config']['buyxgety']['productysku'];

        $cartData=$this->getCartItems();

        // update cart
        //
        if ($cartData)
        {
            foreach ($productXSkus as $key => $productXSku)
            {

                // load product x
                $productX=$this->_productRepository->get($productXSku);
                if (!$productX){$this->addMessage('Buy X Get Y Product X SKU '. $productXSku. ' is not valid.','error');}

                // get aggregated product x cart quantity
                $productXCartQuantity=false;
                if (isset($cartData['cartItemQuantities'][$productX->getID()])) {$productXCartQuantity=array_sum($cartData['cartItemQuantities'][$productX->getID()]);}


                // BUYXGETY LOGIC
                //
                if (
                        !$productXCartQuantity &&
                        !isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x NOT in Cart
                    // product y NOT in Cart
                    $this->log('product x ('. $productX->getSku() .') NOT in cart product y NOT in cart - do nothing.');
                    continue;
                }


                if (
                        ($productXCartQuantity && ($productXCartQuantity +1 ) == $productXMinQtys[$key])

                    )
                {
                    // product x in cart one more required for free shit - send message
                    $this->addMessage('Buy one more '. $productX->getName(). ' to qualify for a free product!');
                }


                if (
                        ($productXCartQuantity && $productXCartQuantity >= $productXMinQtys[$key])
                        &&
                        (isset($cartData[$productYSkus[$key]]))
                    )
                {
                    // product x in Cart and meets required productxqty
                    // product y in Cart
                    $this->log('product x and product y['. $cartData[$productYSkus[$key]]['qty']. '] in cart - product y will be controlled for quantity...');
                    if ($itemId=$cartData[$productYSkus[$key]]['qty'] > 1)
                    {
                        $itemId=$cartData[$productYSkus[$key]]['itemid'];
                        $this->checkProductCartQuantity($itemId);
                    }
                    continue;
                }



                if (
                        ($productXCartQuantity && $productXCartQuantity >= $productXMinQtys[$key] )
                         &&
                        !isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x in Cart and meets required productxqty
                    // product y NOT in Cart
                    // add product y
                    $this->log('product x IN cart product y NOT in cart - adding product y... ');
                    $this->addProductToCart($productYSkus[$key]);
                    continue;
                }



                if (
                        (!$productXCartQuantity) || ($productXCartQuantity && $productXCartQuantity < $productXMinQtys[$key] )
                        &&
                        isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x NOT in cart or product x in cart but doesnt meet qty requirement
                    // product y IS in Cart
                    $this->log('product x not in cart product y IS in cart - product y should be removed.');
                    $itemId=$cartData[$productYSkus[$key]]['itemid'];
                    $this->removeProductFromCart($itemId);
                    continue;
                }
            }
        }



    }

    /**
     * addProductToCart adds $productSku to cart default $qty = 1
     *
     * @param string $productSku
     * @param int $qty
     * @return boolean
     */
    protected function addProductToCart($productSku,$qty=1)
    {
        $product = $this->_productRepository->get($productSku);
        $productID=$product->getId();

        $params = array(
            'product' => $productID,
            'qty' => $qty
        );

        $this->_cart->addProduct($product,$params);
        $this->_cart->save();
        $this->addMessage('Your free product has been added to the cart.','success');
        $this->log('product SKU '. $productSku. ' was added to the cart.');

    }

    /**
     * removeProductFromCart removes $itemID from cart
     *
     * @param string $itemId
     * @return boolean
     */
    protected function removeProductFromCart($itemId)
    {

        $this->_cart->removeItem($itemId);
        $this->_cart->save();
        $this->addMessage('Your free product has been removed from the cart.','notice');
        $this->log('cart ID '. $itemId. ' was removed from the cart.');

    }

    /**
     * checkProductCartQuantity controls the allowed quantitiy for $item in the cart by setting $qty
     *
     * @param string $itemId
     * @param int $qty
     * @return boolean
     */
    protected function checkProductCartQuantity($itemId,$qty=1)
    {
        $params[$itemId]['qty'] = $qty;
        $this->_cart->updateItems($params);
        //$this->_cart->saveQuote();
        $this->_cart->save();
        $this->addMessage('Only '. $qty. ' free product is allowed.','notice');
        $this->log('cart ID '. $itemId. ' was checked for qty.');

    }

    /**
     * Add Message - checks for message duplication
     *
     * @param string $newMessage
     * @param string $messageType - defaults to notice
     * @return boolean
     */
    protected function addMessage($newMessage,$messageType='notice')
    {

        $messages=$this->getMessages();
        foreach ($messages as $message)
        {
            // try not to duplicate messages
            if ($newMessage == $message){return false;}
        }

        if ($messageType==='notice'){$this->_messageManager->addNoticeMessage($newMessage);}
        if ($messageType==='error'){$this->_messageManager->addErrorMessage($newMessage);}
        if ($messageType==='success'){$this->_messageManager->addSuccessMessage($newMessage);}
        if ($messageType==='warning'){$this->_messageManager->addWarningMessage($newMessage);}

    }

    /**
     * getMessages from Message Manager and create array of messages
     *
     * @return array
     */
    protected function getMessages()
    {
        $messages = array();
        $collection = $this->_messageManager->getMessages();
        if ($collection && $collection->getItems()) {
            foreach ($collection->getItems() as $message) {
                $messages[] = $message->getText();
            }
        }

        return $messages;
    }

    /**
     * Gets cart from session
     *
     * @return array
     */
    public function getCartItems()
    {
        // build cart data arrqy
        //
        $cartItems = $this->_helperData->getCartAllItems();
        $cartData=false;
        $count=0;

        foreach ($cartItems as $item)
        {
            $count++;
            // if item has parent, use parent
            //
            if ($item->getParentItem()) {$item=$item->getParentItem();}

            $cartData[$item->getSku()]=array(
                'name' =>  $item->getName(),
                'qty' =>  $item->getQty(),
                'itemid' =>  $item->getId(),
                'type' => $item->getProduct()->getTypeId(),
                'productid' => $item->getProduct()->getId(),
            );

            // buil quantities array to get totals for products with parents, i.e. configurable products
            //
            $cartItemQuantities[$item->getProduct()->getId()][] = $item->getQty();
        }

        $cartData['cartItemQuantities']=$cartItemQuantities;
        $this->log($cartData);
        $this->log('Total Cart Items : '. $count);
        return $cartData;

    }

    /**
     * isEnabled
     *
     * @return boolean
     */
    protected function isEnabled()
    {
        return $this->_helperData->getConfig('buyxgety/general/enable');
    }

    /**
     * log
     *
     * @param string $data
     * @return boolean
     */
    public function log($data)
    {
        if (!$this->debug) {return;}

        if (is_array($data))
        {
            $this->_logger->debug('debug BUYXGETY : '. print_r($data,true));
        } else {
            $this->_logger->debug('debug BUYXGETY : '. $data);
        }

    }

    /**
     * removes empty values from an array - useful when using explode()
     *
     * @param array $array
     * @return array
     */
    private function cleanArray($array)
    {
        foreach ($array as $key => $value) {
            if (empty($value)) {
               unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * isUnique check if array as unique values
     *
     * @param array $array
     * @return boolean
     */
    private function isUnique($array)
	{
        return (array_unique($array) != $array);
	}

}
