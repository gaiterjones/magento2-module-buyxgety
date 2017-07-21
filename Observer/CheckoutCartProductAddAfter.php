<?php
/**
 *  Gaiterjones Observer CheckoutCartProductAddAfter
 *
 *  @category    Gaiterjones
 *  @package     Gaiterjones_Buyxgety
 *  @author      modules@gaiterjones.com
 *
 */
    namespace Gaiterjones\BuyXGetY\Observer;

    use Magento\Framework\Event\ObserverInterface;
    use Gaiterjones\BuyXGetY\Model\BuyXGetY;

    class CheckoutCartProductAddAfter implements ObserverInterface
    {

        protected $_buyxgety;

        public function __construct(
            BuyXGetY $buyxgety
        ) {
            $this->_buyxgety = $buyxgety;
        }

        public function execute(\Magento\Framework\Event\Observer $observer) {

            //$this->_buyxgety->log('checkout_cart_product_add_after Observer');
            //$this->_buyxgety->CartUpdate();

        }

    }
