<?php
/**
 *  Gaiterjones Observer CheckoutCartAddProductComplete
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */
    namespace Gaiterjones\BuyXGetY\Observer;

    use Magento\Framework\Event\ObserverInterface;
    use Gaiterjones\BuyXGetY\Model\BuyXGetY;

    class CheckoutCartAddProductComplete implements ObserverInterface
    {

        protected $_buyxgety;

        public function __construct(
            BuyXGetY $buyxgety
        ) {
            $this->_buyxgety = $buyxgety;
        }

        public function execute(\Magento\Framework\Event\Observer $observer) {

            $this->_buyxgety->log('BUYXGETY / checkout_cart_add_product_complete Observer');
            //$this->_buyxgety->CartUpdate();

        }

    }
