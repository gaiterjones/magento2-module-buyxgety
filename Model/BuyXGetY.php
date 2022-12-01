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
use \Magento\Quote\Api\CartRepositoryInterface;
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
    protected $_cartInterface;
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
        LoggerInterface $loggerInterface,
        CartRepositoryInterface $cartInterface
    ) {
        $this->_productRepository = $productRepository;
        $this->_cart = $cart;
        $this->formKey = $formKey;
        $this->_messageManager = $messageManager;
        $this->_helperData = $helperData;
        $this->_logger = $loggerInterface;
        $this->_cartInterface=$cartInterface;
        $this->loadConfig();

    }

    /**
     * loadConfig load BUYXGETY config data
     *
     * @return boolean
     */
    protected function loadConfig(){

        $this->_debug=true;

        $productXSku=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/buyxgety/productxsku')));
        if (empty($productXSku)){$productXSku=false;}

        $productXMinRequiredQty=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/buyxgety/productxminrequiredqty')));
        if (empty($productXMinRequiredQty)){$productXMinRequiredQty=false;}

        $productXMaxAllowedQty=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/buyxgety/productxmaxallowedqty')));
        if (empty($productXMaxAllowedQty)){$productXMaxAllowedQty=false;}

        $productYSku=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/buyxgety/productysku')));
        if (empty($productYSku)){$productYSku=false;}

        $productYDescription=$this->cleanArray(explode(',',$this->_helperData->getConfig('buyxgety/buyxgety/productydescription')));
        if (empty($productYDescription)){$productYDescription=false;}

        $config=array(
            'buyxgety' => array(
                'productxsku'              => $productXSku,
                'productxminrequiredqty'   => $productXMinRequiredQty,
                'productxmaxallowedqty'    => $productXMaxAllowedQty,
                'productysku'              => $productYSku,
                'productydescription'      => $productYDescription
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
            // ensure config values are unique
            //
            if ($key==='productxsku' ){continue;}
            if ($key==='productydescription' ){continue;}
            if ($key==='productxminrequiredqty' ){continue;}
            if ($key==='productxmaxallowedqty' ){continue;}

            if ($configData===false){return false;}

            if ($this->isUnique($configData)== true){
                return false;
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
        if (!$this->isEnabled()){
            $this->log('BUYXGETY BUY X functionality is disabled in config.');
            return;
        }

        // is module config valid
        //
        if (!$this->isConfigValid())
        {
            $this->addMessage(__('Buy X Get Y configuration is invalid.'),'error');
            $this->log('BUYXGETY configuration is invalid '. print_r($this->_buyxgety['config']['buyxgety'],true));
            return;
        }

        // get config
        //
        $productXSkus=$this->_buyxgety['config']['buyxgety']['productxsku'];
        $productXMinQtys=$this->_buyxgety['config']['buyxgety']['productxminrequiredqty'];
        $productXMaxQtys=$this->_buyxgety['config']['buyxgety']['productxmaxallowedqty'];
        $productYSkus=$this->_buyxgety['config']['buyxgety']['productysku'];
        $productYDescriptions=$this->_buyxgety['config']['buyxgety']['productydescription'];

        $cartData=$this->getCartItems();

        $wildcardSku=false;

        // update cart
        //
        if ($cartData)
        {
            foreach ($productXSkus as $key => $productXSku)
            {
                // check for wildcard sku
                //
                if (strpos($productXSku, '<like>')!== false)
                {
                    // wildcard sku found
                    // extract sku text between * i.e. <like>SKU-XYZ</like>
                    //
                    if (preg_match('/<like>(.*?)<\/like>/s', $productXSku, $match) == 1) {

                        $productXSku=$match[1];
                        $this->log('BUYXGETY wildcard sku found : '.$productXSku);
                        $wildcardSku=true;
                    } else {
                        //$this->addMessage(__('Wildcard sku '. $productXSku. ' is invalid.'),'error');
                        $this->log('BUYXGETY Wildcard sku '. $productXSku. ' is invalid.');
                        continue;
                    }

                    if (!$wildcardSku){$this->addMessage(__('Buy X Get Y Product X WILDCARD SKU '. $productXSku. ' is not valid.'),'error');}

                } else {

                    // load product x
                    //
                    $productX=$this->_productRepository->get($productXSku);
                    if (!$productX){$this->addMessage(__('Buy X Get Y Product X SKU '. $productXSku. ' is not valid.'),'error');}

                    $productXSku=$productX->getSku();
                    $productXId=$productX->getID();
                    $productXName=$productX->getName();
                }

                // get aggregated product x cart quantity
                //
                //
                if ($wildcardSku)
                {
                    $this->log('BUYXGETY wildcardsku='.$productXSku);
                    $productXCartQuantity=0;
                    foreach ($cartData['cartItemQuantitiesBySku'] as $cartItemSku => $cartItemQty)
                    {
                        if (strpos($cartItemSku, $productXSku)!== false)
                        {
                            $productXCartQuantity=$productXCartQuantity+$cartItemQty[0];
                            $this->log('BUYXGETY wildcardsku FOUND in CART - qty '.$cartItemQty[0]. ' total='. $productXCartQuantity. ' min='. $productXMinQtys[$key] . ' max='. $productXMaxQtys[$key]);
                            $productXName=$productXSku;
                        }
                    }

                } else {

                    $productXCartQuantity=false;
                    if (isset($cartData['cartItemQuantitiesBySku'][$productXSku])) {
                        $productXCartQuantity=array_sum($cartData['cartItemQuantitiesBySku'][$productXSku]);
                    }
                }

                $this->log('product x quantity = '. $productXCartQuantity);

                // BUYXGETY LOGIC
                //
                if (
                        !$productXCartQuantity &&
                        !isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x NOT in Cart
                    // product y NOT in Cart
                    $this->log('product x ('. $productXSku .') NOT in cart product y NOT in cart - do nothing.');
                    continue;
                }


                if (
                        ($productXCartQuantity && ($productXCartQuantity +1 ) == $productXMinQtys[$key])

                    )
                {
                    // product x in cart one more required for free shit - send message
                    //$this->addMessage(__('Buy one more '. $productXName. ' to qualify for a free product!'));
                    $this->addMessage(__('Buy one more %1 to qualify for a %2 !',$productXName,$productYDescriptions[$key]));
                    $this->log('product x['.$productXCartQuantity. '] (min='. $productXMinQtys[$key]. '/max='. $productXMaxQtys[$key]. ') one more required to meet min x='.$productXMinQtys[$key]);
                }

                // LOGIC 1
                if (
                        ($productXCartQuantity &&
                            $productXCartQuantity >= $productXMinQtys[$key] &&
                            $productXCartQuantity <= $productXMaxQtys[$key]
                        )
                        &&
                        (isset($cartData[$productYSkus[$key]]))
                    )
                {
                    // product x in Cart and meets required productx MIN and MAX
                    // product y in Cart
                    $this->log('product x['.$productXCartQuantity. '] (min='. $productXMinQtys[$key]. '/max='. $productXMaxQtys[$key]. ') and product y['. $cartData[$productYSkus[$key]]['qty']. '] in cart - product y will be controlled for quantity...');

                    if ($itemId=$cartData[$productYSkus[$key]]['qty'] > 1)
                    {
                        $itemId=$cartData[$productYSkus[$key]]['itemid'];
                        $this->checkProductCartQuantity($itemId);
                    }
                    continue;
                }


                // LOGIC 2
                if (
                        ($productXCartQuantity &&
                            (
                             $productXCartQuantity >= $productXMinQtys[$key] &&
                             $productXCartQuantity <= $productXMaxQtys[$key]
                            )
                        )
                         &&
                        !isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x in Cart and meets required productxqty
                    // product y NOT in Cart
                    // add product y
                    $this->log('product x['.$productXCartQuantity. '] (min='. $productXMinQtys[$key]. '/max='. $productXMaxQtys[$key]. ') IN cart product y NOT in cart - adding product y : '. $productYSkus[$key]);
                    $this->addProductToCart($productYSkus[$key],1,$productYDescriptions[$key]);
                    continue;

                }



                if (
                        (
                            (!$productXCartQuantity) ||
                            ($productXCartQuantity && $productXCartQuantity < $productXMinQtys[$key])
                            ||
                            ($productXCartQuantity && $productXCartQuantity > $productXMaxQtys[$key])
                        )

                        &&
                        isset($cartData[$productYSkus[$key]])
                    )
                {
                    // product x NOT in cart or product x in cart but doesnt meet qty requirement
                    // product y IS in Cart
                    $this->log('product x['.$productXCartQuantity. '] (min='. $productXMinQtys[$key]. '/max='. $productXMaxQtys[$key]. ') not in cart or does not mee max min requirements '. $productXMinQtys[$key]. '/'. $productXMaxQtys[$key]. ' product y IS in cart - product y should be removed.');
                    $itemId=$cartData[$productYSkus[$key]]['itemid'];
                    $this->removeProductFromCart($itemId,$productYDescriptions[$key]);
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
     * @return void
     */
    protected function addProductToCart($productSku,$qty=1,$productYDescription='Free Product')
    {
        $this->log('trying to add product SKU '. $productSku. ' to the cart...');
        $product = $this->_productRepository->get($productSku);
        $productID=$product->getId();

        $params = array(
            'product' => $productID,
            'qty' => $qty
        );

        $this->_cart->addProduct($product,$params);
        $this->_cart->save();

        // https://magento.stackexchange.com/questions/138531/magento-2-how-to-update-cart-after-cart-update-event-checkout-cart-update-ite
        //$quoteObject = $this->_cartInterface->get($this->_cart->getQuote()->getId());
        //$quoteObject->setTriggerRecollect(1);
        //$quoteObject->setIsActive(true);
        //$quoteObject->collectTotals()->save();

        $this->addMessage(__('Your %1 has been added to your cart.',$productYDescription),'notice');
        $this->log('product SKU '. $productSku. ' was added to the cart.');

    }

    /**
     * removeProductFromCart removes $itemID from cart
     *
     * @param string $itemId
     * @return void
     */
    protected function removeProductFromCart($itemId,$productYDescription='Free Product')
    {

        $this->_cart->removeItem($itemId);
        $this->_cart->save();

        // does this need a message?
        //$this->addMessage(__('Your %1 has been removed from your cart.',$productYDescription),'notice');

        $this->log('cart ID '. $itemId. ' was removed from the cart.');

    }

    /**
     * checkProductCartQuantity controls the allowed quantitiy for $item in the cart by setting $qty
     *
     * @param string $itemId
     * @param int $qty
     * @return void
     */
    protected function checkProductCartQuantity($itemId,$qty=1)
    {
        $params[$itemId]['qty'] = $qty;
        $this->_cart->updateItems($params);

        $this->_cart->save();

        //$this->addMessage('Only '. $qty. ' free product is allowed.','notice');

        $this->log('cart ID '. $itemId. ' was checked for qty.');

    }

    /**
     * Add Message - checks for message duplication
     *
     * @param string $newMessage
     * @param string $messageType - defaults to notice
     * @return void
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
        $cartItemQuantities=array();
        $cartItemQuantitiesBySku=array();
        $cartData=array();
        $count=0;

        foreach ($cartItems as $item)
        {
            $count++;

            // if item has parent, use parent
            //
            //if ($item->getParentItem()) {$item=$item->getParentItem();}

            $cartData[$item->getSku()]=array(
                'name' =>  $item->getName(),
                'qty' =>  $item->getQty(),
                'itemid' =>  $item->getId(),
                'type' => $item->getProduct()->getTypeId(),
                'productid' => $item->getProduct()->getId(),
            );

            // build quantities array to get true totals for products with parents, i.e. configurable products
            //
            $cartItemQuantities[$item->getProduct()->getId()][] = $item->getQty();
            $cartItemQuantitiesBySku[$item->getProduct()->getSku()][] = $item->getQty();
        }

        $cartData['cartItemQuantities']=$cartItemQuantities;
        $cartData['cartItemQuantitiesBySku']=$cartItemQuantitiesBySku;

        $this->log(array('BUYXGETY' => $cartData));
        $this->log('BUYXGETY Total Cart Items : '. $count);

        if (count($cartData) > 0 )
        {
            return $cartData;
        }
        return false;

    }

    /**
     * isEnabled
     *
     * @return boolean
     */
    protected function isEnabled()
    {
        return $this->_helperData->getConfig('buyxgety/buyxgety/buyxgetyenable');
    }

    /**
     * log
     *
     * @param string $data
     * @return boolean
     */
    public function log($data)
    {
        if (!$this->_debug) {return;}

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
